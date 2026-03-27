# Vue 3 Frontend - Professional Medical Design

## Architecture Overview

The SmartBlood system now features a **Vue 3 Single Page Application (SPA)** with **Vue Router** for client-side routing. This replaces the Blade template-based pages with a modern, responsive frontend using **Tailwind CSS** for a professional medical theme.

## Technology Stack

- **Vue 3** - Progressive JavaScript framework
- **Vue Router 4** - Client-side routing and navigation
- **Tailwind CSS** - Utility-first CSS framework for design
- **Vite** - Fast bundler and dev server
- **Axios** - HTTP client for API calls (via window.axios)
- **Laravel Sanctum** - Token-based authentication

## Project Structure

```
resources/
├── js/
│   ├── app.js                    # Main Vue app entry point
│   ├── bootstrap.js              # Bootstrap Axios/CSRF
│   ├── App.vue                   # Root component
│   ├── router/
│   │   └── index.js              # Vue Router configuration
│   ├── pages/
│   │   ├── Login.vue             # Professional login page
│   │   ├── AdminDashboard.vue    # Admin with Disaster Response Mode
│   │   ├── HospitalDashboard.vue # Hospital coordinator UI
│   │   ├── DonorDashboard.vue    # Donor management interface
│   │   ├── BloodRequests.vue     # Request management
│   │   ├── DonationHistory.vue   # Historical records
│   │   └── Settings.vue          # User preferences
│   └── components/
│       ├── StatCard.vue          # Reusable stat card
│       └── ActivityItem.vue      # Activity list item
├── css/
│   └── app.css                   # Global styles
└── views/
    ├── app.blade.php             # Main Vue entry point template
    └── [other blade templates]   # Legacy pages (gradual migration)
```

## Routes (Client-Side)

```
/login                              # Public login page
/admin/dashboard                   # Admin dashboard (Feature 15: Disaster mode control)
/hospital/dashboard                # Hospital staff interface
/donor/dashboard                   # Donor portal
/blood-requests                    # Managed blood requests
/donation-history                  # Past donations record
/settings                          # User settings
```

## Authentication Flow

1. **Login Page** (`/login`)
   - Email and password input with demo credentials display
   - Professional gradient background (slate-900 → red-800)
   - Error handling and loading states
   - Post to `/api/login`

2. **Token Storage**
   - `auth_token` - Sanctum API token stored in localStorage
   - `user_role` - User role (admin, hospital, donor)
   - `user_id` - Current user ID
   - `user_name` - Display name

3. **Protected Routes**
   - Navigation guard in Vue Router validates auth before access
   - Redirects unauthenticated users to `/login`
   - Role-based access control per route

4. **Logout**
   - Clear localStorage
   - Redirect to `/login`
   - Optional API call to `/api/logout`

## API Integration

### Login Endpoint
```
POST /api/login
{
  "email": "test@example.com",
  "password": "password"
}
Response:
{
  "token": "...",
  "user": {
    "id": 1,
    "name": "Test User",
    "email": "test@example.com",
    "role": "admin"
  }
}
```

### Admin Disaster Mode
```
POST /api/admin/disaster-mode
Headers: Authorization: Bearer {token}
{
  "trigger": "earthquake|major accident|large-scale emergency",
  "force_priority": true,
  "expanded_radius": 200,
  "mass_notification": true
}
```

## Professional Medical Design

### Color Palette
- **Primary Red**: `#dc2626` - Blood/urgency indicators
- **Slate Gray**: `#334155` - Professional, clinical appearance
- **Success Green**: `#22c55e` - Positive actions
- **Warning Amber**: `#f59e0b` - Caution states
- **White/Light**: Clean, easy-to-read interface

### Typography
- **Font**: Inter (loaded from Google Fonts)
- **Headings**: Bold, slate-900
- **Body**: Regular, slate-700/600
- **Small text**: slate-600/500

### Components

