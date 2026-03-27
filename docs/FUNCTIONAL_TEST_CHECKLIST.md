# Functional Test Case Checklist & Execution Guide

**Smart Blood System - Comprehensive Test Suite**

---

## Test Execution Instructions

### Prerequisites
- Test environment configured with sample data (see database/seeders/TestDataSeeder.php)
- All three dashboards deployed and accessible
- Bearer tokens generated for test users
- Network connectivity stable

### Sample Test User Credentials

```
ADMIN USER:
  Email: admin@prc.org
  Password: AdminTest@123
  Role: admin

HOSPITAL STAFF:
  Email: hospital.staff@test.com
  Password: HospitalTest@123
  Role: hospital
  Hospital: Philippine Red Cross Main

DONOR USER 1 (Eligible):
  Email: donor1@test.com
  Password: DonorTest@123
  Blood Type: O+
  Last Donation: 75 days ago (ELIGIBLE)

DONOR USER 2 (Ineligible):
  Email: donor2@test.com
  Password: DonorTest@123
  Blood Type: A-
  Last Donation: 30 days ago (NOT ELIGIBLE - needs 56 days)
```

---

## 1. AUTHENTICATION TEST SUITE

### Test Group: AUTH-001 to AUTH-010

#### AUTH-001: Valid Login - Donor

**Pre-requisite:** Test user "donor1@test.com" exists with password "DonorTest@123"

**Steps:**
1. Navigate to `/login`
2. Enter email: `donor1@test.com`
3. Enter password: `DonorTest@123`
4. Click "Sign In"

**Expected:** 
- ✅ Login succeeds within 2 seconds
- ✅ Redirected to `/donor/dashboard`
- ✅ Bearer token stored in localStorage
- ✅ Dashboard loads with donor's data (name, blood type, profile)

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### AUTH-002: Invalid Email Format

**Pre-requisite:** Login page accessible

**Steps:**
1. Navigate to `/login`
2. Enter email: `invalidemail` (no @ symbol)
3. Enter password: `anypass`
4. Click "Sign In"

**Expected:**
- ✅ Form shows error: "Invalid email format"
- ✅ Login request not sent to server
- ✅ Validation error highlighted on email field

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### AUTH-003: Wrong Password

**Pre-requisite:** User "donor1@test.com" exists

**Steps:**
1. Navigate to `/login`
2. Enter email: `donor1@test.com`
3. Enter password: `WrongPassword@123` (incorrect)
4. Click "Sign In"

**Expected:**
- ✅ Error message: "Invalid credentials" (generic, no email enumeration)
- ✅ Login fails after 1-2 seconds
- ✅ User remains on login page
- ✅ No data leaked indicating email exists

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### AUTH-004: Non-Existent User

**Pre-requisite:** User "nonexistentuser@test.com" does NOT exist

**Steps:**
1. Navigate to `/login`
2. Enter email: `nonexistentuser@test.com`
3. Enter password: `AnyPassword@123`
4. Click "Sign In"

**Expected:**
- ✅ Error message: "Invalid credentials" (same as wrong password for security)
- ✅ No indication whether email exists
- ✅ Login request processes fully

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### AUTH-005: Admin Login

**Pre-requisite:** Admin user "admin@prc.org" exists

**Steps:**
1. Navigate to `/login`
2. Enter email: `admin@prc.org`
3. Enter password: `AdminTest@123`
4. Click "Sign In"

**Expected:**
- ✅ Login succeeds
- ✅ Redirected to `/admin/dashboard`
- ✅ Admin token issued with admin role
- ✅ System metrics displayed (not donor data)

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### AUTH-006: Hospital Staff Login

**Pre-requisite:** Hospital user exists and is approved

**Steps:**
1. Navigate to `/login`
2. Enter email: `hospital.staff@test.com`
3. Enter password: `HospitalTest@123`
4. Click "Sign In"

**Expected:**
- ✅ Login succeeds
- ✅ Redirected to `/hospital/dashboard`
- ✅ Hospital-scoped permissions granted
- ✅ Hospital name displayed in sidebar

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### AUTH-007: Role-Based Redirection - Donor Accessing Admin

