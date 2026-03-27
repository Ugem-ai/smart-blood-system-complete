# National Systems Integration

## Goal

Provide a future-ready integration layer so Smart Blood can connect with national health organizations and scale beyond a single region.

## Example Partner

- Philippine Red Cross (`philippine_red_cross`)

## Admin API Endpoints

All endpoints below require admin role and Sanctum authentication.

- `GET /api/admin/national-integrations/partners`
- `POST /api/admin/national-integrations/{partner}/sync-emergency`
- `GET /api/admin/national-integrations/logs`

## Partner Configuration

Configured in [config/services.php](../config/services.php):

```php
'national_integrations' => [
    'timeout_seconds' => env('NATIONAL_INTEGRATION_TIMEOUT_SECONDS', 10),
    'partners' => [
        'philippine_red_cross' => [
            'label' => 'Philippine Red Cross',
            'enabled' => env('NATIONAL_PARTNER_PRC_ENABLED', false),
            'endpoint' => env('NATIONAL_PARTNER_PRC_ENDPOINT'),
            'token' => env('NATIONAL_PARTNER_PRC_TOKEN'),
            'scope' => 'national',
        ],
    ],
],
```

## What Gets Synced

`sync-emergency` pushes:

- Source metadata (system/environment/generated timestamp)
- Partner metadata (key/label/scope)
- Real-time emergency dashboard snapshot:
  - live blood requests
  - active donor alerts
  - accepted requests
  - donations completed

## Auditability

Every sync attempt is logged in `national_partner_sync_logs` with:

- partner key
- status (`success`, `failed`, `skipped`)
- HTTP status (if available)
- request/response payload
- error message (if any)
- actor user and timestamp
