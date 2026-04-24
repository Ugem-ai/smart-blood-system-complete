<template>
  <div class="space-y-6">
    <!-- Page Heading -->
    <div>
      <h2 class="text-2xl font-bold text-gray-900">Notifications</h2>
      <p class="mt-1 text-sm text-gray-600">Monitor system alerts and important events</p>
    </div>

    <!-- Action Buttons -->
    <div class="flex items-center gap-3">
      <button
        @click="loadNotifications"
        class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition-colors"
      >
        🔄 Refresh
      </button>
      <button
        v-if="notifications.length > 0"
        @click="markAllRead"
        class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition-colors"
      >
        ✅ Mark All as Read
      </button>
    </div>

    <!-- Filter Tabs -->
    <div class="flex items-center gap-2 border-b border-gray-200">
      <button
        @click="filterType = ''"
        :class="filterType === '' ? 'border-b-2 border-red-600 text-red-600' : 'text-gray-600 hover:text-gray-900'"
        class="px-4 py-2 font-medium transition-colors"
      >
        All ({{ notifications.length }})
      </button>
      <button
        @click="filterType = 'donor-response'"
        :class="filterType === 'donor-response' ? 'border-b-2 border-red-600 text-red-600' : 'text-gray-600 hover:text-gray-900'"
        class="px-4 py-2 font-medium transition-colors"
      >
        Donor Responses ({{ donorResponseCount }})
      </button>
      <button
        @click="filterType = 'system-alert'"
        :class="filterType === 'system-alert' ? 'border-b-2 border-red-600 text-red-600' : 'text-gray-600 hover:text-gray-900'"
        class="px-4 py-2 font-medium transition-colors"
      >
        System Alerts ({{ systemAlertCount }})
      </button>
      <button
        @click="filterType = 'confirmation'"
        :class="filterType === 'confirmation' ? 'border-b-2 border-red-600 text-red-600' : 'text-gray-600 hover:text-gray-900'"
        class="px-4 py-2 font-medium transition-colors"
      >
        Confirmations ({{ confirmationCount }})
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <p class="text-gray-500">Loading notifications...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredNotifications.length === 0" class="rounded-lg border border-gray-200 bg-white p-12 text-center">
      <p class="text-lg font-medium text-gray-900">No notifications</p>
      <p class="text-sm text-gray-600">All caught up!</p>
    </div>

    <!-- Notifications List -->
    <div v-else class="space-y-3">
      <div
        v-for="notification in filteredNotifications"
        :key="notification.id"
        class="rounded-lg border p-4 transition-all hover:shadow-md cursor-pointer"
        :class="notification.is_read ? 'border-gray-200 bg-white' : 'border-red-200 bg-red-50'"
        @click="markAsRead(notification)"
      >
        <div class="flex items-start gap-4">
          <!-- Icon -->
          <span class="text-2xl flex-shrink-0">{{ getNotificationIcon(notification.type) }}</span>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between">
              <div>
                <h3 class="font-semibold text-gray-900">{{ notification.title }}</h3>
                <p class="text-sm text-gray-600 mt-1">{{ notification.message }}</p>

                <!-- Details -->
                <div v-if="notification.details" class="mt-3 text-sm text-gray-700 space-y-1">
                  <div v-for="(value, key) in notification.details" :key="key">
                    <span class="font-medium">{{ formatLabel(key) }}:</span> {{ value }}
                  </div>
                </div>
              </div>

              <!-- Status Badge -->
              <span v-if="!notification.is_read" class="flex-shrink-0 h-3 w-3 rounded-full bg-red-600 mt-1" />
            </div>

            <!-- Timestamp -->
            <div class="mt-3 flex items-center justify-between">
              <p class="text-xs text-gray-500">{{ formatTime(notification.created_at) }}</p>

              <!-- Action Buttons -->
              <div v-if="notification.type === 'donor-response'" class="flex gap-2">
                <button
                  @click.stop="handleDonorAction(notification, 'accept')"
                  class="px-3 py-1 rounded text-xs font-semibold bg-green-100 text-green-800 hover:bg-green-200 transition-colors"
                >
                  Accept
                </button>
                <button
                  @click.stop="handleDonorAction(notification, 'decline')"
                  class="px-3 py-1 rounded text-xs font-semibold bg-red-100 text-red-800 hover:bg-red-200 transition-colors"
                >
                  Decline
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../lib/api';
import { fetchHospitalRequests } from '../../lib/hospitalPanel';

const notifications = ref([]);
const loading = ref(true);
const filterType = ref('');

const donorResponseCount = computed(() => notifications.value.filter(n => n.type === 'donor-response').length);
const systemAlertCount = computed(() => notifications.value.filter(n => n.type === 'system-alert').length);
const confirmationCount = computed(() => notifications.value.filter(n => n.type === 'confirmation').length);

const filteredNotifications = computed(() => {
  if (!filterType.value) return notifications.value;
  return notifications.value.filter(n => n.type === filterType.value);
});

const getNotificationIcon = (type) => {
  const icons = {
    'donor-response': '👤',
    'system-alert': '🚨',
    confirmation: '✅',
    'request-matched': '🎯',
    'urgent-alert': '⚠️',
  };
  return icons[type] || '📬';
};

const formatLabel = (key) => {
  const labels = {
    donor_name: 'Donor',
    blood_type: 'Blood Type',
    units: 'Units',
    distance: 'Distance',
    status: 'Status',
  };
  return labels[key] || key.replace(/_/g, ' ');
};

const formatTime = (timestamp) => {
  if (!timestamp) return 'Unknown';
  const date = new Date(timestamp);
  const now = new Date();
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffMins < 1440) return `${Math.floor(diffMins / 60)}h ago`;
  return date.toLocaleDateString();
};

const markAsRead = async (notification) => {
  if (notification.is_read) return;

  const index = notifications.value.findIndex(n => n.id === notification.id);
  if (index !== -1) {
    notifications.value[index].is_read = true;
  }
};

const markAllRead = async () => {
  notifications.value.forEach(n => {
    n.is_read = true;
  });
};

const handleDonorAction = async (notification, action) => {
  if (notification.type !== 'donor-response') {
    return;
  }

  try {
    await api.post('/hospital/confirm-donation', {
      blood_request_id: Number(notification.blood_request_id),
      donor_id: Number(notification.donor_id),
    });

    if (action === 'accept') {
      notification.details.status = 'accepted';
      notification.is_read = true;
    }
  } catch (err) {
    console.error('Failed to handle donor action:', err);
    alert('Failed to process action. Please try again.');
  }
};

const loadNotifications = async () => {
  loading.value = true;

  try {
    const items = await fetchHospitalRequests();

    notifications.value = items.map((request) => ({
      id: `request-${request.id}`,
      blood_request_id: request.id,
      donor_id: null,
      type: 'system-alert',
      title: `Request ${request.blood_type} is ${request.status}`,
      message: `${request.units_required || request.quantity || 0} unit(s) requested in ${request.city || 'Unknown city'}.`,
      details: {
        blood_type: request.blood_type,
        units: request.units_required || request.quantity || 0,
        status: request.status,
      },
      created_at: request.created_at,
      is_read: false,
    }));

    // Sort by most recent first
    notifications.value.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
  } catch (err) {
    console.error('Failed to load notifications:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadNotifications();

  // Auto-refresh every 10 seconds
  const refreshInterval = setInterval(() => {
    loadNotifications();
  }, 10000);

  // Cleanup on unmount
  return () => clearInterval(refreshInterval);
});
</script>
