# Docker Deployment Guide for Render

## Overview
This guide covers deploying the Smart Blood System on Render using Docker.

---

## Required Environment Variables for Render

Create these environment variables in your Render service dashboard:

### Essential Variables
```bash
# Laravel Core
APP_NAME=SmartBlood
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:YOUR_GENERATED_KEY_HERE  # Generate with: php artisan key:generate
APP_URL=https://your-app-name.onrender.com

# Database Configuration (choose one)
# Option 1: SQLite (default, no external DB needed)
DB_CONNECTION=sqlite
DB_DATABASE=/app/storage/database.sqlite

# Option 2: MySQL (if using external database)
# DB_CONNECTION=mysql
# DB_HOST=your-db-host.render.com
# DB_PORT=3306
# DB_DATABASE=your_db_name
# DB_USERNAME=your_db_user
# DB_PASSWORD=your_db_password

# Session & Caching
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync

# Security
SANCTUM_TOKEN_EXPIRATION=120
BCRYPT_ROUNDS=12

# Laravel Environment
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stack
LOG_LEVEL=info
```

### Optional Variables (for your specific features)
```bash
# Philippine Red Cross specific
PRC_ADMIN_EMAIL_DOMAINS=redcross.org.ph,prc.org.ph
HOSPITAL_EMAIL_DOMAINS=hospital1.gov.ph,hospital2.gov.ph
HOSPITAL_REGISTRATION_CODE=PRC-HOSP-001

# Redis (if using Predis)
REDIS_HOST=localhost
REDIS_PASSWORD=null
REDIS_PORT=6379
```

---

## Render.yaml Configuration

Add this file to your repository root for one-click deployment:

```yaml
services:
  - type: web
    name: smart-blood
    env: docker
    dockerfilePath: ./Dockerfile
    
    envVars:
      - key: APP_NAME
        value: SmartBlood
      - key: APP_ENV
        value: production
      - key: APP_DEBUG
        value: false
      - key: APP_KEY
        sync: false  # Set manually in Render dashboard
      - key: APP_URL
        fromService:
          name: smart-blood
          property: url
      - key: DB_CONNECTION
        value: sqlite
      - key: SESSION_DRIVER
        value: file
      - key: CACHE_DRIVER
        value: file
      - key: QUEUE_CONNECTION
        value: sync
    
    disk:
      name: smart-blood-data
      mountPath: /app/storage
      sizeGB: 2
```

---

## Deployment Steps for Render

### Step 1: Prepare Your Repository
```bash
# Ensure these files are in your repository root:
- Dockerfile
- .dockerignore
- composer.json
- composer.lock
- .env.example (for reference)
```

### Step 2: Push to GitHub
```bash
git add Dockerfile .dockerignore
git commit -m "Add Docker configuration for Render deployment"
git push origin main
```

### Step 3: Create Render Service
1. Go to https://render.com
2. Click "New +" > "Web Service"
3. Connect your GitHub repository
4. Configure:
   - **Name:** smart-blood
   - **Environment:** Docker
   - **Instance Type:** Starter (free tier) or better
   - **Auto-deploy:** Check this box

### Step 4: Set Environment Variables
1. In Render dashboard, go to Environment tab
2. Add all variables from the "Required Environment Variables" section above
3. **IMPORTANT:** Generate APP_KEY before deploying:
   ```bash
   # On your local machine
   php artisan key:generate
   # Copy the output (base64:xxxxx) and paste into APP_KEY in Render
   ```

### Step 5: Add Persistent Storage (Optional but Recommended)
1. Click on "Disks" in Render dashboard
2. Create a disk:
   - **Name:** smart-blood-data
   - **Size:** 2GB
   - **Mount Path:** /app/storage
3. This prevents data loss on redeploys

### Step 6: Deploy
1. Click "Deploy" button
2. Monitor the deploy logs
3. Once successful, click the service URL

---

## Generated APP_KEY

