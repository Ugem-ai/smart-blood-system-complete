# Smart Blood System - System Documentation

## Purpose

Smart Blood System coordinates blood donation requests between donors, hospitals, and administrators with role-based workflows, emergency matching, and operational monitoring.

## Core Capabilities

- Donor registration, profile management, and donation eligibility tracking.
- Hospital registration, approval workflow, and emergency request submission.
- Admin governance for approvals, user oversight, and analytics.
- PAST-Match ranking for donor prioritization.
- Notification dispatch for emergency requests and responses.
- Donation confirmation and history recording.
- Monitoring, audit logging, and security controls.

## Technology Stack

- Laravel 12 (PHP 8.2+)
- MySQL / MariaDB
- Redis for queue and cache
- Sanctum for API authentication
- Prometheus-compatible metrics endpoint

## Documentation Map

- Architecture: `docs/SYSTEM_ARCHITECTURE.md`
- Database schema: `docs/DATABASE_SCHEMA.md`
- Algorithm details: `docs/ALGORITHM_PAST_MATCH.md`
- API reference: `docs/API_DOCUMENTATION.md`
- Developer docs: `docs/DEVELOPER_GUIDE.md`
- Installation and environment: `docs/INSTALLATION_ENV_SETUP.md`
- Deployment: `DEPLOYMENT.md`
- Donor user guide: `docs/DONOR_USER_GUIDE.md`
- Hospital user guide: `docs/HOSPITAL_USER_GUIDE.md`
- Admin manual: `docs/ADMIN_MANUAL.md`
