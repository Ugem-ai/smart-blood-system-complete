# Emergency Dashboard with Grafana

## Prometheus Metrics

The following metrics are exposed at `/api/v1/monitor/metrics`:

- `smartblood_emergency_live_blood_requests`
- `smartblood_emergency_active_donor_alerts`
- `smartblood_emergency_accepted_requests`
- `smartblood_emergency_donations_completed`

If `services.monitoring.metrics_token` is configured, include the token via header (`X-Metrics-Token`).

## Import Grafana Dashboard

1. Open Grafana.
2. Go to Dashboards -> New -> Import.
3. Upload [deployment/grafana/smart-blood-emergency-dashboard.json](../deployment/grafana/smart-blood-emergency-dashboard.json).
4. Select your Prometheus datasource.
5. Save dashboard.

## Alert Rules

Prometheus alert rules file:

- [deployment/monitoring/smart-blood-alerts.yml](../deployment/monitoring/smart-blood-alerts.yml)

Example `prometheus.yml` snippet:

```yaml
rule_files:
  - /etc/prometheus/rules/smart-blood-alerts.yml
```

Then reload Prometheus:

```bash
curl -X POST http://localhost:9090/-/reload
```