Generate your APP_KEY locally BEFORE deploying:

```bash
cd smart-blood
php artisan key:generate
# You'll see: Application key [base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=] set successfully.
```

Copy the `base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=` part and set it as `APP_KEY` in Render.

**NEVER** leave APP_KEY empty in production. This will cause security issues.

---

## Database Options

### Option 1: SQLite (Simplest - Recommended for Thesis)
- **Pros:** No external database needed, works out of the box
- **Cons:** Not ideal for multiple concurrent users
- **Configuration:**
  ```bash
  DB_CONNECTION=sqlite
  DB_DATABASE=/app/storage/database.sqlite
  ```
- Render will persist `/app/storage` on the disk

### Option 2: MySQL (Best for Production)
- **Pros:** Handles multiple users, more reliable
- **Cons:** Requires paid Render plan or external database
- **Configuration:**
  ```bash
  DB_CONNECTION=mysql
  DB_HOST=your-db-host
  DB_PORT=3306
  DB_DATABASE=your_db_name
  DB_USERNAME=your_user
  DB_PASSWORD=your_password
  ```
- You can use free MySQL on services like AWS RDS free tier or Render's database service (paid)

### Option 3: PostgreSQL
- **Configuration:**
  ```bash
  DB_CONNECTION=pgsql
  DB_HOST=your-db-host
  DB_PORT=5432
  DB_DATABASE=your_db_name
  DB_USERNAME=your_user
  DB_PASSWORD=your_password
  ```

---

## Running Database Migrations on First Deploy

The Dockerfile includes Laravel's cached configuration. To run migrations on first deploy:

### Option A: Via Render Shell (Recommended)
1. After deployment, click "Shell" in Render dashboard
2. Run:
   ```bash
   php artisan migrate --force
   php artisan db:seed --force  # If you have seeders
   ```

### Option B: Add to Docker Startup Script
Modify the Dockerfile's CMD to run migrations:
```dockerfile
# Replace the last CMD line with:
RUN php artisan migrate --force || true && \
    php artisan db:seed --force || true
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=10000"]
```
This runs migrations automatically on container start.

---

## Common Deployment Issues & Fixes

### Issue 1: "Application key missing"
**Cause:** APP_KEY environment variable not set
**Fix:** 
```bash
# Generate locally
php artisan key:generate
# Copy base64:xxxxx to Render's APP_KEY variable
```

### Issue 2: "permission denied" errors on storage
**Cause:** File permissions in Docker container
**Status:** Already fixed in our Dockerfile (chmod 775 on storage)
**If still issues:** Run in Render shell:
```bash
chmod -R 777 /app/storage
chmod -R 777 /app/bootstrap/cache
```

### Issue 3: Migrations not running
**Cause:** Not explicitly triggered
**Fix:** Run in Render shell after first deploy:
```bash
php artisan migrate --force
```

### Issue 4: Static assets not loading
**Cause:** Vue/Vite build not run
**Current Status:** Not included in Docker (Laravel serves via artisan serve)
**If needed:** Build locally and commit to public/ folder:
```bash
npm install
npm run build
# Then commit and push
git add public/
git commit -m "Build frontend assets"
```

### Issue 5: Large image size or slow builds
**Current Status:** Using multi-stage Docker build (optimized)
**If still slow:**
- Check composer.lock is present
- Ensure .dockerignore is properly configured
- Render free tier has limitations; consider Starter plan

### Issue 6: "Cannot write to /app/storage"
**Cause:** User permission issue
**Status:** Already fixed in Dockerfile (non-root user with proper permissions)
**Fallback:** Run in Render shell:
```bash
chown -R laravel:laravel /app/storage
chmod -R 775 /app/storage
```

### Issue 7: Out of memory during composer install
**Cause:** Render free tier has limited RAM
**Fix:** In Render environment, add:
```bash
COMPOSER_MEMORY_LIMIT=2G
```

---

## Docker Commands for Local Testing

