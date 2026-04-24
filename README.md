# Smart Blood System

Smart Blood System is a Laravel-based platform for emergency blood request coordination across donors, hospitals, and administrators.

## Documentation Index

### System Documentation

- System overview: `docs/SYSTEM_OVERVIEW.md`
- System architecture: `docs/SYSTEM_ARCHITECTURE.md`
- Database schema: `docs/DATABASE_SCHEMA.md`
- Algorithm explanation: `docs/ALGORITHM_PAST_MATCH.md`
- API documentation: `docs/API_DOCUMENTATION.md`

### Developer Documentation

- Developer guide: `docs/DEVELOPER_GUIDE.md`
- Installation and environment setup: `docs/INSTALLATION_ENV_SETUP.md`
- Deployment steps: `DEPLOYMENT.md`

### User Documentation

- Donor user guide: `docs/DONOR_USER_GUIDE.md`
- Hospital user guide: `docs/HOSPITAL_USER_GUIDE.md`
- Admin manual: `docs/ADMIN_MANUAL.md`

### Operations and Validation

- Monitoring and operational reference: `SYSTEM_DOCUMENTATION.md`
- Load testing guide: `LOAD_TESTING.md`
- **Implemented phases and roadmap (29 complete + Phase 30 planned):** `IMPLEMENTED_FEATURES_ALL_29_PHASES.md`
- **Project completion summary:** `COMPLETION_SUMMARY.md`

### Product Roadmap

- **Phase 30 Future Expansion Plan:** Included in `COMPLETION_SUMMARY.md` and `IMPLEMENTED_FEATURES_ALL_29_PHASES.md`
- Expansion tracks: national registry integration, AI demand prediction, route optimization, mobile app, government health interoperability

### Thesis & Deployment

- **[Fast Start] Phase 29 Quick Reference:** `docs/QUICK_REFERENCE_PHASE29.md` (copy-paste deployment commands)
- **Full Deployment Runbook:** `docs/THESIS_DEPLOYMENT_RUNBOOK.md` (complete 10-step guide for DigitalOcean + thesis evaluation)
- **Thesis Results Template:** `docs/THESIS_CHAPTER4_TEMPLATE.md` (ready-to-use for Chapter 4 Results)

## Quick Start

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --force
composer run dev
```

On Windows, `composer run dev` automatically skips Laravel Pail and starts the backend server, queue worker, and Vite dev server with a Windows-safe launcher.

Run tests:

```bash
php artisan test
```

## Phone Testing With ngrok

To test the app on a phone without deploying it, build the frontend once and serve the Laravel app locally:

```bash
composer phone:test
```

Then expose the local server with ngrok in a separate terminal:

```bash
ngrok http 8000
```

Sync the current ngrok HTTPS URL into `.env` and clear Laravel config cache:

```bash
composer phone:sync-ngrok
```

Open the generated HTTPS ngrok URL on your phone. If login or session-based flows need the public URL reflected in Laravel, update `.env` temporarily:

```env
APP_URL=https://your-ngrok-subdomain.ngrok-free.app
SANCTUM_STATEFUL_DOMAINS=your-ngrok-subdomain.ngrok-free.app
SESSION_SECURE_COOKIE=true
```

Apply the config change with:

```bash
php artisan config:clear
php artisan cache:clear
```

The `composer phone:sync-ngrok` command automates those `.env` updates for the currently running ngrok tunnel.
