@echo off
REM Simple test to see if servers can start

echo Testing if PHP artisan serve works...
echo.
echo Starting Laravel server for 10 seconds...
echo You should see: "Laravel development server started on http://127.0.0.1:8000"
echo.

cd /d "%~dp0"

REM Start the server in a separate window
start "Laravel Server Test" /WAIT cmd /c "php artisan serve --no-ansi 2>&1 | findstr /R "started|ERROR|error|Exception""

echo.
echo If you saw "started on http://127.0.0.1:8000" above, the server works.
echo If you saw an ERROR, there's a problem.
echo.
pause
