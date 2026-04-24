<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Availability and scheduling</div>
          <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950">Control when emergency teams can reach you</h2>
          <p class="mt-2 text-sm text-gray-600">Availability affects whether hospitals can dispatch urgent requests to your donor profile.</p>
        </div>
        <button type="button" class="rounded-2xl px-5 py-3 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-50" :class="availability ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-gray-900 hover:bg-gray-800'" :disabled="saving" @click="toggleAvailability">
          {{ saving ? 'Updating...' : availability ? 'Available for requests' : 'Currently unavailable' }}
        </button>
      </div>

      <div v-if="message" class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ message }}</div>
      <div v-if="error" class="mt-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

      <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-3">
        <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-5">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Current status</div>
          <div class="mt-3 text-2xl font-black tracking-tight text-gray-950">{{ availability ? 'Ready' : 'Paused' }}</div>
          <div class="mt-2 text-sm text-gray-600">{{ availability ? 'You can receive emergency requests now.' : 'Hospitals will not route new requests to you.' }}</div>
        </article>
        <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-5">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Last donation</div>
          <div class="mt-3 text-2xl font-black tracking-tight text-gray-950">{{ formatDate(profile.last_donation_date) }}</div>
          <div class="mt-2 text-sm text-gray-600">{{ eligibility.days_since_last_donation ?? 'No' }} days since your last completed donation.</div>
        </article>
        <article class="rounded-[1.5rem] border p-5" :class="eligibility.is_eligible ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
          <div class="text-xs font-semibold uppercase tracking-[0.16em]" :class="eligibility.is_eligible ? 'text-emerald-700' : 'text-amber-700'">Eligibility window</div>
          <div class="mt-3 text-2xl font-black tracking-tight" :class="eligibility.is_eligible ? 'text-emerald-900' : 'text-amber-900'">{{ eligibility.is_eligible ? 'Open now' : formatDate(eligibility.next_eligible_date) }}</div>
          <div class="mt-2 text-sm" :class="eligibility.is_eligible ? 'text-emerald-800' : 'text-amber-800'">{{ eligibility.is_eligible ? 'Your recovery interval is clear.' : 'Rest period still active before your next donation.' }}</div>
        </article>
      </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[0.9fr_1.1fr]">
      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Preferred schedule</div>
        <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Weekly donor readiness plan</h3>
        <div class="mt-5 space-y-3">
          <div v-for="slot in schedule" :key="slot.day" class="flex items-center justify-between rounded-[1.25rem] border border-gray-200 bg-gray-50 px-4 py-3">
            <div>
              <div class="text-sm font-semibold text-gray-900">{{ slot.day }}</div>
              <div class="text-xs text-gray-500">{{ slot.window }}</div>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="slot.active ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-200 text-gray-600'">{{ slot.active ? 'Ready' : 'Standby' }}</span>
          </div>
        </div>
      </div>

      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Response guidance</div>
        <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Operational reminders before you accept a request</h3>
        <div class="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2">
          <article v-for="item in guidance" :key="item.title" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-900">{{ item.title }}</div>
            <div class="mt-2 text-sm leading-6 text-gray-600">{{ item.detail }}</div>
          </article>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { fetchDonorDashboard, formatDate, updateDonorAvailability } from '../../lib/donorPanel';
import { showDonorToast } from '../../lib/donorToast';

const availability = ref(false);
const saving = ref(false);
const message = ref('');
const error = ref('');
const profile = ref({});
const eligibility = ref({});

const schedule = [
  { day: 'Monday', window: '7:00 AM - 12:00 PM', active: true },
  { day: 'Wednesday', window: '1:00 PM - 5:00 PM', active: true },
  { day: 'Friday', window: '8:00 AM - 11:00 AM', active: false },
  { day: 'Saturday', window: 'Flexible emergency response', active: true },
];

const guidance = [
  { title: 'Confirm travel readiness', detail: 'Review hospital distance and directions before accepting a critical request.' },
  { title: 'Protect recovery intervals', detail: 'If you are still in a deferral period, keep availability disabled until you are eligible again.' },
  { title: 'Keep contact channels open', detail: 'Emergency coordinators may call after you accept, so keep your phone reachable.' },
  { title: 'Hydrate and eat first', detail: 'Basic readiness steps reduce failed appointments during urgent collections.' },
];

async function loadAvailability() {
  const payload = await fetchDonorDashboard();
  profile.value = payload.profile;
  eligibility.value = payload.eligibility;
  availability.value = Boolean(payload.profile.availability);
}

async function toggleAvailability() {
  saving.value = true;
  error.value = '';
  message.value = '';

  try {
    const updated = await updateDonorAvailability(!availability.value);
    availability.value = Boolean(updated.availability);
    message.value = availability.value
      ? 'Emergency routing is now enabled for your donor profile.'
      : 'Emergency routing is paused. New requests will be held back.';
    showDonorToast(message.value);
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Unable to update donor availability.';
    showDonorToast(error.value, 'error');
  } finally {
    saving.value = false;
  }
}

onMounted(loadAvailability);
</script>
