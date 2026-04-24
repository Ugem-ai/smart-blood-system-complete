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

## 6. Test On A Phone Without Deploying

Build the frontend and run the local phone-testing runner:

```bash
composer phone:test
```

In another terminal, expose the local Laravel server through ngrok:

```bash
ngrok http 8000
```

After ngrok starts, sync the live HTTPS tunnel URL into `.env` and refresh Laravel config:

```bash
composer phone:sync-ngrok
```

Open the HTTPS ngrok URL on your phone.

If you want Laravel to generate links and secure cookies against the ngrok address, update `.env` temporarily:

- `APP_URL=https://your-ngrok-subdomain.ngrok-free.app`
- `SANCTUM_STATEFUL_DOMAINS=your-ngrok-subdomain.ngrok-free.app`
- `SESSION_SECURE_COOKIE=true`

Then clear cached config:

```bash
php artisan config:clear
php artisan cache:clear
```

The `composer phone:sync-ngrok` command performs those `.env` updates and cache clears automatically for the active tunnel.
