#!/usr/bin/env bash
set -euo pipefail

APP_DIR="/var/www/smart-blood"
PHP_BIN="/usr/bin/php"
COMPOSER_BIN="/usr/bin/composer"
NPM_BIN="/usr/bin/npm"

cd "$APP_DIR"

# Pull latest code and install dependencies.
git fetch --all
git checkout main
git pull --ff-only origin main
$COMPOSER_BIN install --no-dev --prefer-dist --optimize-autoloader
$NPM_BIN ci
$NPM_BIN run build

# Apply DB migrations and optimize framework caches.
$PHP_BIN artisan migrate --force
$PHP_BIN artisan config:cache
$PHP_BIN artisan route:cache
$PHP_BIN artisan view:cache
$PHP_BIN artisan event:cache

# Gracefully restart queue workers with new code.
$PHP_BIN artisan queue:restart

echo "Deployment completed successfully."
