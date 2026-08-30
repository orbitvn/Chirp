# Chirp - serve over HTTPS for phone testing via a Cloudflare quick tunnel.
#
#   Run from the project root:   powershell -ExecutionPolicy Bypass -File .\tunnel.ps1
#
# It starts `php artisan serve` on 127.0.0.1:8000, opens a Cloudflare quick
# tunnel to it, and prints the public https://*.trycloudflare.com URL. Open
# that URL on your phone -> GPS / drive mode works (secure context).
# Press Ctrl+C to stop; the artisan server is shut down automatically.

param(
    [int]$Port = 8000,
    [switch]$Build   # also run `npm run build` first (use if assets are stale)
)

$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot

# --- cloudflared present? ---------------------------------------------------
$cf = Get-Command cloudflared -ErrorAction SilentlyContinue
if (-not $cf) {
    Write-Host "cloudflared is not installed." -ForegroundColor Yellow
    Write-Host "Install it, then re-run this script:" -ForegroundColor Yellow
    Write-Host "    winget install --id Cloudflare.cloudflared" -ForegroundColor Cyan
    Write-Host "  (or download from https://github.com/cloudflare/cloudflared/releases )"
    exit 1
}

# --- optional asset build ---------------------------------------------------
if ($Build) {
    Write-Host "Building front-end assets (npm run build)..." -ForegroundColor Cyan
    npm run build
}

Write-Host "Clearing cached config so trusted-proxy settings apply..." -ForegroundColor DarkGray
php artisan optimize:clear | Out-Null

# --- start the app ----------------------------------------------------------
Write-Host "Starting Laravel on http://127.0.0.1:$Port ..." -ForegroundColor Cyan
$serve = Start-Process -FilePath "php" `
    -ArgumentList @("artisan", "serve", "--host=127.0.0.1", "--port=$Port") `
    -PassThru -WindowStyle Hidden

# Make sure we always clean up the server process on exit / Ctrl+C.
try {
    Start-Sleep -Seconds 2
    if ($serve.HasExited) {
        Write-Host "Laravel failed to start (is port $Port in use?)." -ForegroundColor Red
        exit 1
    }

    Write-Host ""
    Write-Host "Opening Cloudflare tunnel... watch for the https://<random>.trycloudflare.com URL below." -ForegroundColor Green
    Write-Host "Open that URL on your phone. Ctrl+C here to stop everything." -ForegroundColor Green
    Write-Host ""

    # Foreground: streams the tunnel URL and logs. Blocks until Ctrl+C.
    cloudflared tunnel --url "http://127.0.0.1:$Port"
}
finally {
    if ($serve -and -not $serve.HasExited) {
        Write-Host "`nStopping Laravel server..." -ForegroundColor DarkGray
        Stop-Process -Id $serve.Id -Force -ErrorAction SilentlyContinue
    }
}
