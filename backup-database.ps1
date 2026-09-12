# Automatic Database Backup Script
# This backs up the SQLite database before any migration runs

$timestamp = Get-Date -Format "yyyy-MM-dd_HH-mm-ss"
$dbPath = "database\database.sqlite"
$backupDir = "database\backups"
$backupPath = "$backupDir\database_backup_$timestamp.sqlite"

# Create backup directory if it doesn't exist
if (-not (Test-Path $backupDir)) {
    New-Item -ItemType Directory -Path $backupDir -Force | Out-Null
}

# Check if database exists
if (Test-Path $dbPath) {
    $dbSize = (Get-Item $dbPath).Length
    
    # Only backup if database has data (size > 100KB)
    if ($dbSize -gt 102400) {
        Copy-Item $dbPath $backupPath -Force
        Write-Host "✅ Database backed up to: $backupPath" -ForegroundColor Green
        Write-Host "   Size: $([math]::Round($dbSize/1MB, 2)) MB" -ForegroundColor Gray
        
        # Keep only last 10 backups
        Get-ChildItem $backupDir -Filter "database_backup_*.sqlite" | 
            Sort-Object LastWriteTime -Descending | 
            Select-Object -Skip 10 | 
            Remove-Item -Force
            
        exit 0
    } else {
        Write-Host "⚠️  Database too small ($dbSize bytes) - skipping backup" -ForegroundColor Yellow
        exit 0
    }
} else {
    Write-Host "⚠️  Database file not found - skipping backup" -ForegroundColor Yellow
    exit 0
}
