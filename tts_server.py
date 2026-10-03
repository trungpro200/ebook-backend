"""Local Kokoro ONNX worker. Run from this directory with uvicorn tts_server:app."""

import asyncio
import hmac
import json
import os
import subprocess
import sys
import tempfile
from difflib import SequenceMatcher
from pathlib import Path
from typing import Literal

from dotenv import load_dotenv
from fastapi import FastAPI, Header, HTTPException
from pydantic import BaseModel, Field


ROOT = Path(__file__).resolve().parent
load_dotenv(ROOT / ".env")
OUTPUT_DIR = ROOT / "storage" / "app" / "private" / "tts"
TOKEN = os.environ.get("TTS_SHARED_TOKEN", "")
if not TOKEN:
    raise RuntimeError("Set TTS_SHARED_TOKEN in ebook-backend/.env before starting TTS.")

app = FastAPI(title="Moc Thu local TTS", docs_url=None, redoc_url=None)
_pipeline = None
_generation_lock = asyncio.Lock()


def _word_starts(text: str, timings: list, tokenizer) -> tuple[list[float] | None, str]:
    starts = []
    in_word = False
    for timing in timings:
        if timing.phoneme.isspace():
            in_word = False
        elif not in_word:
            starts.append(round(timing.start, 3))
            in_word = True

    words = text.split()
    if len(starts) == len(words):
        return starts, "phoneme"

    try:
        expected_words = [tokenizer.phonemize(word, lang="en-us").replace(" ", "") for word in words]
    except Exception:
        return None, "estimated"

    actual = [timing for timing in timings if not timing.phoneme.isspace()]
    expected = "".join(expected_words)
    spoken = "".join(timing.phoneme for timing in actual)
    if not expected or not spoken:
        return None, "estimated"

    matcher = SequenceMatcher(None, expected, spoken, autojunk=False)
    if matcher.ratio() < 0.55:
        return None, "estimated"

    blocks = [block for block in matcher.get_matching_blocks() if block.size]
    offsets = []
    offset = 0
    for word in expected_words:
        matching = next((block for block in blocks if block.a <= offset < block.a + block.size), None)
        if matching:
            index = matching.b + offset - matching.a
        else:
            before = next((block for block in reversed(blocks) if block.a + block.size <= offset), None)
            after = next((block for block in blocks if block.a >= offset), None)
            if before and after:
                left = before.a + before.size
                right = after.a
                fraction = (offset - left) / max(1, right - left)
                index = round(before.b + before.size + fraction * (after.b - before.b - before.size))
            elif after:
                index = after.b
            elif before:
                index = before.b + before.size - 1
            else:
                return None, "estimated"
        offsets.append(round(actual[min(max(index, 0), len(actual) - 1)].start, 3))
        offset += len(word)

    for index in range(1, len(offsets)):
        offsets[index] = max(offsets[index - 1], offsets[index])
    return offsets, "estimated"


class SynthesisRequest(BaseModel):
    key: str = Field(pattern=r"^[0-9a-f]{64}$")
    directory: str = Field(pattern=r"^[0-9]+_[a-z0-9-]+/[0-9]+_[a-z0-9-]+$")
    text: str = Field(min_length=1, max_length=250_000)
    voice: Literal["af_heart"]


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "provider": _pipeline.sess.get_providers()[0] if _pipeline else "not loaded"}


@app.post("/synthesize")
async def synthesize(
    request: SynthesisRequest, authorization: str | None = Header(default=None)
) -> dict[str, str]:
    if not authorization or not hmac.compare_digest(authorization, f"Bearer {TOKEN}"):
        raise HTTPException(status_code=401, detail="Unauthorized")

    chapter_dir = OUTPUT_DIR / request.directory
    audio_dir = chapter_dir / "mp3"
    metadata_dir = chapter_dir / "json"
    audio_dir.mkdir(parents=True, exist_ok=True)
    metadata_dir.mkdir(parents=True, exist_ok=True)
    output = audio_dir / f"{request.key}.mp3"
    metadata = metadata_dir / f"{request.key}.json"
    if output.is_file():
        return {"status": "ready"}

    async with _generation_lock:
        if output.is_file():
            return {"status": "ready"}

        global _pipeline
        if _pipeline is None:
            import onnxruntime as ort
            from kokoro_onnx import Kokoro

            if os.name == "nt":
                torch_dlls = Path(sys.base_prefix) / "Lib" / "site-packages" / "torch" / "lib"
                if torch_dlls.is_dir():
                    ort.preload_dlls(directory=str(torch_dlls))
                else:
                    ort.preload_dlls()
            os.environ["ONNX_PROVIDER"] = "CUDAExecutionProvider"
            _pipeline = Kokoro(
                str(ROOT / ".tts-models" / "kokoro-v1.0.onnx"),
                str(ROOT / ".tts-models" / "voices-v1.0.bin"),
            )
            if "CUDAExecutionProvider" not in _pipeline.sess.get_providers():
                _pipeline = None
                raise RuntimeError("ONNX CUDA provider did not initialize")

        with tempfile.NamedTemporaryFile(dir=audio_dir, suffix=".wav", delete=False) as wav:
            wav_path = Path(wav.name)
        with tempfile.NamedTemporaryFile(dir=audio_dir, suffix=".mp3", delete=False) as mp3:
            mp3_path = Path(mp3.name)
        with tempfile.NamedTemporaryFile(dir=metadata_dir, suffix=".json", delete=False) as json_file:
            json_path = Path(json_file.name)

        try:
            import soundfile as sf

            audio, sample_rate, timings = await asyncio.to_thread(
                _pipeline.create_timed, request.text, voice=request.voice, lang="en-us"
            )
            if sample_rate != 24_000:
                raise RuntimeError("Unexpected Kokoro sample rate")
            if len(audio) == 0:
                raise RuntimeError("Kokoro generated no audio")
            sf.write(wav_path, audio, sample_rate)
            word_starts, timing_quality = await asyncio.to_thread(
                _word_starts, request.text, timings, _pipeline.tokenizer
            ) if timings else (None, "estimated")

            subprocess.run(
                ["ffmpeg", "-hide_banner", "-loglevel", "error", "-y", "-i", str(wav_path),
                 "-codec:a", "libmp3lame", "-b:a", "64k", str(mp3_path)],
                check=True,
                capture_output=True,
                timeout=300,
            )
            if mp3_path.stat().st_size == 0:
                raise RuntimeError("ffmpeg generated an empty MP3")
            json_path.write_text(json.dumps({
                "duration": round(len(audio) / sample_rate, 3),
                "word_starts": word_starts,
                "timing_quality": timing_quality,
            }), encoding="utf-8")
            os.replace(json_path, metadata)
            os.replace(mp3_path, output)
        finally:
            wav_path.unlink(missing_ok=True)
            mp3_path.unlink(missing_ok=True)
            json_path.unlink(missing_ok=True)

    return {"status": "ready"}
