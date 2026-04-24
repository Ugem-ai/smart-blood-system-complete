<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Donation history</div>
          <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950">Your completed donation record and impact trail</h2>
          <p class="mt-2 text-sm text-gray-600">Track where you donated, how often you responded, and the approximate number of patients reached.</p>
        </div>
        <button type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="loadHistory">Refresh history</button>
      </div>

      <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-5">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Total donations</div>
          <div class="mt-3 text-3xl font-black tracking-tight text-gray-950">{{ donations.length }}</div>
          <div class="mt-2 text-sm text-gray-600">Lifetime completed donor records on this account.</div>
        </article>
        <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-5">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Units donated</div>
          <div class="mt-3 text-3xl font-black tracking-tight text-gray-950">{{ totalUnits }}</div>
          <div class="mt-2 text-sm text-gray-600">Combined units across all recorded donations.</div>
        </article>
        <article class="rounded-[1.5rem] border border-red-100 bg-red-50 p-5">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Lives impacted</div>
          <div class="mt-3 text-3xl font-black tracking-tight text-red-700">{{ estimatedLivesSaved }}</div>
          <div class="mt-2 text-sm text-red-700">Estimated potential recipients helped by your donations.</div>
        </article>
      </div>
    </section>

    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div v-if="loading" class="space-y-4 animate-pulse">
        <div v-for="index in 4" :key="index" class="h-32 rounded-[1.5rem] bg-gray-100"></div>
      </div>

      <div v-else-if="donations.length === 0" class="rounded-[1.75rem] border border-dashed border-gray-200 bg-gray-50 px-6 py-14 text-center">
        <div class="text-lg font-semibold text-gray-900">No donations recorded yet</div>
        <div class="mt-2 text-sm text-gray-500">Your first completed donation will appear here once the collection is logged.</div>
      </div>

      <div v-else class="space-y-4">
        <article v-for="donation in donations" :key="donation.id" class="rounded-[1.5rem] border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full bg-red-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-red-600">{{ donation.blood_type }}</span>
                <span class="inline-flex rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-emerald-700">{{ donation.status }}</span>
              </div>
              <h3 class="mt-3 text-xl font-bold text-gray-950">{{ donation.hospital_name }}</h3>
              <p class="mt-2 text-sm text-gray-600">{{ donation.location }}</p>
              <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-gray-500">
                <span class="rounded-full bg-gray-100 px-3 py-1">{{ formatDate(donation.donation_date) }}</span>
                <span class="rounded-full bg-gray-100 px-3 py-1">{{ donation.units }} unit{{ donation.units === 1 ? '' : 's' }}</span>
                <span class="rounded-full bg-gray-100 px-3 py-1">{{ donation.certificate_label }}</span>
              </div>
            </div>

            <div class="grid grid-cols-2 gap-3 lg:w-72">
              <div class="rounded-2xl bg-gray-50 p-4 text-center">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Units</div>
                <div class="mt-2 text-2xl font-black tracking-tight text-gray-950">{{ donation.units }}</div>
              </div>
              <div class="rounded-2xl bg-gray-50 p-4 text-center">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Impact</div>
                <div class="mt-2 text-2xl font-black tracking-tight text-gray-950">{{ Math.max(1, donation.units) * 3 }}</div>
              </div>
            </div>
          </div>
        </article>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { fetchDonorDashboard, formatDate } from '../../lib/donorPanel';

const donations = ref([]);
const loading = ref(true);

const totalUnits = computed(() => donations.value.reduce((sum, donation) => sum + (donation.units || 0), 0));
const estimatedLivesSaved = computed(() => totalUnits.value * 3);

async function loadHistory() {
  loading.value = true;

  try {
    const payload = await fetchDonorDashboard();
    donations.value = [...payload.history].sort((left, right) => new Date(right.donation_date) - new Date(left.donation_date));
  } finally {
    loading.value = false;
  }
}

onMounted(loadHistory);
</script>
