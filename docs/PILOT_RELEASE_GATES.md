# Pilot Release Gates

Date: 2026-03-27
Scope: Smart Blood pilot readiness for first 3 hospitals

## Gate 1: Build and Test Health
- Status: PASS
- Evidence: full automated tests passed (114 passed, 0 failed)
- Exit criteria:
  - Full test suite passes
  - No critical static-analysis errors in touched files

## Gate 2: Security Controls
- Status: PASS (code), PARTIAL (ops)
- Evidence:
  - Hospital registration invite-code flow implemented and tested
  - Monitoring metrics endpoint requires X-Metrics-Token header when token is configured
  - Role-based middleware and route separation in place
- Open operational actions:
  - Rotate production secrets before pilot start
  - Confirm TLS cert validity on pilot domains

## Gate 3: Monitoring and Alerting
- Status: PASS (baseline), PARTIAL (integration)
- Evidence:
  - Prometheus metrics endpoint available
  - Emergency Grafana dashboard JSON present
  - Alert rules file present in deployment/monitoring
- Open operational actions:
  - Import dashboard into live Grafana
  - Wire Prometheus scrape job with X-Metrics-Token header
  - Validate alert delivery path (Pager/Email/Chat)

## Gate 4: Deployment Safety
- Status: PASS
- Evidence:
  - Deployment script updated to non-destructive fast-forward pull
  - Queue restart and Laravel cache optimizations included
- Exit criteria:
  - Deploy script succeeds in staging and one canary hospital

## Gate 5: Pilot Operations Readiness
- Status: PARTIAL
- Required before go-live:
  - Select and confirm 3 pilot hospitals
  - Complete staff onboarding and runbook signoff
  - Execute rollback drill and incident escalation drill
  - Capture baseline metrics snapshot before first live day

## Gate 6: Data and Compliance Readiness
- Status: PARTIAL
- Required before go-live:
  - Confirm backup schedule and restore test
  - Verify audit-log retention policy
  - Confirm least-privilege access for hospital users

## Go/No-Go Rule
- Go if Gates 1 and 4 are PASS and all Gate 2/3/5/6 open operational actions are completed.
- No-Go if any critical incident-response dependency is missing (alerting, rollback, or on-call coverage).
