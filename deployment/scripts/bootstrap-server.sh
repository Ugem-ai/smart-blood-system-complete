#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run as root: sudo ./deployment/scripts/bootstrap-server.sh <aws|do>"
    exit 1
fi

PROVIDER="${1:-generic}"
APP_DIR="/var/www/smart-blood"

echo "[1/6] Installing base packages for ${PROVIDER}"
apt update
apt install -y nginx git unzip redis-server certbot python3-certbot-nginx ufw
apt install -y php8.2-fpm php8.2-cli php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-mysql mysql-server

echo "[2/6] Installing Node.js 24 and Composer"
curl -fsSL https://deb.nodesource.com/setup_24.x | bash -
apt install -y nodejs composer

echo "[3/6] Preparing application directory"
mkdir -p "${APP_DIR}"
chown -R www-data:www-data "${APP_DIR}"

echo "[4/6] Enabling required services"
systemctl enable nginx php8.2-fpm redis-server mysql
systemctl restart nginx php8.2-fpm redis-server mysql

echo "[5/6] Configuring firewall (SSH, HTTP, HTTPS)"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable

echo "[6/6] Bootstrap complete"
echo "Next steps:"
echo "  1) Clone repo into ${APP_DIR}"
echo "  2) Run setup-env.sh and setup-database.sh"
echo "  3) Run deployment/scripts/deploy.sh"
echo "  4) Install NGINX site and issue SSL cert with certbot"
