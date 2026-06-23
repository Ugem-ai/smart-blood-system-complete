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

    <a-card :bordered="false" class="rounded-[1.75rem] border border-gray-200 shadow-sm">
      <template #title>
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900">Blood Type Demand</h3>
          <span v-if="loading" class="text-xs text-gray-400">Loading...</span>
        </div>
      </template>
      <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="mx-auto w-full max-w-[260px]">
          <canvas ref="bloodTypeDemandCanvas" aria-label="Blood type demand donut chart" role="img"></canvas>
        </div>
        <div class="w-full lg:max-w-sm">
          <ul class="space-y-3">
            <li v-for="item in bloodTypeLegend" :key="item.type" class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2">
              <div class="flex items-center gap-2">
                <span class="inline-block h-3 w-3 rounded-full" :style="{ backgroundColor: item.color }"></span>
                <span class="text-sm font-semibold text-gray-800">{{ item.type }}</span>
              </div>
              <div class="text-right">
                <p class="text-sm font-semibold text-gray-900">{{ item.percent }}%</p>
                <p class="text-xs text-gray-500">{{ item.count }} requests</p>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </a-card>

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
import Chart from 'chart.js/auto';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import AdminPageFrame from './AdminPageFrame.vue';
import api from '../../lib/api';

const loading = ref(false);
const error = ref('');
const overview = ref({});
const bloodTypeDemandCanvas = ref(null);
const bloodTypeDemand = ref({
  'O+': 0,
  'A-': 0,
  'B+': 0,
  'AB+': 0,
});

let bloodTypeDemandChart = null;
const bloodTypeOrder = ['O+', 'A-', 'B+', 'AB+'];
const bloodTypeColors = ['#7a1020', '#c0392b', '#e67e22', '#f5b8b8'];

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
const bloodTypeLegend = computed(() => {
  const demand = bloodTypeDemand.value || {};
  const total = bloodTypeOrder.reduce((sum, type) => sum + (Number(demand[type]) || 0), 0);

  return bloodTypeOrder.map((type, index) => {
    const count = Number(demand[type]) || 0;
    const percent = total > 0 ? Math.round((count / total) * 100) : 0;

    return {
      type,
      count,
      percent,
      color: bloodTypeColors[index],
    };
  });
});

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

const normalizeRequestsPayload = (rawResponse) => {
  const root = rawResponse?.data ?? rawResponse ?? {};
  const payload = root?.success !== undefined ? root.data : (root.data ?? root);

  if (Array.isArray(payload?.data)) return payload.data;
  if (Array.isArray(payload)) return payload;
  return [];
};

const buildBloodTypeDemand = (requests) => {
  const counts = {
    'O+': 0,
    'A-': 0,
    'B+': 0,
    'AB+': 0,
  };

  requests.forEach((request) => {
    const bloodType = String(request?.blood_type || '').toUpperCase();
    if (Object.prototype.hasOwnProperty.call(counts, bloodType)) {
      counts[bloodType] += 1;
    }
  });

  bloodTypeDemand.value = counts;
};

const destroyBloodTypeDemandChart = () => {
  bloodTypeDemandChart?.destroy();
  bloodTypeDemandChart = null;
};

const renderBloodTypeDemandChart = async () => {
  await nextTick();

  if (!bloodTypeDemandCanvas.value) return;

  destroyBloodTypeDemandChart();

  bloodTypeDemandChart = new Chart(bloodTypeDemandCanvas.value, {
    type: 'doughnut',
    data: {
      labels: bloodTypeOrder,
      datasets: [
        {
          data: bloodTypeOrder.map((type) => Number(bloodTypeDemand.value[type]) || 0),
          backgroundColor: bloodTypeColors,
          borderWidth: 0,
          hoverOffset: 8,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: true,
      cutout: '68%',
      plugins: {
        legend: { display: false },
        tooltip: {
          callbacks: {
            label(context) {
              const value = Number(context.raw) || 0;
              const total = context.dataset.data.reduce((sum, item) => sum + Number(item || 0), 0);
              const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
              return `${context.label}: ${pct}% (${value})`;
            },
          },
        },
      },
    },
  });
};

const loadOverview = async () => {
  loading.value = true;
  error.value = '';

  try {
    const [dashboardResponse, requestsResponse] = await Promise.all([
      api.get('/admin/dashboard'),
      api.get('/admin/requests', { params: { per_page: 200 } }),
    ]);

    const rawRoot = dashboardResponse.data || {};
    const raw = rawRoot.success !== undefined ? (rawRoot.data || {}) : rawRoot;
    const metrics = raw.metrics || {};
    const recentRequests = Array.isArray(raw.recent_requests) ? raw.recent_requests : [];
    const activeRequests = recentRequests.filter((r) => !['completed', 'cancelled'].includes(r.status));
    const criticalRequests = recentRequests.filter((r) => ['high', 'critical'].includes(r.urgency_level));

    const allRequests = normalizeRequestsPayload(requestsResponse.data);
    buildBloodTypeDemand(allRequests);
    await renderBloodTypeDemandChart();

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
onUnmounted(destroyBloodTypeDemandChart);
</script>
