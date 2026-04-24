<template>
  <AdminPageFrame
    kicker="Response Coordination"
    title="Blood Request Management"
    description="Real-time emergency operations console for request monitoring, matching, and status control."
    badge="Live request operations"
  >
    <template #actions>
        <button
          @click="toggleAutoRefresh"
          :class="autoRefresh
            ? 'border-green-300 bg-green-50 text-green-700'
            : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
          class="flex items-center gap-1.5 rounded-xl border px-3 py-2 text-sm font-medium transition-colors"
        >
          <span :class="{ 'animate-spin': autoRefresh }" class="inline-block leading-none">🔄</span>
          <span>{{ autoRefresh ? `Live (${autoRefreshSecs}s)` : 'Auto‑Refresh' }}</span>
        </button>

        <button
          @click="loadRequests(currentPage)"
          :disabled="loading"
          class="admin-button-secondary"
        >
          Refresh
        </button>
    </template>

    <!-- ─── Filter Panel ──────────────────────────────────────────────── -->
    <div class="admin-panel">
      <!-- Urgency toggle buttons -->
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">Urgency</span>
        <button
          v-for="u in urgencyOptions"
          :key="u.value"
          @click="setUrgencyFilter(u.value)"
          :class="filters.urgency === u.value ? u.active : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
          class="rounded-full px-3 py-1 text-xs font-semibold transition-colors"
        >
          {{ u.label }}
        </button>
      </div>

      <div class="mt-3 flex flex-wrap items-center gap-3">
        <!-- Status dropdown -->
        <div class="flex items-center gap-2">
          <span class="text-[11px] font-semibold uppercase tracking-widest text-gray-400">Status</span>
          <select
            v-model="filters.status"
            class="admin-input py-1.5"
          >
            <option value="">All</option>
            <option value="pending">Pending</option>
            <option value="matching">Matching</option>
            <option value="fulfilled">Fulfilled</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <!-- Location text filter -->
        <div class="flex items-center gap-2">
          <span class="text-[11px] font-semibold uppercase tracking-widest text-gray-400 whitespace-nowrap">Location</span>
          <input
            v-model="filters.location"
            type="text"
            placeholder="City or province…"
            class="admin-input min-w-[180px] py-1.5"
          />
        </div>

        <button
          v-if="hasActiveFilters"
          @click="clearFilters"
          class="text-xs font-medium text-red-600 hover:text-red-700"
        >
          Clear filters
        </button>

        <span class="ml-auto text-xs text-gray-400">
          {{ pagination.total }} total request{{ pagination.total !== 1 ? 's' : '' }}
        </span>
      </div>
    </div>

    <!-- ─── Table Card ────────────────────────────────────────────────── -->
    <div class="admin-surface">

      <!-- Loading state -->
      <div v-if="loading" class="flex flex-col items-center justify-center gap-3 py-20">
        <div class="h-9 w-9 rounded-full border-4 border-red-200 border-t-red-600 animate-spin"></div>
        <p class="text-sm text-gray-500">Loading blood requests…</p>
      </div>

      <!-- Error state -->
      <div v-else-if="loadError" class="flex flex-col items-center justify-center gap-4 py-20">
        <span class="text-5xl">⚠️</span>
        <div class="text-center">
          <p class="font-semibold text-gray-900">Failed to load blood requests</p>
          <p class="mt-1 text-sm text-gray-500">Check your connection and try again.</p>
        </div>
        <button
          @click="loadRequests(1)"
          class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700"
        >
          Retry
        </button>
      </div>

      <!-- Empty state -->
      <div v-else-if="sortedRequests.length === 0" class="flex flex-col items-center justify-center gap-4 py-20">
        <span class="text-5xl">🩸</span>
        <div class="text-center">
          <p class="font-semibold text-gray-900">No active blood requests</p>
          <p class="mt-1 text-sm text-gray-500">
            {{ hasActiveFilters ? 'No requests match the current filters.' : 'No requests have been created yet.' }}
          </p>
        </div>
        <button
          v-if="hasActiveFilters"
          @click="clearFilters"
          class="rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
        >
          Clear filters
        </button>
      </div>

      <!-- Data Table -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="border-b border-gray-100 bg-gray-50">
            <tr>
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">Blood Type</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">Hospital</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">Units</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">Urgency</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">Status</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">Responses</th>
              <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500">Required By</th>
              <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-500">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr
              v-for="req in sortedRequests"
              :key="req.id"
              :class="req.is_emergency || req.urgency_level === 'critical'
                ? 'bg-red-50/50 hover:bg-red-50'
                : 'hover:bg-gray-50'"
              class="transition-colors"
            >
              <!-- Blood Type + LIVE dot -->
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <span class="inline-flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-red-100 text-xs font-bold text-red-700 ring-1 ring-red-200">
                    {{ req.blood_type }}
                  </span>
                  <div v-if="isLive(req)" class="flex items-center gap-1">
                    <span class="inline-block h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-red-600">LIVE</span>
                  </div>
                </div>
              </td>

              <!-- Hospital -->
              <td class="px-4 py-3">
                <p class="max-w-[180px] truncate font-medium text-gray-900">{{ req.hospital_name }}</p>
                <p class="text-[11px] text-gray-400">
                  {{ req.city }}{{ req.province ? ', ' + req.province : '' }}
                </p>
              </td>

              <!-- Units -->
              <td class="px-4 py-3 font-semibold text-gray-800">
                {{ req.units_required || req.quantity || 0 }}
              </td>

              <!-- Urgency badge -->
              <td class="px-4 py-3">
                <span
                  :class="urgencyBadge(req.urgency_level)"
                  class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                >{{ req.urgency_level }}</span>
              </td>

              <!-- Status badge -->
              <td class="px-4 py-3">
                <span
                  :class="statusBadge(req.status)"
                  class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
                >{{ req.status }}</span>
              </td>

              <!-- Responses -->
              <td class="px-4 py-3">
                <p class="text-xs">
                  <span class="font-semibold text-green-700">{{ req.accepted_donors ?? 0 }}</span>
                  <span class="text-gray-400"> / </span>
                  <span class="font-semibold text-gray-700">{{ req.matched_donors_count ?? 0 }}</span>
                  <span class="text-gray-400"> donors</span>
                </p>
                <p class="text-[11px] text-gray-400">{{ req.responses_received ?? 0 }} responded</p>
              </td>

              <!-- ETA countdown -->
              <td class="px-4 py-3">
                <template v-if="req.required_on">
                  <p class="text-xs font-semibold" :class="etaClass(req.required_on)">
                    {{ etaLabel(req.required_on) }}
                  </p>
                  <p class="text-[11px] text-gray-400">{{ formatDate(req.required_on) }}</p>
                </template>
                <span v-else class="text-xs text-gray-400">—</span>
              </td>

              <!-- Row actions -->
              <td class="px-4 py-3">
                <div class="flex flex-wrap items-center justify-end gap-1.5">
                  <button
                    @click="openViewModal(req)"
                    class="rounded-md border border-gray-200 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-100"
                  >View</button>

                  <button
                    @click="triggerMatching(req)"
                    :disabled="!!req._matching || ['fulfilled', 'cancelled', 'completed'].includes(req.status)"
                    class="rounded-md border border-blue-200 px-2.5 py-1 text-xs font-medium text-blue-700 hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40"
                  >{{ req._matching ? '…' : 'Match' }}</button>

                  <button
                    @click="fulfillRequest(req)"
                    :disabled="['fulfilled', 'cancelled', 'completed'].includes(req.status)"
                    class="rounded-md border border-green-200 px-2.5 py-1 text-xs font-medium text-green-700 hover:bg-green-50 disabled:cursor-not-allowed disabled:opacity-40"
                  >Fulfill</button>

                  <button
                    @click="cancelRequest(req)"
                    :disabled="['fulfilled', 'cancelled', 'completed'].includes(req.status)"
                    class="rounded-md border border-red-200 px-2.5 py-1 text-xs font-medium text-red-700 hover:bg-red-50 disabled:cursor-not-allowed disabled:opacity-40"
                  >Cancel</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Pagination -->
        <div
          v-if="pagination.lastPage > 1"
          class="flex items-center justify-between border-t border-gray-100 px-4 py-3"
        >
          <p class="text-xs text-gray-500">
            Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
          </p>
          <div class="flex items-center gap-2">
            <button
              @click="loadPage(pagination.currentPage - 1)"
              :disabled="pagination.currentPage <= 1"
              class="rounded-lg border border-gray-200 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50 disabled:opacity-40"
            >← Prev</button>
            <span class="text-xs text-gray-600">
              Page {{ pagination.currentPage }} of {{ pagination.lastPage }}
            </span>
            <button
              @click="loadPage(pagination.currentPage + 1)"
              :disabled="pagination.currentPage >= pagination.lastPage"
              class="rounded-lg border border-gray-200 px-3 py-1 text-xs text-gray-600 hover:bg-gray-50 disabled:opacity-40"
            >Next →</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ─── Toast ─────────────────────────────────────────────────────── -->
    <Transition
      enter-active-class="transition ease-out duration-200"
      enter-from-class="translate-y-3 opacity-0"
      leave-active-class="transition ease-in duration-150"
      leave-to-class="translate-y-3 opacity-0"
    >
      <div
        v-if="toast.message"
        :class="{
          'bg-red-600':   toast.type === 'error',
          'bg-blue-600':  toast.type === 'info',
          'bg-green-600': toast.type === 'success',
        }"
        class="fixed bottom-6 right-6 z-50 flex items-center gap-2 rounded-xl px-4 py-3 text-sm font-medium text-white shadow-xl"
      >
        <span>{{ toast.type === 'error' ? '⚠️' : toast.type === 'info' ? 'ℹ️' : '✅' }}</span>
        {{ toast.message }}
      </div>
    </Transition>

    <!-- ─── View / Details Modal ──────────────────────────────────────── -->
    <div
      v-if="viewModal.open"
      class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4"
      @click.self="viewModal.open = false"
    >
      <div class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-2xl bg-white shadow-2xl">

        <!-- Modal header -->
        <div class="flex items-start justify-between border-b border-gray-100 p-5">
          <div>
            <div class="flex flex-wrap items-center gap-2">
              <h3 class="text-lg font-bold text-gray-900">Request Details</h3>
              <span
                v-if="viewModal.req?.case_id"
                class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-500"
              >{{ viewModal.req.case_id }}</span>
              <span
                v-if="viewModal.req?.is_emergency"
                class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700"
              >🚨 EMERGENCY</span>
            </div>
            <p class="mt-0.5 text-sm text-gray-500">{{ viewModal.req?.hospital_name }}</p>
          </div>
          <button
            @click="viewModal.open = false"
            class="flex-shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-700"
          >✕</button>
        </div>

        <!-- Modal body -->
        <div class="space-y-5 p-5">

          <!-- Key facts grid -->
          <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-3">
              <p class="text-xs text-gray-500">Blood Type</p>
              <p class="mt-1 text-2xl font-bold text-red-600">{{ viewModal.req?.blood_type }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
              <p class="text-xs text-gray-500">Component</p>
              <p class="mt-1 text-sm font-semibold text-gray-800">{{ viewModal.req?.component || 'Whole Blood' }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
              <p class="text-xs text-gray-500">Units Required</p>
              <p class="mt-1 text-2xl font-bold text-gray-800">
                {{ viewModal.req?.units_required || viewModal.req?.quantity || 0 }}
              </p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
              <p class="text-xs text-gray-500">Urgency</p>
              <span
                :class="urgencyBadge(viewModal.req?.urgency_level)"
                class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
              >{{ viewModal.req?.urgency_level }}</span>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
              <p class="text-xs text-gray-500">Status</p>
              <span
                :class="statusBadge(viewModal.req?.status)"
                class="mt-1 inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize"
              >{{ viewModal.req?.status }}</span>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
              <p class="text-xs text-gray-500">Location</p>
              <p class="mt-1 text-sm font-medium text-gray-800">
                {{ viewModal.req?.city }}{{ viewModal.req?.province ? ', ' + viewModal.req.province : '' }}
              </p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
              <p class="text-xs text-gray-500">Required On</p>
              <p class="mt-1 text-sm font-medium text-gray-800">{{ formatDate(viewModal.req?.required_on) }}</p>
            </div>
            <div class="rounded-lg bg-gray-50 p-3">
              <p class="text-xs text-gray-500">Search Radius</p>
              <p class="mt-1 text-sm font-medium text-gray-800">
                {{ viewModal.req?.distance_limit_km ? viewModal.req.distance_limit_km + ' km' : '—' }}
              </p>
            </div>
          </div>

          <!-- Clinical reason -->
          <div
            v-if="viewModal.req?.reason"
            class="rounded-lg border border-amber-100 bg-amber-50 p-3"
          >
            <p class="text-xs font-medium text-amber-700">Clinical Reason</p>
            <p class="mt-1 text-sm text-amber-900">{{ viewModal.req.reason }}</p>
          </div>

          <!-- Matching progress pipeline -->
          <div>
            <p class="mb-3 text-[11px] font-semibold uppercase tracking-widest text-gray-400">
              Matching Pipeline
            </p>
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1">
              <template v-for="(step, i) in pipelineSteps(viewModal.req)" :key="i">
                <div
                  :class="step.done
                    ? 'border-green-200 bg-green-50 text-green-700'
                    : step.active
                      ? 'border-blue-200 bg-blue-50 text-blue-700 ring-2 ring-blue-200'
                      : 'border-gray-200 bg-gray-50 text-gray-400'"
                  class="min-w-[90px] flex-shrink-0 rounded-lg border px-3 py-2 text-center"
                >
                  <div class="text-base">{{ step.done ? '✅' : step.active ? '🔄' : '⬜' }}</div>
                  <div class="mt-0.5 text-[11px] font-semibold leading-tight">{{ step.label }}</div>
                </div>
                <span v-if="i < 4" class="flex-shrink-0 text-xl font-bold text-gray-200">›</span>
              </template>
            </div>
          </div>

          <!-- Donor response stats -->
          <div>
            <p class="mb-3 text-[11px] font-semibold uppercase tracking-widest text-gray-400">
              Donor Response Stats
            </p>
            <div class="grid grid-cols-5 divide-x divide-gray-200 rounded-xl border border-gray-200 bg-gray-50 text-center">
              <div class="py-3">
                <p class="text-xl font-bold text-gray-800">{{ viewModal.req?.matched_donors_count ?? 0 }}</p>
                <p class="mt-0.5 text-[11px] text-gray-500">Matched</p>
              </div>
              <div class="py-3">
                <p class="text-xl font-bold text-gray-800">{{ viewModal.req?.notifications_sent ?? 0 }}</p>
                <p class="mt-0.5 text-[11px] text-gray-500">Notified</p>
              </div>
              <div class="py-3">
                <p class="text-xl font-bold text-gray-800">{{ viewModal.req?.responses_received ?? 0 }}</p>
                <p class="mt-0.5 text-[11px] text-gray-500">Responded</p>
              </div>
              <div class="py-3">
                <p class="text-xl font-bold text-green-600">{{ viewModal.req?.accepted_donors ?? 0 }}</p>
                <p class="mt-0.5 text-[11px] text-gray-500">Accepted</p>
              </div>
              <div class="py-3">
                <p class="text-xl font-bold text-blue-600">{{ viewModal.req?.fulfilled_units ?? 0 }}</p>
                <p class="mt-0.5 text-[11px] text-gray-500">Fulfilled</p>
              </div>
            </div>
          </div>

          <!-- Contact override -->
          <div
            v-if="viewModal.req?.contact_person || viewModal.req?.contact_number"
            class="flex flex-wrap gap-6 rounded-lg bg-gray-50 p-3"
          >
            <div v-if="viewModal.req.contact_person">
              <p class="text-xs text-gray-500">Contact Person</p>
              <p class="mt-0.5 text-sm font-medium text-gray-800">{{ viewModal.req.contact_person }}</p>
            </div>
            <div v-if="viewModal.req.contact_number">
              <p class="text-xs text-gray-500">Contact Number</p>
              <p class="mt-0.5 text-sm font-medium text-gray-800">{{ viewModal.req.contact_number }}</p>
            </div>
          </div>
        </div>

        <!-- Modal footer -->
        <div class="flex flex-wrap items-center justify-end gap-2 border-t border-gray-100 p-5">
          <button
            @click="triggerMatchingFromModal"
            :disabled="viewModal.req?._matching
              || ['fulfilled', 'cancelled', 'completed'].includes(viewModal.req?.status)"
            class="rounded-lg border border-blue-200 px-4 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50 disabled:cursor-not-allowed disabled:opacity-40"
          >🎯 Trigger Matching</button>

          <button
            @click="fulfillFromModal"
            :disabled="['fulfilled', 'cancelled', 'completed'].includes(viewModal.req?.status)"
            class="rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-40"
          >✅ Fulfill</button>

          <button
            @click="viewModal.open = false"
            class="rounded-lg border border-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
          >Close</button>
        </div>
      </div>
    </div>

  </AdminPageFrame>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import api from '../../lib/api';
import AdminPageFrame from './AdminPageFrame.vue';

// ─── State ────────────────────────────────────────────────────────────
const requests    = ref([]);
const loading     = ref(true);
const loadError   = ref(false);
const currentPage = ref(1);
const pagination  = ref({ currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const filters     = ref({ urgency: '', status: '', location: '' });
const autoRefresh     = ref(false);
const autoRefreshSecs = ref(30);
const viewModal = ref({ open: false, req: null });
const toast     = ref({ message: '', type: 'success' });

let autoRefreshTimer    = null;
let autoRefreshCountdown = null;
let locationDebounce    = null;
let toastTimer          = null;

// ─── Filter options ───────────────────────────────────────────────────
const urgencyOptions = [
  { value: '',         label: 'All',      active: 'bg-gray-800 text-white' },
  { value: 'critical', label: '🔴 Critical', active: 'bg-red-600 text-white' },
  { value: 'high',     label: '🟠 High',   active: 'bg-orange-500 text-white' },
  { value: 'medium',   label: '🔵 Medium', active: 'bg-blue-500 text-white' },
  { value: 'low',      label: '⚪ Low',    active: 'bg-gray-500 text-white' },
];

// ─── Computed ─────────────────────────────────────────────────────────
const hasActiveFilters = computed(() =>
  !!(filters.value.urgency || filters.value.status || filters.value.location),
);

const urgencyOrder = { critical: 0, high: 1, medium: 2, low: 3 };

const sortedRequests = computed(() =>
  [...requests.value].sort((a, b) => {
    const diff =
      (urgencyOrder[a.urgency_level] ?? 4) - (urgencyOrder[b.urgency_level] ?? 4);
    return diff !== 0 ? diff : new Date(b.created_at) - new Date(a.created_at);
  }),
);

// ─── Style helpers ────────────────────────────────────────────────────
const urgencyBadge = (u) => {
  switch (String(u ?? '').toLowerCase()) {
    case 'critical': return 'bg-red-100 text-red-700';
    case 'high':     return 'bg-orange-100 text-orange-700';
    case 'medium':   return 'bg-blue-100 text-blue-700';
    default:         return 'bg-gray-100 text-gray-600';
  }
};

const statusBadge = (s) => {
  switch (String(s ?? '').toLowerCase()) {
    case 'pending':   return 'bg-yellow-100 text-yellow-700';
    case 'matching':  return 'bg-blue-100 text-blue-700';
    case 'fulfilled': return 'bg-green-100 text-green-700';
    case 'completed': return 'bg-green-100 text-green-700';
    case 'cancelled': return 'bg-gray-100 text-gray-500';
    default:          return 'bg-gray-100 text-gray-600';
  }
};

const isLive = (req) =>
  ['pending', 'matching'].includes(req.status) &&
  (req.is_emergency || ['critical', 'high'].includes(req.urgency_level));

// ─── Date / ETA helpers ───────────────────────────────────────────────
const formatDate = (d) => {
  if (!d) return '—';
  return new Date(d).toLocaleDateString('en-PH', {
    year: 'numeric', month: 'short', day: 'numeric',
  });
};

const etaLabel = (d) => {
  if (!d) return '';
  const diffMs = new Date(d) - Date.now();
  if (diffMs <= 0) return 'Overdue';
  const h = Math.floor(diffMs / 3_600_000);
  const m = Math.floor((diffMs % 3_600_000) / 60_000);
  if (h > 48) return `in ${Math.floor(h / 24)}d`;
  if (h > 0)  return `in ${h}h ${m}m`;
  return `in ${m}m`;
};

const etaClass = (d) => {
  if (!d) return 'text-gray-400';
  const diffMs = new Date(d) - Date.now();
  if (diffMs <= 0)       return 'text-red-600 font-bold';
  if (diffMs < 3_600_000) return 'text-red-500 font-semibold';   // < 1 h
  if (diffMs < 86_400_000) return 'text-orange-500 font-semibold'; // < 24 h
  return 'text-green-600';
};

// ─── Pipeline step builder ────────────────────────────────────────────
const pipelineSteps = (req) => {
  if (!req) return [];
  const s          = req.status ?? '';
  const notified   = (req.notifications_sent  ?? 0) > 0;
  const responded  = (req.responses_received  ?? 0) > 0;
  const accepted   = (req.accepted_donors      ?? 0) > 0;
  const terminal   = ['fulfilled', 'completed'].includes(s);
  const postCreate = ['matching', 'matched', 'confirmed', 'fulfilled', 'completed'].includes(s);

  return [
    { label: 'Created',    done: true,                active: false },
    { label: 'Matching',   done: postCreate,          active: s === 'pending' },
    { label: 'Notified',   done: notified,            active: postCreate && !notified },
    { label: 'Responses',  done: responded,           active: notified && !responded },
    { label: 'Fulfilled',  done: terminal,            active: accepted && !terminal },
  ];
};

// ─── Toast ────────────────────────────────────────────────────────────
const showToast = (message, type = 'success') => {
  clearTimeout(toastTimer);
  toast.value = { message, type };
  toastTimer  = setTimeout(() => { toast.value = { message: '', type: 'success' }; }, 3500);
};

// ─── Data loading ─────────────────────────────────────────────────────
const loadRequests = async (page = 1) => {
  loading.value   = true;
  loadError.value = false;

  try {
    const params = { page, per_page: 20 };
    if (filters.value.urgency)  params.urgency_level = filters.value.urgency;
    if (filters.value.status)   params.status        = filters.value.status;
    if (filters.value.location) params.location      = filters.value.location;

    const res = await api.get('/admin/requests', { params });

    // Support both old (raw Laravel paginator) and new ({success,data,message}) envelopes
    const payload = res.data?.success !== undefined ? res.data.data : res.data;
    const items   = Array.isArray(payload?.data)
      ? payload.data
      : (Array.isArray(payload) ? payload : []);

    requests.value  = items;
    currentPage.value = page;
    pagination.value = {
      currentPage: payload?.current_page ?? 1,
      lastPage:    payload?.last_page    ?? 1,
      total:       payload?.total        ?? items.length,
      from:        payload?.from         ?? 1,
      to:          payload?.to           ?? items.length,
    };
  } catch {
    loadError.value = true;
  } finally {
    loading.value = false;
  }
};

const loadPage = (page) => {
  if (page < 1 || page > pagination.value.lastPage) return;
  loadRequests(page);
};

// ─── Filter helpers ───────────────────────────────────────────────────
const setUrgencyFilter = (value) => { filters.value.urgency = value; };

const clearFilters = () => {
  filters.value = { urgency: '', status: '', location: '' };
  loadRequests(1);
};

// Watchers – immediate reload on urgency/status; debounced on location
watch(() => filters.value.urgency, () => loadRequests(1));
watch(() => filters.value.status,  () => loadRequests(1));
watch(() => filters.value.location, () => {
  clearTimeout(locationDebounce);
  locationDebounce = setTimeout(() => loadRequests(1), 400);
});

// ─── Auto-refresh ─────────────────────────────────────────────────────
const toggleAutoRefresh = () => {
  autoRefresh.value = !autoRefresh.value;

  if (autoRefresh.value) {
    autoRefreshSecs.value = 30;
    autoRefreshTimer = setInterval(() => {
      loadRequests(currentPage.value);
      autoRefreshSecs.value = 30;
    }, 30_000);
    autoRefreshCountdown = setInterval(() => {
      autoRefreshSecs.value = Math.max(0, autoRefreshSecs.value - 1);
    }, 1_000);
  } else {
    clearInterval(autoRefreshTimer);
    clearInterval(autoRefreshCountdown);
  }
};

// ─── Row actions ──────────────────────────────────────────────────────
const triggerMatching = async (req) => {
  if (!req || req._matching) return;

  req._matching = true; // optimistic loading indicator
  try {
    await api.post(`/admin/requests/${req.id}/trigger-matching`);
    req.status    = 'matching';
    req._matching = false;
    showToast(`Matching triggered for ${req.blood_type} request.`);
  } catch {
    req._matching = false;
    showToast('Failed to trigger matching.', 'error');
  }
};

const fulfillRequest = async (req) => {
  if (!req || ['fulfilled', 'cancelled', 'completed'].includes(req.status)) return;
  const prev    = req.status;
  req.status    = 'fulfilled'; // optimistic

  try {
    const res     = await api.patch(`/admin/requests/${req.id}`, { status: 'fulfilled' });
    const updated = res.data?.data;
    if (updated?.status) req.status = updated.status;
    showToast(`Request #${req.id} marked as fulfilled.`);
  } catch {
    req.status = prev; // rollback
    showToast('Failed to fulfill request.', 'error');
  }
};

const cancelRequest = async (req) => {
  if (!req || ['fulfilled', 'cancelled', 'completed'].includes(req.status)) return;
  if (!window.confirm(
    `Cancel ${String(req.urgency_level ?? '').toUpperCase()} ${req.blood_type} request from ${req.hospital_name}?`,
  )) return;

  const prev = req.status;
  req.status = 'cancelled'; // optimistic

  try {
    const res     = await api.patch(`/admin/requests/${req.id}`, { status: 'cancelled' });
    const updated = res.data?.data;
    if (updated?.status) req.status = updated.status;
    showToast(`Request #${req.id} cancelled.`);
  } catch {
    req.status = prev; // rollback
    showToast('Failed to cancel request.', 'error');
  }
};

// ─── Modal helpers ─────────────────────────────────────────────────────
const openViewModal = (req) => { viewModal.value = { open: true, req }; };

const triggerMatchingFromModal = async () => {
  if (!viewModal.value.req) return;
  await triggerMatching(viewModal.value.req);
};

const fulfillFromModal = async () => {
  if (!viewModal.value.req) return;
  await fulfillRequest(viewModal.value.req);
  viewModal.value.open = false;
};

// ─── Lifecycle ────────────────────────────────────────────────────────
onMounted(() => loadRequests(1));

onUnmounted(() => {
  clearInterval(autoRefreshTimer);
  clearInterval(autoRefreshCountdown);
  clearTimeout(locationDebounce);
  clearTimeout(toastTimer);
});
</script>
