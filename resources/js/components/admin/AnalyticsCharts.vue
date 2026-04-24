<template>
  <AdminPageFrame
    kicker="Decision Intelligence"
    title="Operational Analytics Dashboard"
    description="Transform raw matching and notification activity into live operational decisions for blood donation response teams."
    badge="Executive analytics"
  >
    <template #actions>
      <div class="min-w-0 rounded-3xl border border-white/80 bg-white/90 p-4 shadow-sm backdrop-blur xl:max-w-md">
          <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Executive Summary</p>
          <h3 class="mt-2 text-xl font-black text-gray-950">{{ dashboard.executive_summary.headline }}</h3>
          <div class="mt-4 space-y-2 text-sm text-gray-600">
            <p v-for="line in dashboard.executive_summary.summary_lines" :key="line">{{ line }}</p>
          </div>
        </div>
    </template>

    <div v-if="error" class="rounded-3xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-sm">
      {{ error }}
    </div>

    <div class="relative overflow-hidden rounded-[2rem] border border-gray-200 bg-white p-5 shadow-sm">
      <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <label class="block xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-gray-500">Date Range Preset</span>
          <select v-model="filters.range" class="filter-input" @change="applyFilters">
            <option value="daily">Last 7 days</option>
            <option value="weekly">Last 6 weeks</option>
            <option value="monthly">Last 6 months</option>
          </select>
        </label>

        <label class="block xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-gray-500">Start Date</span>
          <input v-model="filters.startDate" type="date" class="filter-input" />
        </label>

        <label class="block xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-gray-500">End Date</span>
          <input v-model="filters.endDate" type="date" class="filter-input" />
        </label>

        <label class="block xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-gray-500">Blood Type</span>
          <select v-model="filters.bloodType" class="filter-input">
            <option value="">All blood types</option>
            <option v-for="bloodType in dashboard.filters.options.blood_types" :key="bloodType" :value="bloodType">{{ bloodType }}</option>
          </select>
        </label>

        <label class="block xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-gray-500">Hospital</span>
          <select v-model="filters.hospitalId" class="filter-input">
            <option value="">All hospitals</option>
            <option v-for="hospital in dashboard.filters.options.hospitals" :key="hospital.id" :value="hospital.id">{{ hospital.name }}</option>
          </select>
        </label>

        <label class="block xl:col-span-1">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-gray-500">Urgency</span>
          <select v-model="filters.urgencyLevel" class="filter-input">
            <option value="">All</option>
            <option v-for="urgency in dashboard.filters.options.urgency_levels" :key="urgency" :value="urgency">{{ urgency }}</option>
          </select>
        </label>

        <div class="flex items-end gap-3 xl:col-span-1">
          <button type="button" class="inline-flex w-full items-center justify-center rounded-2xl bg-red-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="loading" @click="applyFilters">
            {{ loading ? 'Loading...' : 'Apply' }}
          </button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
      <div v-for="card in kpiCards" :key="card.key" class="relative col-span-12 max-w-full overflow-hidden rounded-[2rem] border p-5 shadow-sm xl:col-span-3" :class="card.shellClass">
        <p class="text-sm font-semibold text-gray-700">{{ card.label }}</p>
        <p class="mt-4 text-3xl font-black tracking-tight text-gray-950">{{ card.value }}</p>
        <p class="mt-2 text-xs font-bold uppercase tracking-[0.18em]" :class="kpiToneClass(card.tone)">{{ card.trend }}</p>
      </div>

      <div class="relative col-span-12 max-w-full overflow-hidden rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm xl:col-span-4">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">System Health Indicator</p>
            <h3 class="mt-2 text-xl font-black text-gray-950">{{ dashboard.system_health.label }}</h3>
          </div>
          <span class="rounded-full px-3 py-1 text-xs font-black uppercase tracking-[0.18em]" :class="healthBadgeClass(dashboard.system_health.status)">
            {{ healthDot(dashboard.system_health.status) }} {{ dashboard.system_health.label }}
          </span>
        </div>

        <p class="mt-4 text-sm leading-6 text-gray-600">{{ dashboard.system_health.message }}</p>

        <div class="mt-5 grid grid-cols-1 gap-3">
          <div class="rounded-3xl border border-gray-200 bg-gray-50 p-4">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Window</p>
            <p class="mt-2 text-sm font-semibold text-gray-950">{{ dashboard.meta.range_label }}</p>
          </div>
          <div class="rounded-3xl border border-gray-200 bg-gray-50 p-4">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Requests Analysed</p>
            <p class="mt-2 text-sm font-semibold text-gray-950">{{ dashboard.meta.request_count }}</p>
          </div>
        </div>
      </div>

      <div class="relative col-span-12 max-w-full overflow-hidden rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm xl:col-span-8">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Live Activity Feed</p>
            <h3 class="mt-2 text-xl font-black text-gray-950">Real-time operational events</h3>
          </div>
          <span class="rounded-full bg-gray-950 px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-white">{{ dashboard.live_activity_feed.length }} events</span>
        </div>

        <div v-if="dashboard.live_activity_feed.length === 0" class="mt-6 rounded-3xl border border-dashed border-gray-200 bg-gray-50 p-10 text-center text-sm text-gray-500">
          No live events were recorded in the selected time window.
        </div>

        <div v-else class="mt-6 grid grid-cols-1 gap-3 lg:grid-cols-2">
          <div v-for="entry in dashboard.live_activity_feed" :key="entry.id" class="rounded-3xl border border-gray-200 bg-gray-50 p-4">
            <div class="flex items-start gap-3">
              <span class="mt-1 h-3 w-3 rounded-full" :class="feedToneClass(entry.tone)"></span>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-gray-950">{{ entry.title }}</p>
                <p class="mt-1 text-sm text-gray-600">{{ entry.detail }}</p>
                <p class="mt-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">{{ formatDateTime(entry.timestamp) }}</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="relative col-span-12 max-w-full overflow-hidden rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm xl:col-span-6">
        <div class="space-y-2">
          <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Algorithm Transparency</p>
          <h3 class="text-xl font-black text-gray-950">Matching Speed Trend</h3>
        </div>
        <div class="relative mt-5 h-80 max-w-full overflow-hidden">
          <canvas ref="matchingSpeedCanvas"></canvas>
        </div>
      </div>

      <div class="relative col-span-12 max-w-full overflow-hidden rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm xl:col-span-6">
        <div class="space-y-2">
          <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Algorithm Transparency</p>
          <h3 class="text-xl font-black text-gray-950">Success Rate by Urgency Level</h3>
        </div>
        <div class="relative mt-5 h-80 max-w-full overflow-hidden">
          <canvas ref="urgencySuccessCanvas"></canvas>
        </div>
      </div>

      <div class="relative col-span-12 max-w-full overflow-hidden rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm xl:col-span-12">
        <div class="space-y-2">
          <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Algorithm Transparency</p>
          <h3 class="text-xl font-black text-gray-950">Average Score Distribution</h3>
        </div>
        <div class="relative mt-5 h-80 max-w-full overflow-hidden">
          <canvas ref="scoreDistributionCanvas"></canvas>
        </div>
      </div>

      <div class="relative col-span-12 max-w-full overflow-hidden rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm xl:col-span-4">
        <div class="space-y-2">
          <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Predictive + Insight Layer</p>
          <h3 class="text-xl font-black text-gray-950">Smart Insights</h3>
        </div>

        <div class="mt-5 space-y-3">
          <div v-for="insight in dashboard.insights.predictive" :key="insight" class="rounded-3xl border border-gray-200 bg-gray-50 p-4 text-sm leading-6 text-gray-700">
            {{ insight }}
          </div>
        </div>
      </div>

      <div class="relative col-span-12 max-w-full overflow-hidden rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm xl:col-span-4">
        <div class="space-y-2">
          <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Bottleneck Detection</p>
          <h3 class="text-xl font-black text-gray-950">Operational Friction Points</h3>
        </div>

        <div class="mt-5 space-y-4 text-sm text-gray-700">
          <div class="rounded-3xl border border-gray-200 bg-gray-50 p-4">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-gray-500">Requests with no matches</p>
            <div class="mt-3 space-y-2">
              <p v-for="item in dashboard.insights.bottlenecks.requests_with_no_matches" :key="item.request_id" class="leading-6">
                {{ item.case_id || `Request #${item.request_id}` }} · {{ item.blood_type }} · {{ item.region }}
              </p>
              <p v-if="dashboard.insights.bottlenecks.requests_with_no_matches.length === 0" class="text-gray-500">No unresolved zero-match requests.</p>
            </div>
          </div>

          <div class="rounded-3xl border border-gray-200 bg-gray-50 p-4">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-gray-500">Slow response clusters</p>
            <div class="mt-3 space-y-2">
              <p v-for="item in dashboard.insights.bottlenecks.slow_response_clusters" :key="item.region" class="leading-6">
                {{ item.region }} · {{ item.average_response_time_seconds }}s average
              </p>
              <p v-if="dashboard.insights.bottlenecks.slow_response_clusters.length === 0" class="text-gray-500">No slow response cluster detected.</p>
            </div>
          </div>

          <div class="rounded-3xl border border-gray-200 bg-gray-50 p-4">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-gray-500">Low donor density regions</p>
            <div class="mt-3 space-y-2">
              <p v-for="item in dashboard.insights.bottlenecks.regions_with_low_donor_density" :key="item.region" class="leading-6">
                {{ item.region }} · {{ item.density_score }}% coverage score
              </p>
              <p v-if="dashboard.insights.bottlenecks.regions_with_low_donor_density.length === 0" class="text-gray-500">Coverage remains stable across filtered regions.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="relative col-span-12 max-w-full overflow-hidden rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm xl:col-span-4">
        <div class="space-y-2">
          <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Geographic Intelligence</p>
          <h3 class="text-xl font-black text-gray-950">Donor Distribution Coverage</h3>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-3">
          <div v-for="region in dashboard.insights.geographic_intelligence.donor_distribution" :key="region.region" class="rounded-3xl border p-4" :class="region.underserved ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-gray-50'">
            <p class="text-sm font-semibold text-gray-950">{{ region.region }}</p>
            <p class="mt-2 text-xs font-bold uppercase tracking-[0.16em] text-gray-500">Donors {{ region.donor_count }} · Requests {{ region.request_count }}</p>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-white">
              <div class="h-full rounded-full" :class="region.underserved ? 'bg-red-500' : 'bg-emerald-500'" :style="{ width: `${Math.min(region.density_score, 100)}%` }"></div>
            </div>
            <p class="mt-2 text-xs font-semibold" :class="region.underserved ? 'text-red-700' : 'text-emerald-700'">{{ region.density_score }}% density score</p>
          </div>
        </div>
      </div>
    </div>
  </AdminPageFrame>
