<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Analytics</div>
          <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950 sm:text-4xl">Operational insight for blood coordination</h2>
          <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">Track demand, response performance, and request resolution across your hospital’s live and recent blood requests.</p>
        </div>
        <select v-model="windowDays" class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100">
          <option :value="7">Daily view</option>
          <option :value="30">Weekly view</option>
          <option :value="90">Monthly view</option>
        </select>
      </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-3">
      <article v-for="card in summaryCards" :key="card.label" class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">{{ card.label }}</div>
        <div class="mt-3 text-4xl font-black tracking-tight text-gray-950">{{ card.value }}</div>
        <div class="mt-2 text-sm text-gray-600">{{ card.detail }}</div>
      </article>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Blood demand trends</div>
        <div class="mt-5 space-y-3">
          <div v-for="item in demandSeries" :key="item.label" class="grid grid-cols-[5rem_1fr_3rem] items-center gap-3">
            <div class="text-sm font-medium text-gray-600">{{ item.label }}</div>
            <div class="h-3 rounded-full bg-gray-100">
              <div class="h-3 rounded-full bg-red-600" :style="{ width: `${Math.max(8, item.width)}%` }"></div>
            </div>
            <div class="text-sm font-semibold text-gray-900">{{ item.value }}</div>
          </div>
        </div>
      </div>

      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Request mix</div>
        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-900">Urgency distribution</div>
            <div class="mt-4 space-y-3">
              <div v-for="item in urgencyBreakdown" :key="item.label" class="flex items-center justify-between gap-3">
                <span class="text-sm text-gray-600">{{ item.label }}</span>
                <span class="text-sm font-semibold text-gray-900">{{ item.value }}</span>
              </div>
            </div>
          </article>
          <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-900">Status distribution</div>
            <div class="mt-4 space-y-3">
              <div v-for="item in statusBreakdown" :key="item.label" class="flex items-center justify-between gap-3">
                <span class="text-sm text-gray-600">{{ item.label }}</span>
                <span class="text-sm font-semibold text-gray-900">{{ item.value }}</span>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { buildAnalyticsSeries, buildStatusBreakdown, buildUrgencyBreakdown, fetchHospitalRequests } from '../../lib/hospitalPanel';

const windowDays = ref(30);
const requests = ref([]);

const summaryCards = computed(() => {
  const fulfilled = requests.value.filter((request) => request.status === 'fulfilled' || request.status === 'completed');
  const accepted = requests.value.reduce((sum, request) => sum + request.accepted_donors, 0);
  const responseRate = requests.value.reduce((sum, request) => sum + request.responses_received, 0);
  const notified = requests.value.reduce((sum, request) => sum + request.notifications_sent, 0);

  return [
    { label: 'Matching success rate', value: `${requests.value.length === 0 ? 0 : Math.round((fulfilled.length / requests.value.length) * 100)}%`, detail: 'Requests reaching fulfilled or completed state' },
    { label: 'Donor response rate', value: `${notified === 0 ? 0 : Math.round((responseRate / notified) * 100)}%`, detail: 'Responses received against notification volume' },
    { label: 'Average fulfillment', value: `${fulfilled.reduce((sum, request) => sum + request.fulfilled_units, 0)} units`, detail: `${accepted} accepted donors across tracked requests` },
  ];
});

const demandSeries = computed(() => {
  const series = buildAnalyticsSeries(requests.value, windowDays.value);
  const max = Math.max(...series.map((item) => item.value), 1);

  return series.map((item) => ({
    ...item,
    width: Math.round((item.value / max) * 100),
  }));
});

const urgencyBreakdown = computed(() => buildUrgencyBreakdown(requests.value));
const statusBreakdown = computed(() => buildStatusBreakdown(requests.value));

async function loadAnalytics() {
  requests.value = await fetchHospitalRequests({ per_page: 80 });
}

onMounted(loadAnalytics);
</script>
