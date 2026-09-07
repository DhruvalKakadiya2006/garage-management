# GMS Automated Quality Checks
# Run this script to verify code quality, tests, and build status

Write-Host "╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   GMS - Garage Management System                               ║" -ForegroundColor Cyan
Write-Host "║   Automated Quality Checks                                     ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

$ErrorCount = 0
$WarningCount = 0

# Function to print section header
function Write-Section {
    param([string]$Title)
    Write-Host ""
    Write-Host "═══ $Title ═══" -ForegroundColor Yellow
    Write-Host ""
}

# Function to print success
function Write-Success {
    param([string]$Message)
    Write-Host "✓ $Message" -ForegroundColor Green
}

# Function to print error
function Write-Failure {
    param([string]$Message)
    Write-Host "✗ $Message" -ForegroundColor Red
    $script:ErrorCount++
}

# Function to print warning
function Write-Warning-Custom {
    param([string]$Message)
    Write-Host "⚠ $Message" -ForegroundColor Yellow
    $script:WarningCount++
}

# 1. PHP Syntax Check
Write-Section "1. PHP Syntax Check"
$phpErrors = Get-ChildItem -Path "app","routes","config","database" -Recurse -Filter "*.php" -ErrorAction SilentlyContinue | 
    ForEach-Object { 
        $result = php -l $_.FullName 2>&1 | Select-String -Pattern "Errors parsing"
        if ($result) { $_ }
    }

if ($phpErrors.Count -eq 0) {
    Write-Success "All PHP files are syntactically valid"
} else {
    Write-Failure "$($phpErrors.Count) PHP files have syntax errors"
}

# 2. Laravel Pint (Code Style)
Write-Section "2. Code Style (Laravel Pint)"
$pintResult = & vendor/bin/pint --test 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Success "Code style is compliant (Laravel Pint)"
} else {
    Write-Failure "Code style violations found. Run: vendor/bin/pint"
}

# 3. Debug Statements Check
Write-Section "3. Debug Statements Check"
$debugStatements = Get-ChildItem -Path "app" -Recurse -Filter "*.php" -ErrorAction SilentlyContinue | 
    Select-String -Pattern "dd\(|dump\(|var_dump\("

if ($debugStatements.Count -eq 0) {
    Write-Success "No debug statements found (dd, dump, var_dump)"
} else {
    Write-Warning-Custom "$($debugStatements.Count) debug statements found - remove before production"
}

# 4. Console.log Check
Write-Section "4. JavaScript Console Statements"
$consoleLog = Get-ChildItem -Path "resources/js" -Recurse -Filter "*.js" -ErrorAction SilentlyContinue | 
    Select-String -Pattern "console\.log\("

if ($consoleLog.Count -eq 0) {
    Write-Success "No console.log statements found"
} else {
    Write-Warning-Custom "$($consoleLog.Count) console.log statements found - remove before production"
}

# 5. Security Audit (Composer)
Write-Section "5. Security Audit (Composer)"
$composerAudit = composer audit --format=plain 2>&1
if ($composerAudit -match "No security vulnerability advisories found") {
    Write-Success "No composer security vulnerabilities"
} else {
    Write-Failure "Composer security vulnerabilities found!"
    Write-Host $composerAudit -ForegroundColor Red
}

# 6. Security Audit (NPM)
Write-Section "6. Security Audit (NPM)"
$npmAudit = npm audit --production 2>&1
if ($npmAudit -match "found 0 vulnerabilities") {
    Write-Success "No npm security vulnerabilities"
} else {
    Write-Warning-Custom "NPM vulnerabilities found - review npm audit output"
}

# 7. Environment Check
Write-Section "7. Environment Configuration"
if (Test-Path ".env") {
    $envContent = Get-Content ".env"
    $appEnv = $envContent | Select-String -Pattern "^APP_ENV=" | ForEach-Object { $_.Line.Split('=')[1] }
    $appDebug = $envContent | Select-String -Pattern "^APP_DEBUG=" | ForEach-Object { $_.Line.Split('=')[1] }
    
    Write-Host "APP_ENV=$appEnv" -ForegroundColor Cyan
    Write-Host "APP_DEBUG=$appDebug" -ForegroundColor Cyan
    
    if ($appEnv -eq "production" -and $appDebug -eq "false") {
        Write-Success "Environment configured for production"
    } else {
        Write-Warning-Custom "Environment is set to development mode"
    }
} else {
    Write-Failure ".env file not found"
}

