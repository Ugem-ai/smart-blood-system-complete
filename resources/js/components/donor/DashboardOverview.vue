<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section v-if="settings.showMissionSummary" class="overflow-hidden rounded-[2rem] border border-red-100 bg-[radial-gradient(circle_at_top_left,_rgba(254,226,226,0.95),_rgba(255,255,255,0.98)_45%,_rgba(248,250,252,1)_100%)] p-6 shadow-sm">
      <div v-if="loading" class="space-y-4 animate-pulse">
        <div class="h-4 w-28 rounded-full bg-red-100"></div>
        <div class="h-10 w-72 rounded-2xl bg-red-100"></div>
        <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
          <div class="h-24 rounded-[1.5rem] bg-white/80"></div>
          <div class="h-24 rounded-[1.5rem] bg-white/80"></div>
          <div class="h-24 rounded-[1.5rem] bg-white/80"></div>
        </div>
      </div>

      <template v-else>
        <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
          <div class="max-w-3xl">
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-red-500">Donor response command</div>
            <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950 sm:text-4xl">Stay ready for the next critical blood request.</h2>
            <p class="mt-2 text-sm leading-6 text-gray-600">This panel prioritizes fast decision-making: readiness status, high-urgency requests, and your live response performance are visible in one surface.</p>
          </div>

          <div class="rounded-[1.75rem] border px-5 py-4 shadow-sm xl:max-w-sm" :class="banner.tone === 'green' ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'">
            <div class="text-xs font-semibold uppercase tracking-[0.18em]" :class="banner.tone === 'green' ? 'text-emerald-700' : 'text-amber-700'">Eligibility status</div>
            <div class="mt-1 text-lg font-bold" :class="banner.tone === 'green' ? 'text-emerald-900' : 'text-amber-900'">{{ banner.title }}</div>
            <div class="mt-1 max-w-sm text-sm" :class="banner.tone === 'green' ? 'text-emerald-800' : 'text-amber-800'">{{ banner.detail }}</div>
          </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
          <article v-for="card in statCards" :key="card.label" class="rounded-[1.75rem] border border-white/70 bg-white/85 p-5 shadow-sm backdrop-blur-sm">
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-gray-400">{{ card.label }}</div>
                <div class="mt-3 text-3xl font-black tracking-tight text-gray-950">{{ card.value }}</div>
              </div>
              <div class="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-red-50 text-xl text-red-600">{{ card.icon }}</div>
            </div>
            <div class="mt-3 text-sm text-gray-500">{{ card.detail }}</div>
          </article>
        </div>
      </template>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-[1.35fr_0.65fr]">
      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Emergency alert section</div>
            <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Urgent nearby blood requests</h3>
            <p class="mt-1 text-sm text-gray-600">Respond with minimal friction. Critical requests stay pinned to the top of the queue.</p>
          </div>
          <div class="inline-flex rounded-full bg-red-50 px-4 py-2 text-xs font-semibold uppercase tracking-[0.16em] text-red-600 sm:self-auto self-start">{{ liveRequests.length }} active match{{ liveRequests.length === 1 ? '' : 'es' }}</div>
        </div>

        <div v-if="loading" class="mt-6 space-y-4 animate-pulse">
          <div v-for="index in 3" :key="index" class="h-40 rounded-[1.75rem] bg-gray-100"></div>
        </div>

        <div v-else-if="liveRequests.length === 0" class="mt-6 rounded-[1.75rem] border border-dashed border-gray-200 bg-gray-50 px-6 py-12 text-center">
          <div class="text-lg font-semibold text-gray-900">No urgent requests right now</div>
          <div class="mt-2 text-sm text-gray-500">Your donor panel will surface new emergency matches as soon as hospitals activate them.</div>
        </div>

        <div v-else class="mt-6 space-y-4">
          <article v-for="request in prioritizedRequests" :key="request.id" class="rounded-[1.75rem] border bg-[linear-gradient(135deg,_rgba(255,255,255,1),_rgba(248,250,252,1))] p-5 shadow-sm" :class="urgencyTheme[request.urgency_level]?.border || 'border-gray-200'">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="urgencyTheme[request.urgency_level]?.badge">{{ request.urgency_label }}</span>
                  <span class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">{{ request.posted_relative }}</span>
                </div>
                <h4 class="mt-3 text-xl font-bold text-gray-950">{{ request.hospital_name }} needs {{ request.blood_type }}</h4>
                <p class="mt-2 text-sm leading-6 text-gray-600">{{ request.units_required }} unit{{ request.units_required === 1 ? '' : 's' }} requested in {{ request.city }}. {{ request.hospital_address }}</p>
                <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-gray-600">
                  <span class="rounded-full bg-gray-100 px-3 py-1">{{ request.distance_display }}</span>
                  <span class="rounded-full bg-gray-100 px-3 py-1">Posted {{ request.posted_time }}</span>
                  <span class="rounded-full bg-gray-100 px-3 py-1">{{ request.compatibility_label }}</span>
                </div>
              </div>

              <div class="flex flex-col gap-2 sm:flex-row xl:flex-col xl:w-44">
                  <button type="button" class="w-full rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="actionLoadingId === request.id" @click="handleResponse(request, 'accept')">
                  {{ actionLoadingId === request.id && actionLoadingType === 'accept' ? 'Sending...' : 'Respond Now' }}
                </button>
                  <button type="button" class="w-full rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm font-semibold text-red-700 transition hover:bg-red-100 disabled:cursor-not-allowed disabled:opacity-50" :disabled="actionLoadingId === request.id" @click="handleResponse(request, 'decline')">
                  {{ actionLoadingId === request.id && actionLoadingType === 'decline' ? 'Declining...' : 'Decline' }}
                </button>
                  <button type="button" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="toggleDetails(request.id)">
                  {{ expandedRequestId === request.id ? 'Hide Details' : 'View Details' }}
                </button>
              </div>
            </div>

            <div v-if="expandedRequestId === request.id" class="mt-5 grid grid-cols-1 gap-3 border-t border-gray-100 pt-5 md:grid-cols-3">
              <div class="rounded-2xl bg-gray-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Hospital</div>
                <div class="mt-2 text-sm font-semibold text-gray-900">{{ request.hospital_name }}</div>
                <div class="mt-1 text-sm text-gray-500">{{ request.hospital_address }}</div>
              </div>
              <div class="rounded-2xl bg-gray-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Urgency</div>
                <div class="mt-2 text-sm font-semibold text-gray-900">{{ request.urgency_label }}</div>
                <div class="mt-1 text-sm text-gray-500">Required on {{ request.required_on || 'Immediate release' }}</div>
              </div>
              <div class="rounded-2xl bg-gray-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Travel</div>
                <div class="mt-2 text-sm font-semibold text-gray-900">{{ request.distance_display }}</div>
                <a :href="request.directions_url" target="_blank" rel="noreferrer" class="mt-2 inline-flex text-sm font-semibold text-red-600 hover:text-red-700">Open directions</a>
              </div>
            </div>
          </article>
        </div>
      </div>

      <div class="space-y-6">
        <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Action readiness</div>
          <h3 class="mt-2 text-xl font-black tracking-tight text-gray-950">Fast donor actions</h3>
          <div class="mt-5 space-y-3">
            <div class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
              <div class="text-sm font-semibold text-gray-900">Availability</div>
              <div class="mt-1 text-sm text-gray-600">{{ profile.availability ? 'Available for urgent requests' : 'Currently unavailable for dispatch' }}</div>
            </div>
            <div class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
              <div class="text-sm font-semibold text-gray-900">Next eligible donation</div>
              <div class="mt-1 text-sm text-gray-600">{{ eligibility.is_eligible ? 'Now' : formatDate(eligibility.next_eligible_date) }}</div>
            </div>
            <div class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
              <div class="text-sm font-semibold text-gray-900">Pending responses</div>
              <div class="mt-1 text-sm text-gray-600">{{ stats.pending_responses || 0 }} request{{ (stats.pending_responses || 0) === 1 ? '' : 's' }} awaiting action</div>
            </div>
          </div>
        </section>

        <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Recent activity</div>
          <h3 class="mt-2 text-xl font-black tracking-tight text-gray-950">Latest donor timeline</h3>
          <div v-if="recentTimeline.length === 0" class="mt-5 rounded-[1.5rem] border border-dashed border-gray-200 bg-gray-50 px-5 py-10 text-center text-sm text-gray-500">No recent donation or request activity yet.</div>
          <div v-else class="mt-5 space-y-3">
            <article v-for="item in recentTimeline" :key="item.id" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 px-4 py-3">
              <div class="flex items-start gap-3">
                <div class="mt-1 inline-flex h-9 w-9 items-center justify-center rounded-2xl bg-white text-lg shadow-sm">{{ item.icon }}</div>
                <div class="min-w-0 flex-1">
                  <div class="text-sm font-semibold text-gray-900">{{ item.title }}</div>
                  <div class="mt-1 text-sm text-gray-600">{{ item.detail }}</div>
                  <div class="mt-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">{{ item.time }}</div>
                </div>
              </div>
            </article>
          </div>
        </section>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { defaultDonorSettings, fetchDonorDashboard, formatDate, respondToRequest, statusBanner, urgencyTheme } from '../../lib/donorPanel';
