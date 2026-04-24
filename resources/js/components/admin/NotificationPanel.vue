<template>
  <AdminPageFrame
    kicker="Communication Intelligence"
    title="Advanced Notifications and Response Tracking"
    description="Real-time visibility into donor outreach, response behavior, delivery reliability, and notification effectiveness during active blood request fulfillment."
    badge="Live delivery telemetry"
  >
    <template #actions>
      <div class="flex w-full flex-col gap-3 xl:min-w-[38rem] xl:max-w-[42rem] sm:flex-row sm:items-center sm:justify-end">
          <div class="relative min-w-0 flex-1 sm:min-w-[18rem]">
            <label class="mb-1 block text-[11px] font-bold uppercase tracking-[0.18em] text-gray-500">Request Selector</label>
            <button type="button" class="selector-button" @click="selectorOpen = !selectorOpen">
              <span class="truncate text-left">{{ selectedRequestOption?.label || 'Select an active request' }}</span>
              <span class="text-xs text-gray-400">{{ selectorOpen ? 'Close' : 'Browse' }}</span>
            </button>

            <div v-if="selectorOpen" class="absolute left-0 right-0 top-[calc(100%+0.5rem)] z-30 overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-2xl">
              <div class="border-b border-gray-100 p-3">
                <input v-model="selectorSearch" type="text" placeholder="Search by case, hospital, blood type, or status" class="selector-input" />
              </div>
              <div v-if="loadingOptions" class="space-y-2 p-3">
                <div v-for="item in 4" :key="item" class="h-12 animate-pulse rounded-2xl bg-gray-100"></div>
              </div>
              <div v-else-if="selectorError" class="p-4 text-sm text-red-600">{{ selectorError }}</div>
              <div v-else-if="!requestOptions.length" class="p-4 text-sm text-gray-500">No recent requests found.</div>
              <div v-else class="max-h-80 overflow-y-auto p-2">
                <button v-for="option in requestOptions" :key="option.id" type="button" class="selector-option" :class="selectedRequestId === option.id ? 'bg-red-50 ring-1 ring-red-200' : ''" @click="selectRequest(option)">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <p class="font-semibold text-gray-900">{{ option.case_id || `Request #${option.id}` }}</p>
                      <p class="mt-1 text-sm text-gray-500">{{ option.hospital_name }} · {{ option.blood_type }} · {{ option.status }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide" :class="urgencyBadge(option.urgency_level)">{{ option.urgency_level || 'medium' }}</span>
                  </div>
                </button>
              </div>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs font-semibold text-gray-600">
              Last updated {{ formatDateTime(dashboard?.meta?.last_updated) }}
            </div>
            <div class="rounded-2xl border px-4 py-3 text-xs font-bold uppercase tracking-[0.18em]" :class="syncStatusClass">
              {{ syncStatusLabel }}
            </div>
            <button type="button" class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!selectedRequestId || loadingDashboard" @click="loadDashboard(false)">Refresh</button>
            <button type="button" class="inline-flex items-center gap-2 rounded-2xl border px-4 py-3 text-sm font-semibold transition" :class="autoRefresh ? 'border-red-200 bg-red-50 text-red-700' : 'border-gray-200 bg-white text-gray-700'" @click="autoRefresh = !autoRefresh">
              <span class="toggle-indicator" :class="autoRefresh ? 'bg-red-500' : 'bg-gray-300'"></span>
              {{ autoRefresh ? `Polling every ${refreshCountdown}s` : 'Polling off' }}
            </button>
          </div>
      </div>
    </template>

    <div v-if="error" class="rounded-3xl border border-red-200 bg-red-50 p-5 text-sm text-red-700 shadow-sm">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="font-semibold text-red-800">Unable to load notification intelligence.</p>
          <p class="mt-1">{{ error }}</p>
        </div>
        <button type="button" class="rounded-2xl bg-red-600 px-4 py-2 font-semibold text-white transition hover:bg-red-700" @click="loadDashboard(false)">Retry</button>
      </div>
    </div>

    <div v-if="!selectedRequestId && !loadingDashboard" class="rounded-[2rem] border border-dashed border-gray-300 bg-white p-16 text-center shadow-sm">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-red-50 text-red-600">
        <svg viewBox="0 0 24 24" class="h-8 w-8 fill-none stroke-current" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 12h16M12 4v16" /></svg>
      </div>
      <h3 class="mt-5 text-xl font-bold text-gray-950">Select a request to monitor donor communication</h3>
      <p class="mt-2 text-sm text-gray-500">No notifications yet. Once a blood request is processed, this panel will display real-time donor communication, delivery status, and response behavior.</p>
    </div>

    <template v-else>
      <div v-if="loadingDashboard && !dashboard" class="space-y-6">
        <div class="h-40 animate-pulse rounded-[2rem] bg-gray-100"></div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
          <div v-for="card in 5" :key="card" class="h-28 animate-pulse rounded-[2rem] bg-gray-100"></div>
        </div>
        <div class="h-96 animate-pulse rounded-[2rem] bg-gray-100"></div>
      </div>

      <template v-else-if="dashboard">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">Request Context Header</p>
                <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">{{ requestContext.request_id }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ requestContext.blood_type }} · {{ requestContext.component }} · {{ requestContext.time_elapsed_human }} elapsed</p>
              </div>
              <div class="flex flex-col items-end gap-2">
                <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" :class="urgencyBadge(requestContext.urgency_level)">{{ requestContext.urgency_level }}</span>
                <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" :class="requestStatusClass(requestContext.status)">{{ requestContext.status }}</span>
              </div>
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-gray-50 p-5">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Units Required vs Fulfilled</p>
                  <p class="mt-2 text-lg font-black text-gray-950">{{ requestContext.fulfilled_units }} / {{ requestContext.units_required }} units</p>
                </div>
                <p class="text-sm font-semibold text-gray-600">{{ requestContext.progress_percentage?.toFixed?.(2) ?? requestContext.progress_percentage }}%</p>
              </div>
              <div class="mt-4 h-4 overflow-hidden rounded-full bg-white">
                <div class="h-full rounded-full bg-gradient-to-r from-red-500 via-amber-500 to-emerald-500" :style="{ width: `${requestContext.progress_percentage}%` }"></div>
              </div>
            </div>
          </div>

          <div class="rounded-[2rem] border border-gray-200 bg-gray-950 p-6 text-white shadow-sm">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-red-300">System Health</p>
            <h3 class="mt-2 text-xl font-black">Notification Effectiveness</h3>
            <div class="mt-5 space-y-4">
              <div class="rounded-3xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-300">Response Health</p>
                <p class="mt-2 text-2xl font-black text-white">{{ dashboard.summary.response_health }}</p>
              </div>
              <div class="rounded-3xl border border-white/10 bg-white/5 p-4">
                <p class="text-xs uppercase tracking-[0.18em] text-gray-300">Average Response Time</p>
                <p class="mt-2 text-2xl font-black text-white">{{ dashboard.summary.avg_response_time.human }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
          <div v-for="card in summaryCards" :key="card.key" class="rounded-[2rem] border p-5 shadow-sm" :class="card.shellClass">
            <p class="text-sm font-semibold text-gray-700">{{ card.label }}</p>
            <p class="mt-4 text-3xl font-black tracking-tight text-gray-950">{{ card.value }}</p>
            <p class="mt-2 text-xs font-semibold uppercase tracking-wide" :class="metricToneClass(card.health)">{{ card.meta }}</p>
          </div>
        </div>

        <div class="rounded-[2rem] border border-gray-200 bg-white p-5 shadow-sm">
          <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
            <label class="filter-field">
              <span class="filter-label">By Response</span>
              <select v-model="filters.response" class="filter-input">
                <option value="all">All</option>
                <option value="Accepted">Accepted</option>
                <option value="Declined">Declined</option>
                <option value="Pending">No response</option>
              </select>
            </label>
            <label class="filter-field">
              <span class="filter-label">By Channel</span>
              <select v-model="filters.channel" class="filter-input">
                <option value="all">All</option>
                <option value="SMS">SMS</option>
                <option value="PUSH">Push</option>
                <option value="EMAIL">Email</option>
                <option value="MULTI">Multi</option>
              </select>
            </label>
            <label class="filter-field">
              <span class="filter-label">By Time Range</span>
              <select v-model="filters.timeRange" class="filter-input">
                <option value="all">All time</option>
                <option value="15m">Last 15 minutes</option>
                <option value="1h">Last 1 hour</option>
                <option value="6h">Last 6 hours</option>
              </select>
            </label>
            <label class="filter-field">
              <span class="filter-label">By Delivery Status</span>
              <select v-model="filters.deliveryStatus" class="filter-input">
                <option value="all">All</option>
                <option value="Delivered">Delivered</option>
                <option value="Failed">Failed</option>
                <option value="Sent">Sent</option>
                <option value="Responded">Responded</option>
              </select>
            </label>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">Real-Time Notification Stream</p>
                <h3 class="mt-2 text-xl font-black text-gray-950">Live donor communication feed</h3>
              </div>
              <span class="rounded-full bg-gray-950 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-white">{{ filteredStream.length }} items</span>
            </div>

            <div v-if="filteredStream.length === 0" class="mt-6 rounded-3xl border border-dashed border-gray-200 bg-gray-50 p-10 text-center text-sm text-gray-500">
              No notifications yet. Once a blood request is processed, this panel will display real-time donor communication, delivery status, and response behavior.
            </div>

            <div v-else class="mt-6 space-y-4">
              <button v-for="entry in filteredStream" :key="entry.id" type="button" class="stream-card text-left" :class="activeEntry?.id === entry.id ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-gray-50'" @click="activeEntryId = entry.id">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p class="font-semibold text-gray-950">{{ entry.donor_name }} <span class="text-xs text-gray-400">{{ entry.donor_code }}</span></p>
                    <p class="mt-1 text-sm text-gray-600">{{ entry.message_preview }}</p>
                  </div>
                  <div class="flex flex-wrap justify-end gap-2">
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide" :class="channelClass(entry.channel)">{{ entry.channel }}</span>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide" :class="deliveryClass(entry.delivery_status)">{{ entry.delivery_status }}</span>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide" :class="responseClass(entry.response_status)">{{ entry.response_status }}</span>
                  </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-3 text-sm text-gray-600 md:grid-cols-2 xl:grid-cols-4">
                  <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Timestamp</p>
                    <p class="mt-1">{{ formatDateTime(entry.timestamp) }}</p>
                  </div>
                  <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Response Time</p>
                    <p class="mt-1">{{ entry.response_time_human }}</p>
                  </div>
                  <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Retry Attempts</p>
                    <p class="mt-1">{{ entry.retry_attempts }}</p>
                  </div>
                  <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-400">Failure Reason</p>
                    <p class="mt-1">{{ entry.failure_reason || 'None' }}</p>
                  </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                  <p class="text-xs font-semibold uppercase tracking-wide" :class="speedClass(entry.speed_label)">{{ entry.speed_label }}</p>
                  <div class="flex flex-wrap gap-2">
                    <button type="button" class="inline-flex items-center rounded-2xl border border-gray-200 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition hover:bg-gray-100" :disabled="!entry.donor_id || controlLoading" @click.stop="runControl('resend_notification', entry)">Resend notification</button>
                    <button type="button" class="inline-flex items-center rounded-2xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-700 transition hover:bg-red-100" :disabled="!entry.donor_id" @click.stop="openManualMessage(entry)">Send manual message</button>
                  </div>
                </div>
              </button>
            </div>
          </div>

          <div class="space-y-6">
            <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">Donor Engagement Insight Panel</p>
                  <h3 class="mt-2 text-xl font-black text-gray-950">Responsive vs at-risk donors</h3>
                </div>
              </div>

              <div class="mt-5 grid grid-cols-1 gap-5">
                <div>
                  <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Most responsive donors</p>
                  <div class="mt-3 space-y-3">
                    <div v-for="item in dashboard.engagement_insights.most_responsive_donors" :key="item.donor_code" class="insight-card">
                      <p class="font-semibold text-gray-950">{{ item.donor_name }}</p>
                      <p class="mt-1 text-sm text-gray-500">{{ item.response_time_human }} · {{ item.speed_label }}</p>
                    </div>
                  </div>
                </div>

                <div>
                  <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Least responsive donors</p>
                  <div class="mt-3 space-y-3">
                    <div v-for="item in dashboard.engagement_insights.least_responsive_donors" :key="item.donor_code" class="insight-card">
                      <p class="font-semibold text-gray-950">{{ item.donor_name }}</p>
                      <p class="mt-1 text-sm text-gray-500">{{ item.delivery_status }} · {{ formatDateTime(item.last_contact_at) }}</p>
                    </div>
                  </div>
                </div>

                <div>
                  <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Reliability scoring trend</p>
                  <div class="mt-3 space-y-3">
                    <div v-for="item in dashboard.engagement_insights.reliability_trend" :key="item.label" class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                      <div class="flex items-center justify-between gap-3">
                        <p class="font-semibold text-gray-950">{{ item.label }}</p>
                        <p class="text-sm font-semibold text-gray-600">{{ item.average_reliability_score.toFixed(2) }}</p>
                      </div>
                      <div class="mt-3 h-3 overflow-hidden rounded-full bg-white">
                        <div class="h-full rounded-full bg-gray-900" :style="{ width: `${Math.min(item.average_reliability_score, 100)}%` }"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">Escalation Trigger Visibility</p>
                  <h3 class="mt-2 text-xl font-black text-gray-950">System escalation timeline</h3>
                </div>
              </div>

              <div class="mt-5 space-y-4">
                <div v-for="(entry, index) in dashboard.escalation_triggers" :key="`${entry.time_triggered}-${index}`" class="rounded-3xl border border-gray-200 bg-gray-50 p-4">
                  <div class="flex flex-wrap items-center justify-between gap-3">
                    <p class="font-semibold text-gray-950">{{ entry.action }}</p>
                    <span class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ formatDateTime(entry.time_triggered) }}</span>
                  </div>
                  <p class="mt-2 text-sm text-gray-600">{{ entry.trigger_condition }}</p>
                </div>
              </div>
            </div>

            <div class="rounded-[2rem] border border-gray-200 bg-gray-950 p-6 text-white shadow-sm">
              <p class="text-xs font-black uppercase tracking-[0.2em] text-red-300">Action Controls</p>
              <h3 class="mt-2 text-xl font-black">Admin intervention panel</h3>
              <div class="mt-5 grid grid-cols-1 gap-3">
                <button type="button" class="control-button border-red-200 bg-red-50 text-red-700 hover:bg-red-100" :disabled="controlLoading" @click="runControl('broadcast_eligible_donors')">Broadcast to all eligible donors</button>
                <button v-if="!dashboard.controls.notifications_paused" type="button" class="control-button border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100" :disabled="controlLoading" @click="runControl('cancel_pending_notifications')">Cancel pending notifications</button>
                <button v-else type="button" class="control-button border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100" :disabled="controlLoading" @click="runControl('resume_notifications')">Resume notifications</button>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm xl:col-span-2">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">Visual Analytics</p>
            <h3 class="mt-2 text-xl font-black text-gray-950">Response rate, notification success, and channel effectiveness</h3>
          </div>

          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
            <h4 class="text-lg font-black text-gray-950">Response rate over time</h4>
            <div class="mt-4 h-80"><canvas ref="responseChartCanvas"></canvas></div>
          </div>

          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
            <h4 class="text-lg font-black text-gray-950">Notification success rate</h4>
            <div class="mt-4 h-80"><canvas ref="successChartCanvas"></canvas></div>
          </div>

          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm xl:col-span-2">
            <h4 class="text-lg font-black text-gray-950">Channel effectiveness</h4>
            <div class="mt-4 h-80"><canvas ref="channelChartCanvas"></canvas></div>
          </div>
        </div>
      </template>
    </template>

    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-4 opacity-0" leave-active-class="transition duration-150 ease-in" leave-to-class="translate-y-4 opacity-0">
      <div v-if="manualModal.open" class="fixed inset-0 z-[60] flex items-center justify-center bg-gray-950/40 px-4">
        <div class="w-full max-w-xl rounded-[2rem] bg-white p-6 shadow-2xl">
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Manual Message</p>
              <h3 class="mt-2 text-xl font-black text-gray-950">Send direct communication to {{ manualModal.donorName }}</h3>
            </div>
            <button type="button" class="rounded-2xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50" @click="closeManualMessage">Close</button>
          </div>

          <div class="mt-5 space-y-4">
            <label class="block">
              <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">Title</span>
              <input v-model="manualModal.title" type="text" class="filter-input" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">Message</span>
              <textarea v-model="manualModal.message" rows="5" class="filter-input resize-none"></textarea>
            </label>
          </div>

          <div class="mt-6 flex justify-end gap-3">
            <button type="button" class="rounded-2xl border border-gray-200 px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50" @click="closeManualMessage">Cancel</button>
            <button type="button" class="rounded-2xl bg-red-600 px-4 py-3 text-sm font-semibold text-white hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="controlLoading" @click="sendManualMessage">Send message</button>
          </div>
        </div>
      </div>
    </Transition>

    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-4 opacity-0" leave-active-class="transition duration-150 ease-in" leave-to-class="translate-y-4 opacity-0">
      <div v-if="toast.message" class="fixed bottom-6 right-6 z-[70] rounded-2xl px-4 py-3 text-sm font-semibold text-white shadow-xl" :class="toast.type === 'error' ? 'bg-red-600' : 'bg-gray-900'">
        {{ toast.message }}
      </div>
    </Transition>
  </AdminPageFrame>
