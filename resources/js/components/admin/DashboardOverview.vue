<template>
  <AdminPageFrame
    kicker="Operations Command"
    title="Admin Dashboard Overview"
    description="Unified demand, donor readiness, and request activity in a single operational snapshot."
    badge="Live request feed"
  >
    <template #actions>
      <button type="button" class="admin-button-secondary" @click="loadOverview">
        Refresh Overview
      </button>
    </template>

    <!-- Error banner -->
    <div
      v-if="error"
      class="rounded-[2rem] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {{ error }}
    </div>

    <!-- KPI cards -->
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-5">
      <div
        v-for="card in metricCards"
        :key="card.key"
        class="relative overflow-hidden rounded-[2rem] border bg-white p-4"
        :class="card.critical ? 'border-red-200' : 'border-gray-200'"
      >
        <!-- bottom accent bar -->
        <div
          class="absolute bottom-0 left-0 right-0 h-[3px]"
          :class="card.critical ? 'bg-red-500' : 'bg-emerald-500'"
        />
        <div class="mb-2 flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-gray-400">
          <span aria-hidden="true">{{ card.icon }}</span>
          {{ card.label }}
        </div>
        <p
          class="text-2xl font-bold leading-none"
          :class="card.critical ? 'text-red-600' : 'text-gray-900'"
        >
          {{ card.value }}
        </p>
        <p
          class="mt-2 flex items-center gap-1 text-[11px]"
          :class="card.critical ? 'text-red-400' : 'text-gray-400'"
        >
          <span aria-hidden="true">{{ card.trendIcon }}</span>
          {{ card.trend }}
        </p>
      </div>
    </div>

    <!-- Charts row -->
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
      <!-- Response rate trend -->
      <div class="admin-panel">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900">Response rate trend</h3>
          <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-medium text-gray-500">
            Last 7 periods
          </span>
        </div>
        <div v-if="loading" class="flex h-32 items-center justify-center text-xs text-gray-400">
          Loading…
        </div>
        <div v-else class="space-y-2.5">
          <div v-for="(point, index) in responseTrend" :key="`res-${index}`">
            <div class="mb-1 flex items-center justify-between text-[11px] text-gray-500">
              <span>Period {{ index + 1 }}</span>
              <span class="font-medium">{{ point }}%</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-gray-100">
              <div
                class="h-full rounded-full bg-emerald-500 transition-all duration-300"
                :style="{ width: `${Math.min(point, 100)}%` }"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Blood type demand -->
      <div class="admin-panel">
        <div class="mb-5 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900">Blood type demand</h3>
          <span
            v-if="loading"
            class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-medium text-gray-500"
          >
            Loading…
          </span>
        </div>
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center">
          <!-- Donut -->
          <div class="mx-auto w-full max-w-[200px] flex-shrink-0">
            <canvas
              ref="bloodTypeDemandCanvas"
              aria-label="Donut chart showing blood type demand distribution"
              role="img"
            >
              Blood type demand: {{ bloodTypeAriaText }}
            </canvas>
          </div>
          <!-- Legend -->
          <ul class="w-full space-y-2 lg:max-w-xs">
            <li
              v-for="item in bloodTypeLegend"
              :key="item.type"
              class="flex items-center justify-between rounded-xl border border-gray-100 px-3 py-2.5"
            >
              <div class="flex items-center gap-2.5">
                <span
                  class="inline-block h-2.5 w-2.5 flex-shrink-0 rounded-full"
                  :style="{ backgroundColor: item.color }"
                />
                <span class="text-sm font-semibold text-gray-800">{{ item.type }}</span>
              </div>
              <div class="text-right">
                <p class="text-sm font-semibold text-gray-900">{{ item.percent }}%</p>
                <p class="text-xs text-gray-400">{{ item.count }} {{ item.count === 1 ? 'request' : 'requests' }}</p>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Requests over time and Activity feed row -->
    <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
      <!-- Requests over time — bars rest on a visible baseline instead of floating -->
      <div class="admin-panel overflow-hidden">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900">Requests over time</h3>
          <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-medium text-gray-500">
            Last 7 periods
          </span>
        </div>
        <div v-if="loading" class="flex h-32 items-center justify-center text-xs text-gray-400">
          Loading…
        </div>
        <div v-else class="flex w-full flex-col gap-2 overflow-x-auto rounded-xl bg-gradient-to-b from-gray-50 to-white p-4">
          <!-- Bars row: contained within the panel, bars rest on a visible baseline -->
          <div class="flex h-40 w-full items-end justify-center gap-3 rounded-lg bg-white/50 px-4 py-3">
            <div
              v-for="(value, index) in requestTrend"
              :key="`req-${index}`"
              class="flex h-full flex-col items-center justify-end gap-2"
            >
              <span class="text-xs font-semibold text-gray-600">{{ value }}</span>
              <div
                class="w-10 rounded-t-md bg-gradient-to-t from-red-500 to-red-400 shadow-md transition-all duration-300 hover:from-red-600 hover:to-red-500"
                :style="{ height: `${barHeightPx(value, requestTrend)}px` }"
              />
            </div>
          </div>
          <!-- Baseline: gives the bars something concrete to sit on -->
          <div class="mx-4 h-px bg-gray-200" />
          <!-- Labels row: sits cleanly below the baseline -->
          <div class="flex w-full justify-center gap-3 px-4 pb-2">
            <div
              v-for="(_, index) in requestTrend"
              :key="`label-${index}`"
              class="text-center text-xs font-semibold text-gray-700"
            >
              {{ index + 1 }}
            </div>
          </div>
        </div>
      </div>

      <!-- Activity feed -->
      <div class="admin-panel">
        <div class="mb-4 flex items-center justify-between">
          <h3 class="text-sm font-semibold text-gray-900">Recent activity feed</h3>
          <button
            type="button"
            class="flex items-center gap-1 text-xs font-medium text-red-600 hover:text-red-700"
            @click="loadOverview"
          >
            ↻ Refresh
          </button>
        </div>

        <div v-if="loading" class="py-8 text-center text-sm text-gray-400">
          Loading activity…
        </div>
        <ul v-else class="space-y-2.5">
          <li
            v-for="entry in activityFeed"
            :key="entry.id"
            class="flex items-start gap-3 rounded-xl border border-gray-100 px-4 py-3"
          >
            <!-- Icon block -->
            <div
              class="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl text-sm"
              :class="feedIconClass(entry.type)"
            >
              {{ feedIcon(entry.type) }}
            </div>
            <!-- Content -->
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm font-semibold text-gray-900">{{ entry.title }}</p>
              <p class="mt-0.5 text-xs text-gray-500">{{ entry.description }}</p>
              <p class="mt-0.5 text-[10px] text-gray-400">{{ formatDateTime(entry.timestamp) }}</p>
            </div>
            <!-- Badge -->
            <span
              class="mt-0.5 flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold"
              :class="badgeClass(entry.type)"
            >
              {{ entry.type }}
            </span>
          </li>
          <li v-if="activityFeed.length === 0" class="py-8 text-center text-sm text-gray-400">
            No recent activity.
          </li>
        </ul>
      </div>
    </div>
  </AdminPageFrame>
