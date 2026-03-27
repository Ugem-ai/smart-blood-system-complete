# Thesis Evaluation Results

## Objective

Measure final system effectiveness using reproducible metrics from live system data.

## Metrics

- matching accuracy
- donor response rate
- request fulfillment time
- system uptime

## Command

Run evaluation for the last 30 days:

```bash
php artisan system:evaluate --days=30
```

Export result to a thesis-ready report file:

```bash
php artisan system:evaluate --days=30 --export=storage/app/evaluation/system-evaluation.md
```

Output JSON for further analysis:

```bash
php artisan system:evaluate --days=30 --json=1
```

## Uptime Sampling

Record health samples periodically (for example every minute via cron):

```bash
php artisan system:record-uptime-sample
```

Recommended cron example:

```bash
* * * * * cd /var/www/smart-blood && /usr/bin/php artisan system:record-uptime-sample >> /dev/null 2>&1
```

## Metric Definitions

- matching accuracy = requests with at least one accepted donor response / requests with generated matches
- donor response rate = donor responses recorded / donor contacts (matches)
- request fulfillment time = average minutes from request creation to completed donation record
- system uptime = up health samples / total health samples

## Thesis Notes

- Always state your evaluation window (for example, last 30 days).
- Include both percentages and supporting raw counts.
- Re-run the same command before final defense to keep figures current.