</template>

<script setup>
import Chart from 'chart.js/auto';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import api from '../../lib/api';
import AdminPageFrame from './AdminPageFrame.vue';

const requestOptions = ref([]);
const selectedRequestId = ref(null);
const selectedRequestOption = ref(null);
const selectorOpen = ref(false);
const selectorSearch = ref('');
const loadingOptions = ref(false);
const selectorError = ref('');

const dashboard = ref(null);
const loadingDashboard = ref(false);
const error = ref('');
const autoRefresh = ref(true);
const refreshCountdown = ref(15);
const controlLoading = ref(false);
const activeEntryId = ref(null);

const filters = ref({
  response: 'all',
  channel: 'all',
  timeRange: 'all',
  deliveryStatus: 'all',
});

const manualModal = ref({
  open: false,
  donorId: null,
  donorName: '',
  title: 'Manual Admin Message',
  message: '',
});

const toast = ref({ message: '', type: 'success' });

const responseChartCanvas = ref(null);
const successChartCanvas = ref(null);
const channelChartCanvas = ref(null);

let selectorDebounceHandle = null;
let autoRefreshHandle = null;
let refreshCountdownHandle = null;
let toastHandle = null;
let responseChart = null;
let successChart = null;
let channelChart = null;

const requestContext = computed(() => dashboard.value?.request || {});
const activeEntry = computed(() => filteredStream.value.find((entry) => entry.id === activeEntryId.value) || filteredStream.value[0] || null);

