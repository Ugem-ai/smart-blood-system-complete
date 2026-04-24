<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Notifications</div>
          <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950">Alert stream for donor action and system reminders</h2>
          <p class="mt-2 text-sm text-gray-600">Requests, reminders, and response updates stay structured so you can act quickly without scanning noisy activity feeds.</p>
        </div>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="loadNotifications">Refresh</button>
          <button v-if="unreadCount" type="button" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700" @click="markAllAsRead">Mark all as read</button>
        </div>
      </div>

      <div class="mt-6 grid grid-cols-1 gap-3 md:grid-cols-3">
        <button v-for="filter in filterOptions" :key="filter.value" type="button" class="rounded-[1.5rem] border px-4 py-3 text-left transition" :class="activeFilter === filter.value ? 'border-red-200 bg-red-50 text-red-700' : 'border-gray-200 bg-gray-50 text-gray-600 hover:bg-white'" @click="activeFilter = filter.value">
          <div class="text-sm font-semibold">{{ filter.label }}</div>
          <div class="mt-1 text-xs">{{ filter.detail }}</div>
        </button>
      </div>
    </section>

    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div v-if="loading" class="space-y-4 animate-pulse">
        <div v-for="index in 5" :key="index" class="h-28 rounded-[1.5rem] bg-gray-100"></div>
      </div>

      <div v-else-if="filteredNotifications.length === 0" class="rounded-[1.75rem] border border-dashed border-gray-200 bg-gray-50 px-6 py-14 text-center">
        <div class="text-lg font-semibold text-gray-900">No notifications in this filter</div>
        <div class="mt-2 text-sm text-gray-500">The donor panel will repopulate this list as new emergencies or reminders are generated.</div>
      </div>

      <div v-else class="space-y-3">
        <article v-for="notification in filteredNotifications" :key="notification.id" class="rounded-[1.5rem] border p-5 shadow-sm transition" :class="notification.is_read ? 'border-gray-200 bg-white' : 'border-red-200 bg-red-50/60'" @click="markAsRead(notification.id)">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="flex min-w-0 gap-4">
              <div class="inline-flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-white text-xl shadow-sm">{{ notification.icon }}</div>
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-sm font-semibold text-gray-900">{{ notification.title }}</span>
                  <span class="inline-flex rounded-full px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" :class="notification.type === 'request' ? urgencyTheme[notification.urgency_level]?.badge : 'bg-gray-100 text-gray-600'">{{ notification.statusLabel }}</span>
                  <span v-if="!notification.is_read" class="inline-flex rounded-full bg-red-600 px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-white">Unread</span>
                </div>
                <p class="mt-2 text-sm leading-6 text-gray-600">{{ notification.message }}</p>
                <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold text-gray-500">
                  <span class="rounded-full bg-white px-3 py-1">{{ notification.timestamp }}</span>
                  <span v-if="notification.detail" class="rounded-full bg-white px-3 py-1">{{ notification.detail }}</span>
                </div>
              </div>
            </div>

            <div class="flex flex-wrap gap-2 lg:justify-end">
              <button v-if="notification.type === 'request' && notification.requestId && notification.canRespond" type="button" class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="respondingId === notification.requestId" @click.stop="handleResponse(notification, 'accept')">
                {{ respondingId === notification.requestId && respondingAction === 'accept' ? 'Sending...' : 'Respond Now' }}
              </button>
              <button v-if="notification.type === 'request' && notification.requestId && notification.canRespond" type="button" class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50" :disabled="respondingId === notification.requestId" @click.stop="handleResponse(notification, 'decline')">
                {{ respondingId === notification.requestId && respondingAction === 'decline' ? 'Declining...' : 'Decline' }}
              </button>
              <button type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click.stop="markAsRead(notification.id)">Mark Read</button>
            </div>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { defaultDonorSettings, fetchDonorDashboard, formatDate, formatDateTime, respondToRequest, urgencyTheme } from '../../lib/donorPanel';
import { showDonorToast } from '../../lib/donorToast';

const loading = ref(true);
const notifications = ref([]);
const activeFilter = ref('all');
const respondingId = ref(null);
const respondingAction = ref(null);
const settings = ref({ ...defaultDonorSettings });

