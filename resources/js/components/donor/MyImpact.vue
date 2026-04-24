<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div>
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">My impact</div>
        <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950 sm:text-4xl">Measure the effect of your donor activity</h2>
        <p class="mt-2 text-sm text-gray-600">This view summarizes lifetime donations, lives potentially supported, streak consistency, and request responsiveness.</p>
      </div>

      <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article v-for="card in cards" :key="card.label" class="rounded-[1.5rem] border p-5" :class="card.tone">
          <div class="text-xs font-semibold uppercase tracking-[0.16em]" :class="card.labelTone">{{ card.label }}</div>
          <div class="mt-3 text-3xl font-black tracking-tight text-gray-950">{{ card.value }}</div>
          <div class="mt-2 text-sm text-gray-600">{{ card.detail }}</div>
        </article>
      </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Impact highlights</div>
        <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">What your donor record says</h3>
        <div class="mt-5 space-y-3">
          <article v-for="item in highlights" :key="item.title" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-900">{{ item.title }}</div>
            <div class="mt-2 text-sm leading-6 text-gray-600">{{ item.detail }}</div>
          </article>
        </div>
      </div>

      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Recent contributions</div>
        <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Last completed donation activity</h3>
        <div v-if="history.length === 0" class="mt-5 rounded-[1.5rem] border border-dashed border-gray-200 bg-gray-50 px-5 py-12 text-center text-sm text-gray-500">No donation record available yet.</div>
        <div v-else class="mt-5 space-y-3">
          <article v-for="donation in history.slice(0, 4)" :key="donation.id" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                <div class="text-sm font-semibold text-gray-900">{{ donation.hospital_name }}</div>
                <div class="mt-1 text-sm text-gray-600">{{ donation.location }}</div>
              </div>
              <div class="text-left sm:text-right">
                <div class="text-sm font-semibold text-gray-900">{{ donation.units }} unit{{ donation.units === 1 ? '' : 's' }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ formatDate(donation.donation_date) }}</div>
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
import { computeDonationStreak, fetchDonorDashboard, formatDate } from '../../lib/donorPanel';

const stats = ref({});
const history = ref([]);

const cards = computed(() => [
  {
    label: 'Total donations',
    value: stats.value.total_donations || history.value.length,
    detail: 'Recorded lifetime donation count',
    tone: 'border-gray-200 bg-gray-50',
    labelTone: 'text-gray-400',
  },
  {
    label: 'Lives impacted',
    value: stats.value.lives_saved_estimate || history.value.reduce((sum, donation) => sum + ((donation.units || 1) * 3), 0),
    detail: 'Estimated potential recipients helped',
    tone: 'border-red-100 bg-red-50',
    labelTone: 'text-red-500',
  },
  {
    label: 'Response rate',
    value: `${stats.value.response_rate || 0}%`,
    detail: 'How often you act on routed requests',
    tone: 'border-gray-200 bg-gray-50',
    labelTone: 'text-gray-400',
  },
  {
    label: 'Donation streak',
    value: computeDonationStreak(history.value),
    detail: 'Consecutive annual donation continuity',
    tone: 'border-gray-200 bg-gray-50',
    labelTone: 'text-gray-400',
  },
]);

const highlights = computed(() => [
  {
    title: 'Emergency reliability',
    detail: `${stats.value.response_rate || 0}% response rate paired with ${stats.value.accepted_requests || 0} accepted request${(stats.value.accepted_requests || 0) === 1 ? '' : 's'}.`,
  },
  {
    title: 'Donation continuity',
    detail: `${computeDonationStreak(history.value)} year streak based on recorded donation history.`,
  },
  {
    title: 'Operational volume',
    detail: `${history.value.reduce((sum, donation) => sum + (donation.units || 0), 0)} units donated across ${history.value.length} collection event${history.value.length === 1 ? '' : 's'}.`,
  },
]);

async function loadImpact() {
  const payload = await fetchDonorDashboard();
  stats.value = payload.stats;
  history.value = payload.history;
}

onMounted(loadImpact);
</script>