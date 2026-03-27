# Smart Blood System - System Documentation

## 1. System Overview

Smart Blood System is a Laravel 12 platform that coordinates blood donation and emergency blood requests across donors, hospitals, and administrators.

Core goals:

- Register and manage donor/hospital accounts.
- Process urgent blood requests.
- Match compatible donors using weighted ranking.
- Track operational activity, notifications, and system health.

## 2. Architecture

Application layers:

- Controllers: HTTP/Web and API request handling.
- Services: domain workflows and business logic.
- Algorithms: weighted matching and ranking.
- Jobs/Queues: async matching and notifications.
- Middleware: role access, audit trail, monitoring.
- Models: Eloquent domain entities and relationships.

Key folders:

- `app/Http/Controllers`: role-specific dashboards and APIs.
- `app/Services`: filtering, notifications, monitoring, matching service.
- `app/Algorithms`: PAST-Match weighted donor ranking.
- `app/Jobs`: queue-based matching + emergency notifications.
- `app/Models`: users, donors, hospitals, requests, matches, histories, logs.

## 3. Main Functional Modules

### Authentication and Access Control

- Sanctum-based token authentication for API clients.
- Role middleware for `donor`, `hospital`, `admin` flows.

### Donor Management

- Donor profile update and availability toggling.
- Donation history tracking and eligibility checks.

### Hospital Request Management

- Hospital request creation with urgency and units.
- Request lifecycle: `pending`, `matching`, `completed`.

### Matching Engine

- `DonorFilterService`: blood compatibility, interval, distance filtering.
- `PASTMatch`: weighted score by proximity, availability, donation interval, travel time, reliability.
- `PastMatchService`: city-based fallback matching and ranking utility.

### Notifications

- Emergency donor alert dispatching.
- Donor response update notifications.
- Donation confirmation notifications.

### Monitoring and Audit

- API latency and request processing instrumentation.
- Notification success/failure counters.
- Prometheus-compatible metrics endpoint and health endpoint.
- Audit trail logging for authenticated data access.

## 4. Data Model (High Level)

Main entities:

- `users`: identity and role source of truth.
- `donors`: donor profile, location, eligibility data.
- `hospitals`: hospital profile and approval status.
- `blood_requests`: request demand, urgency, lifecycle state.
- `request_matches`: ranked donor matches for requests.
- `donation_histories`: confirmed completed donations.
- `donor_request_responses`: donor accept/decline decisions.
- `activity_logs`: auditable action log.

## 5. Queues and Background Processing

Queue backend:

- Redis (`QUEUE_CONNECTION=redis`).

Dedicated workers:

- Matching worker: `php artisan queue:work:matching`
- Notifications worker: `php artisan queue:work:notifications`

Production services:

- `deployment/systemd/smart-blood-queue-matching.service`
- `deployment/systemd/smart-blood-queue-notifications.service`

## 6. Monitoring Endpoints

- `GET /api/v1/monitor/metrics`
- `GET /api/v1/monitor/health`

If `MONITORING_METRICS_TOKEN` is set, send it as `X-Metrics-Token` for metrics scraping.

### Phase 26 Operational KPIs

The Prometheus metrics payload includes operational visibility KPIs for dashboards:

- `smartblood_active_donors`
- `smartblood_daily_requests`
- `smartblood_successful_donations`
- `smartblood_average_response_time_minutes`

These KPIs are intended for Grafana panels tracking live system demand, donor supply, fulfillment outcomes, and response efficiency.

## 7. Deployment Reference

Use:

- `DEPLOYMENT.md`
- `deployment/nginx/smart-blood.conf`
- `deployment/scripts/deploy.sh`

Supported cloud options documented:

- Amazon Web Services (EC2)
- DigitalOcean (Droplet)

## 8. Final Polish Tooling (Phase 20)

### Load Testing

Synthetic matching load test command:

```bash
php artisan system:load-test --iterations=500 --bloodType=A+ --city=Manila --limit=10
```

### Emergency Scenario Simulation

Emergency burst simulation command:

```bash
php artisan system:simulate-emergency --requests=10 --bloodType=O- --city=Manila --limit=10
```

If no donor dataset is available, append `--seed-if-empty=1` to generate temporary in-transaction simulation donors.

Outputs include served/unserved request counts and score snapshots for readiness checks.

## 8.1 Phase 24 Load Testing

Laravel-side preparation command:

```bash
php artisan system:prepare-load-test --hospitals=50 --donors=10000 --city="Metro Manila"
```

External load-test assets:

- `tests/load/phase24-k6-emergency-demand.js`
- `tests/load/jmeter/phase24-emergency-demand.jmx`
- `LOAD_TESTING.md`

Operational metrics to monitor during the emergency spike:

- CPU usage
- memory usage
- database queries
- queue worker load

The detailed execution guide for k6 and Apache JMeter is in `LOAD_TESTING.md`.

## 9. Security and Privacy Notes

- Sensitive profile fields are encrypted at rest where configured.
- API access is role-restricted and audit logged.
- Privacy consent is required for donor registration.

### Phase 25 Security Hardening

Compliance focus:

- Data Privacy Act of 2012 (Republic Act No. 10173)

Authentication and API protection:

- Password hashing is enforced (`Hash::make` and model hash casts).
- Sanctum token expiration is configured via `SANCTUM_TOKEN_EXPIRATION`.
- Role-based access is enforced with `role` middleware.
- API rate limiting is applied to authentication and protected API groups.
- Input validation is enforced on API payloads.
- SQL injection risk is reduced by using Eloquent/Query Builder parameterized queries.

Data protection controls:

- Sensitive donor and hospital fields use encrypted casts where configured.
- Donor contact/location data is exposed only through hospital-authorized request scope endpoint.
- Donor role is blocked from hospital-only sensitive-data endpoint.

Audit logging coverage:

- Admin approvals/rejections.
- Donor profile and availability updates.
- Hospital access events for request list and matched-donor details.
- Generic authenticated API data-access middleware logs.

## 10. Testing and Quality

Run full test suite:

```bash
php artisan test
```

Run matching performance regression test only:

```bash
php artisan test --filter=PastMatchPerformanceTest
```

Optional threshold override for CI environments:

```bash
PASTMATCH_BENCHMARK_P95_MS=150
```

The project includes feature tests for role modules and unit tests for matching behavior.
