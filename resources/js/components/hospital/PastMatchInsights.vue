<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">PAST-Match insights</div>
      <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950 sm:text-4xl">Explainable ranking for matched donors</h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">Inspect donor score contributions and the currently active hospital-visible weight profile for the selected blood request.</p>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[0.8fr_1.2fr]">
      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <select v-model="selectedRequestId" class="w-full rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100" @change="loadMatches">
          <option value="">Select request for match transparency</option>
          <option v-for="request in activeRequests" :key="request.id" :value="String(request.id)">
            {{ request.case_id }} • {{ request.blood_type }} • {{ request.urgency_label }}
          </option>
        </select>

        <div v-if="selectedRequest" class="mt-6 space-y-4">
          <div class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-900">Weight distribution</div>
            <div class="mt-4 space-y-3">
              <div v-for="item in activeWeights" :key="item.label" class="grid grid-cols-[6rem_1fr_3rem] items-center gap-3">
                <div class="text-sm text-gray-600">{{ item.label }}</div>
                <div class="h-3 rounded-full bg-gray-100">
                  <div class="h-3 rounded-full bg-red-600" :style="{ width: `${Math.max(8, Math.round(item.value * 100))}%` }"></div>
                </div>
                <div class="text-sm font-semibold text-gray-900">{{ Math.round(item.value * 100) }}%</div>
              </div>
            </div>
          </div>

          <div class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-sm font-semibold text-gray-900">Operational transparency note</div>
            <div class="mt-2 text-sm leading-6 text-gray-600">Hospitals can inspect ranking factors and request-level donor fit, while central weight tuning remains under admin control.</div>
          </div>
        </div>
      </div>

      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div v-if="matches.length === 0" class="rounded-[1.5rem] border border-dashed border-gray-200 bg-gray-50 px-6 py-14 text-center text-sm text-gray-500">Select an active request to inspect matched-donor insights.</div>
        <div v-else class="space-y-4">
          <article v-for="match in matches" :key="match.donor_id" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <div class="text-sm font-semibold text-gray-900">#{{ match.rank }} {{ match.name }}</div>
                <div class="mt-1 text-sm text-gray-600">{{ match.blood_type }} • {{ match.city }} • {{ match.distance_km == null ? 'same-city estimate' : `${match.distance_km.toFixed(1)} km` }}</div>
              </div>
              <div class="text-right">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">PAST-Match score</div>
                <div class="mt-1 text-2xl font-black tracking-tight text-red-600">{{ Math.round(match.score) }}%</div>
              </div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
              <div v-for="factor in match.score_breakdown" :key="factor.label" class="rounded-2xl bg-white p-3 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">{{ factor.label }}</div>
                <div class="mt-2 text-lg font-bold text-gray-900">{{ factor.value }}%</div>
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
import { fetchHospitalRequests, fetchHospitalSettingsSnapshot, fetchMatchedDonors, normalizeMatch } from '../../lib/hospitalPanel';

const requests = ref([]);
const matches = ref([]);
const settingsSnapshot = ref({});
const selectedRequestId = ref('');

const activeRequests = computed(() => requests.value.filter((request) => ['pending', 'matching'].includes(request.status)));
const selectedRequest = computed(() => activeRequests.value.find((request) => String(request.id) === selectedRequestId.value) || null);
const activeWeights = computed(() => {
  const profiles = settingsSnapshot.value.system_settings?.past_match_weight_profiles || {};
  const weights = selectedRequest.value ? profiles[selectedRequest.value.urgency_level] || settingsSnapshot.value.system_settings?.past_match_weights || {} : {};
  return Object.entries(weights).map(([label, value]) => ({ label, value }));
});

async function loadMatches() {
  if (!selectedRequest.value) {
    matches.value = [];
    return;
  }

  const rawMatches = await fetchMatchedDonors(selectedRequest.value.id);
  matches.value = rawMatches.map((match) => normalizeMatch(match, selectedRequest.value));
}

async function loadData() {
  const [requestRows, snapshot] = await Promise.all([
    fetchHospitalRequests({ per_page: 50 }),
    fetchHospitalSettingsSnapshot(),
  ]);

  requests.value = requestRows;
  settingsSnapshot.value = snapshot;
}

onMounted(loadData);
</script>
