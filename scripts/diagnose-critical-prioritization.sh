#!/bin/bash

# Multi-Critical Prioritization System Diagnostic Script
# Run: ./scripts/diagnose-critical-prioritization.sh

echo "======================================"
echo "PAST-Match Critical Prioritization"
echo "System Diagnostic Report"
echo "======================================"
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

check_result() {
  if [ $1 -eq 0 ]; then
    echo -e "${GREEN}✅ PASS${NC} $2"
  else
    echo -e "${RED}❌ FAIL${NC} $2"
  fi
}

# 1. Check if services exist
echo ""
echo "1. Service Classes"
echo "─────────────────"

if [ -f "app/Services/EmergencyBroadcastModeService.php" ]; then
  check_result 0 "EmergencyBroadcastModeService.php exists"
else
  check_result 1 "EmergencyBroadcastModeService.php not found"
fi

if [ -f "app/Services/EmergencyEscalationService.php" ]; then
  check_result 0 "EmergencyEscalationService.php exists"
else
  check_result 1 "EmergencyEscalationService.php not found"
fi

if [ -f "app/Services/SystemSettingsService.php" ]; then
  check_result 0 "SystemSettingsService.php exists"
else
  check_result 1 "SystemSettingsService.php not found"
fi

if [ -f "app/Algorithms/PASTMatch.php" ]; then
  check_result 0 "PASTMatch.php exists"
else
  check_result 1 "PASTMatch.php not found"
fi

# 2. Check if job classes exist
echo ""
echo "2. Queue Job Classes"
echo "────────────────────"

if [ -f "app/Jobs/ProcessBloodRequestMatchingJob.php" ]; then
  check_result 0 "ProcessBloodRequestMatchingJob.php exists"
else
  check_result 1 "ProcessBloodRequestMatchingJob.php not found"
fi

if [ -f "app/Jobs/SendEmergencyNotificationsJob.php" ]; then
  check_result 0 "SendEmergencyNotificationsJob.php exists"
else
  check_result 1 "SendEmergencyNotificationsJob.php not found"
fi

# 3. Check if routes exist
echo ""
echo "3. API Routes"
echo "─────────────"

if grep -q "emergency-broadcast-status" routes/api.php; then
  check_result 0 "Emergency broadcast status route registered"
else
  check_result 1 "Emergency broadcast status route not found"
fi

if grep -q "/hospital/requests" routes/api.php; then
  check_result 0 "Hospital request routes registered"
else
  check_result 1 "Hospital request routes not found"
fi

# 4. Check if urgency profiles are configured
echo ""
echo "4. Urgency Profile Configuration"
echo "────────────────────────────────"

if grep -q "critical.*1.20" app/Services/SystemSettingsService.php; then
  check_result 0 "Critical priority multiplier (1.20x) configured"
else
  check_result 1 "Critical priority multiplier not found"
fi

if grep -q "critical.*1.35" app/Services/SystemSettingsService.php; then
  check_result 0 "Critical time multiplier (1.35x) configured"
else
  check_result 1 "Critical time multiplier not found"
fi

# 5. Check if escalation stages are configured
echo ""
echo "5. Escalation Stage Configuration"
echo "─────────────────────────────────"

if grep -q "LEVEL_CLOSEST\|LEVEL_WIDER_RADIUS\|LEVEL_ALL_COMPATIBLE" app/Services/EmergencyEscalationService.php; then
  check_result 0 "Three escalation levels defined"
else
  check_result 1 "Escalation levels not found"
fi

# 6. Check database migrations
echo ""
echo "6. Database Tables"
echo "──────────────────"

if [ -f "database/migrations/"*"create_blood_requests_table.php" ]; then
  check_result 0 "BloodRequest table migration exists"
else
  check_result 1 "BloodRequest table migration not found"
fi

if [ -f "database/migrations/"*"create_request_matches_table.php" ]; then
  check_result 0 "RequestMatch table migration exists"
else
  check_result 1 "RequestMatch table migration not found"
fi

if [ -f "database/migrations/"*"create_donor_responses_table.php" ]; then
  check_result 0 "DonorResponse table migration exists"
else
  check_result 1 "DonorResponse table migration not found"
fi

if [ -f "database/migrations/"*"create_emergency_states_table.php" ]; then
  check_result 0 "EmergencyState table migration exists"
else
  check_result 1 "EmergencyState table migration not found"
fi

# 7. Check frontend component
echo ""
echo "7. Frontend Components"
echo "──────────────────────"

if [ -f "resources/js/components/hospital/CreateRequestForm.vue" ]; then
  check_result 0 "CreateRequestForm.vue component exists"
  
  if grep -q "urgency_level.*critical" resources/js/components/hospital/CreateRequestForm.vue; then
    check_result 0 "Critical urgency option in form"
  else
    check_result 1 "Critical urgency option not found in form"
  fi
  
  if grep -q "emergency-broadcast-status" resources/js/components/hospital/CreateRequestForm.vue; then
    check_result 0 "Emergency broadcast status fetch in form"
  else
    check_result 1 "Emergency broadcast status fetch not found in form"
  fi
else
  check_result 1 "CreateRequestForm.vue component not found"
fi

# 8. Documentation
echo ""
echo "8. Documentation"
echo "────────────────"

if [ -f "docs/MULTI_CRITICAL_PRIORITIZATION.md" ]; then
  check_result 0 "MULTI_CRITICAL_PRIORITIZATION.md documentation exists"
else
  check_result 1 "MULTI_CRITICAL_PRIORITIZATION.md documentation not found"
fi

if [ -f "docs/ALGORITHM_PAST_MATCH.md" ]; then
  check_result 0 "ALGORITHM_PAST_MATCH.md documentation exists"
else
  check_result 1 "ALGORITHM_PAST_MATCH.md documentation not found"
fi

# Summary
echo ""
echo "======================================"
echo "Diagnostic Summary"
echo "======================================"
echo ""
echo "Next Steps:"
echo "1. Review docs/MULTI_CRITICAL_PRIORITIZATION.md for system overview"
echo "2. Test critical request submission via hospital dashboard"
echo "3. Verify queue workers: php artisan queue:listen"
echo "4. Check activity logs for critical request processing"
echo "5. Monitor emergency broadcast mode status"
echo ""
echo "For detailed troubleshooting, see:"
echo "  docs/MULTI_CRITICAL_PRIORITIZATION.md#10-troubleshooting"
echo ""