import { showDonorToast } from '../../lib/donorToast';

const loading = ref(true);
const profile = ref({});
const eligibility = ref({});
const stats = ref({});
const requests = ref([]);
const history = ref([]);
const responses = ref([]);
const settings = ref({ ...defaultDonorSettings });
const actionLoadingId = ref(null);
const actionLoadingType = ref(null);
const expandedRequestId = ref(null);

const banner = computed(() => statusBanner(eligibility.value));
const liveRequests = computed(() => requests.value.filter((request) => {
  if (request.response_status != null) return false;
  if (settings.value.urgentOnly && request.urgency_level !== 'critical') return false;
  if (settings.value.maxRadius !== 'all' && request.distance_km != null && request.distance_km > Number(settings.value.maxRadius)) return false;
  return true;
}));
const prioritizedRequests = computed(() => [...liveRequests.value]
  .sort((left, right) => urgencyRank(right.urgency_level) - urgencyRank(left.urgency_level))
  .slice(0, 3));

const statCards = computed(() => [
  {
    label: 'Total Donations',
    value: stats.value.total_donations || 0,
    detail: `${stats.value.donations_this_year || 0} this calendar year`,
    icon: '🩸',
  },
  {
    label: 'Lives Impacted',
    value: stats.value.lives_saved_estimate || 0,
    detail: 'Estimated recipients supported',
    icon: '❤️',
  },
  {
    label: 'Response Rate',
    value: `${stats.value.response_rate || 0}%`,
    detail: `${stats.value.pending_responses || 0} active request${(stats.value.pending_responses || 0) === 1 ? '' : 's'} pending`,
    icon: '⚡',
  },
  {
    label: 'Last Donation Date',
    value: formatDate(profile.value.last_donation_date),
    detail: profile.value.reliability_label ? `${profile.value.reliability_label} reliability profile` : 'Reliability profile unavailable',
    icon: '📅',
  },
]);