Before pushing to Render, test locally:

### Build Docker Image
```bash
cd smart-blood
docker build -t smart-blood:latest .
```

### Run Container Locally
```bash
docker run -p 10000:10000 \
  -e APP_KEY=base64:YOUR_KEY_HERE \
  -e APP_ENV=local \
  -e APP_DEBUG=true \
  -e DB_CONNECTION=sqlite \
  smart-blood:latest
```

### Run with Volume (for development)
```bash
docker run -p 10000:10000 \
  -v $(pwd):/app \
  -e APP_KEY=base64:YOUR_KEY_HERE \
  -e APP_ENV=local \
  smart-blood:latest
```

### Access Container Shell
```bash
docker ps  # Find container ID
docker exec -it CONTAINER_ID /bin/bash
```

---

## Verifying Deployment

After deployment on Render:

1. **Check Health Endpoint**
   ```bash
   curl https://your-app-name.onrender.com/health
   ```
   Expected: 200 OK status

2. **Check Logs**
   - In Render dashboard, view "Logs" tab
   - Look for Laravel bootstrapping messages

3. **Test Application**
   - Visit https://your-app-name.onrender.com
   - Should see your Laravel application

4. **Database Check** (via Render Shell)
   ```bash
   php artisan tinker
   > DB::connection()->getPdo();  // Should not throw error
   ```

---

## Production Checklist

- [ ] APP_KEY generated and set in Render
- [ ] APP_DEBUG set to false
- [ ] APP_ENV set to production
- [ ] Database migrations run
- [ ] Persistent storage disk created (recommended)
- [ ] All required environment variables set
- [ ] Application loads without errors
- [ ] Database connections work
- [ ] Logs are accessible in Render dashboard

---

## Updating Your Application

After deployment, to update your app:

```bash
# Make changes locally
git add .
git commit -m "Your changes"
git push origin main

# Render automatically redeploys on push (if auto-deploy enabled)
# Monitor the deploy in Render dashboard

# If database changes needed:
# 1. Wait for deployment to complete
# 2. Click "Shell" in Render
# 3. Run: php artisan migrate --force
```

---

## Rollback Procedures

If something goes wrong:

**Option 1: Revert Docker Build**
```bash
# In Render dashboard, click the previous successful deployment
# Click "Revert" button
```

**Option 2: Manual Rollback**
```bash
git revert HEAD
git push origin main
# Render will rebuild the previous version
```

---

## Performance Tips for Thesis Project

1. **Keep it simple:** Current setup is optimized for single user/small scale
2. **Use SQLite:** Good enough for thesis, no external DB required
3. **Enable caching:** Already done in Dockerfile (config:cache, route:cache)
4. **Monitor logs:** Check Render logs regularly for errors
5. **Optimize queries:** Use Laravel's query debugging in development

---

## Support & Debugging

If issues persist:

1. **Check Render Logs:**
   ```bash
   # In Render dashboard → Logs tab
   # Look for PHP errors, Laravel stack traces
   ```

2. **Access Container Shell:**
   ```bash
   # In Render dashboard → Shell tab
   php artisan tinker
   # Can run artisan commands and test
   ```

3. **Common Commands in Shell:**
   ```bash
   php artisan config:list           # Check configuration
   php artisan route:list            # Check routes
   php artisan migrate:status        # Check migration status
   php artisan cache:clear           # Clear cache
   php artisan optimize:clear        # Clear optimizations
   ```

---

## Summary

✅ **Dockerfile:** Multi-stage build, PHP 8.2, all extensions, optimized  
✅ **.dockerignore:** Excludes unnecessary files  
✅ **Environment Setup:** Ready for Render  
✅ **Database:** Supports SQLite (default) or MySQL  
✅ **Permissions:** Already configured  
✅ **Port:** Binds to 0.0.0.0:10000 as required  
✅ **Production Ready:** Caching, minified configs, non-root user

**You're ready to deploy!** 🚀
