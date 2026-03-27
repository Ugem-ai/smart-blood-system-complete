# System Architecture

## Layered Design

- Presentation layer: Blade dashboards and API endpoints.
- Application layer: Controllers orchestrating validation and workflows.
- Domain layer: Services and algorithms (matching, filtering, notifications).
- Infrastructure layer: Queue workers, cache, database, monitoring middleware.

## Main Components

- Controllers:
  - `app/Http/Controllers` for web dashboards.
  - `app/Http/Controllers/Api` for API endpoints.
- Services:
  - `DonorFilterService`
  - `PastMatchService`
  - `NotificationService`
  - `MonitoringMetricsService`
- Algorithms:
  - `PASTMatch` weighted ranking engine.
- Jobs:
  - `ProcessBloodRequestMatchingJob`
  - `SendEmergencyNotificationsJob`
- Middleware:
  - `RoleMiddleware`
  - `AuditTrailMiddleware`
  - `MonitoringMiddleware`

## Request Flow (Emergency Request)

1. Hospital submits blood request.
2. Matching job is dispatched to queue.
3. Donor filtering + PAST-Match ranking runs.
4. Match records are persisted.
5. Notification job dispatches donor alerts.
6. Donor accepts/declines.
7. Hospital confirms donation.
8. Donation history and analytics update.

## Security and Observability

- Sanctum token auth + expiration.
- Role-based route enforcement.
- API throttling and strict validation.
- Audit events for sensitive actions.
- Prometheus metrics for operations and performance.
