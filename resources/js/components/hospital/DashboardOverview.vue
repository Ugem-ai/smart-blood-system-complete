<template>
  <div class="space-y-6">
    <section class="overflow-hidden rounded-[2rem] border border-red-100 bg-[radial-gradient(circle_at_top_left,_rgba(254,226,226,0.95),_rgba(255,255,255,1)_56%)] p-6 shadow-sm">
      <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
        <div class="max-w-3xl">
          <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-red-500">Hospital dashboard</p>
          <h2 class="mt-2 text-2xl font-semibold tracking-tight text-gray-900 sm:text-3xl">Emergency blood coordination overview</h2>
          <p class="mt-3 text-sm leading-6 text-gray-600 sm:text-base">Track active shortages, donor response pressure, and hospital operations from a single command surface designed for time-sensitive coordination.</p>
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:w-[26rem]">
          <div class="rounded-[1.5rem] border border-red-200 bg-white/90 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-red-500">Critical load</div>
            <div class="mt-2 text-3xl font-semibold text-gray-900">{{ criticalCount }}</div>
            <div class="mt-1 text-xs text-gray-500">Requests flagged as emergency or critical urgency.</div>
          </div>
          <div class="rounded-[1.5rem] border border-gray-200 bg-white/90 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-500">Open matching</div>
            <div class="mt-2 text-3xl font-semibold text-gray-900">{{ matchingCount }}</div>
            <div class="mt-1 text-xs text-gray-500">Requests still waiting for enough donors to respond.</div>
          </div>
        </div>
      </div>
    </section>

    <section v-if="error" class="rounded-[1.5rem] border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-700">
      {{ error }}
    </section>

    <section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
      <article
        v-for="metric in metrics"
        :key="metric.label"
        class="rounded-[1.5rem] border border-gray-200 bg-white px-5 py-5 shadow-sm"
      >
        <div class="flex items-center justify-between gap-4">
          <div class="text-sm font-medium text-gray-500">{{ metric.label }}</div>
          <div class="text-2xl">{{ metric.icon }}</div>
        </div>
        <div class="mt-4 text-3xl font-semibold tracking-tight text-gray-900">{{ metric.value }}</div>
        <div class="mt-2 text-xs leading-5 text-gray-500">{{ metric.detail }}</div>
      </article>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.35fr_0.95fr]">
      <div class="rounded-[1.75rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Live operational alerts</h3>
            <p class="mt-1 text-sm text-gray-500">Inventory shortages and expiring requests that need immediate staff attention.</p>
          </div>
          <button
            type="button"
            class="rounded-full border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50"
            @click="$emit('navigate', 'emergency-escalation')"
          >
            Open escalation
          </button>
        </div>

        <div v-if="loading" class="mt-6 text-sm text-gray-500">Loading command-center alerts...</div>
        <div v-else-if="alerts.length === 0" class="mt-6 rounded-[1.25rem] border border-dashed border-gray-200 px-4 py-6 text-sm text-gray-500">
          No urgent alerts detected. Inventory and request timings are currently within expected thresholds.
        </div>
        <div v-else class="mt-6 space-y-3">
          <article
            v-for="alert in alerts"
            :key="alert.id"
            class="rounded-[1.25rem] border px-4 py-4"
            :class="alert.tone === 'critical' ? 'border-red-200 bg-red-50' : 'border-amber-200 bg-amber-50'"
          >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <div class="text-sm font-semibold text-gray-900">{{ alert.title }}</div>
                <div class="mt-1 text-sm text-gray-600">{{ alert.detail }}</div>
              </div>
              <span class="inline-flex rounded-full px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em]" :class="alert.tone === 'critical' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'">
                {{ alert.meta }}
              </span>
            </div>
          </article>
        </div>
      </div>

      <div class="rounded-[1.75rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Quick actions</h3>
            <p class="mt-1 text-sm text-gray-500">Move directly into the next operational workflow.</p>
          </div>
        </div>

        <div class="mt-6 grid gap-3">
          <button
            v-for="action in quickActions"
            :key="action.id"
            type="button"
            class="rounded-[1.25rem] border border-gray-200 px-4 py-4 text-left transition hover:border-red-200 hover:bg-red-50"
            @click="$emit('navigate', action.id)"
          >
            <div class="flex items-center gap-3">
              <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gray-100 text-xl">{{ action.icon }}</div>
              <div>
                <div class="text-sm font-semibold text-gray-900">{{ action.label }}</div>
                <div class="mt-1 text-xs leading-5 text-gray-500">{{ action.detail }}</div>
              </div>
            </div>
          </button>
        </div>
      </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
      <div class="rounded-[1.75rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex items-center justify-between gap-4">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Recent activity stream</h3>
            <p class="mt-1 text-sm text-gray-500">Latest request, escalation, and coordination events captured by the hospital workspace.</p>
          </div>
          <button
            type="button"
            class="rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 transition hover:border-red-200 hover:text-red-600"
            @click="$emit('navigate', 'audit-logs')"
          >
            View audit logs
          </button>
        </div>

        <div v-if="activity.length === 0" class="mt-6 rounded-[1.25rem] border border-dashed border-gray-200 px-4 py-6 text-sm text-gray-500">
          No recent activity found for this hospital account.
        </div>
        <div v-else class="mt-6 space-y-4">
          <article v-for="item in activity" :key="item.id" class="flex gap-4 rounded-[1.25rem] border border-gray-100 px-4 py-4">
            <div class="mt-1 h-3 w-3 flex-shrink-0 rounded-full" :class="severityClass(item.severity)"></div>
            <div class="min-w-0 flex-1">
              <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm font-semibold text-gray-900">{{ item.title }}</div>
                <div class="text-xs text-gray-500">{{ item.timestamp_label }}</div>
              </div>
              <div class="mt-1 text-sm text-gray-600">{{ item.detail }}</div>
              <div class="mt-2 text-xs font-medium uppercase tracking-[0.14em] text-gray-400">{{ item.actor }} • {{ item.category }}</div>
            </div>
          </article>
        </div>
      </div>

      <div class="space-y-6">
        <div class="rounded-[1.75rem] border border-gray-200 bg-white p-6 shadow-sm">
          <h3 class="text-lg font-semibold text-gray-900">Request status distribution</h3>
          <div class="mt-5 space-y-3">
            <div v-for="item in statusBreakdown" :key="item.label" class="space-y-1.5">
              <div class="flex items-center justify-between text-sm text-gray-600">
                <span>{{ item.label }}</span>
                <span class="font-semibold text-gray-900">{{ item.value }}</span>
              </div>
              <div class="h-2 rounded-full bg-gray-100">
                <div class="h-2 rounded-full bg-red-500" :style="{ width: percentage(item.value, requestCount) }"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-[1.75rem] border border-gray-200 bg-white p-6 shadow-sm">
          <h3 class="text-lg font-semibold text-gray-900">Urgency distribution</h3>
          <div class="mt-5 grid grid-cols-2 gap-3">
            <article
              v-for="item in urgencyBreakdown"
              :key="item.label"
              class="rounded-[1.25rem] border px-4 py-4"
              :class="urgencyCardClass(item.label)"
            >
              <div class="text-[11px] font-semibold uppercase tracking-[0.16em]">{{ item.label }}</div>
              <div class="mt-2 text-2xl font-semibold">{{ item.value }}</div>
            </article>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import {
  buildHospitalAlerts,
  buildHospitalMetrics,
  buildRecentActivity,
  buildStatusBreakdown,
  buildUrgencyBreakdown,
  fetchHospitalActivityLog,
  fetchHospitalProfile,
  fetchHospitalRequests,
} from '../../lib/hospitalPanel';

