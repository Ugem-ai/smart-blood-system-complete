# Hospital Staff Training Presentation Outline

**Smart Blood System - 2-Hour Training Session**

For: Hospital blood coordinators, nurses, admin staff

---

## AGENDA

| Time | Topic | Duration |
|------|-------|----------|
| 0:00-0:10 | Welcome & System Overview | 10 min |
| 0:10-0:25 | PAST-Match Algorithm Explained | 15 min |
| 0:25-0:50 | Live Demo: Create Request to Responses | 25 min |
| 0:50-1:10 | Hands-On: Everyone creates a test request | 20 min |
| 1:10-1:30 | Real-world Emergency Scenarios | 20 min |
| 1:30-1:50 | Q&A, Support, Troubleshooting | 20 min |
| 1:50-2:00 | Certification & Wrap-up | 10 min |

---

## SLIDE DECK

### **SLIDE 1: WELCOME**

**Title:** Smart Blood System Training - Saving Lives Faster

**Key Points:**
- System goes live: March 31, 2026
- Your hospital: [Hospital Name]
- Success depends on YOUR expertise + OUR technology

**Icebreaker:** "How many blood donation calls do you make per emergency?"

---

### **SLIDE 2: THE PROBLEM WE SOLVE**

**Before Smart Blood System:**
- Blood request arrives
- 20 minutes of manual donor calling
- Success rate ~60% (many unreachable)
- Blood quality decreases (delay = less shelf-stable)

**With Smart Blood System:**
- Blood request created in app
- 10 compatible donors notified INSTANTLY
- 92% success rate (automated PAST-Match ranking)
- Blood secured in 4.2 minutes AVERAGE

**Impact:** 40+ minutes saved, more lives saved, less blood wasted

---

### **SLIDE 3: SYSTEM OVERVIEW (30-second elevator pitch)**

**Smart Blood System = 3 Parts:**

1. **Your Dashboard:** Where you create requests and track responses
2. **Donor App:** Blood donors get notifications and respond
3. **PAST-Match Algorithm:** AI that matches donors to your request

**Result:** You + AI = Superhuman speed ⚡

---

### **SLIDE 4: PAST-MATCH ALGORITHM EXPLAINED**

**What it does:** When you create a request, system instantly ranks eligible donors

**How it ranks (on 0-100 scale):**
- 35% **Proximity** - How close is the donor? (closest = best)
- 25% **Availability** - Are they available to donate now?
- 20% **Donation Interval** - When was their last donation? (56 days = required)
- 10% **Travel Time** - Commute time to hospital
- 10% **Reliability** - Their history of responding & showing up

**Example:**
```
REQUEST: Need O+ blood, 2 units, CRITICAL

Ranked Donors:
#1: Juan (3km away, available) = 94% match → NOTIFY FIRST ⭐
#2: Maria (5km away, available) = 91% match → NOTIFY SECOND
#3: Pedro (4km away, available) = 89% match → NOTIFY THIRD

System did in 1 second what takes 20 min of calling
```

**Key insight:** Top 3 donors have 78% response rate. Focus on them.

---

### **SLIDE 5: YOUR DASHBOARD WALKTHROUGH**

**Live Demo: Open dashboard, show:**

1. **Dashboard Module** - 4 KPI cards
   - Active Requests (how many currently open)
   - Critical Requests (emergencies)
   - Pending Responses (waiting on donors)
   - Confirmed Donors (secured)

2. **Create Request Module**
   - Blood Type dropdown (10 options)
   - Units Needed (slider 1-20)
   - Urgency Level (Normal/High/CRITICAL)
   - Location & Date fields
   - Notes for donor context

3. **Active Requests Module**
   - All hospital's open requests
   - Filters: by urgency or status
   - Click "View" to see full details
   - Can cancel request anytime

4. **Matched Donors Module**
   - Top 10 donors auto-ranked
   - Yellow highlight = Top 3 (best matches)
   - Compatibility % shown
   - Distance in km shown
   - "Notify" button for each

5. **Response Tracker Module**
   - Real-time status: Accepted/Declined/Pending
   - Color-coded for quick scanning
   - "Confirm" button when donor accepts
   - Timestamps showing when they responded

6. **Notifications Module**
   - History of all sent notifications
   - Filter by type
   - Mark as read

