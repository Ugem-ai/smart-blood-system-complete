# Developer Documentation

## Prerequisites

- PHP 8.2+
- Composer
- Node.js + npm
- MySQL/MariaDB
- Redis (recommended)

## Local Setup

1. Install dependencies:

```bash
composer install
npm install
```

2. Configure env:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure database in `.env`, then run migrations:

```bash
php artisan migrate --force
```

4. Start dev services:

```bash
php artisan serve
php artisan queue:work:matching
php artisan queue:work:notifications
npm run dev
```

## Testing

Run full suite:

```bash
php artisan test
```

Focused suites:

```bash
php artisan test --filter=SystemValidationPipelineTest
php artisan test --filter=PASTMatchAlgorithmValidationTest
php artisan test --filter=PerformanceTest
php artisan test --filter=SecurityHardeningTest
```

## Coding Guidelines

- Prefer service-layer logic over controller-heavy implementations.
- Keep validation in request/controller boundary.
- Use Eloquent/Query Builder with parameterized queries.
- Add tests for every behavior change.
- Preserve role and privacy boundaries in all new endpoints.