</template>

<script setup>
import Chart from 'chart.js/auto';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import api from '../../lib/api';
import AdminPageFrame from './AdminPageFrame.vue';

const emptyDashboard = () => ({
  filters: {
    applied: {
      range: 'weekly',
      start_date: '',
      end_date: '',
      blood_type: '',
      hospital_id: '',
      urgency_level: '',
    },
    options: {
      blood_types: [],
      hospitals: [],
      urgency_levels: ['low', 'medium', 'high', 'critical'],
    },
  },
  executive_summary: {
    headline: 'System is operating at 0% efficiency.',
    summary_lines: [],
  },
  kpis: {
    average_match_time_seconds: { display_value: '0s', tone: 'neutral', trend_label: '0% vs previous period' },
    donor_response_rate: { display_value: '0%', tone: 'neutral', trend_label: '0% vs previous period' },
    successful_matches_rate: { display_value: '0%', tone: 'neutral', trend_label: '0% vs previous period' },
    drop_off_rate: { display_value: '0%', tone: 'neutral', trend_label: '0% vs previous period' },
  },
  system_health: {
    status: 'healthy',
    label: 'Healthy',
    message: 'No issues detected.',
  },
  live_activity_feed: [],
  algorithm_transparency: {
    matching_speed_trend: [],
    success_rate_by_urgency: [],
    average_score_distribution: [],
  },
  insights: {
    predictive: [],
    bottlenecks: {
      requests_with_no_matches: [],
      slow_response_clusters: [],
      regions_with_low_donor_density: [],
    },
    geographic_intelligence: {
      donor_distribution: [],
    },
  },
  meta: {
    range_label: 'Current range',
    request_count: 0,
  },
});

