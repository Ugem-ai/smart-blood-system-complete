<template>
  <div class="space-y-6">
    <!-- Page Heading -->
    <div>
      <h2 class="text-2xl font-bold text-gray-900 sm:text-3xl">Active Requests</h2>
      <p class="mt-1 text-sm text-gray-600">Monitor all blood requests and their matching status</p>
    </div>

    <!-- Filters -->
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <label class="text-sm font-medium text-gray-700">Filter by Urgency:</label>
        <select
          v-model="filterUrgency"
          class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
        >
          <option value="">All Urgencies</option>
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
          <option value="critical">Critical</option>
        </select>
      </div>

      <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
        <label class="text-sm font-medium text-gray-700">Filter by Status:</label>
        <select
          v-model="filterStatus"
          class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
        >
          <option value="">All Statuses</option>
          <option value="pending">Pending</option>
          <option value="matching">Matching</option>
          <option value="completed">Completed</option>
          <option value="fulfilled">Fulfilled</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <button
        @click="loadRequests"
        class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-gray-700 font-medium transition-colors hover:bg-gray-50 lg:ml-auto"
      >
        🔄 Refresh
      </button>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="text-center py-12">
      <p class="text-gray-500">Loading requests...</p>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredRequests.length === 0" class="rounded-lg border border-gray-200 bg-white p-12 text-center">
      <p class="text-lg font-medium text-gray-900">No active requests</p>
      <p class="text-sm text-gray-600">Create a new blood request to get started</p>
    </div>

    <!-- Requests Table -->
    <div v-else class="space-y-4">
      <div
        v-for="request in filteredRequests"
        :key="request.id"
        class="rounded-lg border p-4 transition-all hover:shadow-md"
        :class="getRequestBgColor(request.urgency_level)"
      >
        <!-- Emergency banner -->
        <div v-if="request.is_emergency" class="mb-3 flex items-center gap-2 rounded-md bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-800">
          🚨 EMERGENCY REQUEST
          <span v-if="request.case_id" class="ml-auto font-mono text-gray-600">{{ request.case_id }}</span>
        </div>
        <div v-else-if="request.case_id" class="mb-2 text-right font-mono text-xs text-gray-400">{{ request.case_id }}</div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-6">
          <!-- Blood Type -->
          <div>
            <p class="text-xs font-medium text-gray-600">Blood Type</p>
            <p class="mt-1 text-lg font-bold text-gray-900">{{ request.blood_type }}</p>
            <p v-if="request.component" class="text-xs text-gray-500">{{ request.component }}</p>
          </div>

          <!-- Units -->
          <div>
            <p class="text-xs font-medium text-gray-600">Units Needed</p>
            <p class="mt-1 text-gray-900">{{ request.units_required || request.quantity || 0 }} units</p>
          </div>

          <!-- Urgency -->
          <div>
            <p class="text-xs font-medium text-gray-600">Urgency</p>
            <p class="mt-1">
              <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold" :class="getUrgencyBadge(request.urgency_level)">
                {{ request.urgency_level }}
              </span>
            </p>
          </div>

          <!-- Status -->
          <div>
            <p class="text-xs font-medium text-gray-600">Status</p>
            <p class="mt-1">
              <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold" :class="getStatusBadge(request.status)">
                {{ request.status }}
              </span>
            </p>
          </div>

          <!-- Created Date -->
          <div>
            <p class="text-xs font-medium text-gray-600">Created</p>
            <p class="mt-1 text-sm text-gray-700">{{ formatDate(request.created_at) }}</p>
          </div>

          <!-- Actions -->
          <div class="flex items-end gap-2 sm:col-span-2 xl:col-span-1">
            <button
              @click="viewDetails(request)"
              class="inline-flex w-full items-center justify-center rounded-lg bg-red-600 px-3 py-2 text-xs font-semibold text-white transition-colors hover:bg-red-700"
            >
              👁️ View
            </button>
          </div>
        </div>

        <!-- Tracking Counters -->
        <div class="mt-3 grid grid-cols-2 rounded-md border border-gray-200 bg-gray-50 text-center text-xs sm:grid-cols-3 lg:grid-cols-5 lg:divide-x lg:divide-gray-200">
          <div class="py-2 px-1">
            <p class="font-semibold text-gray-700">{{ request.matched_donors_count ?? 0 }}</p>
            <p class="text-gray-500">Matched</p>
          </div>
          <div class="py-2 px-1">
            <p class="font-semibold text-gray-700">{{ request.notifications_sent ?? 0 }}</p>
            <p class="text-gray-500">Notified</p>
          </div>
          <div class="py-2 px-1">
            <p class="font-semibold text-gray-700">{{ request.responses_received ?? 0 }}</p>
            <p class="text-gray-500">Responded</p>
          </div>
          <div class="py-2 px-1">
            <p class="font-semibold text-green-700">{{ request.accepted_donors ?? 0 }}</p>
            <p class="text-gray-500">Accepted</p>
          </div>
          <div class="py-2 px-1">
            <p class="font-semibold text-blue-700">{{ request.fulfilled_units ?? 0 }}</p>
            <p class="text-gray-500">Fulfilled</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Details Modal -->
    <div v-if="selectedRequest" class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50 p-4">
      <div class="max-h-[90vh] w-full max-w-2xl space-y-4 overflow-y-auto rounded-lg bg-white p-6">
        <div class="flex items-center justify-between">
          <h3 class="text-lg font-semibold text-gray-900">Request Details</h3>
          <button @click="selectedRequest = null" class="text-gray-500 hover:text-gray-700">✕</button>
        </div>

        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
          <div><dt class="font-medium text-gray-700">Case ID:</dt><dd class="font-mono text-gray-900">{{ selectedRequest.case_id || 'N/A' }}</dd></div>
          <div><dt class="font-medium text-gray-700">Emergency:</dt><dd class="text-gray-900">{{ selectedRequest.is_emergency ? '🚨 Yes' : 'No' }}</dd></div>
          <div><dt class="font-medium text-gray-700">Blood Type:</dt><dd class="text-gray-900">{{ selectedRequest.blood_type }}</dd></div>
          <div><dt class="font-medium text-gray-700">Component:</dt><dd class="text-gray-900">{{ selectedRequest.component || 'Whole Blood' }}</dd></div>
          <div><dt class="font-medium text-gray-700">Units Needed:</dt><dd class="text-gray-900">{{ selectedRequest.units_required || selectedRequest.quantity || 0 }}</dd></div>
          <div><dt class="font-medium text-gray-700">Urgency:</dt><dd class="text-gray-900">{{ selectedRequest.urgency_level }}</dd></div>
          <div><dt class="font-medium text-gray-700">Status:</dt><dd class="text-gray-900">{{ selectedRequest.status }}</dd></div>
          <div><dt class="font-medium text-gray-700">Location:</dt><dd class="text-gray-900">{{ selectedRequest.city }}{{ selectedRequest.province ? ', ' + selectedRequest.province : '' }}</dd></div>
          <div><dt class="font-medium text-gray-700">Required Date:</dt><dd class="text-gray-900">{{ formatDate(selectedRequest.required_on) }}</dd></div>
          <div><dt class="font-medium text-gray-700">Expiry Time:</dt><dd class="text-gray-900">{{ formatDate(selectedRequest.expiry_time) }}</dd></div>
          <div><dt class="font-medium text-gray-700">Contact Person:</dt><dd class="text-gray-900">{{ selectedRequest.contact_person || 'Default' }}</dd></div>
          <div><dt class="font-medium text-gray-700">Contact Number:</dt><dd class="text-gray-900">{{ selectedRequest.contact_number || 'Default' }}</dd></div>
          <div class="col-span-2"><dt class="font-medium text-gray-700">Reason:</dt><dd class="text-gray-900">{{ selectedRequest.reason || 'Not specified' }}</dd></div>
          <!-- Tracking counters -->
          <div class="col-span-2">
            <dt class="font-medium text-gray-700 mb-2">Matching Progress:</dt>
            <dd>
              <div class="grid grid-cols-2 rounded-md border border-gray-200 bg-gray-50 text-center text-xs sm:grid-cols-3 lg:grid-cols-5 lg:divide-x lg:divide-gray-200">
                <div class="py-2"><p class="font-semibold text-gray-700">{{ selectedRequest.matched_donors_count ?? 0 }}</p><p class="text-gray-500">Matched</p></div>
                <div class="py-2"><p class="font-semibold text-gray-700">{{ selectedRequest.notifications_sent ?? 0 }}</p><p class="text-gray-500">Notified</p></div>
                <div class="py-2"><p class="font-semibold text-gray-700">{{ selectedRequest.responses_received ?? 0 }}</p><p class="text-gray-500">Responded</p></div>
                <div class="py-2"><p class="font-semibold text-green-700">{{ selectedRequest.accepted_donors ?? 0 }}</p><p class="text-gray-500">Accepted</p></div>
                <div class="py-2"><p class="font-semibold text-blue-700">{{ selectedRequest.fulfilled_units ?? 0 }}</p><p class="text-gray-500">Fulfilled</p></div>
              </div>
            </dd>
          </div>
        </dl>

        <div class="flex flex-col gap-3 sm:flex-row">
          <button
            v-if="selectedRequest.status !== 'completed'"
            @click="cancelRequest(selectedRequest)"
            class="flex-1 px-4 py-2 rounded-lg bg-red-600 text-white font-medium hover:bg-red-700 transition-colors"
          >
            ❌ Cancel Request
          </button>
          <button
            @click="selectedRequest = null"
            class="flex-1 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 font-medium hover:bg-gray-50 transition-colors"
          >
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '../../lib/api';
import { fetchHospitalRequests } from '../../lib/hospitalPanel';

