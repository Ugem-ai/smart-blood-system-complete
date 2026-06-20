# Smart Blood System - Deployed Testing Checklist
**Feature-by-Feature Testing Guide**

---

## **PART 1: LOGIN & ACCESS (Test First)**

### **Test 1.1: Admin Login**
- [✅] Go to the system URL
- [✅] Enter: `admin@prc.org`
- [✅] Password: `AdminTest@123`
- [✅] **Expected:** Redirects to Admin Dashboard immediately
- [✅] **Check:** Red "Admin Dashboard" title visible
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 1.2: Hospital Staff Login**
- [✅] Go back to login page
- [✅] Enter: `hospital.staff@test.com`
- [✅] Password: `HospitalTest@123`
- [✅] **Expected:** Redirects to Hospital Dashboard
- [✅] **Check:** Hospital name visible in sidebar
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 1.3: Donor Login**
- [✅] Go back to login page
- [✅] Enter: `donor1@test.com`
- [✅] Password: `DonorTest@123`
- [✅] **Expected:** Redirects to Donor Dashboard
- [✅] **Check:** Your name and blood type (O+) visible
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 1.4: Wrong Password**
- [✅] Try to login with wrong password
- [✅] **Expected:** Red error message "Invalid credentials"
- [❌] **Check:** Does NOT say "email not found" (security)
- **Status:** ✅ Pass / ❌ Fail

---

## **PART 2: ADMIN DASHBOARD FEATURES**

### **Test 2.1: System Metrics Display**
- [ ] Login as Admin
- [ ] Look at top of dashboard for 4 big numbers
- [ ] **Expected:** See:
  - Total Users (number)
  - Active Requests (number)
  - Matched Donors (number)
  - System Uptime (percentage)
- [ ] **Check:** Numbers are visible and make sense
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 2.2: View All Blood Requests**
- [ ] Click "Requests" in left menu
- [ ] **Expected:** See table with columns:
  - Hospital name
  - Blood type needed
  - Units
  - Status (pending/matched/confirmed)
  - Date created
- [ ] **Check:** At least 1 request shows (or message says "No requests")
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 2.3: Filter Requests by Status**
- [ ] On Requests page, find Status dropdown
- [ ] Click and select "pending"
- [ ] **Expected:** Only shows pending requests
- [ ] Click and select "matched"
- [ ] **Expected:** Only shows matched requests
- [ ] **Check:** List changes when you select different status
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 2.4: View All Donors**
- [ ] Click "Donors" in left menu
- [ ] **Expected:** See table with columns:
  - Name
  - Blood type
  - Status (Eligible/Ineligible)
  - Last donation date
  - Reliability score (%)
- [ ] **Check:** At least 1 donor visible
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 2.5: View System Analytics**
- [ ] Click "Analytics" in left menu
- [ ] **Expected:** See charts showing:
  - Donation frequency over time
  - Response rates
  - Geographic distribution of donors
- [ ] **Check:** Charts load and display data
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 2.6: View Activity Logs**
- [ ] Click "Activity" or "Logs" in left menu
- [ ] **Expected:** See list of recent system activities:
  - User logins
  - Requests created
  - Responses received
- [ ] **Check:** Timestamps and descriptions visible
- **Status:** ✅ Pass / ❌ Fail

---

## **PART 3: HOSPITAL DASHBOARD FEATURES**

### **Test 3.1: Create Blood Request**
- [ ] Login as Hospital Staff
- [ ] Click "Create Request" or "New Request" button
- [ ] Fill form:
  - Blood Type: Select `O+`
  - Units Needed: Enter `2`
  - Urgency: Select `Critical` (red)
  - Location: Should auto-fill
  - Required Date: Today or tomorrow
  - Distance Radius: `15 km` (default)
  - Notes: Optional - add "Test request"
- [ ] Click "Submit" or "Create Request"
- [ ] **Expected:** 
  - Success message appears
  - Matched donors list appears with 10 people
  - Request status shows "Active"
