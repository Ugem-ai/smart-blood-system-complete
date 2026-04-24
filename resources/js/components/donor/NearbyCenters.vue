<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Nearby centers</div>
          <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950">Hospitals and donation sites in your emergency network</h2>
          <p class="mt-2 text-sm text-gray-600">Derived from active requests and your recorded donation history so you can reopen familiar destinations quickly.</p>
        </div>
        <button type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="loadCenters">Refresh centers</button>
      </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div v-if="loading" class="space-y-4 animate-pulse">
          <div v-for="index in 4" :key="index" class="h-28 rounded-[1.5rem] bg-gray-100"></div>
        </div>

        <div v-else-if="centers.length === 0" class="rounded-[1.75rem] border border-dashed border-gray-200 bg-gray-50 px-6 py-14 text-center">
          <div class="text-lg font-semibold text-gray-900">No network centers available yet</div>
          <div class="mt-2 text-sm text-gray-500">This list will populate from live requests and your existing donation records.</div>
        </div>

        <div v-else class="space-y-4">
          <article v-for="center in centers" :key="`${center.name}-${center.address}`" class="rounded-[1.5rem] border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
              <div class="min-w-0 flex-1">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Network location</div>
                <h3 class="mt-2 text-xl font-bold text-gray-950">{{ center.name }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ center.address }}</p>
                <div class="mt-3 inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-gray-600">{{ center.distance_display }}</div>
              </div>
              <a :href="center.directions_url" target="_blank" rel="noreferrer" class="inline-flex items-center justify-center rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700">Open directions</a>
            </div>
          </article>
        </div>
      </div>

      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Travel posture</div>
        <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Dispatch guidance</h3>
        <div class="mt-5 grid grid-cols-1 gap-3">
          <article v-for="tip in travelTips" :key="tip.title" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-900">{{ tip.title }}</div>
            <div class="mt-2 text-sm leading-6 text-gray-600">{{ tip.detail }}</div>
          </article>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { buildNearbyCenters, fetchDonorDashboard } from '../../lib/donorPanel';

const loading = ref(true);
const centers = ref([]);

const travelTips = [
  { title: 'Use the closest active partner site', detail: 'Critical requests should prioritize the shortest safe travel path when multiple centers are available.' },
  { title: 'Review address before departure', detail: 'Some requests are logged by city only, so confirm the exact hospital site before dispatch.' },
  { title: 'Expect identity verification', detail: 'Bring your donor credentials or identification for faster intake at arrival.' },
];

async function loadCenters() {
  loading.value = true;
  try {
    const payload = await fetchDonorDashboard();
    centers.value = buildNearbyCenters(payload.requests, payload.history, payload.profile);
  } finally {
    loading.value = false;
  }
}

onMounted(loadCenters);
</script>