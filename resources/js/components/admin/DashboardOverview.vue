<template>
  <AdminPageFrame
    kicker="Operations Command"
    title="Admin Dashboard Overview"
    description="Unified demand, donor readiness, and request activity in a single operational snapshot."
    badge="Live request feed"
  >
    <template #actions>
      <button type="button" class="admin-button-secondary" @click="loadOverview">Refresh Overview</button>
    </template>

    <div v-if="error" class="rounded-[1.75rem] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-sm">
      {{ error }}
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
      <div v-for="card in metricCards" :key="card.key" class="rounded-[1.75rem] border border-gray-200 bg-white p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ card.label }}</p>
        <p class="mt-2 text-2xl font-bold" :class="card.critical ? 'text-red-600' : 'text-gray-900'">{{ card.value }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <div class="admin-panel">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900">Requests Over Time</h3>
          <span v-if="loading" class="text-xs text-gray-400">Loading...</span>
        </div>
        <div class="grid h-40 grid-cols-7 items-end gap-2">
          <div v-for="(value, index) in requestTrend" :key="`req-${index}`" class="flex flex-col items-center gap-1">
            <div class="w-full rounded-t bg-red-500/80" :style="{ height: `${Math.max(8, value * 8)}px` }"></div>
            <span class="text-[10px] text-gray-400">{{ index + 1 }}</span>
          </div>
        </div>
      </div>

      <div class="admin-panel">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900">Response Rate Trend</h3>
          <span v-if="loading" class="text-xs text-gray-400">Loading...</span>
        </div>
        <div class="space-y-3">
          <div v-for="(point, index) in responseTrend" :key="`res-${index}`">
            <div class="mb-1 flex items-center justify-between text-xs text-gray-500">
              <span>Period {{ index + 1 }}</span>
              <span>{{ point }}%</span>
            </div>
            <div class="h-2 rounded-full bg-gray-100">
              <div class="h-2 rounded-full bg-emerald-500" :style="{ width: `${Math.min(point, 100)}%` }"></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="admin-panel">
      <div class="mb-4 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Recent Activity Feed</h3>
        <button type="button" class="text-xs font-medium text-red-600 hover:text-red-700" @click="loadOverview">Refresh</button>
      </div>
      <div v-if="loading" class="py-8 text-center text-sm text-gray-400">Loading activity...</div>
      <ul v-else class="space-y-3">
        <li v-for="entry in activityFeed" :key="entry.id" class="rounded-lg border border-gray-100 px-4 py-3">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-gray-900">{{ entry.title }}</p>
            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="badgeClass(entry.type)">{{ entry.type }}</span>
          </div>
          <p class="mt-1 text-xs text-gray-500">{{ entry.description }}</p>
          <p class="mt-1 text-[11px] text-gray-400">{{ formatDateTime(entry.timestamp) }}</p>
        </li>
        <li v-if="activityFeed.length === 0" class="py-8 text-center text-sm text-gray-400">No recent activity.</li>
      </ul>
    </div>
  </AdminPageFrame>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminPageFrame from './AdminPageFrame.vue';
import api from '../../lib/api';

const loading = ref(false);
const error = ref('');
const overview = ref({});

const metricCards = computed(() => {
  const data = overview.value || {};

  return [
    { key: 'active', label: 'Total Active Requests', value: data.total_active_requests ?? 0, critical: false },
    { key: 'critical', label: 'Critical Requests', value: data.critical_requests ?? 0, critical: (data.critical_requests ?? 0) > 0 },
    { key: 'donors', label: 'Available Donors', value: data.available_donors ?? 0, critical: false },
    { key: 'rate', label: 'Response Rate', value: `${data.response_rate ?? 0}%`, critical: (data.response_rate ?? 0) < 60 },
    { key: 'avg', label: 'Average Matching Time', value: data.average_matching_time ?? '0m', critical: false },
  ];
});

const requestTrend = computed(() => overview.value.requests_over_time || [3, 5, 4, 6, 8, 7, 9]);
const responseTrend = computed(() => overview.value.response_rate_trend || [65, 70, 72, 75, 80, 83, 85]);
const activityFeed = computed(() => overview.value.recent_activity || []);

const badgeClass = (type) => {
  if (type === 'critical') return 'bg-red-100 text-red-700';
  if (type === 'success') return 'bg-emerald-100 text-emerald-700';
  if (type === 'pending') return 'bg-amber-100 text-amber-700';
  return 'bg-gray-100 text-gray-700';
};

const formatDateTime = (value) => {
  if (!value) return 'Unknown';
  return new Date(value).toLocaleString();
};

const loadOverview = async () => {
  loading.value = true;
  error.value = '';

  try {
    const response = await api.get('/admin/dashboard');
    const raw = response.data || {};
    const metrics = raw.metrics || {};
    const recentRequests = Array.isArray(raw.recent_requests) ? raw.recent_requests : [];
    const activeRequests = recentRequests.filter((r) => !['completed', 'cancelled'].includes(r.status));
    const criticalRequests = recentRequests.filter((r) => ['high', 'critical'].includes(r.urgency_level));

    overview.value = {
      total_active_requests: activeRequests.length,
      critical_requests: criticalRequests.length,
      available_donors: metrics.active_donors ?? 0,
      response_rate: metrics.success_rate ?? 0,
      average_matching_time: `${metrics.response_time_minutes ?? 0}m`,
      recent_activity: recentRequests.slice(0, 10).map((r) => ({
        id: r.id,
        title: `${r.blood_type} \u2013 ${r.units_required ?? r.quantity ?? 0} units`,
        description: `${r.hospital_name || 'Hospital'} \u2022 ${r.city || ''}`,
        type: ['high', 'critical'].includes(r.urgency_level) ? 'critical' : r.status === 'completed' ? 'success' : 'pending',
        timestamp: r.created_at,
      })),
    };
  } catch (loadError) {
    error.value = 'Unable to load dashboard overview.';
  } finally {
    loading.value = false;
  }
};

onMounted(loadOverview);
</script>
