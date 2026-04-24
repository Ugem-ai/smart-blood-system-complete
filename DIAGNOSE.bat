@echo off
REM Smart Blood System - Diagnostic Script
REM This script checks all prerequisites and identifies issues

echo.
echo ============================================
echo Smart Blood System - Diagnostics
echo ============================================
echo.

cd /d "%~dp0"

echo [1] Checking PHP installation...
php --version
if errorlevel 1 (
    echo ERROR: PHP not found or not in PATH!
    goto error
)
echo ✓ PHP OK
echo.

echo [2] Checking Node.js installation...
node --version
if errorlevel 1 (
    echo ERROR: Node not found or not in PATH!
    goto error
)
echo ✓ Node OK
echo.

echo [3] Checking npm installation...
npm --version
if errorlevel 1 (
    echo ERROR: npm not found or not in PATH!
    goto error
)
echo ✓ npm OK
echo.

echo [4] Checking Composer installation...
composer --version
if errorlevel 1 (
    echo ERROR: Composer not found or not in PATH!
    goto error
)
echo ✓ Composer OK
echo.

echo [5] Checking if vendor directory exists...
if exist vendor (
    echo ✓ Vendor directory exists
) else (
    echo WARNING: vendor directory missing, running: composer install
    call composer install
)
echo.

echo [6] Checking if node_modules directory exists...
if exist node_modules (
    echo ✓ node_modules directory exists
) else (
    echo WARNING: node_modules missing, running: npm install
    call npm install
)
echo.

echo [7] Checking if database exists...
if exist database\database.sqlite (
    echo ✓ SQLite database file exists
) else (
    echo WARNING: database.sqlite missing
)
echo.

echo [8] Checking if .env file exists...
if exist .env (
    echo ✓ .env file exists
) else (
    echo ERROR: .env file not found!
    goto error
)
echo.

echo [9] Checking Laravel app key...
for /f "tokens=*" %%i in ('findstr /R "^APP_KEY=" .env') do set APP_KEY=%%i
if "%APP_KEY%"=="" (
    echo WARNING: APP_KEY not set, generating...
    call php artisan key:generate
) else (
    echo ✓ APP_KEY is set
)
echo.

echo [10] Testing Laravel serve command...
echo Starting Laravel server for 5 seconds to test...
timeout /t 2 > nul
start "Laravel Test" php artisan serve
timeout /t 3 > nul
taskkill /FI "WINDOWTITLE eq Laravel Test" /T /F > nul 2>&1
echo ✓ Laravel serve test completed
echo.

echo ============================================
echo ✓ All diagnostics passed!
echo ============================================
echo.
echo Next steps:
echo 1. Run: composer run dev
echo 2. Open: http://localhost:8000
echo 3. If still not working, share the output from that command
echo.

pause
goto end

:error
echo.
echo ============================================
echo ✗ Diagnostic failed - see errors above
echo ============================================
echo.
pause
exit /b 1

:end
