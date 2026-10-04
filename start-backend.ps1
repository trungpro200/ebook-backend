$ErrorActionPreference = 'Stop'

function Test-LocalPort {
    param([int] $Port)

    $client = [Net.Sockets.TcpClient]::new()
    try {
        $client.Connect('127.0.0.1', $Port)
        return $true
    } catch [Net.Sockets.SocketException] {
        return $false
    } finally {
        $client.Dispose()
    }
}

function Wait-For-Service {
    param(
        [System.Diagnostics.Process] $Process,
        [string] $Name,
        [int] $Port,
        [string] $HealthUrl = ''
    )

    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        if ($Process.HasExited) {
            throw "$Name exited during startup (code $($Process.ExitCode))."
        }

        if (Test-LocalPort $Port) {
            if (-not $HealthUrl) { return }
            try {
                $health = Invoke-RestMethod -Uri $HealthUrl -TimeoutSec 2
                if ($health.status -eq 'ok') { return }
            } catch {
                # The server may have opened its port before the health route is ready.
            }
        }

        Start-Sleep -Milliseconds 500
    }

    throw "$Name did not become ready on port $Port within 30 seconds."
}

$python = Join-Path $PSScriptRoot '.venv-tts\Scripts\python.exe'
$requiredFiles = @('.env', 'vendor\autoload.php', '.tts-models\kokoro-v1.0.onnx', '.tts-models\voices-v1.0.bin', '.tts-models\korvatts\onnx\tts.json', '.tts-models\korvatts\voice_styles\huu_dat.json')
foreach ($file in $requiredFiles) {
    if (-not (Test-Path -LiteralPath (Join-Path $PSScriptRoot $file))) {
        throw "Missing $file. Complete the first-time setup in README.md."
    }
}
if (-not (Test-Path -LiteralPath $python)) {
    throw 'Missing .venv-tts\Scripts\python.exe. Run .\setup-tts.ps1 first.'
}
$php = (Get-Command php -CommandType Application -ErrorAction Stop).Source

foreach ($port in @(8000, 8765)) {
    if (Test-LocalPort $port) {
        throw "Port $port is already in use. Stop the existing service before running .\start-backend.ps1."
    }
}

$services = [System.Collections.ArrayList]::new()
try {
    Write-Host 'Starting TTS on 127.0.0.1:8765...'
    $tts = Start-Process -FilePath $python -ArgumentList @('-m', 'uvicorn', 'tts_server:app', '--host', '127.0.0.1', '--port', '8765') -WorkingDirectory $PSScriptRoot -NoNewWindow -PassThru
    [void] $services.Add([pscustomobject]@{ Name = 'TTS'; Process = $tts })
    Wait-For-Service -Process $tts -Name 'TTS' -Port 8765 -HealthUrl 'http://127.0.0.1:8765/health'

    Write-Host 'Starting API on port 8000...'
    $api = Start-Process -FilePath $php -ArgumentList @('artisan', 'serve', '--host=0.0.0.0', '--port=8000') -WorkingDirectory $PSScriptRoot -NoNewWindow -PassThru
    [void] $services.Add([pscustomobject]@{ Name = 'API'; Process = $api })
    Wait-For-Service -Process $api -Name 'API' -Port 8000

    Write-Host 'Starting TTS queue worker...'
    $worker = Start-Process -FilePath $php -ArgumentList @('artisan', 'queue:work', 'database', '--queue=tts', '--timeout=1800') -WorkingDirectory $PSScriptRoot -NoNewWindow -PassThru
    [void] $services.Add([pscustomobject]@{ Name = 'Worker'; Process = $worker })

    Write-Host 'API, TTS, and worker are running. Press Ctrl+C to stop all three.'
    while ($true) {
        foreach ($service in $services) {
            if ($service.Process.HasExited) {
                throw "$($service.Name) exited (code $($service.Process.ExitCode)). Stopping the other services."
            }
        }
        Start-Sleep -Seconds 1
    }
} finally {
    for ($index = $services.Count - 1; $index -ge 0; $index--) {
        $service = $services[$index]
        if (-not $service.Process.HasExited) {
            Write-Host "Stopping $($service.Name)..."
            try {
                & taskkill.exe /PID $service.Process.Id /T /F 2>$null | Out-Null
            } catch {
                if (-not $service.Process.HasExited) {
                    Write-Warning "Could not stop $($service.Name) (PID $($service.Process.Id)): $_"
                }
            }
        }
        $service.Process.Dispose()
    }
}
