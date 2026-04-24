@echo off
REM Complete system restart - kills old processes and starts fresh

echo.
echo ============================================
echo Smart Blood System - RESTART
echo ============================================
echo.

echo [Step 1] Killing any running PHP processes...
taskkill /F /IM php.exe /T 2>nul
timeout /t 1 > nul

echo [Step 2] Killing any running Node processes...
taskkill /F /IM node.exe /T 2>nul
timeout /t 1 > nul

echo [Step 3] Navigating to project directory...
cd /d "%~dp0"

echo [Step 4] Clearing all Laravel caches...
php artisan config:clear 2>nul
php artisan cache:clear 2>nul
php artisan view:clear 2>nul
php artisan route:clear 2>nul
echo OK - Caches cleared
echo.

echo [Step 5] Checking database migrations...
php artisan migrate:status 2>nul
if errorlevel 1 (
    echo Running migrations...
    php artisan migrate --force
)
echo.

echo ============================================
echo ✓ System reset complete!
echo ============================================
echo.
echo STARTUP INSTRUCTIONS:
echo.
echo Option A: TWO TERMINALS (RECOMMENDED)
echo ========================================
echo Terminal 1: Double-click start-server.bat
echo Terminal 2: Double-click start-vite.bat
echo.
echo Option B: SINGLE COMMAND
echo ========================================
echo Run: composer run dev
echo.
echo Then open in browser: http://localhost:8000
echo.
echo Login credentials:
echo   Email: test@example.com
echo   Password: password
echo.
echo ============================================
echo.
pause
