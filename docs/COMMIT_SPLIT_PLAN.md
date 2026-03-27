# Commit Split Plan

Date: 2026-03-27

This plan splits the current large worktree into auditable commits.

## Commit 1: Security hardening baseline
Suggested scope:
- invite-code model/service/migration
- registration and admin invite endpoints
- metrics token header hardening
- related tests

Suggested command pattern:
- git add app/Models/HospitalInviteCode.php
- git add app/Services/HospitalInviteCodeService.php
- git add app/Http/Controllers/Api/AuthController.php
- git add app/Http/Controllers/Api/HospitalProfileController.php
- git add app/Http/Controllers/HospitalController.php
- git add app/Http/Controllers/Api/AdminPanelController.php
- git add app/Http/Controllers/Api/MonitoringController.php
- git add database/migrations/2026_03_27_000400_create_hospital_invite_codes_table.php
- git add tests/Feature/HospitalInviteCodeSecurityTest.php
- git add tests/Feature/MonitoringOperationalMetricsTest.php
- git add routes/api.php

Message:
- feat(security): add hospital invite codes and header-only metrics auth

## Commit 2: Emergency operations and services
Suggested scope:
- emergency broadcast/escalation/dashboard services
- monitoring metrics service additions
- national systems integration service
- inventory monitoring service

Message:
- feat(ops): add emergency broadcast, escalation, and monitoring services

## Commit 3: Frontend dashboard refactor
Suggested scope:
- Vue pages/components for admin/hospital/donor dashboards
- router/app shell updates
- style assets

Message:
- feat(frontend): implement role-based Vue dashboards and modules

## Commit 4: Deployment and infrastructure scripts
Suggested scope:
- deployment scripts/nginx/systemd/grafana/monitoring files
- deploy script hardening

Message:
- chore(deploy): add pilot deployment scripts and monitoring artifacts

## Commit 5: Documentation and runbooks
Suggested scope:
- docs/*.md
- DEPLOYMENT.md, SYSTEM_DOCUMENTATION.md, load/performance docs

Message:
- docs: add pilot runbooks, evaluation reports, and user manuals

## Commit 6: Test and validation expansions
Suggested scope:
- Feature/Unit/load tests not included above
- phpunit config changes

Message:
- test: expand feature, reliability, and load test coverage

## Commit 7: Post-stabilization fixes
Suggested scope:
- config/database.php static fix
- routes/web.php auth user accessor fix
- docs auth-header correction

Message:
- fix(stability): resolve config/route errors and monitoring docs mismatch

## Final verification before push
- php artisan test
- php artisan route:list
- php artisan config:clear
- npm run build (if frontend changes included)