const recentTimeline = computed(() => {
  const donationEntries = history.value.slice(0, 3).map((entry) => ({
    id: `donation-${entry.id}`,
    icon: '🩸',
    title: `Donation completed at ${entry.hospital_name}`,
    detail: `${entry.units} unit${entry.units === 1 ? '' : 's'} • ${entry.blood_type} • ${entry.status}`,
    time: formatDate(entry.donation_date),
  }));
  const responseEntries = responses.value.slice(0, 3).map((entry) => ({
    id: `response-${entry.id}`,
    icon: entry.response === 'accepted' ? '✅' : '❌',
    title: `${entry.response_label} ${entry.blood_type} request`,
    detail: `${entry.hospital_name} • ${entry.urgency_label} urgency • Request ${entry.request_status}`,
    time: entry.responded_at_label,
  }));

  return [...responseEntries, ...donationEntries].slice(0, 5);
});

function urgencyRank(level) {
  return { critical: 4, high: 3, medium: 2, low: 1 }[level] || 0;
}

function toggleDetails(requestId) {
  expandedRequestId.value = expandedRequestId.value === requestId ? null : requestId;
}

async function loadDashboard() {
  loading.value = true;

  try {
    const payload = await fetchDonorDashboard();
    profile.value = payload.profile;
    eligibility.value = payload.eligibility;
    stats.value = payload.stats;
    requests.value = payload.requests;
    history.value = payload.history;
    responses.value = payload.responses;
    settings.value = payload.settings;
  } finally {
    loading.value = false;
  }
}

async function handleResponse(request, action) {
  actionLoadingId.value = request.id;
  actionLoadingType.value = action;

  try {
    const result = await respondToRequest(action, request.id);
    request.response_status = result.response || (action === 'accept' ? 'accepted' : 'declined');
    showDonorToast(action === 'accept'
      ? `Response sent to ${request.hospital_name}. Emergency team has been notified.`
      : `Request from ${request.hospital_name} declined.`);
  } catch {
    showDonorToast(`Unable to process the request from ${request.hospital_name}.`, 'error');
  } finally {
    actionLoadingId.value = null;
    actionLoadingType.value = null;
  }
}

onMounted(loadDashboard);
</script>
