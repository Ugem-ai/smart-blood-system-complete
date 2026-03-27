#!/usr/bin/env bash
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/smart-blood}"
ENV_FILE="${APP_DIR}/.env"

DOMAIN="${1:-your-domain.com}"
DB_HOST="${2:-127.0.0.1}"
DB_PORT="${3:-3306}"
DB_NAME="${4:-smartblood}"
DB_USER="${5:-smartblood_user}"
DB_PASS="${6:-replace-with-strong-password}"

if [[ ! -f "${APP_DIR}/.env.example" ]]; then
    echo "Missing ${APP_DIR}/.env.example. Clone project first."
    exit 1
fi

cp "${APP_DIR}/.env.example" "${ENV_FILE}"

sed -i "s|^APP_ENV=.*|APP_ENV=production|" "${ENV_FILE}"
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|" "${ENV_FILE}"
sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" "${ENV_FILE}"

sed -i "s|^DB_HOST=.*|DB_HOST=${DB_HOST}|" "${ENV_FILE}"
sed -i "s|^DB_PORT=.*|DB_PORT=${DB_PORT}|" "${ENV_FILE}"
sed -i "s|^DB_DATABASE=.*|DB_DATABASE=${DB_NAME}|" "${ENV_FILE}"
sed -i "s|^DB_USERNAME=.*|DB_USERNAME=${DB_USER}|" "${ENV_FILE}"
sed -i "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASS}|" "${ENV_FILE}"

sed -i "s|^CACHE_STORE=.*|CACHE_STORE=redis|" "${ENV_FILE}"
sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=redis|" "${ENV_FILE}"
sed -i "s|^QUEUE_CONNECTION=.*|QUEUE_CONNECTION=redis|" "${ENV_FILE}"

if grep -q "^MONITORING_METRICS_TOKEN=" "${ENV_FILE}"; then
    sed -i "s|^MONITORING_METRICS_TOKEN=.*|MONITORING_METRICS_TOKEN=replace-with-secure-token|" "${ENV_FILE}"
else
    echo "MONITORING_METRICS_TOKEN=replace-with-secure-token" >> "${ENV_FILE}"
fi

echo "Generated ${ENV_FILE}."
echo "Next: cd ${APP_DIR}; php artisan key:generate; php artisan migrate --force"
