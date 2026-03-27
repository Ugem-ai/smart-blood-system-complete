# Quick Reference: Thesis Setup Commands

**Copy and paste sequentially on your DigitalOcean droplet.**

```bash
# 1. SSH into your droplet and go to project
ssh root@YOUR_DROPLET_IP
cd /var/www/smart-blood

# 2. Run the full thesis evaluation setup (installs migrations, cron, workers)
sudo ./deployment/scripts/setup-thesis-evaluation.sh

# 3. Verify uptime sampling started
php artisan system:record-uptime-sample
php artisan tinker
>>> DB::table('system_uptime_samples')->count();
# Should show: 1 or more rows

# 4. Verify queue workers are running
sudo systemctl status smart-blood-queue-matching smart-blood-queue-notifications --no-pager

# 5. (Optional) Generate synthetic test data
php artisan system:prepare-load-test --hospitals=5 --donors=50 --city="Your City"

# 6. Visit https://your-domain.com and manually create a test request (optional)

# 7. Wait 7-30 days (let cron sample uptime every minute)

# 8. After sampling window, generate final report
php artisan system:evaluate --days=30 --json=1
# Output file: storage/app/evaluation/system-evaluation.md

# 9. Retrieve and use in thesis
cat storage/app/evaluation/system-evaluation.md
```

**That's it! You now have production-grade thesis metrics.**

---

## What Each Command Does

| Command | Purpose |
|---------|---------|
| `setup-thesis-evaluation.sh` | Runs migrations (creates sampling table), installs uptime cron, enables workers |
| `system:record-uptime-sample` | Manually records one uptime snapshot (or let cron do it automatically) |
| `system:evaluate --days=30` | Computes thesis metrics from collected data and exports markdown report |
| `system:prepare-load-test` | Creates realistic test data for evaluation window |

---

## Verify Everything is Working

```bash
# All checks should show healthy status:

# 1. Check cron is running samples every minute
sudo systemctl status cron && ps aux | grep 'system:record-uptime-sample'

# 2. Check queue workers
sudo systemctl status smart-blood-queue-matching smart-blood-queue-notifications --no-pager

# 3. Check Laravel logs for errors
tail -f storage/logs/laravel.log

# 4. Check MySQL + Redis connectivity
php artisan tinker
>>> DB::connection()->getPdo();
>>> Cache::store('redis')->put('test', 'ok', 60);
>>> exit
```

If all green -> you're ready to start the 30-day evaluation window.