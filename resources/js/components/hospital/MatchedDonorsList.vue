<template>
  <div class="space-y-6">
    <!-- Page Heading -->
    <div>
      <h2 class="text-2xl font-bold text-gray-900">Matched Donors</h2>
      <p class="mt-1 text-sm text-gray-600">View PAST-Match algorithm results for your blood requests</p>
    </div>

    <!-- Request Selector -->
    <div class="rounded-lg border border-gray-200 bg-white p-4 flex items-center gap-4">
      <label class="text-sm font-medium text-gray-700">Select Request:</label>
      <select
        v-model="selectedRequestId"
        @change="loadMatches"
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
      <p class="text-gray-500">Loading matched donors...</p>
    </div>

    <!-- No Selection -->
    <div v-else-if="!selectedRequestId" class="rounded-lg border border-gray-200 bg-white p-12 text-center">
      <p class="text-gray-600">Select a request above to view matched donors</p>
    </div>

    <!-- Matched Donors -->
    <div v-else-if="matches.length === 0" class="rounded-lg border border-gray-200 bg-white p-12 text-center">
      <p class="text-lg font-medium text-gray-900">No matching donors found</p>
      <p class="text-sm text-gray-600">Try expanding your search radius or lowering urgency level</p>
    </div>

    <div v-else class="space-y-4">
      <!-- Top 3 Highlight -->
      <div v-if="matches.slice(0, 3).length > 0" class="rounded-lg border-2 border-yellow-400 bg-yellow-50 p-4">
        <p class="text-sm font-semibold text-yellow-900">⭐ Top Matches (Highest Compatibility Score)</p>
      </div>

      <!-- Matched Donor Cards -->
      <div
        v-for="(donor, index) in matches"
        :key="donor.id"
        class="rounded-lg border p-6 transition-all hover:shadow-md"
        :class="index < 3 ? 'border-yellow-300 bg-yellow-50' : 'border-gray-200 bg-white'"
      >
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-5">
          <!-- Rank -->
          <div>
            <p class="text-xs font-medium text-gray-600">Rank</p>
            <p class="mt-1 text-2xl font-bold" :class="index < 3 ? 'text-yellow-600' : 'text-gray-600'">
              #{{ index + 1 }}
            </p>
          </div>

          <!-- Donor Info -->
          <div>
            <p class="text-xs font-medium text-gray-600">Donor Name</p>
            <p class="mt-1 font-semibold text-gray-900">{{ donor.name || 'Anonymous' }}</p>
            <p class="text-xs text-gray-500">{{ donor.blood_type }}</p>
          </div>

          <!-- Distance -->
          <div>
            <p class="text-xs font-medium text-gray-600">Distance</p>
            <p class="mt-1 text-gray-900">{{ donor.distance_km ?? 'N/A' }}km</p>
            <p class="text-xs text-gray-500">from hospital</p>
          </div>

          <!-- Availability -->
          <div>
            <p class="text-xs font-medium text-gray-600">Availability</p>
            <p class="mt-1">
              <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold" :class="donor.availability ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'">
                {{ donor.availability ? 'Available' : 'Unavailable' }}
              </span>
            </p>
          </div>

          <!-- Score -->
          <div>
            <p class="text-xs font-medium text-gray-600">Compatibility</p>
            <div class="mt-1 space-y-1">
              <p class="text-2xl font-bold text-red-600">{{ Math.round(Number(donor.score || 0)) }}%</p>
              <div class="w-full bg-gray-200 rounded-full h-2">
                <div
                  class="bg-red-600 h-2 rounded-full transition-all"
                  :style="{ width: Math.min(100, Math.max(0, Math.round(Number(donor.score || 0))) ) + '%' }"
                />
              </div>
            </div>
          </div>
        </div>

        <!-- Contact Button -->
        <div class="mt-4">
          <button
            :disabled="true"
            class="inline-flex items-center px-4 py-2 rounded-lg bg-gray-200 text-gray-600 font-semibold cursor-not-allowed"
          >
            Auto-notification handled by backend queue
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { fetchHospitalRequests, fetchMatchedDonors } from '../../lib/hospitalPanel';

const activeRequests = ref([]);
const selectedRequestId = ref('');
const matches = ref([]);
const loading = ref(false);

const loadActiveRequests = async () => {
  try {
    const requests = await fetchHospitalRequests();
    activeRequests.value = requests.filter((req) => ['pending', 'matching'].includes(req.status));
  } catch (err) {
    console.error('Failed to load requests:', err);
  }
};

const loadMatches = async () => {
  if (!selectedRequestId.value) return;

  loading.value = true;

  try {
    matches.value = await fetchMatchedDonors(selectedRequestId.value);
  } catch (err) {
    console.error('Failed to load matches:', err);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  loadActiveRequests();
});
</script>