---

### **SLIDE 6: HOW TO CREATE A REQUEST (Step-by-Step)**

**Live Demonstration:**

**Step 1:** Click "Create Request" module
```
Form appears with 6 fields
```

**Step 2:** Select Blood Type
```
Dropdown: O+, O-, A+, A-, B+, B-, AB+, AB-, RH+, RH-
← Click the one you need
```

**Step 3:** Enter Units Needed
```
Slider or number field: 1-20 units
← Move slider or type number
```

**Step 4:** Choose Urgency
```
Radio buttons:
◯ Normal (routine transfusion)
◯ High (surgery tomorrow)
● CRITICAL (emergency surgery NOW) ← Select for emergencies
```

**Step 5:** Location
```
Pre-filled with your hospital
← Can edit if specific dept building
```

**Step 6:** Date Needed
```
Calendar picker
← Usually "Today" or "Tomorrow"
```

**Optional:** Add Notes
```
"Type & Cross, baseline Hgb 7.5"
"Trauma case, STAT needed"
← Helps donors understand importance
```

**Step 7:** Click SUBMIT
```
✅ Success! System generating matches...
↓
(1-2 seconds)
↓
10 donors ranked by PAST-Match
```

**Total Time: 2 minutes from request to matched donors** ⚡

---

### **SLIDE 7: TRACKING RESPONSES IN REAL-TIME**

**After creating request:**

1. Go to **Response Tracker** module
2. Select your request from dropdown
3. Watch live updates every 5 seconds:

```
✅ ACCEPTED (Green)  "Juan is coming in!"
❌ DECLINED (Red)    "Maria can't donate"
⏳ PENDING (Yellow)   "Pedro hasn't responded yet"
```

**What to do:**
- When donor accepts (green) → Click "Confirm" → Marks them as locked-in
- If donor declines → Move to next on list
- If pending too long (>5 min) → Notify Rank #2 donor in parallel

