# Smart Blood System - Quick Start Guide

## Prerequisites Check

Before starting the servers, verify you have these tools installed and accessible:

```bash
php --version
node --version
npm --version
composer --version
```

## Starting the Development Environment

### Method 1: Using Composer Dev Runner (RECOMMENDED)

The easiest way to start all services together:

```bash
cd "c:\Users\Acer\Desktop\Smart Blood System\smart-blood"
composer run dev
```

This will start:
- ✅ Laravel development server (http://localhost:8000)
- ✅ Laravel queue listener
- ✅ Vite dev server (http://localhost:5173)

All in one command with concurrent execution!

### Method 2: Manual Start (Separate Terminals)

If you prefer to run services separately:

**Terminal 1 - Start Laravel Server:**
```bash
cd "c:\Users\Acer\Desktop\Smart Blood System\smart-blood"
php artisan serve
```
You should see: `Laravel development server started on http://127.0.0.1:8000`

**Terminal 2 - Start Vite Dev Server:**
```bash
cd "c:\Users\Acer\Desktop\Smart Blood System\smart-blood"
npm run dev
```
You should see: `Local: http://localhost:5173`

### Method 3: Using Batch Scripts

Windows batch scripts are available in the project:

```cmd
startup.bat
```

This will:
1. Check all prerequisites
2. Verify database and migrations
3. Clear all caches
4. Verify dependencies
5. Start all servers

## Expected Startup Output

When services are running correctly, you should see:

```
[dev-runner] Starting: server, queue, vite

server   | Laravel development server started on http://127.0.0.1:8000
queue    | Starting queue worker
vite     | VITE v7.0.7 building for production...
vite     | ✓ 1234 modules transformed.
vite     | Local: http://localhost:5173
```

## Accessing the Application

Once servers are running:

1. **Web Application**: http://localhost:8000
   - Login page will be displayed
   - Use your credentials to access the system

2. **Dev Tools** (if needed): http://localhost:5173
   - Vite development server for frontend assets

## Stopping the Servers

- If using `composer run dev`: Press **Ctrl+C** to stop all services
- If using separate terminals: Press **Ctrl+C** in each terminal

## Troubleshooting

### Issue: Port 8000 already in use
```bash
php artisan serve --port=8001
```

### Issue: Vite port 5173 in use
```bash
npm run dev -- --port 5174
```

### Issue: Database migrations need to be run
```bash
php artisan migrate --force
```

### Issue: Cache issues
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Issue: Dependencies not installed
```bash
composer install
npm install
```

## Environment Configuration

Your application is configured with:
- **Database**: SQLite (database.sqlite)
- **Cache**: Database-backed cache
- **Session**: Database-backed sessions
- **Mail**: Log-based (for development)
- **Frontend Framework**: Vue 3 with Vite

See `.env` file for all configuration options.

## Next Steps

1. Start the servers using one of the methods above
2. Navigate to http://localhost:8000
3. Log in with your credentials
4. Start developing!

For more information, see:
- `README.md` - Project overview
- `SYSTEM_DOCUMENTATION.md` - System architecture
- `DEPLOYMENT.md` - Production deployment
- `VUE_FRONTEND_SETUP.md` - Frontend development
