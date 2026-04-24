                              1# Pilot Day-0 Runbook

Date: 2026-03-27
Scope: First-day go-live operations for 3 pilot hospitals

## 1. Roles and Contacts
- Incident Commander: Project Lead
- Technical Lead: Backend/Infra owner
- Hospital Coordinator: One per pilot hospital
- On-call channels: phone, chat, email escalation

## 2. Pre-Go-Live (T-2h)
- Confirm production env values and secrets are set.
- Confirm database connectivity and migration state.
- Confirm queues and workers are active.
- Confirm metrics endpoint scrape works with X-Metrics-Token header.
- Confirm Grafana dashboard panels have live data.
- Confirm alert route (email/chat/pager) is receiving test alert.

## 3. Go-Live Checklist (T-0)
- Enable hospital users for all 3 pilot hospitals.
- Verify each hospital can login and create one test request.
- Verify donor notifications are delivered (SMS/push configured path).
- Verify accepted donor response appears in hospital tracker.
- Verify admin dashboard reflects live request/response counters.

## 4. First 4-Hour Operating Window
- Monitoring cadence: every 15 minutes.
- Required metrics to track:
  - API response latency
  - Request creation success rate
  - Notification delivery success/failure
  - Acceptance-to-completion lead time
- If latency or failures spike, trigger incident protocol.

## 5. Incident Triage Levels
- Sev-1: System unavailable or data integrity risk.
- Sev-2: Core workflow degraded (request creation/notification failures).
- Sev-3: Non-blocking defects with workaround.

## 6. Rollback Criteria
- Sev-1 unresolved for > 15 minutes.
- Request creation failure rate > 20% for 10 minutes.
- Notification failure rate > 30% for 10 minutes.

## 7. Rollback Steps
- Pause new hospital onboarding.
- Switch pilot hospitals to manual fallback process.
- Revert to last known stable release tag.
- Clear and rebuild Laravel caches.
- Restart queue workers.
- Announce status and ETA updates every 15 minutes.

## 8. End-of-Day Evidence Capture
- Export key Grafana charts (Day-0 period).
- Collect request/response counts from admin panel.
- Record incidents, mitigations, and resolutions.
- Gather per-hospital signoff using signoff template.

## 9. Day+1 Action Items
- Review defects and classify hotfix vs backlog.
- Adjust alert thresholds from real Day-0 data.
- Confirm pilot schedule for next operating day.
