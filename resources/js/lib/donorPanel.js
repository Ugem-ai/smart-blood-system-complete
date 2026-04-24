import api from './api';

export const defaultDonorSettings = {
  smsAlerts: true,
  emailAlerts: true,
  urgentOnly: false,
  availabilityReminders: true,
  defaultRequestFilter: 'all',
  maxRadius: '25',
  showMissionSummary: true,
};

export const urgencyTheme = {
  low: {
    badge: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
    accent: 'text-emerald-600',
    border: 'border-emerald-200',
    surface: 'from-emerald-50 to-white',
    label: 'Low',
  },
  medium: {
    badge: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
    accent: 'text-amber-600',
    border: 'border-amber-200',
    surface: 'from-amber-50 to-white',
    label: 'Medium',
  },
  high: {
    badge: 'bg-orange-50 text-orange-700 ring-1 ring-orange-200',
    accent: 'text-orange-600',
    border: 'border-orange-200',
    surface: 'from-orange-50 to-white',
    label: 'High',
  },
  critical: {
    badge: 'bg-red-50 text-red-700 ring-1 ring-red-200',
    accent: 'text-red-700',
    border: 'border-red-200',
    surface: 'from-red-50 to-white',
    label: 'Critical',
  },
};

export async function fetchDonorDashboard() {
  const response = await api.get('/donor/dashboard');
  const data = response.data?.data || {};
  const requests = (data.incoming_requests || []).map(normalizeRequest);
  const history = (data.donation_history || []).map(normalizeDonation);
  const responses = (data.response_history || []).map(normalizeResponseHistory);

  return {
    profile: data.profile || {},
    eligibility: data.eligibility || {},
    stats: data.stats || {},
    requests,
    history,
    responses,
    settings: normalizeDonorSettings(data.settings),
    unreadNotifications: requests.filter((request) => request.response_status == null).length,
  };
}

export async function fetchDonorProfile() {
  const response = await api.get('/donor/profile');
  return normalizeDonorProfile(response.data?.data || {});
}

export async function updateDonorProfile(profile) {
  const response = await api.put('/donor/update', profile);
  return normalizeDonorProfile(response.data?.data || {});
}

export async function fetchDonorSettings() {
  const response = await api.get('/donor/settings');
  return normalizeDonorSettings(response.data?.data);
}

export async function updateDonorSettings(settings) {
  const response = await api.patch('/donor/settings', settings);
  return normalizeDonorSettings(response.data?.data);
}

export function normalizeDonorSettings(settings) {
  const source = settings && typeof settings === 'object' ? settings : {};

  return {
    smsAlerts: source.smsAlerts ?? defaultDonorSettings.smsAlerts,
    emailAlerts: source.emailAlerts ?? defaultDonorSettings.emailAlerts,
    urgentOnly: source.urgentOnly ?? defaultDonorSettings.urgentOnly,
    availabilityReminders: source.availabilityReminders ?? defaultDonorSettings.availabilityReminders,
    defaultRequestFilter: ['all', 'critical', 'unresponded'].includes(source.defaultRequestFilter)
      ? source.defaultRequestFilter
      : defaultDonorSettings.defaultRequestFilter,
    maxRadius: ['10', '25', '50', 'all'].includes(String(source.maxRadius))
      ? String(source.maxRadius)
      : defaultDonorSettings.maxRadius,
    showMissionSummary: source.showMissionSummary ?? defaultDonorSettings.showMissionSummary,
  };
}

export function normalizeDonorProfile(data) {
  return {
    ...data,
    email: data.email || '',
    phone: data.phone || data.contact_number || '',
    contact_number: data.contact_number || data.phone || '',
    response_history: (data.response_history || []).map(normalizeResponseHistory),
  };
}

export async function updateDonorAvailability(availability) {
  const response = await api.post('/donor/status', { availability });
  return response.data?.data || {};
}

export async function respondToRequest(action, bloodRequestId) {
  const endpoint = action === 'accept' ? '/donor/accept' : '/donor/decline';
  const response = await api.post(endpoint, { blood_request_id: bloodRequestId });
  return response.data?.data || {};
}

export function normalizeRequest(item) {
  const distanceKm = item.distance_km != null ? Number(item.distance_km) : null;
  const urgency = String(item.urgency_level || 'medium').toLowerCase();

  return {
    id: item.id,
    hospital_name: item.hospital_name || item.hospital?.name || 'Unknown Hospital',
    hospital_address: item.hospital?.address || item.city || 'Location unavailable',
    hospital_latitude: item.hospital?.latitude ?? null,
    hospital_longitude: item.hospital?.longitude ?? null,
    blood_type: item.blood_type,
    units_required: item.units_required,
    urgency_level: urgency,
    urgency_label: urgencyTheme[urgency]?.label || 'Medium',
    city: item.city,
    created_at: item.created_at,
    required_on: item.required_on,
    status: item.status,
    response_status: item.response,
    responded_at: item.responded_at,
    distance_km: distanceKm,
    distance_display: distanceKm != null ? `${distanceKm.toFixed(1)} km` : (item.city ? `${item.city} zone` : 'Local area'),
    posted_relative: formatRelativeTime(item.created_at),
    posted_time: formatDateTime(item.created_at),
    directions_url: buildDirectionsUrl(item),
    compatibility_label: item.blood_type ? `${item.blood_type} compatible` : 'Compatibility check required',
  };
}