</template>

<script setup>
import Chart from 'chart.js/auto';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import AdminPageFrame from './AdminPageFrame.vue';
import api from '../../lib/api';

// ─── State ───────────────────────────────────────────────────────────────────

const loading = ref(false);
const error = ref('');
const overview = ref({});
const bloodTypeDemandCanvas = ref(null);
const bloodTypeDemand = ref({ 'O+': 0, 'A-': 0, 'B+': 0, 'AB+': 0 });

let bloodTypeDemandChart = null;

// ─── Constants ───────────────────────────────────────────────────────────────

const BLOOD_TYPE_ORDER = ['O+', 'A-', 'B+', 'AB+'];
const BLOOD_TYPE_COLORS = ['#7a1020', '#c0392b', '#e67e22', '#f5b8b8'];

// ─── Computed ─────────────────────────────────────────────────────────────────

const metricCards = computed(() => {
  const d = overview.value ?? {};
  const rate = d.response_rate ?? 0;
  const critical = d.critical_requests ?? 0;

  return [
    {
      key: 'active',
      label: 'Active requests',
      value: d.total_active_requests ?? 0,
      icon: '🩸',
      trend: 'Stable',
      trendIcon: '→',
      critical: false,
    },
    {
      key: 'critical',
      label: 'Critical',
      value: critical,
      icon: '⚠️',
      trend: critical > 0 ? 'Needs attention' : 'All clear',
      trendIcon: critical > 0 ? '↑' : '✓',
      critical: critical > 0,
    },
    {
      key: 'donors',
      label: 'Available donors',
      value: d.available_donors ?? 0,
      icon: '👤',
      trend: 'No change',
      trendIcon: '→',
      critical: false,
    },
    {
      key: 'rate',
      label: 'Response rate',
      value: `${rate}%`,
      icon: '📊',
      trend: rate < 60 ? 'Below threshold' : 'On target',
      trendIcon: rate < 60 ? '↓' : '↑',
      critical: rate < 60,
    },
    {
      key: 'avg',
      label: 'Avg match time',
      value: d.average_matching_time ?? '0m',
      icon: '⏱',
      trend: 'Baseline',
      trendIcon: '→',
      critical: false,
    },
  ];
});

