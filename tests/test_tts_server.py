import json
import os
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

import numpy as np
import soundfile as sf
from fastapi.testclient import TestClient

os.environ.setdefault("TTS_SHARED_TOKEN", "tts-test-token")

import tts_server


class FakeEnglishPipeline:
    def create_timed(self, text, voice, lang):
        return np.full(2400, 0.1, dtype=np.float32), 24_000, []


class FakeVietnamesePipeline:
    sample_rate = 24_000

    def synthesize(self, text, voice, lang):
        return np.full(2400, 0.1, dtype=np.float32), None


class TtsTrailingPauseTest(unittest.TestCase):
    def test_period_gets_silence_in_both_voices_and_other_endings_do_not(self):
        with tempfile.TemporaryDirectory() as directory:
            with (
                patch.object(tts_server, "OUTPUT_DIR", Path(directory)),
                patch.object(tts_server, "_pipeline", FakeEnglishPipeline()),
                patch.object(tts_server, "_vietnamese_pipeline", FakeVietnamesePipeline()),
            ):
                client = TestClient(tts_server.app)
                cases = [
                    ("en", "af_heart", "The train arrived.", "a" * 64, 0.5),
                    ("vi", "huu_dat", "Tàu đã đến.”", "b" * 64, 0.5),
                    ("vi", "huu_dat", "Tàu đang chạy", "c" * 64, 0.1),
                ]

                for language, voice, text, key, expected_duration in cases:
                    with self.subTest(language=language, text=text):
                        response = client.post(
                            "/synthesize",
                            headers={"Authorization": f"Bearer {tts_server.TOKEN}"},
                            json={
                                "key": key,
                                "directory": "1_demo/1_chapter",
                                "text": text,
                                "voice": voice,
                                "language": language,
                            },
                        )
                        self.assertEqual(response.status_code, 200)
                        metadata = json.loads(
                            (Path(directory) / "1_demo/1_chapter/json" / f"{key}.json").read_text()
                        )
                        self.assertAlmostEqual(metadata["duration"], expected_duration, places=3)

                        samples, sample_rate = sf.read(
                            Path(directory) / "1_demo/1_chapter/mp3" / f"{key}.mp3"
                        )
                        self.assertEqual(sample_rate, 24_000)
                        self.assertGreater(np.abs(samples[:1200]).mean(), 0.05)
                        if expected_duration > 0.1:
                            self.assertLess(np.abs(samples[-2400:]).mean(), 0.01)


if __name__ == "__main__":
    unittest.main()