**Pre-requisite:** Donor logged in as donor1@test.com

**Steps:**
1. After successful donor login, directly type: `/admin/dashboard` in URL
2. Press Enter

**Expected:**
- ✅ Browser redirects to `/donor/dashboard`
- ✅ Admin dashboard never loads
- ✅ Error message optional but recommended: "Access Denied"

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### AUTH-008: Role-Based Redirection - Hospital Accessing Admin

**Pre-requisite:** Hospital staff logged in

**Steps:**
1. After hospital login, type: `/admin/dashboard` in URL
2. Press Enter

**Expected:**
- ✅ Redirected to `/hospital/dashboard`
- ✅ Authorization middleware prevents access
- ✅ 403 Forbidden error returned by API

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### AUTH-009: Token Expiration Handling

**Pre-requisite:** Token with very short TTL (e.g., 1 minute for testing)

**Steps:**
1. Login successfully
2. Wait for token to expire (or modify localStorage token to expired payload)
3. Make any API request (e.g., refresh dashboard)

**Expected:**
- ✅ API returns 401 Unauthorized
- ✅ App auto-redirects to `/login`
- ✅ Previous session cleared
- ✅ User prompted to re-authenticate

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### AUTH-010: Logout Functionality

**Pre-requisite:** User logged into any dashboard

**Steps:**
1. Click logout button (typically bottom of sidebar)
2. Confirm logout if prompted

**Expected:**
- ✅ Session cleared immediately (< 1 second)
- ✅ Redirected to `/login`
- ✅ Bearer token removed from localStorage
- ✅ Dashboard inaccessible if URL re-entered

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

## 2. ADMIN DASHBOARD TEST SUITE

### Test Group: ADMIN-001 to ADMIN-010

#### ADMIN-001: View System Metrics

**Pre-requisite:** Admin logged in, dashboard loads

**Steps:**
1. On `/admin/dashboard`, wait for page to load
2. Observe metrics on Dashboard module

**Expected:**
- ✅ Displays: Total Users, Active Requests, Matched Donors, System Uptime
- ✅ Numbers update every 10 seconds
- ✅ All KPI cards visible and readable
- ✅ No error messages

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### ADMIN-002: Filter Blood Requests by Status

**Pre-requisite:** Admin on Requests module with data

**Steps:**
1. Click Requests module
2. Click "Status" dropdown
3. Select "pending"
4. Wait for table to refresh

**Expected:**
- ✅ Table shows only pending requests
- ✅ Other statuses (matched, confirmed) disappear
- ✅ Request count updates
- ✅ Filter applied instantly (< 500ms)

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### ADMIN-003: View Donor Analytics

**Pre-requisite:** Admin on Analytics module

**Steps:**
1. Click Analytics module
2. Observe analytics charts

**Expected:**
- ✅ Charts load successfully
- ✅ Shows: Donation frequency, response rates, geographic distribution
- ✅ Data properly formatted (no console errors)
- ✅ Charts responsive to screen resize

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### ADMIN-004: Approve New Hospital User

**Pre-requisite:** Pending hospital registration exists

**Steps:**
1. Click Users/Hospitals module
2. Find pending user
3. Click checkbox next to user
4. Click "Approve" button
5. Confirm if prompted

**Expected:**
- ✅ User moves to "Approved" section
- ✅ Status field changes to "approved"
- ✅ Approval email sent to user
- ✅ User can now login

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### ADMIN-005: Reject Hospital User Registration

**Pre-requisite:** Pending hospital registration exists

**Steps:**
1. Click Users/Hospitals module
2. Find pending user
3. Click checkbox
4. Click "Reject" button
5. Enter rejection reason (optional)
6. Confirm

**Expected:**
- ✅ User removed from pending list
- ✅ User account remains but marked as "rejected"
- ✅ Rejection email sent
- ✅ User cannot login

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### ADMIN-006 through ADMIN-010