const dashboard = ref(emptyDashboard());
const loading = ref(false);
const error = ref('');
const filters = ref({
  range: 'weekly',
  startDate: '',
  endDate: '',
  bloodType: '',
  hospitalId: '',
  urgencyLevel: '',
});

const matchingSpeedCanvas = ref(null);
const urgencySuccessCanvas = ref(null);
const scoreDistributionCanvas = ref(null);

let matchingSpeedChart = null;
let urgencySuccessChart = null;
let scoreDistributionChart = null;

const kpiCards = computed(() => ([
  {
    key: 'match-time',
    label: 'Average Match Time',
    value: dashboard.value.kpis.average_match_time_seconds.display_value,
    trend: dashboard.value.kpis.average_match_time_seconds.trend_label,
    tone: dashboard.value.kpis.average_match_time_seconds.tone,
    shellClass: 'border-blue-200 bg-blue-50',
  },
  {
    key: 'response-rate',
    label: 'Donor Response Rate',
    value: dashboard.value.kpis.donor_response_rate.display_value,
    trend: dashboard.value.kpis.donor_response_rate.trend_label,
    tone: dashboard.value.kpis.donor_response_rate.tone,
    shellClass: 'border-emerald-200 bg-emerald-50',
  },
  {
    key: 'success-rate',
    label: 'Successful Matches',
    value: dashboard.value.kpis.successful_matches_rate.display_value,
    trend: dashboard.value.kpis.successful_matches_rate.trend_label,
    tone: dashboard.value.kpis.successful_matches_rate.tone,
    shellClass: 'border-red-200 bg-red-50',
  },
  {
    key: 'drop-off',
    label: 'Drop-off Rate',
    value: dashboard.value.kpis.drop_off_rate.display_value,
    trend: dashboard.value.kpis.drop_off_rate.trend_label,
    tone: dashboard.value.kpis.drop_off_rate.tone,
    shellClass: 'border-amber-200 bg-amber-50',
  },
]));