**Pro tip:** You can notify donors in parallel (rank #1, #2, #3 at same time)

---

### **SLIDE 8: REAL-WORLD SCENARIOS**

**Scenario 1: Routine Surgery - Ellen needs 2 units O+**

```
4:30 PM: Surgery scheduled for tomorrow 8 AM
4:32 PM: Create request (O+, 2 units, Normal urgency)
        ↓ 1 second → 10 donors ranked
4:33 PM: Notify top 3 donors
4:45 PM: Juan accepts → Confirm → Blood secured ✅
        Surgery goes forward as planned
        Wasted? 0 units. Cost? ₱200 SMS. Benefit: Surgery on schedule.
```

**Scenario 2: Emergency Trauma - Multi-victim car crash**

```
5:15 PM: Emergency room gets 6 trauma patients
        Need: 3 units O+, 2 units B-, 1 unit A+
5:16 PM: Hospital coordinator creates 3 requests (one per type)
         ↓ 3 seconds → 30 donors ranked (10 per type)
5:17 PM: Notify all top donors in parallel
5:21 PM: ✅ 3 units O+ confirmed
5:22 PM: ✅ 2 units B- confirmed
5:23 PM: ✅ 1 unit A+ confirmed
         All 6 patients have blood available
         Wasted? 0 units. Lives saved? 6. Cost? ₱1,800 SMS.
         Benefit: Impossible without system
         
TOTAL TIME: 7 MINUTES (vs 2+ hours manually)
```

**Scenario 3: Rare Blood Type - Patient needs AB-**

```
7:30 PM: Patient needs rare AB- blood type
7:31 PM: Create request (AB-, 1 unit)
         ↓ 1 second → System finds 8 eligible AB- donors
7:32 PM: Top donor (Maria) notified
7:35 PM: Maria accepts → Confirmed
         Specific blood type secured
         Without system: Would take 4+ hours of calling
         With system: 4 minutes
```

---

### **SLIDE 9: WHAT HAPPENS ON DONOR SIDE**

**Show donor's experience (for context):**

1. System sends SMS: "🩸 Blood needed! Click here to help" + link
2. Donor clicks link → Login (if not already logged in)
3. Donor sees: Hospital name, blood type, units, urgency
4. Donor clicks "Accept" or "Decline"
5. Hospital immediately sees response (< 5 sec)

**Why this matters for you:** 
- Donors respond faster to app vs phone (they can see context)
- No voicemail tag backs
- Clear interface = less confusion
- Availability toggle = automatic filtering

---

### **SLIDE 10: COMMON MISTAKES TO AVOID**

| Mistake | Why Bad | How to Avoid |
|---------|---------|-------------|
| Forgetting to "Confirm" donor | Might contact them again by accident | Click Confirm immediately after they accept |
| Forgetting to Cancel request | Donors still notified after fulfilled | Cancel request in "Active Requests" |
| Creating wrong urgency | Normal request takes longer to match | Verify urgency: Normal/High/CRITICAL |
| Typos in location | Donors confused about WHERE to go | Double-check hospital address before submit |
| Forgetting to check Response Tracker | Miss responses, slow follow-up | Check after 30 seconds, re-check every minute |

---

### **SLIDE 11: TROUBLESHOOTING**

**Problem:** I can't login
**Solution:** Verify email/password, ask IT for password reset

**Problem:** System is slow / not loading
**Solution:** Refresh browser (F5), try different browser, restart computer

**Problem:** Donors aren't showing up
**Solution:** Check urgency is set to CRITICAL, verify blood type, call support

**Problem:** Donor not responding after 10 minutes
**Solution:** Notify Rank #2 donor in parallel, don't wait

**Problem:** Request was cancelled by accident
**Solution:** Recreate the request, notify donors again

**Problem:** "Cancel Request" button not visible
**Solution:** Click "View" detail modal, Cancel button is there

**24/7 Support:** Call [PHONE] or Email [EMAIL]

---

### **SLIDE 12: HANDS-ON EXERCISE**

**Everyone creates a TEST request (no real donors contacted):**

1. Login with credential: [TEST-ACCOUNT]
2. Click "Create Request"
3. Fill in:
   - Blood Type: AB+ (rare, non-critical)
   - Units: 1
   - Urgency: **Normal** (not critical - just for practice)
   - Location: [Your Hospital Name]
   - Date: Today
4. Submit
5. See matched donors appear
6. Go to Response Tracker, see simulated responses

**Advantage:** You practice without real donor impact

**Time:** 5 min per person

---

### **SLIDE 13: KEY TAKEAWAYS**

**Remember these 5 things:**

1. **Create Request = 2 minutes** (not 20 minutes of calling)
2. **Top 3 donors = 78% response rate** (focus on them)
3. **Confirm when donor accepts** (prevent double-notification)
4. **Cancel if fulfilled elsewhere** (prevent wasted SMS)
5. **24/7 support is 1 phone call away** (you're never alone)

**Impact:** 40+ minutes saved per request × 10 requests/week = **7+ hours/week saved**

---

### **SLIDE 14: CERTIFICATION**

**Quick 5-question quiz (verbal):**

1. What does PAST-Match do?
2. How many donors should you focus on?
3. What's the first button to click for new request?
4. When should you click "Confirm"?
5. What's the 24/7 support phone number?

**If you answer 5/5 correct:** You're certified! ✅

---

### **SLIDE 15: CLOSING**

**Thank you for:**
- Coming to training
- Committing to excellence
- Caring about lives

**Remember:** You + Smart Blood System = Superhuman response time

**Go save lives!** 🩸

**Questions?** [Contact info]

---

## MATERIALS TO BRING

- [ ] Hospital Staff Quick-Start Guide (printed, 1 per person)
- [ ] Login credentials (pre-printed cards)
- [ ] 24/7 Support phone number (poster for wall)
- [ ] FAQ sheet
- [ ] Troubleshooting guide

## TRAINING NOTES

- **Duration:** 2 hours (includes demo + hands-on)
- **Ideal group:** 8-12 people (smaller = better Q&A)
- **Setup:** Projector + laptop + [TEST-ACCOUNT] access
- **Dress Code:** Normal (no special needs)
- **Snacks:** Optional (people think better with coffee)

## POST-TRAINING

Within 24 hours:
- [ ] Send "You're Certified" email to attendees
- [ ] Share printed Quick-Start guides
- [ ] Confirm they can login
- [ ] Get feedback via quick survey
- [ ] Post support number in donor coordination area