const requestTrend = computed(() => overview.value.requests_over_time ?? [3, 5, 4, 6, 8, 7, 9]);
const responseTrend = computed(() => overview.value.response_rate_trend ?? [65, 70, 72, 75, 80, 83, 85]);
const activityFeed = computed(() => overview.value.recent_activity ?? []);

const bloodTypeLegend = computed(() => {
  const demand = bloodTypeDemand.value;
  const total = BLOOD_TYPE_ORDER.reduce((sum, t) => sum + (Number(demand[t]) || 0), 0);

  return BLOOD_TYPE_ORDER.map((type, i) => {
    const count = Number(demand[type]) || 0;
    const percent = total > 0 ? Math.round((count / total) * 100) : 0;
    return { type, count, percent, color: BLOOD_TYPE_COLORS[i] };
  });
});

const bloodTypeAriaText = computed(() =>
  bloodTypeLegend.value.map((b) => `${b.type} ${b.percent}%`).join(', '),
);

// ─── Helpers ─────────────────────────────────────────────────────────────────

// Pixel-based bar height keeps the bars visually anchored to the baseline
// regardless of how small/uniform the underlying values are.
const barHeightPx = (value, dataset) => {
  const max = Math.max(...dataset, 1);
  const maxBarHeight = 96; // fits within the h-32 chart area, leaving room for the value label
  const minBarHeight = 6; // ensures even zero/near-zero values stay visible as a sliver
  return Math.max(minBarHeight, Math.round((Number(value) / max) * maxBarHeight));
};

const badgeClass = (type) => {
  const map = {
    critical: 'bg-red-100 text-red-700',
    success: 'bg-emerald-100 text-emerald-700',
    pending: 'bg-amber-100 text-amber-700',
  };
  return map[type] ?? 'bg-gray-100 text-gray-600';
};

const feedIconClass = (type) => {
  const map = {
    critical: 'bg-red-50 text-red-600',
    success: 'bg-emerald-50 text-emerald-600',
    pending: 'bg-amber-50 text-amber-600',
  };
  return map[type] ?? 'bg-gray-100 text-gray-500';
};

const feedIcon = (type) => {
  const map = { critical: '⚠', success: '✓', pending: '◷' };
  return map[type] ?? '•';
};

const formatDateTime = (value) => {
  if (!value) return 'Unknown';
  return new Date(value).toLocaleString();
};

// ─── Data normalisation ───────────────────────────────────────────────────────

