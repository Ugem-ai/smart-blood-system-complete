<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Health and eligibility</div>
          <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950">Current screening posture and donation readiness</h2>
          <p class="mt-2 text-sm text-gray-600">Eligibility windows, deferral context, and response readiness are summarized here for quick donor decisions.</p>
        </div>
        <div class="rounded-[1.5rem] border px-5 py-4" :class="eligibility.is_eligible ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
          <div class="text-xs font-semibold uppercase tracking-[0.16em]" :class="eligibility.is_eligible ? 'text-emerald-700' : 'text-amber-700'">Status</div>
          <div class="mt-2 text-xl font-black tracking-tight" :class="eligibility.is_eligible ? 'text-emerald-900' : 'text-amber-900'">{{ eligibility.is_eligible ? 'Eligible to donate' : 'Temporarily deferred' }}</div>
        </div>
      </div>

      <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-5">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Last screening result</div>
          <div class="mt-3 text-2xl font-black tracking-tight text-gray-950">{{ eligibility.last_screening_result || 'Not recorded' }}</div>
          <div class="mt-2 text-sm text-gray-600">Latest verification carried by the donor profile endpoint.</div>
        </article>
        <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-5">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Days since donation</div>
          <div class="mt-3 text-2xl font-black tracking-tight text-gray-950">{{ eligibility.days_since_last_donation ?? 'N/A' }}</div>
          <div class="mt-2 text-sm text-gray-600">Recovery interval tracking for safe repeat donation windows.</div>
        </article>
        <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-5">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Next eligible date</div>
          <div class="mt-3 text-2xl font-black tracking-tight text-gray-950">{{ eligibility.is_eligible ? 'Now' : formatDate(eligibility.next_eligible_date) }}</div>
          <div class="mt-2 text-sm text-gray-600">Use this as the earliest safe response date for new donation cycles.</div>
        </article>
      </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.1fr_0.9fr]">
      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Readiness checklist</div>
        <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Before accepting an urgent collection</h3>
        <div class="mt-5 space-y-3">
          <article v-for="item in checklist" :key="item.title" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="flex items-start gap-3">
              <div class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-xl" :class="item.pass ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'">{{ item.pass ? '✓' : '!' }}</div>
              <div>
                <div class="text-sm font-semibold text-gray-900">{{ item.title }}</div>
                <div class="mt-1 text-sm text-gray-600">{{ item.detail }}</div>
              </div>
            </div>
          </article>
        </div>
      </div>

      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Health reminders</div>
        <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Operational safety reminders</h3>
        <div class="mt-5 grid grid-cols-1 gap-3">
          <article v-for="tip in tips" :key="tip.title" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-900">{{ tip.title }}</div>
            <div class="mt-2 text-sm leading-6 text-gray-600">{{ tip.detail }}</div>
          </article>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { fetchDonorDashboard, formatDate } from '../../lib/donorPanel';

const profile = ref({});
const eligibility = ref({});

const checklist = computed(() => [
  {
    title: 'Eligibility window clear',
    detail: eligibility.value.is_eligible ? 'Your current donation interval allows a new response.' : `Wait until ${formatDate(eligibility.value.next_eligible_date)} before accepting another request.`,
    pass: Boolean(eligibility.value.is_eligible),
  },
  {
    title: 'Availability is active',
    detail: profile.value.availability ? 'Hospitals can route requests to you right now.' : 'Enable availability so emergency coordinators can contact you.',
    pass: Boolean(profile.value.availability),
  },
  {
    title: 'Privacy consent recorded',
    detail: profile.value.privacy_consent_at ? `Consent recorded on ${formatDate(profile.value.privacy_consent_at)}.` : 'Consent timestamp is not currently visible in your donor record.',
    pass: Boolean(profile.value.privacy_consent_at),
  },
]);

const tips = [
  { title: 'Do not donate while unwell', detail: 'Fever, infection symptoms, or active recovery should pause emergency response even when the system shows open availability.' },
  { title: 'Hydrate before dispatch', detail: 'Carry water and a quick meal before traveling to a hospital or collection center.' },
  { title: 'Bring valid identification', detail: 'Hospitals may verify your donor identity before final collection and release coordination.' },
  { title: 'Report screening changes', detail: 'If your health status changes, update availability instead of accepting then canceling later.' },
];

async function loadData() {
  const payload = await fetchDonorDashboard();
  profile.value = payload.profile;
  eligibility.value = payload.eligibility;
}

onMounted(loadData);
</script>