const summaryCards = computed(() => {
  if (!dashboard.value) return [];

  return [
    {
      key: 'accepted',
      label: 'Accepted',
      value: dashboard.value.summary.accepted.count,
      meta: `${dashboard.value.summary.accepted.conversion_rate.toFixed(2)}% conversion`,
      health: dashboard.value.summary.accepted.health,
      shellClass: 'border-emerald-200 bg-emerald-50',
    },
    {
      key: 'declined',
      label: 'Declined',
      value: dashboard.value.summary.declined.count,
      meta: `${dashboard.value.summary.declined.conversion_rate.toFixed(2)}% decline rate`,
      health: dashboard.value.summary.declined.health,
      shellClass: 'border-red-200 bg-red-50',
    },
    {
      key: 'no_response',
      label: 'No Response',
      value: dashboard.value.summary.no_response.count,
      meta: `${dashboard.value.summary.no_response.overdue_count} timed out`,
      health: dashboard.value.summary.no_response.health,
      shellClass: 'border-amber-200 bg-amber-50',
    },
    {
      key: 'avg_response',
      label: 'Avg Response Time',
      value: dashboard.value.summary.avg_response_time.human,
      meta: `Threshold ${dashboard.value.summary.no_response.timeout_threshold_minutes} min`,
      health: dashboard.value.summary.avg_response_time.health,
      shellClass: 'border-blue-200 bg-blue-50',
    },
    {
      key: 'total_sent',
      label: 'Total Notifications Sent',
      value: dashboard.value.summary.total_notifications_sent,
      meta: dashboard.value.summary.response_health,
      health: dashboard.value.summary.response_health,
      shellClass: 'border-gray-200 bg-gray-50',
    },
  ];
});

