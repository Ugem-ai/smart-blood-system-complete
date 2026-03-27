# Smart Blood System Deployment Guide

This guide covers production deployment using NGINX on Ubuntu for both AWS and DigitalOcean.

## 1. Provision a Production Server

Minimum recommended server:

- 2 vCPU
- 4 GB RAM
- 80 GB SSD
- Ubuntu 22.04 LTS

Supported providers:

- Amazon Web Services: EC2 instance + Elastic IP
- DigitalOcean: Droplet + Reserved IP

Install runtime dependencies:

```bash
sudo apt update
sudo apt install -y nginx git unzip redis-server certbot python3-certbot-nginx
sudo apt install -y php8.2-fpm php8.2-cli php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath php8.2-mysql
```

Install Node.js 24 and Composer:

```bash
curl -fsSL https://deb.nodesource.com/setup_24.x | sudo -E bash -
sudo apt install -y nodejs composer
```

## 2. Deploy Application Code

```bash
sudo mkdir -p /var/www/smart-blood
sudo chown -R $USER:$USER /var/www/smart-blood
git clone <YOUR_REPO_URL> /var/www/smart-blood
cd /var/www/smart-blood
cp .env.example .env
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan key:generate
```

Set permissions:

```bash
sudo chown -R www-data:www-data /var/www/smart-blood
sudo chmod -R 775 /var/www/smart-blood/storage /var/www/smart-blood/bootstrap/cache
```

## 3. Configure Environment Variables

Update `/var/www/smart-blood/.env` for production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

LOG_CHANNEL=stack
LOG_LEVEL=info

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smartblood
DB_USERNAME=smartblood_user
DB_PASSWORD=strong_password

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

MONITORING_METRICS_TOKEN=replace-with-secure-token
NOTIFICATION_MAX_BURST=20

FCM_SERVER_KEY=
TWILIO_SID=
TWILIO_AUTH_TOKEN=
TWILIO_FROM=
```

Run production optimization and migrations:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

## 4. Configure NGINX

Copy the provided config:

```bash
sudo cp deployment/nginx/smart-blood.conf /etc/nginx/sites-available/smart-blood
sudo ln -s /etc/nginx/sites-available/smart-blood /etc/nginx/sites-enabled/smart-blood
sudo nginx -t
sudo systemctl reload nginx
```

If your PHP-FPM socket differs, update `fastcgi_pass` in `deployment/nginx/smart-blood.conf`.

## 5. Setup SSL (Let's Encrypt)

```bash
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
sudo certbot renew --dry-run
```

This configures HTTPS and auto-redirect from HTTP.

## 6. Configure Queue Workers

Install systemd units:

```bash
sudo cp deployment/systemd/smart-blood-queue-matching.service /etc/systemd/system/
sudo cp deployment/systemd/smart-blood-queue-notifications.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable smart-blood-queue-matching smart-blood-queue-notifications
sudo systemctl start smart-blood-queue-matching smart-blood-queue-notifications
```

Check worker status:

```bash
sudo systemctl status smart-blood-queue-matching
sudo systemctl status smart-blood-queue-notifications
```

## 7. Optional Zero-Downtime Deploy Script

A deployment script is included at `deployment/scripts/deploy.sh`.

Usage:

```bash
chmod +x deployment/scripts/deploy.sh
./deployment/scripts/deploy.sh
```

## 8. Post-Deployment Verification

```bash
php artisan about
php artisan route:list --path=api/v1/monitor
curl -I https://your-domain.com
curl -H "Authorization: Bearer <token>" https://your-domain.com/api/v1/me
curl -H "X-Metrics-Token: <MONITORING_METRICS_TOKEN>" https://your-domain.com/api/v1/monitor/metrics
```

Expected outcomes:

- Application responds on HTTPS.
- API authentication works.
- Queue workers are active.
- Monitoring endpoints are reachable.

## 9. Phase 28 Deployment Simulation (AWS and DigitalOcean)

This simulation provides a reproducible path for both cloud providers without requiring provider-specific IaC.

### 9.1 AWS EC2 simulation path

1. Create Ubuntu 22.04 EC2 instance (2 vCPU, 4 GB RAM minimum).
2. Attach Elastic IP and point domain A record to that IP.
3. Security group rules:
	- Inbound TCP 22 from your admin IP.
	- Inbound TCP 80 and 443 from `0.0.0.0/0`.
	- Outbound allow all.
4. SSH in and run:

```bash
chmod +x deployment/scripts/bootstrap-server.sh
sudo ./deployment/scripts/bootstrap-server.sh aws
```

### 9.2 DigitalOcean Droplet simulation path

1. Create Ubuntu 22.04 Droplet (Basic, 2 vCPU, 4 GB RAM minimum).
2. Attach Reserved IP and point domain A record to that IP.
3. In cloud firewall, allow 22 (admin IP only), 80, and 443.
4. SSH in and run:

```bash
chmod +x deployment/scripts/bootstrap-server.sh
sudo ./deployment/scripts/bootstrap-server.sh do
```

### 9.3 Database server setup

For single-server simulation (MySQL on same host):

```bash
chmod +x deployment/scripts/setup-database.sh
sudo ./deployment/scripts/setup-database.sh smartblood smartblood_user 'replace-with-strong-password'
```

For managed DB services:

- AWS RDS MySQL: update `.env` with private RDS endpoint and credentials.
- DigitalOcean Managed MySQL: use cluster private hostname and enforce SSL mode if enabled.

### 9.4 Environment variable setup

```bash
chmod +x deployment/scripts/setup-env.sh
./deployment/scripts/setup-env.sh your-domain.com 127.0.0.1 3306 smartblood smartblood_user 'replace-with-strong-password'
```

Then review `.env` and set secrets (`FCM_SERVER_KEY`, `TWILIO_*`, `MONITORING_METRICS_TOKEN`) before caching config.

### 9.5 NGINX and SSL activation

```bash
sudo cp deployment/nginx/smart-blood.conf /etc/nginx/sites-available/smart-blood
sudo ln -sf /etc/nginx/sites-available/smart-blood /etc/nginx/sites-enabled/smart-blood
sudo nginx -t
sudo systemctl reload nginx
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
sudo certbot renew --dry-run
```

### 9.6 Queue workers as services

```bash
sudo cp deployment/systemd/smart-blood-queue-matching.service /etc/systemd/system/
sudo cp deployment/systemd/smart-blood-queue-notifications.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable smart-blood-queue-matching smart-blood-queue-notifications
sudo systemctl restart smart-blood-queue-matching smart-blood-queue-notifications
sudo systemctl status smart-blood-queue-matching smart-blood-queue-notifications --no-pager
```
