@echo off
setlocal enabledelayedexpansion

cd /d "c:\Users\Acer\Desktop\Smart Blood System\smart-blood"

echo.
echo ============================================================
echo SMART BLOOD SYSTEM - Diagnostic and Startup
echo ============================================================
echo.

REM Step 1: Version Checks
echo Step 1: Checking Prerequisites
echo.
echo === PHP Version ===
php --version
if errorlevel 1 (
    echo ERROR: PHP is not installed or not in PATH
    pause
    exit /b 1
)
echo.

echo === Node Version ===
node --version
if errorlevel 1 (
    echo ERROR: Node is not installed or not in PATH
    pause
    exit /b 1
)
echo.

echo === npm Version ===
npm --version
if errorlevel 1 (
    echo ERROR: npm is not installed or not in PATH
    pause
    exit /b 1
)
echo.

echo === Composer Version ===
composer --version
if errorlevel 1 (
    echo ERROR: Composer is not installed or not in PATH
    pause
    exit /b 1
)
echo.

REM Step 2: Check Database and Migrations
echo Step 2: Checking Database and Migrations
echo.
echo === Migration Status ===
php artisan migrate:status
if errorlevel 1 (
    echo Migration check had an issue, but continuing...
)
echo.

REM Step 3: Clear Caches
echo Step 3: Clearing Caches
echo.
echo Clearing config cache...
php artisan config:clear
echo Clearing application cache...
php artisan cache:clear
echo Clearing view cache...
php artisan view:clear
echo.

REM Step 4: Check and Install Dependencies
echo Step 4: Checking Dependencies
echo.

if not exist "vendor" (
    echo Installing Composer dependencies...
    call composer install
    if errorlevel 1 (
        echo WARNING: Composer install may have had issues
    )
) else (
    echo Composer dependencies already installed
)
echo.

if not exist "node_modules" (
    echo Installing npm dependencies...
    call npm install
    if errorlevel 1 (
        echo WARNING: npm install may have had issues
    )
) else (
    echo npm dependencies already installed
)
echo.

REM Step 5: Start Servers
echo Step 5: Starting Development Servers
echo.
echo.
echo ============================================================
echo STARTING SERVERS NOW
echo ============================================================
echo.
echo If you haven't opened the app yet, you can access it at:
echo   http://localhost:8000
echo.
echo Vite dev server will be available at:
echo   http://localhost:5173
echo.
echo Press Ctrl+C to stop all servers.
echo ============================================================
echo.

REM Use the dev-runner script if available, otherwise fall back to manual startup
if exist "scripts\dev-runner.php" (
    echo Using dev-runner to start all services...
    php scripts\dev-runner.php
) else (
    echo Using composer run dev...
    composer run dev
)

endlocal