const filteredStream = computed(() => {
  if (!dashboard.value?.notification_stream) return [];

  return dashboard.value.notification_stream.filter((entry) => {
    if (filters.value.response !== 'all' && entry.response_status !== filters.value.response) return false;
    if (filters.value.channel !== 'all' && entry.channel !== filters.value.channel) return false;
    if (filters.value.deliveryStatus !== 'all' && entry.delivery_status !== filters.value.deliveryStatus) return false;
    if (filters.value.timeRange !== 'all') {
      const timestamp = entry.timestamp ? new Date(entry.timestamp).getTime() : null;
      if (!timestamp) return false;
      const now = Date.now();
      const ranges = { '15m': 15 * 60 * 1000, '1h': 60 * 60 * 1000, '6h': 6 * 60 * 60 * 1000 };
      if (now - timestamp > ranges[filters.value.timeRange]) return false;
    }
    return true;
  });
});

const syncStatusLabel = computed(() => {
  if (loadingDashboard.value) return 'Syncing';
  if (!dashboard.value) return 'Standby';
  return dashboard.value.meta?.sync_status === 'paused' ? 'Notifications Paused' : 'Live Polling';
});

const syncStatusClass = computed(() => {
  if (loadingDashboard.value) return 'border-blue-200 bg-blue-50 text-blue-700';
  if (dashboard.value?.meta?.sync_status === 'paused') return 'border-amber-200 bg-amber-50 text-amber-700';
  return 'border-emerald-200 bg-emerald-50 text-emerald-700';
});

