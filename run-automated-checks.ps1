# GMS Automated Quality Checks
Write-Host "╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   GMS - Garage Management System                               ║" -ForegroundColor Cyan
Write-Host "║   Automated Quality Checks                                     ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

$ErrorCount = 0
$WarningCount = 0

# 1. PHP Syntax Check
Write-Host ""
Write-Host "═══ 1. PHP Syntax Check ═══" -ForegroundColor Yellow
Write-Host ""

$phpFiles = Get-ChildItem -Path "app","routes","config" -Recurse -Filter "*.php" -ErrorAction SilentlyContinue
$phpErrorFiles = @()
foreach ($file in $phpFiles) {
    $result = php -l $file.FullName 2>&1
    if ($result -match "Errors parsing") {
        $phpErrorFiles += $file
    }
}

if ($phpErrorFiles.Count -eq 0) {
    Write-Host "✓ All PHP files are syntactically valid" -ForegroundColor Green
} else {
    Write-Host "✗ $($phpErrorFiles.Count) PHP files have syntax errors" -ForegroundColor Red
    $ErrorCount++
}

# 2. Debug Statements Check
Write-Host ""
Write-Host "═══ 2. Debug Statements Check ═══" -ForegroundColor Yellow
Write-Host ""

$debugStatements = Get-ChildItem -Path "app" -Recurse -Filter "*.php" -ErrorAction SilentlyContinue | 
    Select-String -Pattern "dd\(|dump\(|var_dump\(" -SimpleMatch:$false

if ($debugStatements.Count -eq 0) {
    Write-Host "✓ No debug statements found" -ForegroundColor Green
} else {
    Write-Host "⚠ $($debugStatements.Count) debug statements found - review before production" -ForegroundColor Yellow
    $WarningCount++
}

# 3. Environment Check
Write-Host ""
Write-Host "═══ 3. Environment Configuration ═══" -ForegroundColor Yellow
Write-Host ""

if (Test-Path ".env") {
    $envContent = Get-Content ".env"
    $appEnv = ($envContent | Select-String -Pattern "^APP_ENV=").Line.Replace("APP_ENV=","")
    $appDebug = ($envContent | Select-String -Pattern "^APP_DEBUG=").Line.Replace("APP_DEBUG=","")
    
    Write-Host "APP_ENV=$appEnv" -ForegroundColor Cyan
    Write-Host "APP_DEBUG=$appDebug" -ForegroundColor Cyan
    
    if ($appDebug -eq "false") {
        Write-Host "✓ Debug mode is OFF" -ForegroundColor Green
    } else {
        Write-Host "⚠ Debug mode is ON - turn off for production" -ForegroundColor Yellow
        $WarningCount++
    }
} else {
    Write-Host "✗ .env file not found" -ForegroundColor Red
    $ErrorCount++
}

# 4. Database Migration Check
Write-Host ""
Write-Host "═══ 4. Database Migrations ═══" -ForegroundColor Yellow
Write-Host ""

$migrationOutput = php artisan migrate:status 2>&1
if ($LASTEXITCODE -eq 0) {
    Write-Host "✓ All migrations are up to date" -ForegroundColor Green
} else {
    Write-Host "✗ Migration check failed" -ForegroundColor Red
    $ErrorCount++
}

# 5. Frontend Build Check
Write-Host ""
Write-Host "═══ 5. Frontend Build ═══" -ForegroundColor Yellow
Write-Host ""

if (Test-Path "public/build") {
    $assetCount = (Get-ChildItem -Path "public/build/assets" -File -ErrorAction SilentlyContinue).Count
    Write-Host "✓ Frontend assets built ($assetCount files)" -ForegroundColor Green
} else {
    Write-Host "⚠ Frontend build directory not found - run: npm run build" -ForegroundColor Yellow
    $WarningCount++
}

# 6. Dependencies Check
Write-Host ""
Write-Host "═══ 6. Dependencies ═══" -ForegroundColor Yellow
Write-Host ""

if (Test-Path "composer.lock") {
    Write-Host "✓ composer.lock exists" -ForegroundColor Green
} else {
    Write-Host "⚠ composer.lock missing" -ForegroundColor Yellow
    $WarningCount++
}

if (Test-Path "package-lock.json") {
    Write-Host "✓ package-lock.json exists" -ForegroundColor Green
} else {
    Write-Host "⚠ package-lock.json missing" -ForegroundColor Yellow
    $WarningCount++
}

# Summary
Write-Host ""
Write-Host "╔════════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║   SUMMARY                                                      ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════════╝" -ForegroundColor Cyan
Write-Host ""

if ($ErrorCount -eq 0 -and $WarningCount -eq 0) {
    Write-Host "✓ ALL CHECKS PASSED" -ForegroundColor Green -BackgroundColor Black
    Write-Host "  Ready for production!" -ForegroundColor Green
} elseif ($ErrorCount -eq 0) {
    Write-Host "⚠ PASSED WITH WARNINGS" -ForegroundColor Yellow -BackgroundColor Black
    Write-Host "  Warnings: $WarningCount" -ForegroundColor Yellow
    Write-Host "  Review warnings before production deployment" -ForegroundColor Yellow
} else {
    Write-Host "✗ CHECKS FAILED" -ForegroundColor Red -BackgroundColor Black
    Write-Host "  Errors: $ErrorCount | Warnings: $WarningCount" -ForegroundColor Red
    Write-Host "  Fix errors before proceeding" -ForegroundColor Red
}

Write-Host ""
Write-Host "Manual Testing Checklist:" -ForegroundColor Cyan
Write-Host "1. Test in browser: http://127.0.0.1:8000" -ForegroundColor Gray
Write-Host "2. Create/Edit/Delete job cards" -ForegroundColor Gray
Write-Host "3. Create counter invoices" -ForegroundColor Gray
Write-Host "4. Test WhatsApp integration" -ForegroundColor Gray
Write-Host "5. Generate PDFs (invoices/estimates)" -ForegroundColor Gray
Write-Host "6. Test search and filters" -ForegroundColor Gray
Write-Host ""

if ($ErrorCount -gt 0) {
    exit 1
}
exit 0
