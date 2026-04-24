<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Emergency requests</div>
          <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950 sm:text-4xl">Active requests requiring donor action</h2>
          <p class="mt-2 text-sm text-gray-600">Filter by urgency, radius, and compatibility, then respond or navigate with minimal friction.</p>
        </div>
        <button type="button" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 sm:w-auto" @click="loadRequests">Refresh queue</button>
      </div>

      <div class="mt-6 grid grid-cols-1 gap-4 rounded-[1.75rem] border border-gray-200 bg-gray-50 p-4 md:grid-cols-2 xl:grid-cols-3">
        <label>
          <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Distance radius</div>
          <select v-model="filters.radius" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100">
            <option value="all">All available distances</option>
            <option value="10">Within 10 km</option>
            <option value="25">Within 25 km</option>
            <option value="50">Within 50 km</option>
          </select>
        </label>
        <label>
          <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Urgency level</div>
          <select v-model="filters.urgency" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100">
            <option value="all">All urgency levels</option>
            <option value="critical">Critical only</option>
            <option value="high">High only</option>
            <option value="medium">Medium only</option>
            <option value="low">Low only</option>
          </select>
        </label>
        <label>
          <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Blood type compatibility</div>
          <select v-model="filters.compatibility" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100">
            <option value="all">All compatible requests</option>
            <option value="exact">Exact blood type match</option>
            <option value="unresponded">Unresponded requests only</option>
          </select>
        </label>
      </div>
    </section>

    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div v-if="loading" class="space-y-4 animate-pulse">
        <div v-for="index in 4" :key="index" class="h-36 rounded-[1.75rem] bg-gray-100"></div>
      </div>

      <div v-else-if="filteredRequests.length === 0" class="rounded-[1.75rem] border border-dashed border-gray-200 bg-gray-50 px-6 py-14 text-center">
        <div class="text-lg font-semibold text-gray-900">No requests match your current filters</div>
        <div class="mt-2 text-sm text-gray-500">Adjust the urgency or radius filters to widen the emergency response queue.</div>
      </div>

      <div v-else class="space-y-4">
        <article v-for="request in filteredRequests" :key="request.id" class="rounded-[1.75rem] border bg-white p-5 shadow-sm" :class="urgencyTheme[request.urgency_level]?.border || 'border-gray-200'">
          <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="urgencyTheme[request.urgency_level]?.badge">{{ request.urgency_label }}</span>
                <span class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">{{ request.posted_relative }}</span>
              </div>
              <h3 class="mt-3 text-xl font-bold text-gray-950">{{ request.hospital_name }}</h3>
              <div class="mt-2 grid grid-cols-1 gap-3 text-sm text-gray-600 md:grid-cols-2 xl:grid-cols-4">
                <div><span class="font-semibold text-gray-900">Location:</span> {{ request.hospital_address }}</div>
                <div><span class="font-semibold text-gray-900">Distance:</span> {{ request.distance_display }}</div>
                <div><span class="font-semibold text-gray-900">Blood type:</span> {{ request.blood_type }}</div>
                <div><span class="font-semibold text-gray-900">Units required:</span> {{ request.units_required }}</div>
              </div>
              <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-gray-600">
                <span class="rounded-full bg-gray-100 px-3 py-1">Posted {{ request.posted_time }}</span>
                <span class="rounded-full bg-gray-100 px-3 py-1">Status {{ request.status }}</span>
                <span class="rounded-full bg-gray-100 px-3 py-1">{{ request.compatibility_label }}</span>
              </div>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row xl:flex-col xl:w-48">
              <button type="button" class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="request.response_status === 'accepted' || actionLoadingId === request.id" @click="handleResponse(request, 'accept')">
                {{ request.response_status === 'accepted' ? 'Accepted' : actionLoadingId === request.id && actionLoadingType === 'accept' ? 'Sending...' : 'Accept / Respond' }}
              </button>
              <button type="button" class="w-full rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50" :disabled="request.response_status === 'declined' || actionLoadingId === request.id" @click="handleResponse(request, 'decline')">
                {{ request.response_status === 'declined' ? 'Declined' : actionLoadingId === request.id && actionLoadingType === 'decline' ? 'Declining...' : 'Decline' }}
              </button>
              <button type="button" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="toggleExpanded(request.id)">
                {{ expandedId === request.id ? 'Hide Full Details' : 'View Full Details' }}
              </button>
              <a :href="request.directions_url" target="_blank" rel="noreferrer" class="inline-flex w-full items-center justify-center rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50">Navigate to Hospital</a>
            </div>
          </div>

          <div v-if="expandedId === request.id" class="mt-5 grid grid-cols-1 gap-3 border-t border-gray-100 pt-5 lg:grid-cols-3">
            <div class="rounded-2xl bg-gray-50 p-4">
              <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Requested blood</div>
              <div class="mt-2 text-lg font-bold text-gray-950">{{ request.blood_type }}</div>
              <div class="mt-1 text-sm text-gray-600">{{ request.units_required }} units required</div>
            </div>
            <div class="rounded-2xl bg-gray-50 p-4">
              <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Operational urgency</div>
              <div class="mt-2 text-lg font-bold text-gray-950">{{ request.urgency_label }}</div>
              <div class="mt-1 text-sm text-gray-600">Request city: {{ request.city }}</div>
            </div>
            <div class="rounded-2xl bg-gray-50 p-4">
              <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Scheduling</div>
              <div class="mt-2 text-lg font-bold text-gray-950">{{ request.required_on || 'Immediate need' }}</div>
              <div class="mt-1 text-sm text-gray-600">Latest queue update {{ request.posted_relative }}</div>
            </div>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { defaultDonorSettings, fetchDonorDashboard, respondToRequest, urgencyTheme } from '../../lib/donorPanel';
