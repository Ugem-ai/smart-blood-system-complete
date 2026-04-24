# 🚀 Smart Blood System - Server Startup Guide

## QUICK START (30 seconds)

```bash
cd "c:\Users\Acer\Desktop\Smart Blood System\smart-blood"
composer run dev
```

Then open your browser to: **http://localhost:8000**

That's it! ✨

---

## 📁 Documentation Files (Read These)

Start with these files in order:

1. **START_HERE.md** ⭐ START HERE
   - Quick overview of current status
   - Links to all other resources
   - Quick troubleshooting

2. **CHECKLIST.md**
   - Pre-launch verification checklist
   - All checks are marked complete ✅
   - Verification steps after startup

3. **QUICK_START.md**
   - Detailed startup methods
   - Expected output
   - Troubleshooting guide

4. **SERVER_STARTUP_REPORT.md**
   - Comprehensive diagnostic report
   - Full configuration details
   - All available commands

5. **COMMANDS.txt**
   - Quick command reference
   - All relevant CLI commands
   - Copy-paste friendly

---

## 🎯 What's Ready

✅ **Backend (Laravel 12)**
- PHP environment ready
- All dependencies installed (vendor/)
- Database configured (SQLite)
- Queue system ready
- API authentication ready

✅ **Frontend (Vue 3 + Vite)**
- All npm packages installed (node_modules/)
- Vue 3 components ready
- Vite dev server configured
- Hot module replacement enabled
- Tailwind CSS and Ant Design Vue included

✅ **Database**
- SQLite database file exists
- Migrations ready to run
- Session and cache configured

✅ **Configuration**
- .env file configured
- Environment ready for development
- Debug mode enabled

---

## 🚀 Three Ways to Start

### Method 1: Single Command (RECOMMENDED)
```bash
cd "c:\Users\Acer\Desktop\Smart Blood System\smart-blood"
composer run dev
```
Starts both servers automatically!

### Method 2: Batch Script
```bash
startup.bat
```

### Method 3: Manual (Separate Terminals)

Terminal 1:
```bash
php artisan serve
```

Terminal 2:
```bash
npm run dev
```

---

## 📊 Created Helper Files

These files have been created to help you:

- ✅ **startup.bat** - Batch script with diagnostics
- ✅ **startup.ps1** - PowerShell startup script
- ✅ **run-diagnostic.bat** - Run diagnostic checks
- ✅ **start-server.bat** - Start Laravel only
- ✅ **start-vite.bat** - Start Vite only

---

## 🌐 Access Points

| Service | URL | Port |
|---------|-----|------|
| Smart Blood System | http://localhost:8000 | 8000 |
| Vite Dev Server | http://localhost:5173 | 5173 |

---

## ⚙️ Technical Details

### Backend Stack
- **Framework**: Laravel 12.0
- **Database**: SQLite (database/database.sqlite)
- **Authentication**: Laravel Sanctum
- **Queue**: Database-based
- **Sessions**: Database-based

### Frontend Stack
- **Framework**: Vue 3.5.30
- **Build Tool**: Vite 7.0.7
- **UI Library**: Ant Design Vue 4.2.6
- **Styling**: Tailwind CSS
- **Router**: Vue Router 4.6.4
- **Data Viz**: Chart.js 4.5.1

---

## 📝 Expected Output

When you run `composer run dev`, you should see:

```
[dev-runner] Starting: server, queue, vite

server   │ Laravel development server started on http://127.0.0.1:8000
queue    │ Waiting for jobs on default
vite     │ VITE v7.0.7 building for production...
vite     │ ✓ 2845 modules transformed
vite     │ Local: http://localhost:5173
vite     │ press h to show help
```

---

## ⚡ Quick Commands

```bash
# Start servers (MAIN COMMAND)
composer run dev

# Clear caches
php artisan config:clear && php artisan cache:clear && php artisan view:clear

# Run migrations
php artisan migrate --force

# Install dependencies
composer install && npm install

# Build frontend
npm run build

# Stop servers
Ctrl+C (in terminal)
```

---

## 🆘 Common Issues

| Issue | Solution |
|-------|----------|
| Port 8000 in use | `php artisan serve --port=8001` |
| Port 5173 in use | `npm run dev -- --port 5174` |
| Database errors | `php artisan migrate --force` |
| Cache errors | Clear caches (see QUICK_START.md) |
| Missing dependencies | `composer install && npm install` |

---

## 📚 Full Documentation

For comprehensive guides, see:
- `README.md` - Project overview
- `SYSTEM_DOCUMENTATION.md` - System architecture
- `DEPLOYMENT.md` - Production deployment
- `VUE_FRONTEND_SETUP.md` - Frontend development
- `IMPLEMENTED_FEATURES.md` - All features

---

## ✨ Current Status

```
✅ Project Location:  c:\Users\Acer\Desktop\Smart Blood System\smart-blood
✅ PHP Version:       ^8.2 (installed)
✅ Node.js:           Installed
✅ npm:               Installed
✅ Composer:          Installed
✅ Vendor:            Installed (vendor/)
✅ npm packages:      Installed (node_modules/)
✅ Database:          SQLite (ready)
✅ Configuration:     .env (ready)
✅ Dependencies:      All installed
✅ Ready Status:      READY TO LAUNCH 🚀
```

---

## 🎯 Let's Go!

1. Open Command Prompt or PowerShell
2. Navigate to: `c:\Users\Acer\Desktop\Smart Blood System\smart-blood`
3. Run: `composer run dev`
4. Wait for startup messages
5. Open browser to: http://localhost:8000
6. Log in and start using Smart Blood System!

---

## 📖 File Navigation

- **Want quick overview?** → Read START_HERE.md
- **Want to verify everything?** → Check CHECKLIST.md
- **Need detailed guide?** → Read QUICK_START.md
- **Need all commands?** → See COMMANDS.txt
- **Want full report?** → Read SERVER_STARTUP_REPORT.md

---

## 🎉 Ready?

Everything is configured and ready. Just run:

```bash
composer run dev
```

Then enjoy Smart Blood System at http://localhost:8000 ✨

---

*Created: 2024*
*Project: Smart Blood System (Laravel + Vue 3)*
*Status: ✅ READY FOR DEVELOPMENT*