const loadRequestOptions = async (search = '') => {
  loadingOptions.value = true;
  selectorError.value = '';
  try {
    console.log('🔍 [NOTIFICATIONS] Starting API call to /admin/notifications/requests');
    
    // Debug auth
    const authSession = JSON.parse(localStorage.getItem('smartblood.auth') || 'null');
    console.log('🔐 [AUTH] Token in storage:', authSession?.token ? 'YES (exists)' : 'NO (missing)');
    
    const response = await api.get('/admin/notifications/requests', { params: { search, limit: 20 } });
    console.log('✅ [NOTIFICATIONS] API SUCCESS. Response:', response);
    console.log('📋 [NOTIFICATIONS] response.data?.data:', response.data?.data);
    
    requestOptions.value = response.data?.data || [];
    console.log('📌 [NOTIFICATIONS] Request options set. Length:', requestOptions.value.length);
  } catch (err) {
    console.error('❌ [NOTIFICATIONS] Error loading requests:', {
      status: err?.response?.status,
      message: err?.message,
      fullError: err
    });
    selectorError.value = `Error (${err?.response?.status || 'unknown'}): ${err?.message || 'Check console'}`;
  } finally {
    loadingOptions.value = false;
  }
};

const selectRequest = async (option) => {
  selectedRequestId.value = option.id;
  selectedRequestOption.value = option;
  selectorOpen.value = false;
  await loadDashboard(false);
};