const requests = ref([]);
const loading = ref(true);
const filterUrgency = ref('');
const filterStatus = ref('');
const selectedRequest = ref(null);

const cancelRequest = async (request) => {
  if (!window.confirm(`Are you sure you want to cancel this ${request.urgency_level} ${request.blood_type} request?`)) {
    return;
  }

  try {
    await api.patch(`/hospital/requests/${request.id}`, { status: 'cancelled' });
    
    // Update local state
    const index = requests.value.findIndex(r => r.id === request.id);
    if (index !== -1) {
      requests.value[index].status = 'cancelled';
    }
    
    selectedRequest.value = null;
    alert('Request cancelled successfully.');
  } catch (err) {
    console.error('Failed to cancel request:', err);
    alert('Failed to cancel request. Please try again.');
  }
};

const filteredRequests = computed(() => {
  return requests.value.filter(req => {
    if (filterUrgency.value && req.urgency_level !== filterUrgency.value) return false;
    if (filterStatus.value && req.status !== filterStatus.value) return false;
    return true;
  });
});

const formatDate = (date) => {
  if (!date) return 'N/A';
  return new Date(date).toLocaleDateString();
};

const getRequestBgColor = (urgency) => {
  switch (urgency) {
    case 'critical':
      return 'border-red-300 bg-red-50';
    case 'high':
      return 'border-orange-300 bg-orange-50';
    default:
      return 'border-gray-200 bg-white';
  }
};

const getUrgencyBadge = (urgency) => {
  switch (urgency) {
    case 'critical':
      return 'bg-red-100 text-red-800';
    case 'high':
      return 'bg-orange-100 text-orange-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
};

const getStatusBadge = (status) => {
  switch (status) {
    case 'completed':
    case 'fulfilled':
      return 'bg-green-100 text-green-800';
    case 'matching':
      return 'bg-purple-100 text-purple-800';
    case 'cancelled':
      return 'bg-red-100 text-red-800';
    default:
      return 'bg-gray-100 text-gray-800';
  }
};

const viewDetails = (request) => {
  selectedRequest.value = request;
};

const loadRequests = async () => {
  loading.value = true;

  try {
    requests.value = await fetchHospitalRequests();
  } catch (err) {
    console.error('Failed to load requests:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadRequests();
});
</script>
