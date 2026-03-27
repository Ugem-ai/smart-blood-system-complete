# Phase 24 Load Testing

This guide simulates real emergency demand with 50 hospitals sending blood requests simultaneously.

## 1. Prepare Data in Laravel

Seed approved hospital accounts and a donor pool:

```bash
php artisan system:prepare-load-test --hospitals=50 --donors=10000 --city="Metro Manila"
```

Output:

- Approved hospital users: `load.hospital.1@example.com` through `load.hospital.50@example.com`
- Generated credentials CSV: `storage/app/load-test/phase24-hospitals.csv`
- Large donor pool for matching and queue pressure

## 2. k6 Scenario

Script:

- `tests/load/phase24-k6-emergency-demand.js`

Run:

```bash
k6 run tests/load/phase24-k6-emergency-demand.js \
  -e BASE_URL=http://127.0.0.1:8000 \
  -e HOSPITAL_COUNT=50 \
  -e HOSPITAL_PASSWORD=Password123! \
  -e CITY="Metro Manila" \
  -e METRICS_TOKEN=<MONITORING_METRICS_TOKEN>
```

What it does:

- Logs in 50 approved hospital users
- Fires 50 emergency request submissions concurrently
- Optionally scrapes `/api/v1/monitor/metrics` during teardown

## 3. Apache JMeter Scenario

Plan:

- `tests/load/jmeter/phase24-emergency-demand.jmx`

Input CSV:

- `storage/app/load-test/phase24-hospitals.csv`

Run example:

```bash
jmeter -n \
  -t tests/load/jmeter/phase24-emergency-demand.jmx \
  -JBASE_URL=http://127.0.0.1:8000 \
  -JCSV_FILE=storage/app/load-test/phase24-hospitals.csv \
  -JCITY="Metro Manila"
```

What it does:

- Starts 50 threads
- Logs in one hospital per thread
- Creates one emergency request per thread
- Emits Summary Report data

## 4. Metrics to Monitor

### CPU Usage

Monitor on the server while k6/JMeter runs.

Linux examples:

```bash
top
htop
mpstat 1
```

### Memory Usage

Linux examples:

```bash
free -m
vmstat 1
```

### Database Queries

Use the Prometheus-compatible metrics endpoint:

```bash
curl -H "X-Metrics-Token: <MONITORING_METRICS_TOKEN>" http://127.0.0.1:8000/api/v1/monitor/metrics
```

Also track DB process load:

```bash
mysqladmin processlist
```

### Queue Worker Load

Run dedicated workers during the test:

```bash
php artisan queue:work:matching
php artisan queue:work:notifications
```

Monitor queue backlog:

```bash
php artisan queue:monitor redis:default --max=100
php artisan queue:monitor redis:matching --max=100
php artisan queue:monitor redis:notifications --max=100
```

## 5. Success Targets

Target thresholds for the emergency spike:

- Matching runtime stays within operational target
- API p95 stays under 200 ms
- Notification dispatch remains under 5 seconds per request burst window
- Queue backlog drains without sustained growth
- CPU and memory remain below server saturation

## 6. Recommended Test Flow

1. Start Laravel app and queue workers.
2. Run `php artisan system:prepare-load-test --hospitals=50 --donors=10000`.
3. Start CPU, memory, DB, and queue monitoring commands.
4. Run k6 or JMeter scenario.
5. Capture `/api/v1/monitor/metrics` before and after the run.
6. Review response times, queue backlog, and worker throughput.

## 7. Notes

- k6/JMeter are external tools and are not bundled with Laravel.
- Laravel-side preparation and monitoring support is included in this repository.
- For repeatable results, use a production-like environment with Redis queue workers enabled.