const loadDashboard = async (silent = false) => {
  if (!selectedRequestId.value) return;
  if (!silent) loadingDashboard.value = true;
  error.value = '';

  try {
    console.log('📡 [NOTIFICATIONS-LOAD] Starting load for request:', selectedRequestId.value);
    const response = await api.get(`/admin/notifications/${selectedRequestId.value}`);
    console.log('✅ [NOTIFICATIONS-LOAD] API Response received:', response);
    
    dashboard.value = response.data?.data || null;
    console.log('🎯 [NOTIFICATIONS-LOAD] Dashboard updated');
    console.log('  - notification_stream entries:', dashboard.value?.notification_stream?.length || 0);
    console.log('  - summary:', dashboard.value?.summary);
    
    refreshCountdown.value = dashboard.value?.meta?.auto_refresh_seconds || 15;
    activeEntryId.value = dashboard.value?.notification_stream?.[0]?.id || null;

    await nextTick();
    renderCharts();
  } catch (err) {
    console.error('❌ [NOTIFICATIONS-LOAD] Error:', {
      message: err?.message,
      status: err?.response?.status,
      fullError: err
    });
    error.value = 'The notifications dashboard payload could not be retrieved for the selected request.';
  } finally {
    loadingDashboard.value = false;
  }
};

const runControl = async (action, entry = null, extra = {}) => {
  if (!selectedRequestId.value) return;
  controlLoading.value = true;

  try {
    const payload = { action, ...extra };
    if (entry?.donor_id) payload.donor_id = entry.donor_id;

    const response = await api.post(`/admin/notifications/${selectedRequestId.value}/control`, payload);
    showToast(response.data?.message || 'Control action completed.');
    await loadDashboard(true);
  } catch {
    showToast('Control action failed.', 'error');
  } finally {
    controlLoading.value = false;
  }
};

const openManualMessage = (entry) => {
  manualModal.value = {
    open: true,
    donorId: entry.donor_id,
    donorName: entry.donor_name,
    title: 'Manual Admin Message',
    message: '',
  };
};

const closeManualMessage = () => {
  manualModal.value = { open: false, donorId: null, donorName: '', title: 'Manual Admin Message', message: '' };
};

