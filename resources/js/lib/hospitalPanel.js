import api from './api';

export const urgencyTheme = {
  critical: {
    badge: 'bg-red-100 text-red-700 ring-1 ring-red-200',
    border: 'border-red-200',
  },
  high: {
    badge: 'bg-orange-100 text-orange-700 ring-1 ring-orange-200',
    border: 'border-orange-200',
  },
  medium: {
    badge: 'bg-amber-100 text-amber-700 ring-1 ring-amber-200',
    border: 'border-amber-200',
  },
  low: {
    badge: 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
    border: 'border-emerald-200',
  },
};

export function formatDateTime(value) {
  if (!value) return 'Not available';

  return new Date(value).toLocaleString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

export function formatRelativeTime(value) {
  if (!value) return 'Unknown';

  const now = Date.now();
  const target = new Date(value).getTime();
  const diffMinutes = Math.round((target - now) / 60000);
  const absMinutes = Math.abs(diffMinutes);

  if (absMinutes < 1) return 'Just now';
  if (absMinutes < 60) return diffMinutes >= 0 ? `in ${absMinutes}m` : `${absMinutes}m ago`;

  const absHours = Math.round(absMinutes / 60);
  if (absHours < 24) return diffMinutes >= 0 ? `in ${absHours}h` : `${absHours}h ago`;

  const absDays = Math.round(absHours / 24);
  return diffMinutes >= 0 ? `in ${absDays}d` : `${absDays}d ago`;
}

export function normalizeRequest(request) {
  return {
    ...request,
    units_required: Number(request.units_required ?? request.quantity ?? 0),
    matched_donors_count: Number(request.matched_donors_count ?? 0),
    notifications_sent: Number(request.notifications_sent ?? 0),
    responses_received: Number(request.responses_received ?? 0),
    accepted_donors: Number(request.accepted_donors ?? 0),
    fulfilled_units: Number(request.fulfilled_units ?? 0),
    distance_limit_km: request.distance_limit_km != null ? Number(request.distance_limit_km) : null,
    urgency_label: (request.urgency_level || 'low').toUpperCase(),
    created_at_label: formatDateTime(request.created_at),
    created_relative: formatRelativeTime(request.created_at),
    expiry_relative: formatRelativeTime(request.expiry_time),
  };
}

export function normalizeMatch(match, request = null) {
  const distanceScore = match.distance_km == null ? 70 : Math.max(12, Math.round(100 - Number(match.distance_km) * 1.6));
  const availabilityScore = match.availability ? 100 : 25;
  const reliabilityScore = Math.round(Number(match.reliability_score ?? 0));
  const compatibilityScore = request && match.blood_type === request.blood_type ? 100 : 82;

  return {
    ...match,
    score: Number(match.score ?? 0),
    distance_km: match.distance_km != null ? Number(match.distance_km) : null,
    reliability_score: reliabilityScore,
    responded_at_label: formatDateTime(match.responded_at),
    responded_at_relative: formatRelativeTime(match.responded_at),
    score_breakdown: [
      { label: 'Distance', value: distanceScore },
      { label: 'Availability', value: availabilityScore },
      { label: 'Response rate', value: reliabilityScore },
      { label: 'Compatibility', value: compatibilityScore },
    ],
  };
}

function extractRows(payload) {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
}

export async function fetchHospitalProfile() {
  const response = await api.get('/hospital/profile');
  return response.data?.data ?? {};
}

export async function fetchHospitalRequests(params = {}) {
  const response = await api.get('/hospital/requests', { params });
  return extractRows(response.data?.data).map(normalizeRequest);
}

export async function fetchMatchedDonors(requestId) {
  if (!requestId) return [];
  const response = await api.get(`/hospital/requests/${requestId}/matched-donors`);
  return response.data?.data?.donors ?? [];
}

export async function fetchHospitalActivityLog(params = {}) {
  const response = await api.get('/hospital/activity-log', { params });
  return extractRows(response.data?.data);
}

export async function fetchHospitalSettingsSnapshot() {
  const response = await api.get('/hospital/settings-snapshot');
  return response.data?.data ?? {};
}

export async function updateHospitalRequest(requestId, payload) {
  const response = await api.patch(`/hospital/requests/${requestId}`, payload);
  return normalizeRequest(response.data?.data ?? {});
}

export async function createHospitalRequest(payload) {
  const response = await api.post('/hospital/requests', payload);
  return response.data ?? {};
}

export function buildHospitalMetrics(profile, requests) {
  const activeRequests = requests.filter((request) => ['pending', 'matching'].includes(request.status));
  const criticalCases = requests.filter((request) => request.urgency_level === 'critical' || request.is_emergency).length;
  const pendingResponses = requests.reduce((sum, request) => sum + Math.max(0, request.notifications_sent - request.responses_received), 0);
  const confirmedDonors = requests.reduce((sum, request) => sum + request.accepted_donors, 0);
  const unitsFulfilled = requests.reduce((sum, request) => sum + request.fulfilled_units, 0);

  return [
    { label: 'Active Requests', value: activeRequests.length, detail: `${profile.dashboard?.matching_requests ?? 0} in active matching`, icon: '🩸' },
    { label: 'Critical Cases', value: criticalCases, detail: 'Requests under highest urgency', icon: '🚨' },
    { label: 'Pending Responses', value: pendingResponses, detail: 'Donor replies still outstanding', icon: '⏳' },
    { label: 'Confirmed Donors', value: confirmedDonors, detail: 'Accepted donor commitments', icon: '✅' },
    { label: 'Units Fulfilled', value: unitsFulfilled, detail: 'Completed blood unit coverage', icon: '🏥' },
  ];
}

export function buildHospitalAlerts(profile, requests) {
  const lowStockAlerts = (profile.low_stock_alerts ?? []).map((alert, index) => ({
    id: `inventory-${index}`,
    tone: 'critical',
    title: `${alert.blood_type} shortage`,
    detail: alert.message || 'Inventory is below the operating threshold.',
    meta: 'Inventory monitoring',
  }));

  const expiringRequests = requests
    .filter((request) => request.expiry_time && ['pending', 'matching'].includes(request.status))
    .sort((left, right) => new Date(left.expiry_time) - new Date(right.expiry_time))
    .slice(0, 4)
    .map((request) => ({
      id: `expiry-${request.id}`,
      tone: request.urgency_level === 'critical' ? 'critical' : 'warning',
      title: `${request.case_id} is expiring soon`,
      detail: `${request.blood_type} for ${request.city} expires ${formatRelativeTime(request.expiry_time)}.`,
      meta: request.expiry_time ? formatDateTime(request.expiry_time) : 'Open request',
    }));

  return [...lowStockAlerts, ...expiringRequests].slice(0, 6);
}

export function humanizeAction(action) {
  return action
    .split(/[._-]/g)
    .filter(Boolean)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
}

export function normalizeActivityLog(log) {
  const details = log.details ?? {};

  return {
    id: log.id,
    actor: log.actor?.name || 'System',
    action: log.action,
    category: details.category || 'operations',
    severity: details.severity || 'info',
    timestamp: log.created_at,
    timestamp_label: formatDateTime(log.created_at),
    title: humanizeAction(log.action),
    detail: details.target_label || details.hospital_name || details.case_id || details.reason || 'Operational event recorded.',
    request_id: details.blood_request_id || details.request_id || details.target_id || null,
  };
}

export function buildRecentActivity(logs, requests) {
  const mappedLogs = logs.map(normalizeActivityLog);
  if (mappedLogs.length > 0) return mappedLogs.slice(0, 8);

  return requests.slice(0, 8).map((request) => ({
    id: `request-${request.id}`,
    actor: 'Hospital staff',
    action: 'blood-request.created',
    category: 'blood_requests',
    severity: request.urgency_level === 'critical' ? 'high' : 'info',
    timestamp: request.created_at,
    timestamp_label: formatDateTime(request.created_at),
    title: `${request.case_id} created`,
    detail: `${request.blood_type} • ${request.units_required} unit${request.units_required === 1 ? '' : 's'} • ${request.status}`,
    request_id: request.id,
  }));
}

export function buildAnalyticsSeries(requests, windowDays = 30) {
  const cutoff = Date.now() - (windowDays * 24 * 60 * 60 * 1000);
  const filtered = requests.filter((request) => new Date(request.created_at).getTime() >= cutoff);
  const trendMap = new Map();

  filtered.forEach((request) => {
    const key = new Date(request.created_at).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
    trendMap.set(key, (trendMap.get(key) ?? 0) + 1);
  });

  return Array.from(trendMap.entries()).map(([label, value]) => ({ label, value }));
}

export function buildStatusBreakdown(requests) {
  const statuses = ['pending', 'matching', 'fulfilled', 'completed', 'cancelled'];
  return statuses.map((status) => ({
    label: status.charAt(0).toUpperCase() + status.slice(1),
    value: requests.filter((request) => request.status === status).length,
  }));
}

export function buildUrgencyBreakdown(requests) {
  const levels = ['critical', 'high', 'medium', 'low'];
  return levels.map((level) => ({
    label: level.charAt(0).toUpperCase() + level.slice(1),
    value: requests.filter((request) => request.urgency_level === level).length,
  }));
}

export function buildNotificationHistory(requests) {
  return requests
    .filter((request) => request.notifications_sent > 0 || request.responses_received > 0)
    .slice(0, 12)
    .map((request) => ({
      id: request.id,
      title: `${request.case_id} donor dispatch`,
      request_id: request.id,
      status: request.responses_received > 0 ? 'Read / Responded' : request.notifications_sent > 0 ? 'Sent' : 'Pending',
      detail: `${request.notifications_sent} notifications sent, ${request.responses_received} responses received.`,
      created_at: request.created_at,
      urgency_level: request.urgency_level,
    }));
}