defineEmits(['navigate']);

const loading = ref(true);
const error = ref('');
const profile = ref({});
const requests = ref([]);
const logs = ref([]);

const metrics = computed(() => buildHospitalMetrics(profile.value, requests.value));
const alerts = computed(() => buildHospitalAlerts(profile.value, requests.value));
const activity = computed(() => buildRecentActivity(logs.value, requests.value));
const statusBreakdown = computed(() => buildStatusBreakdown(requests.value));
const urgencyBreakdown = computed(() => buildUrgencyBreakdown(requests.value));

const requestCount = computed(() => Math.max(requests.value.length, 1));
const criticalCount = computed(() => requests.value.filter((request) => request.urgency_level === 'critical' || request.is_emergency).length);
const matchingCount = computed(() => requests.value.filter((request) => request.status === 'matching').length);

const quickActions = [
  {
    id: 'create-request',
    label: 'Create blood request',
    detail: 'Launch a new shortage request with urgency, units, and radius controls.',
    icon: '📝',
  },
  {
    id: 'active-requests',
    label: 'Review active requests',
    detail: 'Check live case progress, donor counts, and open coordination gaps.',
    icon: '🩸',
  },
  {
    id: 'broadcast-requests',
    label: 'Broadcast urgent shortage',
    detail: 'Escalate a critical request across the donor response network.',
    icon: '📣',
  },
  {
    id: 'matched-donors',
    label: 'Inspect matched donors',
    detail: 'Review response strength, reliability, and matching confidence.',
    icon: '🧩',
  },
];

function severityClass(severity) {
  if (severity === 'high' || severity === 'critical') return 'bg-red-500';
  if (severity === 'warning') return 'bg-amber-500';
  return 'bg-emerald-500';
}

function urgencyCardClass(label) {
  if (label === 'Critical') return 'border-red-200 bg-red-50 text-red-700';
  if (label === 'High') return 'border-orange-200 bg-orange-50 text-orange-700';
  if (label === 'Medium') return 'border-amber-200 bg-amber-50 text-amber-700';
  return 'border-emerald-200 bg-emerald-50 text-emerald-700';
}

function percentage(value, total) {
  return `${Math.max(6, Math.round((value / total) * 100))}%`;
}

onMounted(async () => {
  loading.value = true;
  error.value = '';

  try {
    const [profilePayload, requestPayload, activityPayload] = await Promise.all([
      fetchHospitalProfile(),
      fetchHospitalRequests(),
      fetchHospitalActivityLog({ limit: 12 }),
    ]);

    profile.value = profilePayload;
    requests.value = requestPayload;
    logs.value = activityPayload;
  } catch (err) {
    error.value = 'Failed to load hospital command center overview.';
    console.error(err);
  } finally {
    loading.value = false;
  }
});
</script>
