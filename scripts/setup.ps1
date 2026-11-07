# One command to start CourseWave in Docker and seed it correctly.
#
#   .\scripts\setup.ps1 -Mode production -AdminEmail you@example.com -AdminName "Your Name"
#   .\scripts\setup.ps1 -Mode demo
#   .\scripts\setup.ps1 -Mode demo -Reset        # wipe the database first
#
# production: roles, plans, permissions, one admin. No fake data.
# demo:       every seeder + ~95 days of analytics data. APP_ENV=local. Scheduler is stopped so the seeded
#             pending/unconfirmed refunds and payouts are not sent to Stripe. NEVER use real Stripe keys.
param(
    [ValidateSet("production", "demo")][string]$Mode = "production",
    [string]$AdminEmail,
    [string]$AdminName = "Admin",
    [switch]$Reset,
    [switch]$NoBuild
)

$ErrorActionPreference = "Stop"
Set-Location (Split-Path $PSScriptRoot -Parent)

$files = @("-f", "docker-compose.yml")
if ($Mode -eq "demo") { $files += @("-f", "docker-compose.demo.yml") }

function Dc {
    & docker compose @files @args
    if ($LASTEXITCODE -ne 0) { throw "docker compose $($args -join ' ') failed (exit $LASTEXITCODE)" }
}
function Artisan {
    Dc exec -T -u www-data web php artisan @args
}
function Step([string]$text) { Write-Host "`n==> $text" -ForegroundColor Cyan }

# Quote anything that contains a comma: in PowerShell an unquoted comma builds an array.
$instructorResources = "--resource=CourseResource,LessonResource,QuizResource"
$adminResources      = "--resource=CourseResource,LessonResource,QuizResource,PlanResource"

# 1) .env
if (-not (Test-Path .env)) {
    Step "Creating .env with fresh secrets"
    & "$PSScriptRoot\init-env.ps1"
}

# 2) Optional wipe
if ($Reset) {
    Write-Host "-Reset deletes the database, Redis data and certificate PDFs." -ForegroundColor Yellow
    if ((Read-Host "Type 'yes' to continue") -ne "yes") { Write-Host "Cancelled."; exit 1 }
    Step "Removing containers and volumes"
    & docker compose -f docker-compose.yml -f docker-compose.demo.yml down -v
}

# 3) Build and start
Step "Starting the stack ($Mode mode)"
if ($NoBuild) { Dc up -d } else { Dc up -d --build }

# 4) Wait for the API (migrations run first, automatically)
$port = 8000
$m = Select-String -Path .env -Pattern '^API_PORT=(\d+)' | Select-Object -First 1
if ($m) { $port = [int]$m.Matches[0].Groups[1].Value }

Step "Waiting for the API on port $port"
$ok = $false
for ($i = 0; $i -lt 60; $i++) {
    try {
        if ((Invoke-WebRequest "http://localhost:$port/api/plans" -UseBasicParsing -TimeoutSec 5).StatusCode -eq 200) { $ok = $true; break }
    } catch { }
    Start-Sleep -Seconds 3
}
if (-not $ok) { throw "The API did not become healthy. Run: docker compose logs --tail=80 migrate web" }
Write-Host "API is up."

# 5) Seed
Step "Seeding roles and permissions"
Artisan db:seed --class=RoleSeeder --force
Artisan shield:generate --panel=instructor "$instructorResources" --no-interaction
Artisan shield:generate --panel=admin "$adminResources" --no-interaction

if ($Mode -eq "production") {
    Artisan db:seed --class=PlanSeeder --force
    Artisan db:seed --class=RolePermissionSyncSeeder --force

    if (-not $AdminEmail) { $AdminEmail = Read-Host "Admin email (empty = skip)" }
    if ($AdminEmail) {
        Step "Creating the admin account"
        try { Artisan coursewave:create-admin $AdminEmail "--name=$AdminName" }
        catch { Write-Host "Admin not created (it may already exist). To promote an existing user: docker compose exec web php artisan coursewave:create-admin $AdminEmail --promote" -ForegroundColor Yellow }
    }
}
else {
    Step "Seeding users, plans, courses and live sessions (DatabaseSeeder)"
    Artisan db:seed --force

    Step "Seeding ~95 days of analytics demo data"
    Artisan db:seed --class=AnalyticsDemoSeeder --force

    Step "Stopping the scheduler so demo refunds/payouts are not sent to Stripe"
    Dc stop scheduler
}

# 6) Done
Write-Host "`nCourseWave is running in $Mode mode." -ForegroundColor Green
Write-Host "  Student app : http://localhost:3000"
Write-Host "  Admin panel : http://localhost:$port/admin"
Write-Host "  Instructor  : http://localhost:$port/instructor"
if ($Mode -eq "demo") {
    Write-Host "  Logins      : admin@llm.com / admin@12334 , instructor@llm.com / instructor@12334 , student@llm.com / student@12334"
    Write-Host "                demo.student01@llm.com ... 40 / Password123"
    Write-Host "  Scheduler   : stopped on purpose. Start it with: docker compose start scheduler"
}
Write-Host "  Verify      : .\scripts\smoke-test.ps1 -Mode $Mode"