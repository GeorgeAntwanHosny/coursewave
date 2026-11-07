# Verifies the whole stack end to end.
#   .\scripts\smoke-test.ps1                 # production mode
#   .\scripts\smoke-test.ps1 -Mode demo      # demo mode (APP_ENV=local, scheduler stopped on purpose)
param(
    [ValidateSet("production", "demo")][string]$Mode = "production",
    [int]$ApiPort = 8000,
    [int]$WebPort = 3000,
    [int]$WsPort = 8080
)

Set-Location (Split-Path $PSScriptRoot -Parent)
$script:failed = 0
$expectedEnv = if ($Mode -eq "demo") { "local" } else { "production" }

function Check([string]$name, [scriptblock]$test) {
    try { $ok = [bool](& $test) } catch { $ok = $false }
    if ($ok) { Write-Host "  PASS  $name" -ForegroundColor Green }
    else     { Write-Host "  FAIL  $name" -ForegroundColor Red; $script:failed++ }
}

function Http([string]$url) {
    (Invoke-WebRequest -Uri $url -UseBasicParsing -TimeoutSec 15).StatusCode
}

Write-Host "`nMode: $Mode (expects APP_ENV=$expectedEnv)" -ForegroundColor Cyan

Write-Host "`nContainers" -ForegroundColor Cyan
$running = docker compose ps --status running --services
$services = "postgres", "redis", "web", "worker", "reverb", "frontend"
if ($Mode -eq "production") { $services += "scheduler" }
foreach ($s in $services) {
    Check "$s is running" { $running -contains $s }
}
Check "migrate exited with code 0" { (docker compose ps -a migrate --format json | ConvertFrom-Json).ExitCode -eq 0 }
if ($Mode -eq "production") {
    Check "exactly one scheduler" { @($running | Where-Object { $_ -eq "scheduler" }).Count -eq 1 }
} else {
    Check "scheduler is stopped (demo data is protected)" { $running -notcontains "scheduler" }
}

Write-Host "`nEndpoints" -ForegroundColor Cyan
Check "API  GET /api/plans -> 200"     { (Http "http://localhost:$ApiPort/api/plans") -eq 200 }
Check "Admin login page -> 200"        { (Http "http://localhost:$ApiPort/admin/login") -eq 200 }
Check "Instructor login page -> 200"   { (Http "http://localhost:$ApiPort/instructor/login") -eq 200 }
Check "Next.js home -> 200"            { (Http "http://localhost:$WebPort/") -eq 200 }
Check "Reverb port $WsPort reachable"  { (Test-NetConnection localhost -Port $WsPort -WarningAction SilentlyContinue).TcpTestSucceeded }

Write-Host "`nBackends" -ForegroundColor Cyan
Check "PostgreSQL accepts connections" { docker compose exec -T postgres pg_isready | Out-Null; $LASTEXITCODE -eq 0 }
Check "Redis answers PONG"             { (docker compose exec -T redis redis-cli ping) -match "PONG" }
Check "No pending migrations"          { -not ((docker compose exec -T web php artisan migrate:status) -match "Pending") }
Check "APP_ENV is $expectedEnv"        { (docker compose exec -T web printenv APP_ENV) -match $expectedEnv }
Check "Schedule has tasks registered"  { (docker compose exec -T web php artisan schedule:list) -match "recogni|payout|certificates" }

Write-Host "`nSeed data" -ForegroundColor Cyan

# Ask PostgreSQL directly: no tinker, no PHP namespaces, no PowerShell quoting problems.
function Sql([string]$query) {
    $dbUser = "coursewave"; $dbName = "coursewave"
    $envUser = Select-String -Path .env -Pattern '^DB_USERNAME=(.+)$' | Select-Object -First 1
    $envName = Select-String -Path .env -Pattern '^DB_DATABASE=(.+)$' | Select-Object -First 1
    if ($envUser) { $dbUser = $envUser.Matches[0].Groups[1].Value.Trim() }
    if ($envName) { $dbName = $envName.Matches[0].Groups[1].Value.Trim() }
    ((docker compose exec -T postgres psql -U $dbUser -d $dbName -t -A -c $query) | Out-String).Trim()
}

Check "Roles exist (student, instructor, admin)" {
    (Sql "select count(*) from roles where name in ('student','instructor','admin');") -eq "3"
}
Check "Plans exist (3)" {
    [int](Sql "select count(*) from plans;") -ge 3
}
Check "At least one admin exists" {
    [int](Sql "select count(*) from model_has_roles mhr join roles r on r.id = mhr.role_id where r.name = 'admin';") -ge 1
}
Check "Permissions were generated" {
    [int](Sql "select count(*) from permissions;") -gt 0
}
if ($Mode -eq "demo") {
    Check "Demo students exist" {
        (Sql "select count(*) from users where email = 'demo.student01@llm.com';") -eq "1"
    }
    Check "Demo ledger has entries" {
        [int](Sql "select count(*) from ledger_entries;") -gt 0
    }
} else {
    Check "No weak default accounts (admin@llm.com)" {
        (Sql "select count(*) from users where email = 'admin@llm.com';") -eq "0"
    }
}

Write-Host "`nQueue round trip (web -> redis -> worker)" -ForegroundColor Cyan
docker compose exec -T web php artisan tinker --execute="cache()->put('smoke','pending',300); Artisan::queue('cache:forget', ['key' => 'smoke']);" | Out-Null
Start-Sleep -Seconds 8
Check "Worker processed the queued job" {
    (docker compose exec -T web php artisan tinker --execute="echo cache()->has('smoke') ? 'still-there' : 'gone';") -match "gone"
}

Write-Host ""
if ($script:failed -eq 0) { Write-Host "All checks passed. CourseWave is running correctly ($Mode)." -ForegroundColor Green; exit 0 }
Write-Host "$($script:failed) check(s) failed. Run: docker compose logs --tail=80 <service>" -ForegroundColor Red
exit 1