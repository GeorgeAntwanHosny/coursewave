# Creates .env from .env.example, generates every secret, and copies Stripe/business values from apps/api/.env.
# Safe to re-run: it refuses to overwrite an existing .env unless you pass -Force.
param([switch]$Force)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)

if ((Test-Path .env) -and -not $Force) {
    Write-Host ".env already exists. Re-run with -Force to regenerate it." -ForegroundColor Yellow
    exit 0
}

function New-Secret([int]$length = 32) {
    $chars = [char[]]('abcdefghijklmnopqrstuvwxyz0123456789')
    $bytes = New-Object byte[] $length
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($bytes)
    -join ($bytes | ForEach-Object { $chars[$_ % $chars.Length] })
}

function New-AppKey {
    $b = New-Object byte[] 32
    [Security.Cryptography.RandomNumberGenerator]::Create().GetBytes($b)
    "base64:" + [Convert]::ToBase64String($b)
}

function Set-Var([string]$content, [string]$key, [string]$value) {
    $line = "$key=$value"
    $pattern = "(?m)^#?\s*$([regex]::Escape($key))=.*$"
    if ($content -match $pattern) {
        return [regex]::Replace($content, $pattern, { param($m) $line }, 1)
    }
    return $content.TrimEnd() + "`n$line`n"
}

$content = Get-Content .env.example -Raw

$content = Set-Var $content "APP_KEY" (New-AppKey)
$content = Set-Var $content "DB_PASSWORD" (New-Secret 32)
$content = Set-Var $content "REDIS_PASSWORD" (New-Secret 32)
$content = Set-Var $content "REVERB_APP_KEY" (New-Secret 20)
$content = Set-Var $content "REVERB_APP_SECRET" (New-Secret 32)

$apiEnv = "apps/api/.env"
if (Test-Path $apiEnv) {
    $keys = "STRIPE_KEY","STRIPE_SECRET","STRIPE_WEBHOOK_SECRET","STRIPE_V2_API_VERSION",
            "STRIPE_CONNECT_COUNTRY","STRIPE_CONNECT_ENTITY_TYPE","PLATFORM_FEE_PERCENT","PAYOUT_MINIMUM_CENTS"
    $lines = Get-Content $apiEnv
    foreach ($k in $keys) {
        $hit = $lines | Where-Object { $_ -match "^$k=(.+)$" } | Select-Object -First 1
        if ($hit) {
            $v = ($hit -replace "^$k=", "").Trim().Trim('"').Trim("'")
            if ($v) { $content = Set-Var $content $k $v; Write-Host "  copied $k" }
        }
    }
} else {
    Write-Host "apps/api/.env not found: fill the STRIPE_* values in .env by hand." -ForegroundColor Yellow
}

[IO.File]::WriteAllText((Join-Path (Get-Location) ".env"), ($content -replace "`r`n", "`n"), (New-Object Text.UTF8Encoding $false))
Write-Host ".env created with fresh secrets." -ForegroundColor Green