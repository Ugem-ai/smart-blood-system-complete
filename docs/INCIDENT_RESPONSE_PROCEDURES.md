# Incident Response Procedures

**Smart Blood System - What To Do When Things Go Wrong**

---

## 🚨 **QUICK REFERENCE: 24/7 EMERGENCY CONTACTS**

| Severity | Contact | Phone | Email | Response Time |
|----------|---------|-------|-------|----------------|
| 🔴 CRITICAL (System down) | On-Call Architect | [PHONE] | [EMAIL] | Immediate |
| 🟠 URGENT (Request failing) | Support Lead | [PHONE] | [EMAIL] | 5 minutes |
| 🟡 HIGH (Feature broken) | Support Team | [PHONE] | [EMAIL] | 15 minutes |
| 🔵 NORMAL (Question) | Support Desk | [PHONE] | [EMAIL] | 1 hour |

**TEXT THIS NUMBER FOR EMERGENCIES:** [PHONE]

---

## INCIDENT SEVERITY LEVELS

### 🔴 **CRITICAL** (System down, data loss, security breach)
- **System is completely inaccessible**
- **Requests cannot be created**
- **Donors not receiving notifications**
- **Data is disappearing or corrupted**

**Your Action:** Call on-call architect immediately

### 🟠 **URGENT** (Major feature broken, preventing work)
- **Can't create specific blood types**
- **Response Tracker not updating**
- **Matched donors list empty when donors exist**
- **Can't confirm donors (holding up collection)**

**Your Action:** Call support lead, they'll escalate if needed

### 🟡 **HIGH** (Feature degraded, workaround exists)
- **System is slow (> 2 seconds response)**
- **Notifications sent but delayed (> 1 minute)**
- **Minor UI button not working**
- **Can't export report (but manual workaround works)**

**Your Action:** Call support, document issue, continue working with workaround

### 🔵 **NORMAL** (Question, feature request, learning)
- **How do I use feature X?**
- **Can we customize the form?**
- **Request a new report type**

**Your Action:** Email support desk, response in business hours

---

## COMMON ISSUES & IMMEDIATE SOLUTIONS

### ❌ **"System Won't Load / Blank Screen"**

**Check:**
1. Do you have internet? (Try www.google.com)
2. Are you using Chrome or Firefox? (Safari/Edge may have issues)
3. Clear browser cache: `Ctrl+Shift+Del` (Windows) or `Cmd+Shift+Del` (Mac)

**If still broken:**
- [ ] Try from different browser
- [ ] Try from different device
- [ ] Call support if multiple people have issue

**Workaround:** Call donors directly (use phone numbers from last request)

---

### ❌ **"I Can't Login / Wrong Password Error"**

**Check:**
1. Is CAPS LOCK on? (Password is case-sensitive)
2. Are you using correct email? (Usually is @hospital.com)
3. Did you recently change password?

**If still locked out:**
- [ ] Click "Forgot Password" link
- [ ] Check email for reset link
- [ ] If email doesn't arrive: Call IT immediately

**Workaround:** Have backup person with another login ready

---

### ❌ **"Request Created but No Donors Showing"**

**Check:**
1. Is urgency set to "CRITICAL"? (Critical requests match faster)
2. Is blood type valid? (Should show in dropdown)
3. Did you press SUBMIT? (Not just "Save")
4. Wait 5-10 seconds (matching algorithm takes 1-2 seconds)

**If still no donors:**
- [ ] Check: Do donors of this blood type exist in system?
- [ ] Call support: "Created request but no matched donors"
- [ ] Emergency workaround: Call known donors directly

**What to tell support:**
```
"Created request for: [Blood Type], [Units], [Urgency]
System showed: [What you see - none? Error message? Blank?]
Expected: 10 matched donors list
Time created: [What time?]"
```

---

### ❌ **"Donor Didn't Receive SMS Notification"**

**Check:**
1. Did system show "Notification sent"? (Green checkmark = sent)
2. Is donor's phone number correct? (Check in donor profile)
3. Did SMS send immediately or delayed? (Check Notification history)
4. Is SMS service working? (Check with second donor)

**If confirmed SMS not sent:**
- [ ] Manual fallback: Call donor directly
- [ ] Call support: "SMS not sent to [donor name, phone]"
- [ ] They'll check SMS service status

**Never wait for SMS** - If critical: Call donors

---

### ❌ **"Donor Accepted but Response Not Showing"**

**Check:**
1. Did you click "Refresh" button?
2. Close and re-open Response Tracker module
3. Wait 10 seconds (auto-refresh runs every 5 sec)
4. Is donor shown in list at all?

**If response still not visible:**
- [ ] Manually check with donor: "Did you accept?"
- [ ] If yes: Call support, give donor name + timestamp
- [ ] Click "Confirm" anyway (manual override if urgent)

---

### ❌ **"Can't Find Cancel Request Button"**

**It's in the modal. Here's how:**

