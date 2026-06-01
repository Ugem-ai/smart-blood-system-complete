# Emergency Broadcast Mode - Admin Quick Guide

## What is Emergency Broadcast Mode?

Emergency Broadcast Mode is a system-wide activation that enhances critical blood request processing during disasters, mass casualties, and large-scale emergencies.

**When to activate:** Major accidents, earthquakes, fires, pandemics, multi-vehicle collisions, or any event with multiple critical patients simultaneously.

---

## Quick Start: Activating Emergency Mode

### Option 1: Via Admin Dashboard

1. Go to **Admin Dashboard** → **Emergency Control**
2. Click **"Activate Emergency Mode"**
3. Select **Trigger Type:**
   - "earthquake"
   - "major accident"
   - "large-scale emergency"
   - Custom text (max 255 chars)
4. Set **Expiration:** (e.g., 1-24 hours)
5. Click **"Confirm"**

### Option 2: Via API (Programmatic)

```bash
curl -X POST https://smart-blood.local/api/admin/emergency-mode \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "enabled": true,
    "trigger": "major accident - 10 vehicle pileup on EDSA",
    "expires_in_hours": 6
  }'
```

---

## What Happens When Activated?

| Aspect | Normal Mode | Emergency Mode | Effect |
|--------|------------|---|---|
| **Priority Boost** | 1.0x | 1.20x | All critical requests get priority |
| **Time Sensitivity** | 1.0x | 1.35x | Fastest arrival becomes critical |
| **Search Radius** | 35-50 km | 200 km | Massive geographic expansion |
| **Emergency Boost** | 0% | +15% | Additional operational score |
| **Escalation Timer** | 5 minutes | 2 minutes | Faster stage progression |
| **Notification Channels** | SMS/Email | SMS + Email + In-App | All channels active |
| **Donor Notifications** | Gradual | Aggressive | More donors contacted immediately |

---

## Real-Time Status Monitoring

### View Current Status

```bash
# Check emergency mode status
curl https://smart-blood.local/api/admin/system/emergency-broadcast-status \
  -H "Authorization: Bearer YOUR_TOKEN"

# Response:
{
  "data": {
    "enabled": true,
    "trigger": "major accident",
    "activated_at": "2026-05-23T17:30:00Z",
    "expires_at": "2026-05-24T17:30:00Z",
    "active_duration_seconds": 3600,
    "is_disaster_response": true
  }
}
```

### Hospital View

Hospitals will see a banner on their request form:

```
⚠️ System-wide Emergency Broadcast Mode Active
Trigger: major accident
Expires at: 2026-05-24T17:30:00Z

All requests are being processed with expanded donor pools 
and accelerated notification timelines.
```

---

## Managing Multiple Critical Requests During Emergency

### Example: Hospital Coordinator Actions

```
5:15 PM - Emergency arrives (6 patients, 3 blood types)
         └─ Hospital coordinator creates 3 critical requests:
            └─ O+ (3 units)
            └─ B- (2 units)
            └─ A+ (1 unit)

5:16 PM - All 3 requests processing in parallel:
         ├─ Request 1: Filtering O+ donors
         ├─ Request 2: Filtering B- donors
         └─ Request 3: Filtering A+ donors

5:17 PM - Parallel ranking completed (~3 sec):
         ├─ Request 1: 10 O+ donors ranked
         ├─ Request 2: 10 B- donors ranked
         └─ Request 3: 10 A+ donors ranked

5:17 PM - Stage 1 notifications sent (to 30 donors total):
         ├─ SMS: "URGENT: Hospital needs O+, distance: 5km"
         ├─ Push: Emergency blood request alert
         └─ In-App: Request card with 1-click accept

5:21 PM - First acceptance (O+ donor):
         └─ O+ request status: "Confirmed"

5:22 PM - Second acceptance (B- donor):
         └─ B- request status: "Confirmed"

5:23 PM - Third acceptance (A+ donor):
         └─ A+ request status: "Confirmed"

✅ RESULT: All blood units secured in 8 minutes
```

---

## Escalation Stages (Automatic)

When a **critical request** doesn't get immediate response:

### Stage 1: Immediate (0 min)
- **Scope:** Closest 5 donors
- **Notification:** SMS + Push + In-App
- **Timeline:** Immediately
- **Success Rate:** ~60% of critical requests

### Stage 2: Wider Radius (After 5 min if no response)
- **Scope:** Next 10 compatible donors
- **Radius Expansion:** +60 km
- **Notification:** SMS + Push (escalated priority)
- **Timeline:** 5 minutes after request
- **Success Rate:** ~25% additional fulfillment