export function normalizeDonation(item) {
  return {
    id: item.id,
    donation_date: item.donation_date || item.donated_at,
    hospital_name: item.hospital_name || 'Red Cross Partner Hospital',
    blood_type: item.blood_type || 'Unknown',
    units: item.units || 0,
    status: item.status || 'Completed',
    location: item.location || 'Recorded donation center',
    certificate_label: `Certificate ${formatDate(item.donation_date || item.donated_at)}`,
  };
}

export function normalizeResponseHistory(item) {
  const response = String(item.response || 'pending').toLowerCase();
  const urgency = String(item.urgency_level || 'medium').toLowerCase();

  return {
    id: item.id,
    response,
    response_label: response === 'accepted' ? 'Accepted' : response === 'declined' ? 'Declined' : 'Pending',
    responded_at: item.responded_at,
    responded_at_label: formatDateTime(item.responded_at),
    responded_relative: formatRelativeTime(item.responded_at),
    blood_request_id: item.blood_request_id,
    hospital_name: item.hospital_name || 'Partner hospital',
    hospital_address: item.hospital_address || item.city || 'Location unavailable',
    blood_type: item.blood_type || 'Unknown',
    urgency_level: urgency,
    urgency_label: urgencyTheme[urgency]?.label || 'Medium',
    city: item.city || 'Unknown city',
    request_status: item.request_status || 'pending',
  };
}

export function formatDate(value) {
  if (!value) return 'Not available';

  return new Date(value).toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

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

  const timestamp = new Date(value).getTime();
  if (Number.isNaN(timestamp)) return 'Unknown';

  const diffMinutes = Math.round((Date.now() - timestamp) / 60000);
  if (diffMinutes < 1) return 'Just now';
  if (diffMinutes < 60) return `${diffMinutes}m ago`;
  if (diffMinutes < 1440) return `${Math.floor(diffMinutes / 60)}h ago`;
  return `${Math.floor(diffMinutes / 1440)}d ago`;
}

export function statusBanner(eligibility) {
  if (eligibility?.is_eligible) {
    return {
      tone: 'green',
      title: 'You are eligible to donate',
      detail: 'Your donation interval is clear and you can respond to emergency requests immediately.',
    };
  }

  return {
    tone: 'amber',
    title: `Not eligible until ${formatDate(eligibility?.next_eligible_date)}`,
    detail: 'Your recovery interval is still active. You can keep notifications on and plan your next response window.',
  };
}

export function computeDonationStreak(history) {
  if (!history.length) return 0;

  const sorted = [...history]
    .filter((entry) => entry.donation_date)
    .sort((left, right) => new Date(right.donation_date) - new Date(left.donation_date));

  let streak = 0;
  let previousYear = null;

  for (const donation of sorted) {
    const year = new Date(donation.donation_date).getFullYear();
    if (previousYear === null || previousYear === year + 1 || previousYear === year) {
      streak += 1;
      previousYear = year;
      continue;
    }
    break;
  }

  return streak;
}

export function buildNearbyCenters(requests, history, profile) {
  const centerMap = new Map();
  const pushCenter = (entry) => {
    if (!entry?.hospital_name) return;

    const key = `${entry.hospital_name}-${entry.hospital_address || entry.location || ''}`;
    if (!centerMap.has(key)) {
      centerMap.set(key, {
        name: entry.hospital_name,
        address: entry.hospital_address || entry.location || profile?.city || 'Location unavailable',
        distance_display: entry.distance_display || (profile?.city ? `${profile.city} zone` : 'Local network'),
        directions_url: entry.directions_url || buildDirectionsUrl({
          hospital_name: entry.hospital_name,
          city: profile?.city,
          hospital: {
            latitude: entry.hospital_latitude,
            longitude: entry.hospital_longitude,
            address: entry.hospital_address || entry.location,
          },
        }),
      });
    }
  };

  requests.forEach(pushCenter);
  history.forEach(pushCenter);

  return [...centerMap.values()].slice(0, 6);
}

function buildDirectionsUrl(item) {
  const latitude = item.hospital?.latitude ?? item.hospital_latitude;
  const longitude = item.hospital?.longitude ?? item.hospital_longitude;

  if (latitude != null && longitude != null) {
    return `https://www.google.com/maps/search/?api=1&query=${latitude},${longitude}`;
  }

  const query = encodeURIComponent([
    item.hospital_name || item.hospital?.name,
    item.hospital?.address || item.hospital_address,
    item.city,
  ].filter(Boolean).join(', '));

  return `https://www.google.com/maps/search/?api=1&query=${query}`;
}