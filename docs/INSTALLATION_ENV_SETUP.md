# Installation and Environment Setup

## 1. Clone and Install

```bash
git clone <YOUR_REPO_URL> smart-blood
cd smart-blood
composer install
npm install
```

## 2. Environment Configuration

```bash
cp .env.example .env
php artisan key:generate
```

Set key values in `.env`:

- `APP_ENV=local`
- `APP_DEBUG=true`
- `DB_CONNECTION=mysql`
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `QUEUE_CONNECTION=redis`
- `CACHE_STORE=redis`
- `SANCTUM_TOKEN_EXPIRATION=120`

## 3. Database and Cache

```bash
php artisan migrate --force
```

Ensure Redis is running when queue and cache use Redis.

## 4. Run App

```bash
php artisan serve
npm run dev
php artisan queue:work:matching
php artisan queue:work:notifications
```

## 5. Verify

```bash
php artisan route:list --path=api
php artisan test
```