const chartBaseOptions = () => ({
  responsive: true,
  maintainAspectRatio: false,
  animation: false,
  plugins: {
    legend: {
      labels: {
        color: '#374151',
        font: { family: 'ui-sans-serif, system-ui, sans-serif', weight: '700' },
      },
    },
  },
  scales: {
    x: {
      ticks: { color: '#6b7280' },
      grid: { color: 'rgba(229,231,235,0.35)' },
    },
    y: {
      beginAtZero: true,
      ticks: { color: '#6b7280' },
      grid: { color: 'rgba(229,231,235,0.35)' },
    },
  },
});

const buildParams = () => {
  const params = { range: filters.value.range };

  if (filters.value.startDate) params.start_date = filters.value.startDate;
  if (filters.value.endDate) params.end_date = filters.value.endDate;
  if (filters.value.bloodType) params.blood_type = filters.value.bloodType;
  if (filters.value.hospitalId) params.hospital_id = filters.value.hospitalId;
  if (filters.value.urgencyLevel) params.urgency_level = filters.value.urgencyLevel;

  return params;
};

const destroyCharts = () => {
  [matchingSpeedChart, urgencySuccessChart, scoreDistributionChart].forEach((chart) => chart?.destroy());
  matchingSpeedChart = null;
  urgencySuccessChart = null;
  scoreDistributionChart = null;
};

