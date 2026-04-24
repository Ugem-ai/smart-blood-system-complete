@echo off
cd /d "c:\Users\Acer\Desktop\Smart Blood System\smart-blood"
echo === PHP Version ===
php --version
echo.
echo === Node Version ===
node --version
echo.
echo === npm Version ===
npm --version
echo.
echo === Composer Version ===
composer --version
echo.
echo === Migration Status ===
php artisan migrate:status
echo.
echo === Clearing Caches ===
php artisan config:clear
php artisan cache:clear
php artisan view:clear
echo.
echo === All diagnostics complete ===
pause
