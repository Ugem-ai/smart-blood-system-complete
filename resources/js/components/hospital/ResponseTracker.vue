<template>
  <div class="space-y-6">
    <!-- Page Heading -->
    <div>
      <h2 class="text-2xl font-bold text-gray-900">Response Tracking</h2>
      <p class="mt-1 text-sm text-gray-600">Monitor donor responses to your blood requests in real-time</p>
    </div>

    <!-- Request Selector -->
    <div class="rounded-lg border border-gray-200 bg-white p-4 flex items-center gap-4">
      <label class="text-sm font-medium text-gray-700">Select Request:</label>
      <select
        v-model="selectedRequestId"
        @change="handleRequestSelection(selectedRequestId)"
        class="flex-1 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
      >
        <option value="">-- Choose a request --</option>
        <option v-for="req in activeRequests" :key="req.id" :value="req.id">
          {{ req.blood_type }} · {{ req.units_required || req.quantity || 0 }} units ({{ req.status }})
        </option>
      </select>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <p class="text-gray-500">Loading responses...</p>
    </div>

    <!-- No Selection -->
    <div v-else-if="!selectedRequestId" class="rounded-lg border border-gray-200 bg-white p-12 text-center">
      <p class="text-gray-600">Select a request above to view donor responses</p>
    </div>

    <!-- No Responses -->
    <div v-else-if="responses.length === 0" class="rounded-lg border border-gray-200 bg-white p-12 text-center">
      <p class="text-lg font-medium text-gray-900">No responses yet</p>
      <p class="text-sm text-gray-600">Donors will appear here as they respond to your request</p>
    </div>

    <!-- Response Stats -->
    <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-3">
      <div class="rounded-lg border border-green-200 bg-green-50 p-4">
        <p class="text-sm font-medium text-green-700">Accepted</p>
        <p class="mt-1 text-2xl font-bold text-green-600">{{ acceptedCount }}</p>
      </div>
      <div class="rounded-lg border border-red-200 bg-red-50 p-4">
        <p class="text-sm font-medium text-red-700">Declined</p>
        <p class="mt-1 text-2xl font-bold text-red-600">{{ declinedCount }}</p>
      </div>
      <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4">
        <p class="text-sm font-medium text-yellow-700">Awaiting Response</p>
        <p class="mt-1 text-2xl font-bold text-yellow-600">{{ pendingCount }}</p>
      </div>
    </div>

    <!-- Response Timeline -->
    <div v-if="responses.length > 0" class="space-y-3">
      <div
        v-for="response in responses"
        :key="response.id"
        class="rounded-lg border p-4 transition-all hover:shadow-md"
        :class="getResponseBgColor(response.response_status)"
      >
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
          <!-- Donor -->
          <div>
            <p class="text-xs font-medium text-gray-600">Donor</p>
            <p class="mt-1 font-semibold text-gray-900">{{ response.name || 'Anonymous' }}</p>
            <p class="text-xs text-gray-500">{{ response.contact_number || response.email || 'No contact' }}</p>
          </div>

          <!-- Response Status -->
          <div>
            <p class="text-xs font-medium text-gray-600">Response</p>
            <p class="mt-1">
              <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold" :class="getStatusBadge(response.response_status)">
                {{ response.response_status }}
              </span>
            </p>
          </div>

          <!-- Timestamp -->
          <div>
            <p class="text-xs font-medium text-gray-600">Time</p>
            <p class="mt-1 text-sm text-gray-700">{{ formatTime(response.responded_at) }}</p>
            <p class="text-xs text-gray-500">{{ formatRelativeTime(response.responded_at) }}</p>
          </div>

          <!-- Blood Type -->
          <div>
            <p class="text-xs font-medium text-gray-600">Blood Type</p>
            <p class="mt-1 font-bold text-gray-900">{{ response.blood_type || selectedRequestBloodType || 'N/A' }}</p>
          </div>

          <!-- Actions -->
          <div class="flex items-end gap-2 sm:col-span-2 lg:col-span-1">
            <button
              v-if="response.response_status === 'accepted'"
              @click="confirmDonor(response)"
              class="flex-1 inline-flex items-center justify-center px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs font-semibold hover:bg-green-700 transition-colors"
            >
              ✅ Confirm
            </button>
            <button
              v-else
              class="flex-1 inline-flex items-center justify-center px-3 py-1.5 rounded-lg bg-gray-200 text-gray-600 text-xs font-semibold cursor-not-allowed"
            >
              {{ response.response_status === 'declined' ? '❌ Declined' : '⏳ Pending' }}
            </button>
          </div>
        </div>

        <!-- Additional Info -->
        <div v-if="response.notes" class="mt-3 border-t border-gray-300 pt-3">
          <p class="text-xs font-medium text-gray-600">Donor Notes</p>
          <p class="mt-1 text-sm text-gray-700">{{ response.notes }}</p>
        </div>
      </div>
    </div>

    <!-- Empty Response State -->
    <div v-if="selectedRequestId && responses.length === 0" class="rounded-lg border border-blue-200 bg-blue-50 p-6 mt-4">
      <p class="text-sm text-blue-800">ℹ️ Once donors respond to your request, their responses will appear here with real-time updates.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import api from '../../lib/api';