[Similar detailed test steps for remaining admin tests...]

---

## 3. HOSPITAL DASHBOARD TEST SUITE

### Test Group: HOSP-001 to HOSP-010

#### HOSP-001: Create Blood Request

**Pre-requisite:** Hospital staff logged in, at Create Request module

**Steps:**
1. Fill form:
   - Blood Type: B+ (select from dropdown)
   - Units Needed: 5
   - Urgency: Critical
   - Location: Hospital Address
   - Required Date: Today
   - Distance Radius: 15 km
   - Notes: "Emergency trauma case" (optional)
2. Click "Submit Request"

**Expected:**
- ✅ Form validates all required fields
- ✅ Submit button disabled during submission
- ✅ Request created successfully (< 2 seconds including PAST-Match)
- ✅ Success message: "Request created successfully"
- ✅ Matched donors list appears immediately with 10 results

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### HOSP-002: View Matched Donors

**Pre-requisite:** Blood request created with at least 10 eligible donors

**Steps:**
1. After request creation, view "Matched Donors" module
2. Observe donor list

**Expected:**
- ✅ Displays donors ranked by PAST-Match score (highest first)
- ✅ Shows: Rank #1-10, Name, Compatibility %, Distance, Availability
- ✅ Top 3 highlighted (yellow background)
- ✅ Scores between 50-100%

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### HOSP-003: Notify Top Donor

**Pre-requisite:** Matched donors visible, at least 1 donor available