1. Go to **"Active Requests"** module
2. Click **"View"** button on your request
3. Details modal pops up
4. Scroll down if needed
5. Look for red **"❌ Cancel Request"** button
6. (Appears only if request hasn't completed)

**If you still can't find it:**
- Call support: "I need to cancel a request"
- They'll walk you through it over phone

---

### ❌ **"System is Really Slow (Taking > 5 seconds)"**

**This is NOT normal.** Immediate actions:

1. Refresh browser: `F5`
2. If still slow: **Call support immediately**
   - Tell them: "System is slow, > 5 sec response"
   - They'll check server status
   - May need to scale up capacity

**Meanwhile:** 
- Close other tabs/apps using internet
- Try again in 2 minutes (might be temporary spike)
- If urgent: Call donors directly (don't wait for system)

---

### ❌ **"I Made a Mistake - Sent to Wrong Hospital / Blood Type"**

**If request just created (< 1 min):**
1. Go to "Active Requests"
2. Click "View" on wrong request
3. Click **"Cancel Request"**
4. Confirm the cancellation
5. Recreate with correct info
6. Notify correct donors

**If notifications already sent:**
- Call support: "Please cancel request [ID] - sent to wrong hospital"
- They can help recall notifications
- Then re-notify correct donors

**Important:** Cancel quickly to minimize wasted SMS

---

### ❌ **"Doctor Wants to Modify Request Details"**

**What you CAN change:**
- Cancel request (and recreate with new details)
- Modify notes (hidden until donor accepts)

**What you CANNOT change:**
- Blood type (once created, can't modify)
- Urgency level (once created, can't modify)
- Units needed (once matched, can't reduce)

**If modification needed:**
1. Cancel current request
2. Create new request with correct details
3. Notify donors again

**Important:** This creates new donor notifications (costs SMS)

---

## SERIOUS ISSUES - ESCALATION PATH

### 🔴 **If System Goes Completely Down**

**Contact immediately (in order):**
1. Call on-call architect: [PHONE]
   - "System completely down, can't login"
   - They'll investigate server
   
If no response after 5 min:
2. Call IT director: [PHONE]
   - "System still down, architect not responding"
   - They'll activate backup team

**Meanwhile:**
- Keep trying to access system (might auto-recover)
- Prepare manual donor list (backup spreadsheet)
- Start calling donors directly if requests pending

**Status Updates:**
- System will post updates on [STATUS PAGE]
- Or they'll call you with ETA

---

### 🔴 **If You Suspect Security Breach**

**Examples:**
- Someone logged in without your password
- Data appears to be corrupted/missing
- System showing other hospital's data
- Donor personal info visible when it shouldn't be

**Immediately:**
1. **STOP using system** - log out
2. Call IT security: [PHONE]
3. Tell them: "Possible security breach - [specific issue]"
4. They'll investigate and lock down account
5. Change password after they confirm it's safe

**DO NOT:**
- Continue using system (might spread breach)
- Tell other staff (avoid panic)
- Post on public channels/social media

---

## BACKUP PROCEDURES (If System Down)

### **Backup Plan: Manual Donor List**

**Keep a printed list of:**
- Top 20 donors per blood type
- Their phone numbers
- Their last donation date
- Their reliability rating

**Use if:** System is down > 15 minutes and you have urgent requests

**How to call:**
1. Find donors who match blood type needed
2. Check: Last donation date (must be > 56 days ago)
3. Call in order of highest reliability
4. Ask: "Can you donate [blood type] today?"
5. Track: Who said yes/no in notebook

**This replaces PAST-Match ranking:**
- Less efficient (takes 3-4x longer)
- Less accurate (no algorithm)
- But still works

**Important:** Revert to system as soon as it's back online

---

## REPORTING ISSUES

### **When Reporting to Support, Provide:**

```
Subject: [SEVERITY] System Issue - [Blood Type] Request

1. TIME: When did it happen? (Exact time: 4:23 PM)
2. WHAT: What were you doing? 
   "Tried to create O+ request"
3. ERROR: What error appeared?
   "Error 500 Internal Server Error"
4. IMPACT: Can you work around it?
   "No - can't create any requests"
5. AFFECTED: How many people impacted?
   "Our whole coordinator team (3 people)"
6. SCREENSHOT: Can you take screenshot? (Optional but helpful)
```

**Call vs Email:**
- **CRITICAL:** Always call first
- **URGENT:** Call, then follow up with email
- **HIGH:** Email with phone number for callback
- **NORMAL:** Email only

---

## COMMUNICATION CHECKLIST

When incident occurs, communicate:

### **Step 1: Internal (Your Team)**
- [ ] "System issue - here's workaround"
- [ ] "Keep trying / call donors directly"
- [ ] "Support team is investigating"

### **Step 2: Escalation (IT/Support)**
- [ ] Call on-call number immediately
- [ ] Describe severity + impact
- [ ] Give them permission to access account

### **Step 3: Updates (Hospital Leadership)**
- [ ] If issue > 30 min: Notify hospital director
- [ ] "System temporarily down, using manual procedures"
- [ ] "ETA for restoration: [TIME GIVEN BY SUPPORT]"

### **Step 4: All Clear**
- [ ] Confirm system is back online
- [ ] Test by creating dummy request
- [ ] Notify team it's safe to resume
- [ ] Thank support team

---

## MONTHLY DISASTER RECOVERY DRILL

**First Friday of every month (optional but recommended):**

1. **Time:** 14:00 (2 PM) - low traffic time
2. **Duration:** 15 minutes
3. **Test:** Manually call 3 donors using backup list
4. **Check:** Your backup donor list is current
5. **Result:** Confidence that you can handle outages

---

## RECOVERY SUCCESS CRITERIA

After any incident, system is back to normal when:

✅ Can login successfully  
✅ Can create blood requests  
✅ Can see matched donors (within 2 seconds)  
✅ Donors can receive SMS notifications  
✅ Response tracking updates in real-time  
✅ All data appears correct (no corruption)  

**Declare "all clear" only when ALL 6 are true**

---

## NOTES

- **Keep this guide visible** in donor coordination area
- **Memorize the emergency number:** [PHONE]
- **Update it** if contacts change
- **Practice** the backup procedures quarterly
- **Report** even small issues (helps improve system)

---

**Document Version:** 1.0  
**Last Updated:** March 26, 2026  
**Next Review:** June 26, 2026

**Questions?** Contact [EMAIL] or call [PHONE]