import { fetchHospitalRequests, fetchMatchedDonors } from '../../lib/hospitalPanel';

const activeRequests = ref([]);
const selectedRequestId = ref('');
const responses = ref([]);
const loading = ref(false);
let refreshInterval = null;

const selectedRequestBloodType = computed(() => {
  const targetId = Number(selectedRequestId.value);
  return activeRequests.value.find((req) => Number(req.id) === targetId)?.blood_type || null;
});

const acceptedCount = computed(() => responses.value.filter(r => r.response_status === 'accepted').length);
const declinedCount = computed(() => responses.value.filter(r => r.response_status === 'declined').length);
const pendingCount = computed(() => responses.value.filter(r => r.response_status === 'pending').length);

const formatTime = (timestamp) => {
  if (!timestamp) return 'N/A';
  return new Date(timestamp).toLocaleTimeString();
};

const formatRelativeTime = (timestamp) => {
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

const getResponseBgColor = (status) => {
  switch (status) {
    case 'accepted':
      return 'border-green-200 bg-green-50';
    case 'declined':
      return 'border-red-200 bg-red-50';
    default:
      return 'border-yellow-200 bg-yellow-50';
  }
};

const getStatusBadge = (status) => {
  switch (status) {
    case 'accepted':
      return 'bg-green-100 text-green-800';
    case 'declined':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-yellow-100 text-yellow-800';
  }
};

const loadActiveRequests = async () => {
  try {
    const requests = await fetchHospitalRequests();
    activeRequests.value = requests.filter((req) => ['pending', 'matching'].includes(req.status));
  } catch (err) {
    console.error('Failed to load requests:', err);
  }
};

const loadResponses = async () => {
  if (!selectedRequestId.value) return;

  loading.value = true;

  try {
    responses.value = await fetchMatchedDonors(selectedRequestId.value);
    // Sort by most recent first
    responses.value.sort((a, b) => Number(a.rank || 9999) - Number(b.rank || 9999));
  } catch (err) {
    console.error('Failed to load responses:', err);
  } finally {
    loading.value = false;
  }
};

const confirmDonor = async (response) => {
  try {
    await api.post('/hospital/confirm-donation', {
      blood_request_id: Number(selectedRequestId.value),
      donor_id: Number(response.donor_id),
    });

    // Update the response status
    const index = responses.value.findIndex(r => Number(r.donor_id) === Number(response.donor_id));
    if (index !== -1) {
      responses.value[index].response_status = 'accepted';
    }
  } catch (err) {
    console.error('Failed to confirm donor:', err);
    alert('Failed to confirm donor. Please try again.');
  }
};

onMounted(() => {
  loadActiveRequests();

  // Watch selectedRequestId to set up auto-refresh (prevents memory leaks)
  watch(selectedRequestId, async (newValue) => {
    // Clear existing interval
    if (refreshInterval) {
      clearInterval(refreshInterval);
      refreshInterval = null;
    }

    // Load responses and set up new interval
    if (newValue) {
      await loadResponses();
      refreshInterval = setInterval(() => {
        loadResponses();
      }, 5000);
    }
  }, { immediate: false });
});

onBeforeUnmount(() => {
  if (refreshInterval) {
    clearInterval(refreshInterval);
    refreshInterval = null;
  }
});

const handleRequestSelection = (newValue) => {
  selectedRequestId.value = newValue;
};
</script>
