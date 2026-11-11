# Scans the repo and its full Git history for secrets and risky files before publishing.
#   .\scripts\pre-publish-check.ps1
$ErrorActionPreference = "Continue"
Set-Location (Split-Path $PSScriptRoot -Parent)
$script:problems = 0

function Fail([string]$msg) { Write-Host "  FAIL  $msg" -ForegroundColor Red; $script:problems++ }
function Pass([string]$msg) { Write-Host "  PASS  $msg" -ForegroundColor Green }

Write-Host "`n1. Tracked files that must never be public" -ForegroundColor Cyan
$tracked = git ls-files
$bad = $tracked | Where-Object {
    $_ -match '(^|/)\.env($|\.)' -and $_ -notmatch '\.env\.example$'
}
if ($bad) { $bad | ForEach-Object { Fail "tracked: $_" } } else { Pass "no .env files are tracked" }

$keys = $tracked | Where-Object { $_ -match '\.(pem|key|p12|pfx)$' -or $_ -match 'id_rsa' }
if ($keys) { $keys | ForEach-Object { Fail "tracked key file: $_" } } else { Pass "no key files are tracked" }

$private = $tracked | Where-Object { $_ -match 'PROJECT_STATE\.internal|AI_SESSION_WORKFLOW|\.dump$|\.sql\.gz$' }
if ($private) { $private | ForEach-Object { Fail "tracked private file: $_" } } else { Pass "no private notes or database dumps are tracked" }

Write-Host "`n2. Secrets in current files" -ForegroundColor Cyan
$patterns = @(
    'sk_live_[0-9a-zA-Z]{10,}', 'rk_live_[0-9a-zA-Z]{10,}', 'sk_test_[0-9a-zA-Z]{20,}',
    'whsec_[0-9a-zA-Z]{20,}', 'AKIA[0-9A-Z]{16}', '-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----',
    'base64:[A-Za-z0-9+/]{43}='
)
foreach ($p in $patterns) {
    $hits = git grep -n -I -E $p -- . ':(exclude)*.example' 2>$null
    if ($hits) { Fail "pattern $p found:"; $hits | Select-Object -First 5 | ForEach-Object { Write-Host "        $_" -ForegroundColor Yellow } }
    else { Pass "no match for $p" }
}

Write-Host "`n3. Secrets anywhere in Git history" -ForegroundColor Cyan
foreach ($p in @('sk_live_', 'rk_live_', 'sk_test_', 'whsec_', 'PRIVATE KEY-----')) {
    $found = git log --all --oneline -S $p 2>$null
    if ($found) { Fail "'$p' appears in history (commits below). Rotate the key and clean the history."; $found | Select-Object -First 5 | ForEach-Object { Write-Host "        $_" -ForegroundColor Yellow } }
    else { Pass "history is clean for '$p'" }
}
$envHistory = git log --all --diff-filter=A --name-only --pretty=format: 2>$null | Where-Object { $_ -match '(^|/)\.env($|\.)' -and $_ -notmatch '\.example$' } | Sort-Object -Unique
if ($envHistory) { $envHistory | ForEach-Object { Fail ".env file was committed at some point: $_" } } else { Pass "no .env file was ever committed" }

Write-Host "`n4. Public-repo basics" -ForegroundColor Cyan
if (Test-Path LICENSE) { Pass "LICENSE exists" } else { Fail "LICENSE is missing" }
if (Test-Path README.md) { Pass "README.md exists" } else { Fail "README.md is missing" }
if (Test-Path .env.example) { Pass ".env.example exists" } else { Fail ".env.example is missing" }

Write-Host ""
if ($script:problems -eq 0) { Write-Host "Ready to publish." -ForegroundColor Green; exit 0 }
Write-Host "$($script:problems) problem(s) found. Fix them before making the repo public." -ForegroundColor Red
exit 1