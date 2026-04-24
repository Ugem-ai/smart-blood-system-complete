@echo off
REM Smart Blood System - Complete Reset and Startup Script
REM This script will:
REM 1. Clear all Laravel caches
REM 2. Reset the database
REM 3. Seed demo data
REM 4. Start the development servers

echo.
echo ============================================
echo Smart Blood System - Reset & Start
echo ============================================
echo.

cd /d "%~dp0"

echo [1/6] Clearing Laravel config cache...
call php artisan config:clear
if errorlevel 1 goto error

echo.
echo [2/6] Clearing Laravel application cache...
call php artisan cache:clear
if errorlevel 1 goto error

echo.
echo [3/6] Clearing Laravel view cache...
call php artisan view:clear
if errorlevel 1 goto error

echo.
echo [4/6] Clearing Laravel route cache...
call php artisan route:clear
if errorlevel 1 goto error

echo.
echo [5/6] Running fresh migrations...
call php artisan migrate:fresh --force --seed
if errorlevel 1 goto error

echo.
echo ============================================
echo ✓ System reset complete!
echo ============================================
echo.
echo Starting development servers...
echo.
echo You will see:
echo   - Laravel server on http://127.0.0.1:8000
echo   - Vite dev server on http://localhost:5173
echo.
echo Demo credentials:
echo   Email: test@example.com
echo   Password: password
echo.
echo Press Ctrl+C to stop all servers
echo ============================================
echo.

call composer run dev

goto end

:error
echo.
echo ============================================
echo ✗ ERROR: Setup failed!
echo ============================================
echo.
echo Please check the error messages above.
echo Common issues:
echo   - PHP not installed or not in PATH
echo   - Node/npm not installed or not in PATH
echo   - Database permissions
echo.
pause
exit /b 1

:end
pause
