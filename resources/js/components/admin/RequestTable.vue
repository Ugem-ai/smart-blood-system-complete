<template>
  <section class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
    <div class="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
      <h2 class="text-base font-semibold text-gray-900">Blood Request Management</h2>
      <button type="button" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50" @click="loadRequests">
        Refresh
      </button>
    </div>

    <div v-if="message" class="mb-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700">{{ message }}</div>
    <div v-if="error" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{{ error }}</div>

    <div class="mb-4 grid grid-cols-1 gap-3 md:grid-cols-3">
      <select v-model="filters.urgency" class="rounded-lg border border-gray-200 px-3 py-2 text-sm">
        <option value="">All urgency</option>
        <option value="low">Low</option>
        <option value="medium">Medium</option>
        <option value="high">High</option>
      </select>
      <select v-model="filters.status" class="rounded-lg border border-gray-200 px-3 py-2 text-sm">
        <option value="">All status</option>
        <option value="pending">Pending</option>
        <option value="matched">Matched</option>
        <option value="confirmed">Confirmed</option>
        <option value="completed">Completed</option>
      </select>
      <input v-model.trim="filters.location" type="text" placeholder="Filter by location" class="rounded-lg border border-gray-200 px-3 py-2 text-sm" />
    </div>

    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-left font-semibold text-gray-600">Blood Type</th>
            <th class="px-3 py-2 text-left font-semibold text-gray-600">Hospital</th>
            <th class="px-3 py-2 text-left font-semibold text-gray-600">Units</th>
            <th class="px-3 py-2 text-left font-semibold text-gray-600">Urgency</th>
            <th class="px-3 py-2 text-left font-semibold text-gray-600">Status</th>
            <th class="px-3 py-2 text-left font-semibold text-gray-600">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
          <tr v-if="loading">
            <td colspan="6" class="px-3 py-8 text-center text-gray-400">Loading requests...</td>
          </tr>
          <tr v-for="request in filteredRequests" :key="request.id">
            <td class="px-3 py-2 font-semibold text-gray-900">{{ request.blood_type }}</td>
            <td class="px-3 py-2 text-gray-700">{{ request.hospital_name }}</td>
            <td class="px-3 py-2 text-gray-700">{{ request.units_needed }}</td>
            <td class="px-3 py-2">
              <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="urgencyClass(request.urgency_level)">{{ request.urgency_level }}</span>
            </td>
            <td class="px-3 py-2 text-gray-700">{{ request.status }}</td>
            <td class="px-3 py-2">
              <div class="flex flex-wrap gap-2">
                <button type="button" class="rounded border border-gray-200 px-2 py-1 text-xs hover:bg-gray-50" @click="viewRequest(request)">View</button>
                <button type="button" class="rounded border border-amber-300 px-2 py-1 text-xs text-amber-700 hover:bg-amber-50" @click="overrideStatus(request)">Override</button>
                <button type="button" class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50" @click="escalate(request)">Escalate</button>
              </div>
            </td>
          </tr>
          <tr v-if="!loading && filteredRequests.length === 0">
            <td colspan="6" class="px-3 py-8 text-center text-gray-400">No requests found.</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="selectedRequest" class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-3">
      <p class="text-sm font-semibold text-gray-800">Request Details</p>
      <p class="mt-1 text-sm text-gray-600">{{ selectedRequest.hospital_name }} requested {{ selectedRequest.units_needed }} units of {{ selectedRequest.blood_type }} in {{ selectedRequest.location || selectedRequest.city }}.</p>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../lib/api';

const loading = ref(false);
const error = ref('');
const message = ref('');
const requests = ref([]);
const selectedRequest = ref(null);
const filters = ref({ urgency: '', status: '', location: '' });

const filteredRequests = computed(() => {
  return requests.value.filter((item) => {
    const urgencyOk = !filters.value.urgency || `${item.urgency_level}`.toLowerCase() === filters.value.urgency;
    const statusOk = !filters.value.status || `${item.status}`.toLowerCase() === filters.value.status;
    const locationText = `${item.location || item.city || ''}`.toLowerCase();
    const locationOk = !filters.value.location || locationText.includes(filters.value.location.toLowerCase());
    return urgencyOk && statusOk && locationOk;
  });
});

const urgencyClass = (urgency) => {
  if (`${urgency}`.toLowerCase() === 'high') return 'bg-red-100 text-red-700';
  if (`${urgency}`.toLowerCase() === 'medium') return 'bg-amber-100 text-amber-700';
  return 'bg-emerald-100 text-emerald-700';
};

const loadRequests = async () => {
  loading.value = true;
  error.value = '';

  try {
    const response = await api.get('/admin/requests');
    requests.value = response.data?.data || response.data || [];
  } catch (loadError) {
    error.value = 'Unable to load requests.';
  } finally {
    loading.value = false;
  }
};

const viewRequest = (request) => {
  selectedRequest.value = request;
};

const overrideStatus = async (request) => {
  const nextStatus = request.status === 'pending' ? 'matched' : 'pending';

  try {
    await api.patch(`/admin/requests/${request.id}`, {
      status: nextStatus,
    });

    request.status = nextStatus;
    message.value = `Request #${request.id} status updated to ${nextStatus}.`;
  } catch (actionError) {
    error.value = 'Unable to override request status.';
  }
};

const escalate = async (request) => {
  try {
    await api.patch(`/admin/requests/${request.id}`, {
      urgency_level: 'critical',
      status: 'pending',
    });

    request.urgency_level = 'high';
    message.value = `Request #${request.id} escalated to emergency.`;
  } catch (actionError) {
    error.value = 'Unable to escalate request.';
  }
};

onMounted(loadRequests);
</script>