const sendManualMessage = async () => {
  if (!manualModal.value.donorId) return;
  await runControl('manual_message', { donor_id: manualModal.value.donorId }, {
    donor_id: manualModal.value.donorId,
    title: manualModal.value.title,
    message: manualModal.value.message,
  });
  closeManualMessage();
};

const urgencyBadge = (urgency) => {
  switch ((urgency || '').toLowerCase()) {
    case 'critical': return 'bg-red-100 text-red-700';
    case 'high': return 'bg-amber-100 text-amber-700';
    case 'medium': return 'bg-blue-100 text-blue-700';
    default: return 'bg-gray-100 text-gray-600';
  }
};

const requestStatusClass = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'fulfilled': return 'bg-emerald-100 text-emerald-700';
    case 'escalated': return 'bg-amber-100 text-amber-700';
    default: return 'bg-blue-100 text-blue-700';
  }
};

const responseClass = (response) => {
  switch ((response || '').toLowerCase()) {
    case 'accepted': return 'bg-emerald-100 text-emerald-700';
    case 'declined': return 'bg-red-100 text-red-700';
    default: return 'bg-amber-100 text-amber-700';
  }
};

const deliveryClass = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'responded': return 'bg-emerald-100 text-emerald-700';
    case 'failed': return 'bg-red-100 text-red-700';
    case 'sent': return 'bg-blue-100 text-blue-700';
    default: return 'bg-gray-100 text-gray-600';
  }
};

const channelClass = (channel) => {
  switch ((channel || '').toLowerCase()) {
    case 'sms': return 'bg-amber-100 text-amber-700';
    case 'push': return 'bg-blue-100 text-blue-700';
    case 'email': return 'bg-violet-100 text-violet-700';
    default: return 'bg-gray-100 text-gray-600';
  }
};

const metricToneClass = (health) => {
  switch ((health || '').toLowerCase()) {
    case 'healthy': return 'text-emerald-700';
    case 'critical': return 'text-red-700';
    default: return 'text-amber-700';
  }
};

const speedClass = (label) => {
  switch ((label || '').toLowerCase()) {
    case 'fast responder': return 'text-emerald-700';
    case 'slow responder': return 'text-red-700';
    default: return 'text-gray-500';
  }
};

const formatDateTime = (value) => {
  if (!value) return 'Unknown';
  return new Date(value).toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
};

const showToast = (message, type = 'success') => {
  clearTimeout(toastHandle);
  toast.value = { message, type };
  toastHandle = setTimeout(() => {
    toast.value = { message: '', type: 'success' };
  }, 3200);
};

const destroyCharts = () => {
  [responseChart, successChart, channelChart].forEach((chart) => chart?.destroy());
  responseChart = null;
  successChart = null;
  channelChart = null;
};

const chartBaseOptions = () => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      labels: {
        color: '#374151',
        font: { family: 'ui-sans-serif, system-ui, sans-serif', weight: '600' },
      },
    },
  },
  scales: {
    x: { ticks: { color: '#6b7280' }, grid: { color: 'rgba(229, 231, 235, 0.45)' } },
    y: { beginAtZero: true, ticks: { color: '#6b7280' }, grid: { color: 'rgba(229, 231, 235, 0.45)' } },
  },
});

const renderCharts = () => {
  destroyCharts();
  if (!dashboard.value?.analytics) return;

  if (responseChartCanvas.value) {
    responseChart = new Chart(responseChartCanvas.value, {
      type: 'line',
      data: {
        labels: dashboard.value.analytics.response_rate_over_time.map((item) => item.label),
        datasets: [{
          label: 'Response Rate %',
          data: dashboard.value.analytics.response_rate_over_time.map((item) => item.response_rate),
          borderColor: '#ef4444',
          backgroundColor: 'rgba(239, 68, 68, 0.18)',
          fill: true,
          tension: 0.3,
          pointRadius: 4,
        }],
      },
      options: chartBaseOptions(),
    });
  }

  if (successChartCanvas.value) {
    successChart = new Chart(successChartCanvas.value, {
      type: 'doughnut',
      data: {
        labels: ['Successful', 'Failed'],
        datasets: [{
          data: [dashboard.value.analytics.notification_success_rate.successful, dashboard.value.analytics.notification_success_rate.failed],
          backgroundColor: ['#10b981', '#ef4444'],
          borderWidth: 0,
        }],
      },
      options: { ...chartBaseOptions(), cutout: '70%' },
    });
  }

  if (channelChartCanvas.value) {
    channelChart = new Chart(channelChartCanvas.value, {
      type: 'bar',
      data: {
        labels: dashboard.value.analytics.channel_effectiveness.map((item) => item.channel),
        datasets: [
          { label: 'Success Rate %', data: dashboard.value.analytics.channel_effectiveness.map((item) => item.success_rate), backgroundColor: '#3b82f6', borderRadius: 10 },
          { label: 'Response Rate %', data: dashboard.value.analytics.channel_effectiveness.map((item) => item.response_rate), backgroundColor: '#10b981', borderRadius: 10 },
        ],
      },
      options: chartBaseOptions(),
    });
  }
};