**Steps:**
1. On top donor (#1 ranked), click "Notify Donor" button
2. Confirm if prompted

**Expected:**
- ✅ Button shows loading state during submission
- ✅ Notification sent (SMS + in-app) to donor
- ✅ Success message: "Donor notified"
- ✅ Donor automatically receives notification in their dashboard

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### HOSP-004: Track Donor Responses

**Pre-requisite:** Donations sent to at least 5 donors

**Steps:**
1. Click "Response Tracker" module
2. Select request from dropdown
3. Observe responses

**Expected:**
- ✅ Displays all donors notified for selected request
- ✅ Color coded: Green = Accepted, Red = Declined, Yellow = Pending
- ✅ Updates every 5 seconds automatically
- ✅ Shows response count: "3 Accepted, 2 Declined, 3 Awaiting"

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### HOSP-005 through HOSP-010

[Similar detailed test steps for remaining hospital tests...]

---

## 4. DONOR DASHBOARD TEST SUITE

### Test Group: DONOR-001 to DONOR-010

#### DONOR-001: Receive Blood Request Notification

**Pre-requisite:** 
- Donor logged in (donor1@test.com)
- Hospital makes a blood request matching donor's blood type
- Donor ranks in top 10 for PAST-Match

**Steps:**
1. Have hospital staff create blood request (O+ type)
2. Observe donor's screen

**Expected:**
- ✅ In-app notification appears in notification panel (within 2 seconds)
- ✅ SMS sent to donor's phone (if SMS enabled)
- ✅ Notification badge shows unread count
- ✅ Notification shows: Hospital name, blood type, urgency, units

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### DONOR-002: View Incoming Request Details

**Pre-requisite:** Notification received

**Steps:**
1. Click on notification
2. Observe expanded details

**Expected:**
- ✅ Shows: Hospital name, blood type, units, urgency, location
- ✅ Distance calculation shown
- ✅ Display clearly emphasizes if CRITICAL
- ✅ Accept/Decline buttons prominent

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### DONOR-003: Accept Request

**Pre-requisite:** Notification displayed with Accept button visible

**Steps:**
1. Click "Accept" button
2. Confirm if prompted

**Expected:**
- ✅ Button shows loading state during submission
- ✅ Status changes to "Accepted" (visual feedback)
- ✅ Hospital receives response immediately (< 1 second)
- ✅ Next request fires in system (if queued)
- ✅ Notification removed from pending list

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### DONOR-004: Decline Request

**Pre-requisite:** New notification with Decline button

**Steps:**
1. Click "Decline" button
2. Optionally enter reason
3. Confirm

**Expected:**
- ✅ Request status changes to "Declined"
- ✅ Hospital notified of decline (immediately)
- ✅ Notification removed
- ✅ Donor not penalized; can accept future requests

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### DONOR-005: Update Availability

**Pre-requisite:** On Availability module

**Steps:**
1. Click Availability toggle switch
2. Change from Available → Unavailable (or vice versa)

**Expected:**
- ✅ Toggle animates smoothly
- ✅ Status updates immediately in UI
- ✅ Update sent to server (< 500ms)
- ✅ Affects future PAST-Match calculations
- ✅ Current pending notifications NOT affected

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### DONOR-006: View Donation History

**Pre-requisite:** On History module with past donations

**Steps:**
1. Click "History" module
2. Scroll through donation records

**Expected:**
- ✅ Shows timeline sorted by date (newest first)
- ✅ Displays: Hospital name, date, blood type, units
- ✅ Shows status (completed, pending, cancelled)
- ✅ Total units donated calculated
- ✅ "Lives saved estimate" shown (units / 2)

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### DONOR-007: View Profile

**Pre-requisite:** On Profile module

**Steps:**
1. Click "Profile" module
2. Observe all sections

**Expected:**
- ✅ Personal info: Name, email, phone
- ✅ Blood type prominently displayed
- ✅ Medical history: Genotype, weight, last checkup
- ✅ Location: City, state, coordinates
- ✅ Reliability score with percentage

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### DONOR-008: Check Eligibility

**Pre-requisite:** On Dashboard Overview

**Steps:**
1. Look at eligibility status message
2. (If not eligible) wait until next eligible date

**Expected:**
- ✅ If eligible: "✅ You're eligible to donate"
- ✅ If not: "⏸️ Can donate in X days" with countdown
- ✅ Color changes: Green (eligible), Yellow (soon), Red (recently donated)

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### DONOR-009: View Statistics

**Pre-requisite:** Dashboard Overview loaded

**Steps:**
1. Observe stat cards on Dashboard

**Expected:**
- ✅ Total Donations: [number]
- ✅ Donations This Year: [number]
- ✅ Lives Saved: [estimate]
- ✅ Response Rate: [%]
- ✅ All numbers calculated correctly

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### DONOR-010: Accept Request After Decline

**Pre-requisite:** Donor declined one request, receives another

**Steps:**
1. Receive new notification for different request
2. Click Accept

**Expected:**
- ✅ New request accepted successfully
- ✅ Previous decline not blocking new response
- ✅ Each request evaluated independently

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

## 5. API INTEGRATION TEST SUITE

### Test Group: API-001 to API-010

#### API-001: Missing Authentication Token

**Tool:** Postman / cURL

**Steps:**
```bash
curl -X GET http://localhost:8000/api/hospital/requests \
  -H "Content-Type: application/json"
```
(Note: No Authorization header)

**Expected:**
- ✅ Response Status: 401 Unauthorized
- ✅ Body: `{"message":"Unauthenticated"}`
- ✅ No data returned

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### API-002: Invalid Token

**Tool:** Postman / cURL

**Steps:**
```bash
curl -X GET http://localhost:8000/api/hospital/requests \
  -H "Authorization: Bearer invalid_token_here" \
  -H "Content-Type: application/json"
```

**Expected:**
- ✅ Status: 401 Unauthorized
- ✅ Message: "Invalid token" or "Unauthenticated"

**Actual Result:** [FILL AFTER EXECUTION]

**Pass/Fail:** [ ]

---

#### API-003 through API-010

[Similar API test cases with curl examples...]

---

## Test Execution Summary

**Total Tests:** 50
**Tests Passed:** ____ / 50
**Tests Failed:** ____ / 50
**Success Rate:** _____% 

**Failed Tests Details:**
[List any failures with brief description]

**Tester Name:** ________________
**Date:** _____________________
**Environment:** Production / Staging / Development
**Notes:** ________________

---

**Document Version:** 1.0
**Last Updated:** March 26, 2026