- [ ] **Check:** All fields saved correctly
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 3.2: View Matched Donors**
- [ ] On the request you just created, find "Matched Donors" section
- [ ] **Expected:** See list of 10 donors with:
  - Rank (#1, #2, #3... #10)
  - Donor name
  - Compatibility % (score like 94%, 91%, etc)
  - Distance (in km)
  - Availability (Yes/No)
- [ ] **Check:** 
  - List is sorted by score (highest first)
  - Top 3 donors are highlighted (yellow/bright background)
  - Scores are between 50-100%
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 3.3: Send Notification to Donor**
- [ ] On matched donor list, click "Notify" button for #1 ranked donor
- [ ] **Expected:** 
  - Loading animation shows briefly
  - Success message: "Donor notified"
  - Button changes to "Notified" (grayed out)
- [ ] **Check:** Button response is instant (< 1 second)
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 3.4: Track Donor Responses**
- [ ] Click "Response Tracker" or similar in left menu
- [ ] Select the request you created
- [ ] **Expected:** See table showing:
  - Donor name
  - Response status (Accepted/Declined/Awaiting)
  - Time of response
- [ ] **Color Check:** 
  - Green = Accepted
  - Red = Declined
  - Yellow = Still waiting
- [ ] **Check:** Updates show in real-time as donors respond
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 3.5: View Active Requests**
- [ ] Click "Active Requests" or "My Requests" in left menu
- [ ] **Expected:** See list of all your hospital's active requests
- [ ] **Check:** Shows:
  - Blood type
  - Units needed
  - Status (Active/Completed/Cancelled)
  - Date created
  - Number of responses
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 3.6: View Request History**
- [ ] Click "Request History" or "All Requests" in left menu
- [ ] **Expected:** See all past and current requests
- [ ] **Check:** Pagination works (if many requests)
- **Status:** ✅ Pass / ❌ Fail

---

## **PART 4: DONOR DASHBOARD FEATURES**

### **Test 4.1: Receive Blood Request Notification**
- [ ] Login as Donor (donor1@test.com)
- [ ] **Check:** Do you see notification about hospital's request?
  - Red alert/badge at top
  - OR notification bell with red number
  - OR in "Incoming Requests" section
- [ ] **If YES:** Feature works
- [ ] **If NO:** Try refreshing page (F5)
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 4.2: View Notification Details**
- [ ] Click on the notification
- [ ] **Expected:** See:
  - Hospital name
  - Blood type needed (O+)
  - Units needed (2)
  - Urgency (Critical in red)
  - Location
  - Required date
- [ ] **Check:** All information is clear and readable
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 4.3: Accept Blood Request**
- [ ] Click "ACCEPT" button (green)
- [ ] **Expected:** 
  - Button changes to "Accepted" (grayed out)
  - Page message: "You have accepted this request"
  - Request moves to "Confirmed" section
- [ ] **Check:** Action is instant (< 1 second)
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 4.4: Decline Blood Request**
- [ ] (If you have another notification) Click "DECLINE" button (red)
- [ ] **Optional:** Add reason for declining
- [ ] **Expected:** 
  - Button changes to "Declined"
  - Hospital sees your response
- [ ] **Check:** Hospital can see you declined (in Response Tracker)
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 4.5: Check Eligibility Status**
- [ ] On Donor Dashboard, look for "Eligibility" section
- [ ] **Expected:** Shows either:
  - ✅ "ELIGIBLE to donate"
  - ❌ "Not eligible - can donate in X days"
- [ ] **Check:** 
  - For donor1: Should show "ELIGIBLE" (last donation 75 days ago)
  - For donor2: Should show "Not eligible - can donate in X days"
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 4.6: View Donation History**
- [ ] Click "Donation History" or "My Donations" in left menu
- [ ] **Expected:** See list of past donations:
  - Hospital name
  - Date
  - Units donated
  - Status
- [ ] **Check:** Shows all previous donations in chronological order
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 4.7: View Personal Profile**
- [ ] Click "Profile" or "My Profile" in left menu
- [ ] **Expected:** See your information:
  - Name
  - Email
  - Blood type (O+ for donor1)
  - Phone number
  - Address
  - Reliability score (%)
  - Last donation date
- [ ] **Check:** All information is correct and up-to-date
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 4.8: View Personal Statistics**
- [ ] Look for "Statistics" or "My Stats" section on dashboard
- [ ] **Expected:** See:
  - Total donations made
  - Units donated
  - Lives saved estimate
  - Response rate (%)
- [ ] **Check:** Numbers are reasonable (e.g., total donations > 0)
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 4.9: Update Availability Status**
- [ ] Look for "Available / Unavailable" toggle
- [ ] Click to toggle it OFF (unavailable)
- [ ] **Expected:** Status changes immediately
- [ ] Toggle it back ON
- [ ] **Expected:** Shows available again
- [ ] **Check:** Hospital's next request should skip this donor when unavailable
- **Status:** ✅ Pass / ❌ Fail

---

## **PART 5: SECURITY & ACCESS CONTROL**

### **Test 5.1: Donor Cannot Access Hospital Dashboard**
- [ ] Login as Donor (donor1@test.com)
- [ ] Manually type URL: `/hospital/dashboard` or `/hospital`
- [ ] **Expected:** Redirected back to `/donor/dashboard`
- [ ] OR error message: "Access Denied"
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 5.2: Hospital Cannot Access Admin Dashboard**
- [ ] Login as Hospital (hospital.staff@test.com)
- [ ] Manually type URL: `/admin/dashboard` or `/admin`
- [ ] **Expected:** Redirected back to `/hospital/dashboard`
- [ ] OR error message: "Access Denied"
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 5.3: Logout Works**
- [ ] Click logout button (usually bottom of left menu)
- [ ] **Expected:** 
  - Redirected to login page
  - Session cleared
  - Cannot go back to dashboard with browser back button
- [ ] **Check:** Trying to access dashboard redirects to login
- **Status:** ✅ Pass / ❌ Fail

---

## **PART 6: BUSINESS LOGIC TESTS**

### **Test 6.1: Ineligible Donor Is Filtered**
- [ ] Login as Admin
- [ ] Go to Donors list
- [ ] Find "donor2@test.com" (A- blood type, last donation 30 days ago)
- [ ] **Expected:** Shows "Ineligible" status (not "Eligible")
- [ ] Now login as Hospital and create A- blood request
- [ ] **Expected:** donor2 does NOT appear in matched donors list
- [ ] **Check:** System correctly blocks ineligible donors
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 6.2: PAST-Match Algorithm Works**
- [ ] Look at matched donors for any request
- [ ] **Expected:** 
  - Donors are ranked by score (highest first)
  - Closer donors score higher than far donors
  - Top 3 donors highlighted
  - Scores make sense (donor at 2km scores higher than 50km)
- [ ] **Check:** Ranking seems logical
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 6.3: Critical Urgency Processes Faster**
- [ ] Create two requests: one "Critical", one "Regular"
- [ ] **Expected:** 
  - Critical request shows results in 1-2 seconds
  - Regular request shows results in 1-3 seconds
- [ ] **Check:** Both complete quickly (< 5 seconds)
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 6.4: Blood Type Compatibility**
- [ ] Create blood request for "O+" type
- [ ] **Expected:** Matched donors only have O+ blood type
- [ ] Create blood request for "A-" type
- [ ] **Expected:** Matched donors only have A- or O- (compatible types)
- [ ] **Check:** No incompatible blood types appear
- **Status:** ✅ Pass / ❌ Fail

---

## **PART 7: PERFORMANCE CHECKS**

### **Test 7.1: Page Load Speed**
- [ ] Time how long each page takes to load:

| Page | Expected | Actual | Pass/Fail |
|------|----------|--------|-----------|
| Login page | < 2 sec | ___ | ✅/❌ |
| Admin dashboard | < 2 sec | ___ | ✅/❌ |
| Hospital dashboard | < 2 sec | ___ | ✅/❌ |
| Donor dashboard | < 2 sec | ___ | ✅/❌ |
| Matched donors list | < 1 sec | ___ | ✅/❌ |

- [ ] **Check:** All pages load quickly

---

### **Test 7.2: Create Request Speed**
- [ ] Hospital creates blood request
- [ ] Time how long until matched donors appear
- [ ] **Expected:** < 3 seconds
- [ ] **Actual:** ___ seconds
- [ ] **Status:** ✅ Pass / ❌ Fail

---

### **Test 7.3: Accept/Decline Response Speed**
- [ ] Donor clicks Accept on a request
- [ ] Time how long until status updates in hospital view
- [ ] **Expected:** < 1 second
- [ ] **Check:** Update is instant
- **Status:** ✅ Pass / ❌ Fail

---

## **PART 8: DATA VALIDATION**

### **Test 8.1: Required Fields Cannot Be Empty**
- [ ] Hospital creates blood request
- [ ] Try to submit with missing blood type
- [ ] **Expected:** Red error message
- [ ] Try to submit with missing units
- [ ] **Expected:** Red error message
- [ ] **Check:** Form prevents submission with missing data
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 8.2: Invalid Email Format Is Rejected**
- [ ] Go to login page
- [ ] Enter email: `invalidemail` (no @)
- [ ] Enter password: `anypass`
- [ ] **Expected:** Error message before trying to login
- [ ] **Check:** Form validates format
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 8.3: Numbers Are Validated**
- [ ] Hospital creates blood request
- [ ] Enter units: `-5` (negative)
- [ ] **Expected:** Red error or system changes it to positive
- [ ] Enter units: `1000` (unrealistic)
- [ ] **Expected:** Either error or accepts it (check what's reasonable)
- **Status:** ✅ Pass / ❌ Fail

---

## **PART 9: NOTIFICATIONS**

### **Test 9.1: In-App Notification**
- [ ] Hospital creates request
- [ ] Logged-in donor should see notification immediately
- [ ] **Expected:** Red alert or notification badge appears
- [ ] **Check:** Notification appears within 2 seconds
- **Status:** ✅ Pass / ❌ Fail

---

### **Test 9.2: Notification Details Are Correct**
- [ ] Click on notification
- [ ] **Expected:** Shows:
  - Correct hospital name
  - Correct blood type
  - Correct units
  - Correct urgency
- [ ] **Check:** No mismatched information
- **Status:** ✅ Pass / ❌ Fail

---

## **PART 10: FINAL SUMMARY**

### **Count Your Results:**
- [ ] Total Tests: **54**
- [ ] Passed: ___
- [ ] Failed: ___
- [ ] Success Rate: ___%

### **Critical Features (Must Pass):**
- [ ] Login works for all three roles
- [ ] Hospital can create requests
- [ ] Donors see notifications
- [ ] Donors can accept/decline
- [ ] Hospital sees responses
- [ ] Ineligible donors are blocked
- [ ] Access control works (no role crossover)

### **If Critical Features Pass:**
✅ **System is WORKING and READY**

### **If Any Critical Feature Fails:**
❌ **Report issue immediately** - describes which feature failed and what happened

---

## **NOTES & OBSERVATIONS**

Write any issues, slowness, or unexpected behavior here:

```
Issue 1: _______________________________________________
Status: Pass / Fail

Issue 2: _______________________________________________
Status: Pass / Fail

Issue 3: _______________________________________________
Status: Pass / Fail
```

---

**Testing Date:** ___________
**Tester Name:** ___________
**System Status:** ✅ WORKING / ❌ NEEDS FIXES
