# API Documentation

Base path:

- `/api/v1`

Authentication:

- Bearer token via Sanctum.

## Public Endpoints

- `POST /api/v1/register`
- `POST /api/v1/login`
- `GET /api/v1/monitor/health`
- `GET /api/v1/monitor/metrics` (token-protected when configured)
- `POST /api/hospital/register`

## Authenticated Common Endpoints

- `GET /api/v1/me`
- `POST /api/v1/logout`

## Donor Endpoints

- `GET /api/donor/profile`
- `PUT /api/donor/update`
- `POST /api/donor/status`
- `POST /api/donor/accept`
- `POST /api/donor/decline`

## Hospital Endpoints

- `GET /api/hospital/profile`
- `POST /api/hospital/request`
- `GET /api/hospital/request/list`
- `GET /api/hospital/request/{bloodRequest}/matched-donors`
- `POST /api/hospital/confirm-donation`

`POST /api/hospital/request` response includes `operational_mode`:

- `disaster_response_active`
- `priority_request_applied`
- `expanded_radius_km`
- `mass_notification`

## Admin Endpoints

- `GET /api/admin/dashboard`
- `GET /api/admin/emergency-mode`
- `PATCH /api/admin/emergency-mode`
- `PATCH /api/admin/hospitals/{hospital}/approve`
- `PATCH /api/admin/hospitals/{hospital}/reject`
- `GET /api/admin/requests`
- `GET /api/admin/donors/active`
- `GET /api/admin/users`
- `PATCH /api/admin/users/{user}`
- `DELETE /api/admin/users/{user}`

`GET /api/admin/emergency-mode` returns:

- `data.emergency_mode`
- `data.disaster_response_mode`

`GET /api/admin/dashboard` includes:

- `emergency_mode`
- `disaster_response_mode`
- `smart_notification_timing`

## Security Controls on API

- Role middleware on donor/hospital/admin groups.
- Throttle middleware for auth and protected endpoints.
- Validation rules for payloads.
- Audit logging middleware for authenticated access.