const renderCharts = () => {
  destroyCharts();

  if (matchingSpeedCanvas.value) {
    matchingSpeedChart = new Chart(matchingSpeedCanvas.value, {
      type: 'line',
      data: {
        labels: dashboard.value.algorithm_transparency.matching_speed_trend.map((item) => item.label),
        datasets: [{
          label: 'Avg Match Time (s)',
          data: dashboard.value.algorithm_transparency.matching_speed_trend.map((item) => item.value),
          borderColor: '#ef4444',
          backgroundColor: 'rgba(239,68,68,0.18)',
          fill: true,
          tension: 0.32,
          pointRadius: 4,
        }],
      },
      options: chartBaseOptions(),
    });
  }

  if (urgencySuccessCanvas.value) {
    urgencySuccessChart = new Chart(urgencySuccessCanvas.value, {
      type: 'bar',
      data: {
        labels: dashboard.value.algorithm_transparency.success_rate_by_urgency.map((item) => item.label),
        datasets: [{
          label: 'Success Rate %',
          data: dashboard.value.algorithm_transparency.success_rate_by_urgency.map((item) => item.value),
          backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#ef4444'],
          borderRadius: 12,
        }],
      },
      options: chartBaseOptions(),
    });
  }

  if (scoreDistributionCanvas.value) {
    scoreDistributionChart = new Chart(scoreDistributionCanvas.value, {
      type: 'bar',
      data: {
        labels: dashboard.value.algorithm_transparency.average_score_distribution.map((item) => item.label),
        datasets: [{
          label: 'Matched Donor Scores',
          data: dashboard.value.algorithm_transparency.average_score_distribution.map((item) => item.value),
          backgroundColor: '#111827',
          borderRadius: 12,
        }],
      },
      options: chartBaseOptions(),
    });
  }
};

const loadAnalytics = async () => {
  loading.value = true;
  error.value = '';

  try {
    const response = await api.get('/admin/analytics', { params: buildParams() });
    dashboard.value = response.data?.data || emptyDashboard();
  } catch (loadError) {
    error.value = 'Unable to load analytics.';
  } finally {
    loading.value = false;
    await nextTick();
    renderCharts();
  }
};

const applyFilters = async () => {
  await loadAnalytics();
};

const kpiToneClass = (tone) => {
  if (tone === 'positive') return 'text-emerald-700';
  if (tone === 'negative') return 'text-red-700';
  return 'text-gray-500';
};

const healthBadgeClass = (status) => {
  if (status === 'critical') return 'bg-red-100 text-red-700';
  if (status === 'slowing') return 'bg-amber-100 text-amber-700';
  return 'bg-emerald-100 text-emerald-700';
};

const healthDot = (status) => {
  if (status === 'critical') return '🔴';
  if (status === 'slowing') return '🟡';
  return '🟢';
};

const feedToneClass = (tone) => {
  if (tone === 'critical') return 'bg-red-500';
  if (tone === 'warning') return 'bg-amber-500';
  if (tone === 'success') return 'bg-emerald-500';
  return 'bg-blue-500';
};

const formatDateTime = (value) => {
  if (!value) return 'Unknown';
  return new Date(value).toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
};

onMounted(loadAnalytics);
onUnmounted(destroyCharts);
</script>

<style scoped>
.filter-input {
  width: 100%;
  max-width: 100%;
  overflow: hidden;
  position: relative;
  border-radius: 1rem;
  border: 1px solid rgb(229 231 235);
  background: rgb(249 250 251);
  padding: 0.85rem 1rem;
  font-size: 0.875rem;
  outline: none;
  transition: 150ms ease;
}

.filter-input:focus {
  border-color: rgb(248 113 113);
  background: white;
  box-shadow: 0 0 0 4px rgb(254 226 226);
}
</style>