const setupAutoRefresh = () => {
  clearInterval(autoRefreshHandle);
  clearInterval(refreshCountdownHandle);

  if (!autoRefresh.value) return;

  const intervalSeconds = dashboard.value?.meta?.auto_refresh_seconds || 15;
  refreshCountdown.value = intervalSeconds;

  autoRefreshHandle = setInterval(() => {
    if (selectedRequestId.value) {
      loadDashboard(true);
    }
  }, intervalSeconds * 1000);

  refreshCountdownHandle = setInterval(() => {
    refreshCountdown.value = refreshCountdown.value > 1 ? refreshCountdown.value - 1 : intervalSeconds;
  }, 1000);
};

watch(selectorSearch, (value) => {
  clearTimeout(selectorDebounceHandle);
  selectorDebounceHandle = setTimeout(() => loadRequestOptions(value.trim()), 250);
});

watch(() => requestOptions.value, async (options) => {
  // Auto-select first request when options load, but only if nothing selected yet
  if (options.length > 0 && !selectedRequestId.value) {
    const firstRequest = options[0];
    console.log('⚡ [NOTIFICATIONS-AUTO-SELECT] Selecting first request:', firstRequest);
    selectedRequestId.value = firstRequest.id;
    selectedRequestOption.value = firstRequest;
    await loadDashboard(false);
  }
});

watch(autoRefresh, setupAutoRefresh);
watch(() => dashboard.value?.meta?.auto_refresh_seconds, setupAutoRefresh);

watch(() => dashboard.value?.analytics, async () => {
  if (dashboard.value) {
    await nextTick();
    renderCharts();
  }
});

onMounted(async () => {
  await loadRequestOptions('');
  setupAutoRefresh();
});

onUnmounted(() => {
  clearTimeout(selectorDebounceHandle);
  clearTimeout(toastHandle);
  clearInterval(autoRefreshHandle);
  clearInterval(refreshCountdownHandle);
  destroyCharts();
});
</script>

<style scoped>
.selector-button {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  border-radius: 1.25rem;
  border: 1px solid rgb(229 231 235);
  background: white;
  padding: 0.9rem 1rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: rgb(17 24 39);
  transition: 160ms ease;
}

.selector-button:hover {
  border-color: rgb(252 165 165);
}

.selector-input,
.filter-input {
  width: 100%;
  border-radius: 1rem;
  border: 1px solid rgb(229 231 235);
  background: rgb(249 250 251);
  padding: 0.8rem 1rem;
  font-size: 0.875rem;
  outline: none;
}

.selector-input:focus,
.filter-input:focus {
  border-color: rgb(248 113 113);
  background: white;
  box-shadow: 0 0 0 4px rgb(254 226 226);
}

.selector-option {
  display: block;
  width: 100%;
  border-radius: 1.25rem;
  padding: 0.9rem 1rem;
  text-align: left;
  transition: 160ms ease;
}

.selector-option:hover {
  background: rgb(249 250 251);
}

.toggle-indicator {
  display: inline-block;
  height: 0.75rem;
  width: 0.75rem;
  border-radius: 9999px;
}

.filter-field {
  display: block;
}

.filter-label {
  margin-bottom: 0.4rem;
  display: block;
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgb(107 114 128);
}

.stream-card,
.insight-card {
  display: block;
  width: 100%;
  border-radius: 1.5rem;
  border: 1px solid rgb(229 231 235);
  padding: 1rem;
  transition: 160ms ease;
}

.control-button {
  border-radius: 1rem;
  border: 1px solid transparent;
  padding: 0.85rem 1rem;
  font-size: 0.875rem;
  font-weight: 700;
  transition: 150ms ease;
}

.control-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}
</style>