const filterOptions = [
  { value: 'all', label: 'All', detail: 'Every alert, reminder, and response update' },
  { value: 'unread', label: 'Unread', detail: 'Items that still require attention' },
  { value: 'requests', label: 'Requests Only', detail: 'Emergency request alerts ready for action' },
];

const unreadCount = computed(() => notifications.value.filter((notification) => !notification.is_read).length);
const filteredNotifications = computed(() => notifications.value.filter((notification) => {
  if (settings.value.urgentOnly) {
    if (notification.type !== 'request') return false;
    if (notification.urgency_level !== 'critical') return false;
  }

  if (activeFilter.value === 'unread') return !notification.is_read;
  if (activeFilter.value === 'requests') return notification.type === 'request';
  return true;
}));

function buildNotifications(payload) {
  const requestNotifications = payload.requests.map((request) => ({
    id: `request-${request.id}`,
    type: 'request',
    title: `${request.hospital_name} needs ${request.blood_type}`,
    message: `${request.units_required} unit${request.units_required === 1 ? '' : 's'} requested in ${request.city}. ${request.distance_display} from your current donor network.`,
    timestamp: request.posted_time,
    detail: request.urgency_label,
    urgency_level: request.urgency_level,
    icon: '🚨',
    requestId: request.id,
    responseStatus: request.response_status,
    canRespond: request.response_status == null,
    is_read: request.response_status != null,
    statusLabel: request.response_status ? `Response: ${request.response_status}` : 'Emergency request',
  }));

  const reminderNotifications = [
    {
      id: 'reminder-eligibility',
      type: 'reminder',
      title: payload.eligibility.is_eligible ? 'You are currently eligible to donate' : `Not eligible until ${formatDate(payload.eligibility.next_eligible_date)}`,
      message: payload.eligibility.is_eligible
        ? 'Keep your availability current so hospitals can reach you immediately during emergencies.'
        : 'Your recovery interval is active. Use Availability & Schedule to prepare for your next eligible date.',
      timestamp: 'System reminder',
      detail: payload.profile.blood_type ? `${payload.profile.blood_type} donor profile` : '',
      icon: payload.eligibility.is_eligible ? '✅' : '⏳',
      requestId: null,
      urgency_level: 'medium',
      is_read: false,
      statusLabel: 'Reminder',
    },
    {
      id: 'update-impact',
      type: 'update',
      title: 'Impact and reliability updated',
      message: `Your current response rate is ${payload.stats.response_rate || 0}% with ${payload.stats.total_donations || 0} completed donations on record.`,
      timestamp: formatDateTime(new Date()),
      detail: `${payload.profile.reliability_label || 'Donor'} profile`,
      icon: '📈',
      requestId: null,
      urgency_level: 'low',
      is_read: true,
      statusLabel: 'Read',
    },
  ];

  return [...requestNotifications, ...reminderNotifications];
}

function markAsRead(id) {
  notifications.value = notifications.value.map((notification) => notification.id === id ? { ...notification, is_read: true } : notification);
}

function markAllAsRead() {
  notifications.value = notifications.value.map((notification) => ({ ...notification, is_read: true }));
  showDonorToast('All donor notifications marked as read.', 'info');
}

async function loadNotifications() {
  loading.value = true;

  try {
    const payload = await fetchDonorDashboard();
    settings.value = payload.settings;
    activeFilter.value = payload.settings.urgentOnly ? 'requests' : activeFilter.value;
    notifications.value = buildNotifications(payload);
  } finally {
    loading.value = false;
  }
}

async function handleResponse(notification, action) {
  if (!notification.requestId) return;

  respondingId.value = notification.requestId;
  respondingAction.value = action;
  try {
    await respondToRequest(action, notification.requestId);
    notifications.value = notifications.value.map((entry) => entry.id === notification.id
      ? {
        ...entry,
        is_read: true,
        canRespond: false,
        responseStatus: action === 'accept' ? 'accepted' : 'declined',
        statusLabel: `Response: ${action === 'accept' ? 'accepted' : 'declined'}`,
      }
      : entry);
    showDonorToast(action === 'accept'
      ? 'Emergency notification accepted and routed to the hospital.'
      : 'Emergency notification declined.');
  } catch {
    showDonorToast('Failed to respond to the notification.', 'error');
  } finally {
    respondingId.value = null;
    respondingAction.value = null;
  }
}

onMounted(loadNotifications);
</script>
