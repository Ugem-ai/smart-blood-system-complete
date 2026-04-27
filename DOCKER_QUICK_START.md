# Docker Deployment - Quick Start

## Files Created
- ✅ `Dockerfile` - Complete Docker configuration
- ✅ `.dockerignore` - Exclude unnecessary files
- ✅ `DOCKER_DEPLOYMENT.md` - Full deployment guide
- ✅ `render.yaml` - Render service configuration
- ✅ This file - Quick reference

---

## 60-Second Deploy to Render

### Step 1: Generate App Key
```bash
php artisan key:generate
# Copy the base64:xxxxx part
```

### Step 2: Push to GitHub
```bash
git add Dockerfile .dockerignore render.yaml DOCKER_DEPLOYMENT.md
git commit -m "Add Docker configuration"
git push origin main
```

### Step 3: Deploy on Render
1. Go to https://render.com
2. Click "New +" > "Web Service"
3. Connect GitHub repo
4. Select "Docker"
5. In environment variables, add:
   - `APP_KEY=base64:xxxxx` (from Step 1)
   - `APP_ENV=production`
   - `APP_DEBUG=false`
   - `DB_CONNECTION=sqlite`
6. Click "Deploy"

### Step 4: Run Migrations (via Render Shell)
1. Wait for deployment to complete
2. Click "Shell" tab in Render
3. Run: `php artisan migrate --force`

Done! 🎉

---

## Environment Variables Required

### Minimum (just to run)
```
APP_KEY=base64:xxxxx
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=sqlite
```

### Full Set (recommended)
```
APP_NAME=SmartBlood
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:xxxxx
APP_URL=https://your-app.onrender.com
DB_CONNECTION=sqlite
DB_DATABASE=/app/storage/database.sqlite
SESSION_DRIVER=file
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
SANCTUM_TOKEN_EXPIRATION=120
LOG_CHANNEL=stack
LOG_LEVEL=info
```

See `DOCKER_DEPLOYMENT.md` for all options.

---

## Database Choice

**For Thesis/Prototype:**
- Use **SQLite** (default) ✅
- No external DB needed
- Data persists on Render disk
- Perfect for single user

**For Production:**
- Use **MySQL** or **PostgreSQL**
- Set DB_HOST, DB_USER, DB_PASSWORD
- Use Render or external DB service

---

## Troubleshooting

### Issue: "Application key missing"
```bash
# Generate locally
php artisan key:generate
# Set APP_KEY in Render to the output (base64:xxxxx)
```

### Issue: Migrations didn't run
```bash
# In Render Shell tab:
php artisan migrate --force
```

### Issue: Can't write to storage
```bash
# In Render Shell tab:
chmod -R 777 /app/storage
chmod -R 777 /app/bootstrap/cache
```

### Issue: Static assets not loading
Build Vue/Vite assets locally first:
```bash
npm install
npm run build
git add public/
git commit -m "Add built assets"
git push origin main
```

### More issues?
See `DOCKER_DEPLOYMENT.md` → "Common Deployment Issues & Fixes"

---

## Testing Locally First

Before pushing to Render:

```bash
# Build Docker image
docker build -t smart-blood:latest .

# Run container
docker run -p 10000:10000 \
  -e APP_KEY=base64:YOUR_KEY_HERE \
  -e APP_ENV=local \
  -e APP_DEBUG=true \
  -e DB_CONNECTION=sqlite \
  smart-blood:latest

# Should see:
# Laravel development server started on [http://0.0.0.0:10000]
# Open http://localhost:10000
```

---

## Key Features of Our Docker Setup

✅ **PHP 8.2** - Latest stable version  
✅ **All Required Extensions** - pdo, mysql, sqlite, mbstring, zip, gd, xml, bcmath  
✅ **Composer** - Installed and dependencies cached  
✅ **Laravel Optimization** - Config/route/view cached  
✅ **Multi-stage Build** - Smaller final image (production)  
✅ **Proper Permissions** - Non-root user, correct chmod  
✅ **Port 10000** - Binds to 0.0.0.0 for Render  
✅ **Health Check** - Included for monitoring  
✅ **Optimized** - 150-200MB final image size  

---

## File Descriptions

### Dockerfile
- Multi-stage build (builder → runtime)
- PHP 8.2 with all Laravel extensions
- Composer install with optimizations
- Laravel config/route/view caching
- Proper permissions and non-root user
- Health check endpoint
- Runs on 0.0.0.0:10000

### .dockerignore
- Excludes node_modules, vendor, git
- Excludes development files
- Excludes documentation
- Reduces image size

### render.yaml
- Web service configuration
- Environment variables
- Storage disk setup
- Ready for Render deployment

### DOCKER_DEPLOYMENT.md
- Complete deployment guide
- All environment variables explained
- Render setup instructions
- Database options
- Common issues & fixes
- Performance tips

---

## Next Steps

1. ✅ Files are created in your project root
2. ⏭️ Generate APP_KEY locally
3. ⏭️ Push to GitHub
4. ⏭️ Deploy to Render
5. ⏭️ Run migrations in Render Shell
6. ⏭️ Visit your deployed app!

---

## Support

Full documentation: See `DOCKER_DEPLOYMENT.md`

Render docs: https://render.com/docs/docker

Laravel docs: https://laravel.com/docs

---

**Questions?** Check the detailed guide in `DOCKER_DEPLOYMENT.md` 📖
