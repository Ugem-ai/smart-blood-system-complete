<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-red-100 bg-[linear-gradient(135deg,_rgba(254,242,242,1),_rgba(255,255,255,1))] p-6 shadow-sm">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Emergency escalation</div>
          <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950 sm:text-4xl">Aggressive controls for critical hospital cases</h2>
          <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">Escalate urgent requests, expand search radius, and re-run matching with emergency posture.</p>
        </div>
        <div class="rounded-[1.5rem] border border-red-200 bg-white px-5 py-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Active escalations</div>
          <div class="mt-2 text-3xl font-black tracking-tight text-gray-950">{{ emergencyRequests.length }}</div>
          <div class="mt-1 text-sm text-gray-500">Requests already running in emergency mode</div>
        </div>
      </div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[0.9fr_1.1fr]">
      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Select active request</div>
        <select v-model="selectedRequestId" class="mt-4 w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100">
          <option value="">Choose request for escalation</option>
          <option v-for="request in activeRequests" :key="request.id" :value="String(request.id)">
            {{ request.case_id }} • {{ request.blood_type }} • {{ request.city }}
          </option>
        </select>

        <div v-if="selectedRequest" class="mt-6 space-y-4">
          <div class="rounded-[1.5rem] border bg-gray-50 p-4" :class="urgencyTheme[selectedRequest.urgency_level]?.border || 'border-gray-200'">
            <div class="flex flex-wrap items-center gap-2">
              <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="urgencyTheme[selectedRequest.urgency_level]?.badge">{{ selectedRequest.urgency_label }}</span>
              <span class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">{{ selectedRequest.created_relative }}</span>
            </div>
            <div class="mt-3 text-lg font-bold text-gray-950">{{ selectedRequest.case_id }}</div>
            <div class="mt-1 text-sm text-gray-600">{{ selectedRequest.units_required }} unit{{ selectedRequest.units_required === 1 ? '' : 's' }} needed in {{ selectedRequest.city }}</div>
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <label>
              <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Escalation level</div>
              <select v-model="form.escalationLevel" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100">
                <option value="monitor">Monitor</option>
                <option value="critical">Critical</option>
                <option value="broadcast">Broadcast all compatible donors</option>
              </select>
            </label>
            <label>
              <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Expanded search radius</div>
              <input v-model.number="form.distance_limit_km" type="number" min="10" max="500" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100" />
            </label>
          </div>

          <label class="flex items-start gap-3 rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <input v-model="form.is_emergency" type="checkbox" class="mt-1 h-4 w-4 rounded border-gray-300 text-red-600 focus:ring-red-500" />
            <div>
              <div class="text-sm font-semibold text-gray-900">Emergency broadcast toggle</div>
              <div class="mt-1 text-sm text-gray-600">Mark this request as emergency and re-run PAST-Match with updated urgency and radius.</div>
            </div>
          </label>

          <button type="button" class="inline-flex w-full items-center justify-center rounded-2xl bg-red-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving" @click="applyEscalation">
            {{ saving ? 'Applying escalation...' : 'Apply escalation controls' }}
          </button>
          <div v-if="message" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ message }}</div>
        </div>
      </div>

      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Escalation playbook</div>
        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
          <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-900">Stage 1</div>
            <div class="mt-2 text-sm text-gray-600">Keep the request in matching and tighten operational follow-up.</div>
          </article>
          <article class="rounded-[1.5rem] border border-red-100 bg-red-50 p-4">
            <div class="text-sm font-semibold text-red-700">Stage 2</div>
            <div class="mt-2 text-sm text-red-800">Promote to critical urgency and widen the search radius.</div>
          </article>
          <article class="rounded-[1.5rem] border border-orange-100 bg-orange-50 p-4">
            <div class="text-sm font-semibold text-orange-700">Stage 3</div>
            <div class="mt-2 text-sm text-orange-800">Broadcast emergency posture and coordinate manual fallback if unresolved.</div>
          </article>
        </div>

        <div class="mt-6 space-y-3">
          <article v-for="request in emergencyRequests.slice(0, 5)" :key="request.id" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <div class="text-sm font-semibold text-gray-900">{{ request.case_id }}</div>
                <div class="mt-1 text-sm text-gray-600">{{ request.blood_type }} • {{ request.city }} • radius {{ request.distance_limit_km || 'n/a' }} km</div>
              </div>
              <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="urgencyTheme[request.urgency_level]?.badge">{{ request.urgency_label }}</span>
            </div>
          </article>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { fetchHospitalRequests, updateHospitalRequest, urgencyTheme } from '../../lib/hospitalPanel';

const requests = ref([]);
const selectedRequestId = ref('');
const saving = ref(false);
const message = ref('');
const form = reactive({
  escalationLevel: 'critical',
  distance_limit_km: 50,
  is_emergency: true,
});

const activeRequests = computed(() => requests.value.filter((request) => ['pending', 'matching'].includes(request.status)));
const emergencyRequests = computed(() => requests.value.filter((request) => request.is_emergency || request.urgency_level === 'critical'));
const selectedRequest = computed(() => activeRequests.value.find((request) => String(request.id) === selectedRequestId.value) || null);

async function loadRequests() {
  requests.value = await fetchHospitalRequests({ per_page: 50 });
}

async function applyEscalation() {
  if (!selectedRequest.value) return;

  saving.value = true;
  message.value = '';

  try {
    const urgency = form.escalationLevel === 'monitor' ? 'high' : 'critical';
    await updateHospitalRequest(selectedRequest.value.id, {
      urgency_level: urgency,
      is_emergency: form.is_emergency,
      distance_limit_km: form.distance_limit_km,
    });
    message.value = 'Escalation saved. Matching has been re-run with the updated emergency parameters.';
    await loadRequests();
  } finally {
    saving.value = false;
  }
}

onMounted(loadRequests);
</script>
