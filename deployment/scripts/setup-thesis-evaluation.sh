#!/usr/bin/env bash
set -euo pipefail

# Thesis Evaluation Setup Script
# Run this on your DigitalOcean server AFTER deployment is complete
# Usage: ./deployment/scripts/setup-thesis-evaluation.sh

APP_DIR="${APP_DIR:-/var/www/smart-blood}"
CRON_MINUTE="${CRON_MINUTE:-*}"
CRON_HOUR="${CRON_HOUR:-*}"

echo "[1/4] Running database migrations..."
cd "$APP_DIR"
/usr/bin/php artisan migrate --force

echo "[2/4] Setting up cron for automatic uptime sampling..."
CRON_JOB="$CRON_MINUTE $CRON_HOUR * * * cd $APP_DIR && /usr/bin/php artisan system:record-uptime-sample >> /dev/null 2>&1"

if crontab -l 2>/dev/null | grep -q "system:record-uptime-sample"; then
    echo "Uptime sampling cron already exists."
else
    (crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -
    echo "Cron installed: $CRON_JOB"
fi

echo "[3/4] Ensuring queue workers are active..."
sudo systemctl enable --now smart-blood-queue-matching smart-blood-queue-notifications
sudo systemctl status smart-blood-queue-matching smart-blood-queue-notifications --no-pager

echo "[4/4] Setup complete."
echo ""
echo "Next steps:"
echo "  1) Visit https://your-domain.com and exercise the system with test requests"
echo "  2) Wait at least 7 days (for statistically strong uptime sampling)"
echo "  3) Run: php artisan system:evaluate --days=30 --json=1"
echo "  4) Copy the exported report to your thesis: storage/app/evaluation/system-evaluation.md"
echo ""