import { showDonorToast } from '../../lib/donorToast';

const loading = ref(true);
const requests = ref([]);
const expandedId = ref(null);
const actionLoadingId = ref(null);
const actionLoadingType = ref(null);
const settings = ref({ ...defaultDonorSettings });
const filters = reactive({
  radius: 'all',
  urgency: 'all',
  compatibility: 'all',
});

const filteredRequests = computed(() => requests.value.filter((request) => {
  if (filters.urgency !== 'all' && request.urgency_level !== filters.urgency) return false;
  if (filters.radius !== 'all' && request.distance_km != null && request.distance_km > Number(filters.radius)) return false;
  if (filters.compatibility === 'unresponded' && request.response_status != null) return false;
  if (filters.compatibility === 'exact' && !request.compatibility_label.toLowerCase().includes('compatible')) return false;
  return true;
}).sort((left, right) => urgencyRank(right.urgency_level) - urgencyRank(left.urgency_level)));

function urgencyRank(level) {
  return { critical: 4, high: 3, medium: 2, low: 1 }[level] || 0;
}

function toggleExpanded(requestId) {
  expandedId.value = expandedId.value === requestId ? null : requestId;
}

async function loadRequests() {
  loading.value = true;

  try {
    const payload = await fetchDonorDashboard();
    requests.value = payload.requests;
    settings.value = payload.settings;

    filters.radius = payload.settings.maxRadius;
    filters.compatibility = payload.settings.defaultRequestFilter === 'unresponded' ? 'unresponded' : 'all';
    filters.urgency = payload.settings.urgentOnly || payload.settings.defaultRequestFilter === 'critical' ? 'critical' : 'all';
  } finally {
    loading.value = false;
  }
}

async function handleResponse(request, action) {
  actionLoadingId.value = request.id;
  actionLoadingType.value = action;

  try {
    const result = await respondToRequest(action, request.id);
    request.response_status = result.response || (action === 'accept' ? 'accepted' : 'declined');
    showDonorToast(action === 'accept'
      ? `Accepted ${request.blood_type} request from ${request.hospital_name}.`
      : `Declined ${request.blood_type} request from ${request.hospital_name}.`);
  } catch {
    showDonorToast(`Unable to update ${request.hospital_name} request status.`, 'error');
  } finally {
    actionLoadingId.value = null;
    actionLoadingType.value = null;
  }
}

onMounted(loadRequests);
</script>
