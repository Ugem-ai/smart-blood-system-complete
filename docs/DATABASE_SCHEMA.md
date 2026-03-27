# Database Schema (High-Level)

## Users and Roles

### `users`

- id
- name
- email
- password
- role (`donor`, `hospital`, `admin`)
- timestamps

## Donor Domain

### `donors`

- user_id (1:1 with users)
- blood_type
- city
- contact_number / phone (encrypted)
- latitude, longitude
- last_donation_date
- availability
- reliability_score
- privacy_consent_at

### `donation_histories`

- donor_id
- hospital_id
- request_id
- donated_at / donation_date
- units
- status
- location

## Hospital Domain

### `hospitals`

- user_id (1:1 with users)
- hospital_name
- address/location (encrypted)
- latitude, longitude
- contact_person / contact_number (encrypted)
- status (`pending`, `approved`, `rejected`)

## Request and Matching Domain

### `blood_requests`

- hospital_id
- blood_type
- units_required / quantity / requested_units
- urgency_level
- city
- latitude, longitude
- status (`pending`, `matching`, `completed`, `cancelled`)
- matched_donors (array)

### `matches`

- blood_request_id
- request_id
- donor_id
- score
- rank
- response_status (`pending`, `accepted`, `declined`, `expired`)

### `donor_request_responses`

- donor_id
- blood_request_id
- response (`accepted`, `declined`)
- responded_at

## Operations and Compliance

### `activity_logs`

- user_id
- action
- metadata (JSON)
- timestamps

Used for audit trail and compliance visibility.