### Stage 3: Regional Broadcast (After 10 min if still no response)
- **Scope:** All remaining compatible donors (500+ donors)
- **Radius:** Regional/National (per config)
- **Notification:** SMS + Email + In-App (all channels)
- **Timeline:** 10 minutes after request
- **Success Rate:** ~100% (unless blood type unavailable)

---

## Deactivating Emergency Mode

### Automatic Expiration
- Automatically expires after configured hours
- No action needed
- Hospitals receive notification when mode ends

### Manual Deactivation (If Emergency Ends Early)

1. **Via Admin Dashboard:**
   - Click **"Deactivate Emergency Mode"**
   - Confirm action
   - Mode disabled immediately

2. **Via API:**
```bash
curl -X POST https://smart-blood.local/api/admin/emergency-mode \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -d '{"enabled": false}'
```

---

## Best Practices

### ✅ DO

- ✅ Activate early during large incidents (don't wait)
- ✅ Set realistic expiration time (6-24 hours typical)
- ✅ Monitor hospital request submission rates
- ✅ Check escalation stage progression
- ✅ Deactivate when incident stabilizes
- ✅ Review activity logs after incident

### ❌ DON'T

- ❌ Keep emergency mode active longer than necessary
- ❌ Activate for single critical requests (not needed)
- ❌ Forget to deactivate after incident resolved
- ❌ Use custom trigger text that's unclear
- ❌ Expect manual approval for hospital requests during mode

---

## Monitoring Emergency Response

### Key Metrics to Watch

1. **Request Fulfillment Time**
   - Goal: < 5 min per request
   - Monitor: Activity logs → blood-request metrics

2. **Escalation Stage Distribution**
   - Goal: 80% fulfilled at Stage 1
   - Monitor: Request matches table

3. **Donor Response Rate**
   - Goal: > 50% acceptance rate
   - Monitor: donor_responses table

4. **Notification Delivery**
   - Goal: 100% delivery rate
   - Monitor: notification_deliveries table

### View Activity Logs

```sql
SELECT * FROM activity_logs 
WHERE action LIKE '%blood-request%' 
  AND created_at > NOW() - INTERVAL 1 HOUR
ORDER BY created_at DESC;
```

---

## Fairness During Emergency

### Important: Donor Rotation Still Applies

Even during Emergency Mode, donors receive cooldown penalties:

```
Donor A matched 2 hours ago:
  ├─ Base score: 95.0
  ├─ Emergency boost: +15
  ├─ = 110.0 (before penalties)
  ├─ Cooldown penalty: -8
  └─ Final score: 102.0

Donor B never matched before:
  ├─ Base score: 92.0
  ├─ Emergency boost: +15
  ├─ = 107.0
  ├─ Cooldown penalty: 0
  └─ Final score: 107.0

Result: Donor B ranks higher (fresher donor)
```

**Why?** Prevents burnout and maintains long-term pool sustainability, even during disasters.

---

## Common Issues & Troubleshooting

### Problem: Mode activated but hospitals don't see it

**Solution:**
```bash
# Check cache
redis-cli get "system:emergency_broadcast_mode:v1"

# Force cache refresh
redis-cli del "system:emergency_broadcast_mode:v1"

# Verify database state
SELECT * FROM emergency_states WHERE id = 1;
```

### Problem: Requests not escalating through stages

**Solution:**
```php
// Check escalation configuration
$settings = app(SystemSettingsService::class)->current();
$escalationTimer = $settings['control_center']['emergency']['escalation_timer_minutes'];
// Should be 2-5 minutes

// Check if jobs are queued
SELECT * FROM jobs WHERE payload LIKE '%SendEmergencyNotificationsJob%';
```

### Problem: Donors complaining about duplicate notifications

**Solution:**
- Check donor cooldown service
- Verify throttle rates in config
- Review alert logs for timing issues

---

## Documentation References

- **Full System Documentation:** [docs/MULTI_CRITICAL_PRIORITIZATION.md](../MULTI_CRITICAL_PRIORITIZATION.md)
- **Algorithm Details:** [docs/ALGORITHM_PAST_MATCH.md](../ALGORITHM_PAST_MATCH.md)
- **Hospital Training:** [docs/HOSPITAL_TRAINING_PRESENTATION.md](../HOSPITAL_TRAINING_PRESENTATION.md)

---

## Emergency Contact

For system issues during active emergency:

1. **Check Logs:** `tail -f storage/logs/laravel.log`
2. **Restart Queue Workers:** `php artisan queue:restart`
3. **Check Database:** Verify connectivity to production DB
4. **Monitor:** Real-time metrics at `/admin/emergency-dashboard/live`

---

**Last Updated:** May 23, 2026
**Version:** 1.0