# 8. Database Migration Check
Write-Section "8. Database Migrations"
Write-Host "Testing fresh migration..." -ForegroundColor Cyan
$migrationResult = php artisan migrate:fresh --force --no-interaction 2>&1
if ($LASTEXITCODE -eq 0) {
    $migrationCount = ($migrationResult | Select-String -Pattern "DONE").Count
    Write-Success "Fresh migration successful ($migrationCount migrations)"
} else {
    Write-Failure "Migration failed!"
    Write-Host $migrationResult -ForegroundColor Red
}

# 9. PHPUnit Tests
Write-Section "9. Automated Tests (PHPUnit)"
Write-Host "Running test suite (this may take a minute)..." -ForegroundColor Cyan
$testResult = php artisan test --compact 2>&1
if ($LASTEXITCODE -eq 0) {
    $passedTests = ($testResult | Select-String -Pattern "Tests:\s+(\d+)\s+passed" | 
        ForEach-Object { $_.Matches.Groups[1].Value })
    $skippedTests = ($testResult | Select-String -Pattern "(\d+)\s+skipped" | 
        ForEach-Object { $_.Matches.Groups[1].Value })
    
    Write-Success "$passedTests tests passed"
    if ($skippedTests) {
        Write-Warning-Custom "$skippedTests tests skipped (stock-related tests - expected)"
    }
} else {
    Write-Failure "Some tests failed - review output above"
}

# 10. Frontend Build
Write-Section "10. Frontend Build (Vite)"
Write-Host "Building frontend assets..." -ForegroundColor Cyan
$buildResult = npm run build 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Success "Frontend build successful"
    
    # Check if build directory exists
    if (Test-Path "public/build") {
        $assetCount = (Get-ChildItem -Path "public/build/assets" -File).Count
        Write-Host "  Generated $assetCount asset files" -ForegroundColor Gray
    }
} else {
    Write-Failure "Frontend build failed!"
}

# 11. Composer Dependencies
Write-Section "11. Dependency Status"
if (Test-Path "composer.lock") {
    Write-Success "composer.lock exists"
} else {
    Write-Warning-Custom "composer.lock missing - run: composer install"
}

if (Test-Path "package-lock.json") {
    Write-Success "package-lock.json exists"
} else {
    Write-Warning-Custom "package-lock.json missing - run: npm install"
}

# Summary
Write-Host ""
Write-Host "╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   SUMMARY                                                      ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

if ($ErrorCount -eq 0 -and $WarningCount -eq 0) {
    Write-Host "✓ ALL CHECKS PASSED" -ForegroundColor Green -BackgroundColor Black
    Write-Host "  Ready for manual testing" -ForegroundColor Green
} elseif ($ErrorCount -eq 0) {
    Write-Host "⚠ CHECKS PASSED WITH WARNINGS" -ForegroundColor Yellow -BackgroundColor Black
    Write-Host "  Errors: $ErrorCount | Warnings: $WarningCount" -ForegroundColor Yellow
    Write-Host "  Review warnings before production deployment" -ForegroundColor Yellow
} else {
    Write-Host "✗ CHECKS FAILED" -ForegroundColor Red -BackgroundColor Black
    Write-Host "  Errors: $ErrorCount | Warnings: $WarningCount" -ForegroundColor Red
    Write-Host "  Fix errors before proceeding" -ForegroundColor Red
}

Write-Host ""
Write-Host "Next Steps:" -ForegroundColor Cyan
Write-Host "1. Review PRODUCTION_READINESS_CHECKLIST.md for manual tests" -ForegroundColor Gray
Write-Host "2. Test in browser: http://127.0.0.1:8000" -ForegroundColor Gray
Write-Host "3. Check browser console for JavaScript errors" -ForegroundColor Gray
Write-Host "4. Test all search/filter/sort functionality" -ForegroundColor Gray
Write-Host ""

# Exit with error code if there are errors
if ($ErrorCount -gt 0) {
    exit 1
}
exit 0