#### StatCard
Shows key metrics with emoji icons and trend indicators
- Used on dashboards for blood requests, active donors, hospitals, system health
- 4-column grid on desktop, responsive on mobile

#### ActivityItem
Displays timeline events with type-based coloring
- Request, Donation, Alert types
- Shows time relative to now

#### Header Navigation
- SmartBlood logo with diamond icon
- User role display
- Logout button

## Key Features

### Login Page
- Gradient background with medical aesthetics
- Email/password form with validation
- Demo credentials clearly displayed
- "24/7 Always Available", "Live Real-time Matching", "Smart AI Predictions" features

### Admin Dashboard
- **Disaster Response Mode Panel** (Feature 15)
  - Display active/inactive status
  - Shows trigger type (earthquake, major accident, large-scale emergency)
  - Visualizes enabled policies:
    - Force High Priority Requests
    - Expanded Radius (200km)
    - Mass Notification Enabled
  - Dropdown to activate/deactivate disaster mode
  - Color-coded: amber for disaster state

- **Quick Actions**
  - View Blood Inventory
  - Active Requests
  - Hospital Network
  - System Logs

- **Recent Activity**
  - Blood request notifications
  - Donation completions
  - System alerts

### Hospital Dashboard
- Blood request statistics (active, available donors, success rate)
- Create blood request form
- Request status tracking

### Donor Dashboard
- Personal donation statistics
- Lives saved counter
- Donor score/rating
- Availability management

## Tailwind CSS Configuration

### Updated Configuration
- Added Vue file paths to content scanner
- Extended theme with medical color palette
- International font support
- Form styling via @tailwindcss/forms plugin

### Responsive Design
- Mobile-first approach
- Breakpoints: sm, md, lg, xl, 2xl
- Touch-friendly button sizes (min 44x44px)
- Flexible grid layouts

## Development Workflow

### Start Development
```bash
npm run dev        # Vite dev server (hot reload)
php artisan serve  # Laravel backend (port 8000)
```

### Build for Production
```bash
npm run build      # Vite production build
php artisan optimize  # Laravel optimization
```

### Testing
```bash
npm run test       # Vue component tests
php artisan test   # Laravel test suite
```

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Vue 3 requires ES2015+ support
- No IE11 support

## Migration from Blade

### Legacy Routes Maintained
- `/login` - Now handled by Laravel auth routes (temporary)
- `/register` - Now handled by Laravel auth routes (temporary)
- Profile and settings pages can be migrated gradually

### Gradual Migration Path
1. ✅ Login/Dashboard routes now use Vue
2. 🔄 Can keep Blade templates in parallel
3. ❌ Remove old routes once Vue dashboard is stable
4. Update API endpoints as needed

## Next Steps

1. **Enhanced Dashboards**
   - Implement blood request form with validation
   - Add real-time donor matching visualization
   - Create inventory management interface

2. **Real-time Features**
   - WebSocket integration for live updates
   - Push notifications for new requests
   - Live disaster mode status sync

3. **Advanced Components**
   - Data tables with sorting/pagination
   - Charts and analytics (Chart.js integration)
   - Modal dialogs and notifications
   - File upload handling

4. **Performance**
   - Route code-splitting for faster initial load
   - Service worker for offline support
   - Image optimization

5. **Accessibility**
   - WCAG 2.1 AA compliance
   - Screen reader testing
   - Keyboard navigation

## Features Currently Implemented

✅ **Feature 14 - Smart Donor Availability Prediction**
- Backend: Work-hour decline detection ready
- Frontend: Integration point ready on Donor Dashboard

✅ **Feature 15 - Disaster Response Mode**
- Full UI implemented on Admin Dashboard
- Trigger selection dropdown
- Policy visualization cards
- Amber color scheme for disaster state
- API integration ready

## Demo Credentials

```
Email: test@example.com
Password: password
```

Auto-redirects based on user role (admin, hospital, donor)