const extractRequestArray = (rawResponse) => {
  const root = rawResponse?.data ?? rawResponse ?? {};
  const payload = root?.success !== undefined ? (root.data ?? {}) : root;

  if (Array.isArray(payload?.data)) return payload.data;
  if (Array.isArray(payload)) return payload;
  return [];
};

const buildBloodTypeDemand = (requests) => {
  const counts = { 'O+': 0, 'A-': 0, 'B+': 0, 'AB+': 0 };

  for (const req of requests) {
    const bt = String(req?.blood_type ?? '').toUpperCase();
    if (Object.hasOwn(counts, bt)) counts[bt]++;
  }

  bloodTypeDemand.value = counts;
};

// ─── Chart ────────────────────────────────────────────────────────────────────

const destroyChart = () => {
  bloodTypeDemandChart?.destroy();
  bloodTypeDemandChart = null;
};

const renderChart = async () => {
  await nextTick();
  if (!bloodTypeDemandCanvas.value) return;

  destroyChart();

  bloodTypeDemandChart = new Chart(bloodTypeDemandCanvas.value, {
    type: 'doughnut',
    data: {
      labels: BLOOD_TYPE_ORDER,
      datasets: [
        {
          data: BLOOD_TYPE_ORDER.map((t) => Number(bloodTypeDemand.value[t]) || 0),
          backgroundColor: BLOOD_TYPE_COLORS,
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
            label(ctx) {
              const value = Number(ctx.raw) || 0;
              const total = ctx.dataset.data.reduce((s, v) => s + Number(v || 0), 0);
              const pct = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
              return `${ctx.label}: ${pct}% (${value})`;
            },
          },
        },
      },
    },
  });
};

// ─── Load ─────────────────────────────────────────────────────────────────────

const loadOverview = async () => {
  loading.value = true;
  error.value = '';

  try {
    const [dashboardRes, requestsRes] = await Promise.all([
      api.get('/admin/dashboard'),
      api.get('/admin/requests', { params: { per_page: 200 } }),
    ]);

    // ── Dashboard metrics ──────────────────────────────────────────────────
    const rawRoot = dashboardRes.data ?? {};
    const raw = rawRoot.success !== undefined ? (rawRoot.data ?? {}) : rawRoot;
    const metrics = raw.metrics ?? {};
    const recentRequests = Array.isArray(raw.recent_requests) ? raw.recent_requests : [];

    const activeRequests = recentRequests.filter(
      (r) => !['completed', 'cancelled'].includes(r.status),
    );
    const criticalRequests = recentRequests.filter((r) =>
      ['high', 'critical'].includes(r.urgency_level),
    );

    // ── Blood type demand ──────────────────────────────────────────────────
    const allRequests = extractRequestArray(requestsRes.data);
    buildBloodTypeDemand(allRequests);
    await renderChart();

    // ── Compose overview ───────────────────────────────────────────────────
    overview.value = {
      total_active_requests: activeRequests.length,
      critical_requests: criticalRequests.length,
      available_donors: metrics.active_donors ?? 0,
      response_rate: metrics.success_rate ?? 0,
      average_matching_time: `${metrics.response_time_minutes ?? 0}m`,
      recent_activity: recentRequests.slice(0, 10).map((r) => ({
        id: r.id,
        title: `${r.blood_type} – ${r.units_required ?? r.quantity ?? 0} units`,
        description: `${r.hospital_name ?? 'Hospital'} • ${r.city ?? ''}`,
        type: ['high', 'critical'].includes(r.urgency_level)
          ? 'critical'
          : r.status === 'completed'
            ? 'success'
            : 'pending',
        timestamp: r.created_at,
      })),
    };
  } catch (err) {
    console.error('[AdminDashboard] loadOverview failed:', err);
    error.value = 'Unable to load dashboard overview. Please try again.';
  } finally {
    loading.value = false;
  }
};

// ─── Lifecycle ────────────────────────────────────────────────────────────────

onMounted(loadOverview);
onUnmounted(destroyChart);
</script>