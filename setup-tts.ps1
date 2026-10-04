$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot

$envPath = Join-Path $PSScriptRoot '.env'
if (-not (Test-Path -LiteralPath $envPath)) {
    throw 'Create ebook-backend/.env before setting up TTS.'
}

$content = Get-Content -LiteralPath $envPath -Raw
if ($content -notmatch '(?m)^TTS_SHARED_TOKEN=[^\r\n]+') {
    $bytes = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    $rng.GetBytes($bytes)
    $rng.Dispose()
    $token = [BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant()
    if ($content -match '(?m)^TTS_SHARED_TOKEN=') {
        $content = [regex]::Replace($content, '(?m)^TTS_SHARED_TOKEN=.*$', "TTS_SHARED_TOKEN=$token")
    } else {
        $content = $content.TrimEnd() + "`r`nTTS_SHARED_TOKEN=$token`r`n"
    }
}
if ($content -match '(?m)^DB_QUEUE_RETRY_AFTER=') {
    $content = [regex]::Replace($content, '(?m)^DB_QUEUE_RETRY_AFTER=.*$', 'DB_QUEUE_RETRY_AFTER=3600')
} else {
    $content = $content.TrimEnd() + "`r`nDB_QUEUE_RETRY_AFTER=3600`r`n"
}
[IO.File]::WriteAllText($envPath, $content, [Text.UTF8Encoding]::new($false))

python -m venv --system-site-packages .venv-tts
if ($LASTEXITCODE -ne 0) { throw 'Could not create the Python virtual environment.' }
$python = Join-Path $PSScriptRoot '.venv-tts\Scripts\python.exe'
& $python -m pip install -r tts-requirements.txt
if ($LASTEXITCODE -ne 0) { throw 'Could not install TTS dependencies.' }
& $python -m pip install --no-deps kokoro-onnx==0.6.1
if ($LASTEXITCODE -ne 0) { throw 'Could not install Kokoro ONNX.' }
& $python -m pip install --no-deps korvatts==0.1.3
if ($LASTEXITCODE -ne 0) { throw 'Could not install KorvaTTS.' }

$models = Join-Path $PSScriptRoot '.tts-models'
New-Item -ItemType Directory -Path $models -Force | Out-Null
$base = 'https://github.com/thewh1teagle/kokoro-onnx/releases/download/model-files-v1.1'
foreach ($file in @(@('kokoro-v1.0.onnx', 100000000), @('voices-v1.0.bin', 20000000))) {
    $path = Join-Path $models $file[0]
    if ((Test-Path -LiteralPath $path) -and (Get-Item -LiteralPath $path).Length -ge $file[1]) { continue }
    curl.exe --fail --location --silent --show-error --output $path "$base/$($file[0])"
    if ($LASTEXITCODE -ne 0 -or (Get-Item -LiteralPath $path).Length -lt $file[1]) {
        throw "Could not download $($file[0])."
    }
}

$downloadKorva = @'
from huggingface_hub import snapshot_download
snapshot_download(
    repo_id="dogenthq/KorvaTTS",
    revision="e5c8c218e93c1d8daa53bfe0e62b8fbc5a5e991d",
    local_dir=".tts-models/korvatts",
    allow_patterns=["onnx/*", "voice_styles/huu_dat.json"],
)
'@
$downloadKorva | & $python -
if ($LASTEXITCODE -ne 0) { throw 'Could not download KorvaTTS assets.' }

Write-Host 'Kokoro and KorvaTTS setup completed. Run .\start-backend.ps1 to start the API, TTS server, and queue worker.'
