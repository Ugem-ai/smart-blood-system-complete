# Smart Blood System - Windows PowerShell Startup Script
# Location: c:\Users\Acer\Desktop\Smart Blood System\smart-blood\startup.ps1
# Usage: powershell -ExecutionPolicy Bypass -File startup.ps1

param(
    [switch]$SkipDiagnostics = $false,
    [switch]$SkipDependencies = $false,
    [switch]$SkipCache = $false
)

$projectRoot = "c:\Users\Acer\Desktop\Smart Blood System\smart-blood"
Set-Location $projectRoot

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Smart Blood System - Startup Script" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if (-not $SkipDiagnostics) {
    Write-Host "Step 1: Checking Prerequisites" -ForegroundColor Yellow
    Write-Host "==============================" -ForegroundColor Yellow
    
    Write-Host "PHP Version:" -ForegroundColor Green
    & php --version | Select-Object -First 1
    
    Write-Host "Node Version:" -ForegroundColor Green
    & node --version
    
    Write-Host "npm Version:" -ForegroundColor Green
    & npm --version
    
    Write-Host "Composer Version:" -ForegroundColor Green
    & composer --version
    
    Write-Host "Database Status:" -ForegroundColor Green
    if (Test-Path "database/database.sqlite") {
        Write-Host "✓ SQLite database file exists"
    } else {
        Write-Host "✗ SQLite database file missing"
    }
    
    Write-Host "Vendor directory:" -ForegroundColor Green
    if (Test-Path "vendor") {
        Write-Host "✓ Composer packages installed"
    } else {
        Write-Host "✗ Composer packages missing"
    }
    
    Write-Host "Node modules directory:" -ForegroundColor Green
    if (Test-Path "node_modules") {
        Write-Host "✓ npm packages installed"
    } else {
        Write-Host "✗ npm packages missing"
    }
    
    Write-Host ""
}

if (-not $SkipDependencies) {
    Write-Host "Step 2: Installing Dependencies" -ForegroundColor Yellow
    Write-Host "===============================" -ForegroundColor Yellow
    
    if (-not (Test-Path "vendor")) {
        Write-Host "Installing Composer packages..."
        & composer install
    }
    
    if (-not (Test-Path "node_modules")) {
        Write-Host "Installing npm packages..."
        & npm install
    }
    
    Write-Host ""
}

if (-not $SkipCache) {
    Write-Host "Step 3: Clearing Caches" -ForegroundColor Yellow
    Write-Host "======================" -ForegroundColor Yellow
    
    Write-Host "Clearing config cache..."
    & php artisan config:clear
    
    Write-Host "Clearing application cache..."
    & php artisan cache:clear
    
    Write-Host "Clearing view cache..."
    & php artisan view:clear
    
    Write-Host ""
}

Write-Host "Step 4: Starting Development Servers" -ForegroundColor Yellow
Write-Host "===================================" -ForegroundColor Yellow
Write-Host ""
Write-Host "Starting servers..." -ForegroundColor Cyan
Write-Host "Access the application at: http://localhost:8000" -ForegroundColor Green
Write-Host "Vite dev server available at: http://localhost:5173" -ForegroundColor Green
Write-Host ""
Write-Host "Press Ctrl+C to stop all services" -ForegroundColor Yellow
Write-Host ""

# Start the dev runner
& composer run dev
