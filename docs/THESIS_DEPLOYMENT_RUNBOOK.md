# Thesis Evaluation Deployment Runbook

## Full Setup for DigitalOcean

This runbook takes you from cloud server spin-up to final thesis metrics in a reproducible way.

### Prerequisites

- DigitalOcean Droplet (Ubuntu 22.04, 2 vCPU, 4 GB RAM)
- Domain with A record pointing to your droplet IP
- ~30 minutes for initial setup + 7-30 days for metric collection

### Step 1: Initial Server Bootstrap

```bash
# SSH into your droplet
ssh root@your-droplet-ip

# Clone your repo and run bootstrap
cd /var/www
git clone <YOUR_REPO_URL> smart-blood
cd smart-blood

chmod +x deployment/scripts/bootstrap-server.sh
chmod +x deployment/scripts/setup-env.sh
chmod +x deployment/scripts/setup-database.sh
chmod +x deployment/scripts/setup-thesis-evaluation.sh

sudo ./deployment/scripts/bootstrap-server.sh do
```

### Step 2: App Deployment + Environment

```bash
# Set up database and environment
sudo ./deployment/scripts/setup-database.sh smartblood smartblood_user '<STRONG_DB_PASSWORD>'
sudo APP_DIR=/var/www/smart-blood ./deployment/scripts/setup-env.sh your-domain.com 127.0.0.1 3306 smartblood smartblood_user '<STRONG_DB_PASSWORD>'

# Deploy code
sudo chown -R www-data:www-data /var/www/smart-blood
sudo chmod -R 775 /var/www/smart-blood/storage /var/www/smart-blood/bootstrap/cache
sudo ./deployment/scripts/deploy.sh
```

### Step 3: NGINX + SSL

```bash
sudo cp deployment/nginx/smart-blood.conf /etc/nginx/sites-available/smart-blood
sudo ln -sf /etc/nginx/sites-available/smart-blood /etc/nginx/sites-enabled/smart-blood
sudo nginx -t
sudo systemctl reload nginx

# Issue SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com
sudo certbot renew --dry-run
```

### Step 4: Queue Workers

```bash
sudo cp deployment/systemd/smart-blood-queue-matching.service /etc/systemd/system/
sudo cp deployment/systemd/smart-blood-queue-notifications.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable smart-blood-queue-matching smart-blood-queue-notifications
sudo systemctl restart smart-blood-queue-matching smart-blood-queue-notifications
```

### Step 5: Enable Thesis Evaluation

```bash
# Run the thesis evaluation setup script
sudo ./deployment/scripts/setup-thesis-evaluation.sh

# It will:
# 1. Run migrations (creates system_uptime_samples table)
# 2. Install cron job for automatic uptime sampling (every minute)
# 3. Enable queue workers
# 4. Print next steps
```

### Step 6: Test the System

```bash
# Verify all components are up
curl -I https://your-domain.com

# Check queue workers
sudo systemctl status smart-blood-queue-matching smart-blood-queue-notifications --no-pager

# Check uptime sampling is working
ps aux | grep 'system:record-uptime-sample'

# Verify first uptime sample recorded
cd /var/www/smart-blood
php artisan tinker
>>> DB::table('system_uptime_samples')->latest()->first();
```

### Step 7: Generate Live Traffic (Optional but Recommended)

To get meaningful metrics, exercise the system:

1. Register a few test donors
2. Register a test hospital
3. Have admin approve the hospital
4. Create a blood request
5. Check donor matches
6. Record a donation

Or run the automated load test:

```bash
php artisan system:prepare-load-test --hospitals=5 --donors=100 --city="Your City"
# Then run k6 or JMeter against https://your-domain.com/api/hospital/request
```

### Step 8: Wait for Sampling Window

Uptime sampling runs every minute (1440 samples/day). For strong statistical results:

- **Minimum**: 7 days (10,080 samples) = ~99% confidence in uptime percentage
- **Recommended**: 30 days (43,200 samples) = very strong for thesis

### Step 9: Generate Final Thesis Report

After your measurement window (7-30 days):

```bash
# Generate evaluation metrics
cd /var/www/smart-blood
php artisan system:evaluate --days=30 --json=1

# Result is exported to: storage/app/evaluation/system-evaluation.md
```

The file contains:

```
# Final System Evaluation

Generated at: 2026-MM-DD HH:MM:SS
Evaluation window: last 30 days

## Thesis Evaluation Metrics

- matching accuracy: X%
- donor response rate: Y%
- request fulfillment time: Z minutes
- system uptime: W%

## Metric Definitions

[explanations for each metric]

## Supporting Counts

[raw data supporting each metric]
```

### Step 10: Use in Your Thesis

Copy the results section into your thesis "Chapter 4: Results" or "Evaluation" chapter:

```
## 4. System Evaluation Results

Our evaluation measured system effectiveness over a 30-day production window:

| Metric | Result |
|--------|--------|
| Matching Accuracy | X% |
| Donor Response Rate | Y% |
| Request Fulfillment Time | Z minutes |
| System Uptime | W% |

These results demonstrate that the Smart Blood System successfully...
[your analysis and interpretation]
```

### Troubleshooting

**Uptime sampling not running:**
```bash
# Check cron log
sudo tail -f /var/log/syslog | grep cron

# Manually run one sample
php artisan system:record-uptime-sample
```

**No metrics showing (0 requests):**
- System has no live data yet
- Either increase your evaluation window (--days=option) or wait longer
- Or manually create test data with system:prepare-load-test

**Database connection error:**
- Ensure MySQL is running: `sudo systemctl status mysql`
- Check Laravel logs: `tail -f storage/logs/laravel.log`

### Production Monitoring Tip

After deployment, I recommend monitoring these in real-time:

```bash
# Watch active database connections
watch "mysql -u smartblood_user -p<PASSWORD> smartblood -e 'SHOW PROCESSLIST;'"

# Monitor queue depth
watch "cd /var/www/smart-blood && php artisan queue:list"

# Check system resources
watch "free -h && df -h"
```

---

**Ready to defend your thesis!**
