<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Broadcast requests</div>
          <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950 sm:text-4xl">Send mass alerts for high-priority blood shortages</h2>
          <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">This fast lane creates an emergency request with donor broadcast posture enabled. Matching and notification dispatch still run through the normal hospital workflow so the request remains auditable.</p>
        </div>
        <div class="rounded-[1.5rem] border border-red-100 bg-red-50 px-5 py-4">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Dispatch mode</div>
          <div class="mt-2 text-lg font-bold text-red-700">Immediate matching + notifications</div>
        </div>
      </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[0.95fr_1.05fr]">
      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <label>
            <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Blood type</div>
            <select v-model="form.blood_type" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100">
              <option value="">Select blood type</option>
              <option v-for="type in bloodTypes" :key="type" :value="type">{{ type }}</option>
            </select>
          </label>
          <label>
            <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Units required</div>
            <input v-model.number="form.units_required" type="number" min="1" max="20" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100" />
          </label>
          <label>
            <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Location / Department</div>
            <input v-model="form.city" type="text" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100" placeholder="e.g. ER - Manila" />
          </label>
          <label>
            <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Search radius</div>
            <input v-model.number="form.distance_limit_km" type="number" min="10" max="500" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100" />
          </label>
        </div>

        <label class="mt-4 block">
          <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Broadcast notes</div>
          <textarea v-model="form.reason" rows="5" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100" placeholder="Short operational note for the alert payload"></textarea>
        </label>

        <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
          <label class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700"><input checked disabled type="checkbox" class="mr-2" /> SMS dispatch</label>
          <label class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700"><input checked disabled type="checkbox" class="mr-2" /> In-app notification</label>
          <label class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700"><input checked disabled type="checkbox" class="mr-2" /> Compatible donors only</label>
        </div>

        <button type="button" class="mt-6 inline-flex w-full items-center justify-center rounded-2xl bg-red-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="submitting || !canSubmit" @click="submitBroadcast">
          {{ submitting ? 'Dispatching broadcast...' : 'Send broadcast request' }}
        </button>
        <div v-if="message" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ message }}</div>
      </div>

      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Message preview</div>
        <div class="mt-4 rounded-[1.5rem] border border-gray-200 bg-gray-50 p-5">
          <div class="text-sm font-semibold text-gray-900">Emergency blood request</div>
          <div class="mt-3 text-sm leading-6 text-gray-600">{{ previewMessage }}</div>
        </div>

        <div class="mt-6 space-y-3">
          <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-900">Operational guardrail</div>
            <div class="mt-2 text-sm text-gray-600">Broadcast requests remain normal blood-request records, so matching, activity logging, and donor responses all stay inside the hospital audit trail.</div>
          </article>
          <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-900">Minimal clicks</div>
            <div class="mt-2 text-sm text-gray-600">The form pre-sets critical urgency and emergency mode so hospital staff can dispatch without opening the longer request form.</div>
          </article>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue';
import { createHospitalRequest } from '../../lib/hospitalPanel';

const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const submitting = ref(false);
const message = ref('');
const form = reactive({
  blood_type: '',
  units_required: 1,
  city: '',
  province: 'Metro Manila',
  distance_limit_km: 60,
  reason: '',
});

const canSubmit = computed(() => form.blood_type && form.units_required > 0 && form.city);
const previewMessage = computed(() => `${form.blood_type || 'Blood type'} urgently needed in ${form.city || 'hospital zone'} for ${form.units_required} unit${form.units_required === 1 ? '' : 's'}. ${form.reason || 'Emergency dispatch requested by hospital command center.'}`);

async function submitBroadcast() {
  if (!canSubmit.value) return;

  submitting.value = true;
  message.value = '';

  try {
    const response = await createHospitalRequest({
      blood_type: form.blood_type,
      component: 'Whole Blood',
      units_required: form.units_required,
      urgency_level: 'critical',
      city: form.city,
      province: form.province,
      distance_limit_km: form.distance_limit_km,
      reason: form.reason || 'Broadcast request initiated from hospital command center.',
      is_emergency: true,
    });
    const request = response.data;
    message.value = `Broadcast request ${request?.case_id || request?.id} created. Matching and notification dispatch have started immediately.`;
  } finally {
    submitting.value = false;
  }
}
</script>
