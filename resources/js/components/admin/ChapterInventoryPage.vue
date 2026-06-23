<template>
  <div class="space-y-6">
    <AdminPageFrame
      title="Chapter Branch Inventory"
      description="Track branch-level blood inventory, coordinate inter-chapter transfers, and manage chapter API keys."
      badge="Inventory Coordination"
    >
      <template #actions>
        <div class="flex flex-wrap items-center gap-2">
          <button class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="refreshAll">
            Refresh
          </button>
        </div>
      </template>

      <template #metrics>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
          <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Total PRC Chapters</p>
            <p class="mt-3 text-3xl font-black text-gray-900">{{ kpis.totalChapters }}</p>
          </div>
          <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Total Available Inventory Lines</p>
            <p class="mt-3 text-3xl font-black text-emerald-900">{{ kpis.availableInventoryLines }}</p>
          </div>
          <div class="rounded-2xl border border-red-200 bg-red-50 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Critical Alerts</p>
            <p class="mt-3 text-3xl font-black text-red-700">{{ kpis.criticalAlerts }}</p>
            <p class="mt-1 text-xs text-red-600">Blood types currently at zero stock</p>
          </div>
          <div class="rounded-2xl border p-4 shadow-sm" :class="lastSyncCardClass">
            <p class="text-xs font-semibold uppercase tracking-wide" :class="lastSyncLabelClass">Last Sync Status</p>
            <p class="mt-3 text-lg font-black" :class="lastSyncValueClass">{{ lastSyncText }}</p>
          </div>
        </div>
      </template>
    </AdminPageFrame>

    <div v-if="globalError.show" class="rounded-2xl border border-red-200 bg-red-50 p-4 shadow-sm">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="text-sm font-semibold text-red-800">{{ globalError.message }}</p>
        </div>
        <div class="flex items-center gap-2">
          <button class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100" @click="refreshAll">
            Refresh
          </button>
          <button class="rounded-lg border border-red-200 bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-200" @click="dismissError">
            Dismiss
          </button>
        </div>
      </div>
    </div>

    <a-spin :spinning="isBusy">
      <div class="grid gap-6 xl:grid-cols-[0.9fr_1.6fr]">
        <section class="space-y-4 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <div>
              <h3 class="text-sm font-black uppercase tracking-[0.16em] text-gray-700">Branches</h3>
              <p class="mt-1 text-xs text-gray-500">Select a chapter branch to view full inventory details.</p>
            </div>
          </div>

          <div v-if="!chapters.length" class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600">🏥</div>
            <p class="mt-3 text-sm font-semibold text-gray-700">No chapters are registered</p>
            <p class="mt-1 text-xs text-gray-500">Create or sync chapter records to begin inventory coordination.</p>
          </div>

          <div v-else class="space-y-3">
            <button
              v-for="chapter in chapters"
              :key="chapter.id"
              class="w-full rounded-2xl border px-4 py-3 text-left transition"
              :class="selectedChapter?.id === chapter.id
                ? 'border-red-300 bg-red-50 shadow-sm ring-1 ring-red-200 border-l-4 border-l-red-600'
                : 'border-gray-200 bg-white hover:border-red-200 hover:bg-red-50/40'"
              @click="selectChapter(chapter)"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-bold text-gray-900">{{ chapter.name }}</p>
                  <p class="mt-1 text-xs text-gray-500">{{ chapter.location || 'Unknown location' }}</p>
                </div>
                <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide" :class="syncBadgeClass(chapter.sync_status)">
                  {{ chapter.sync_status || 'offline' }}
                </span>
              </div>
              <p class="mt-2 text-xs font-semibold text-gray-600">
                {{ chapterSummary(chapter) }}
              </p>
            </button>
          </div>
        </section>

        <section class="space-y-5 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
          <div v-if="!selectedChapter" class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center">
            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600">🩸</div>
            <p class="mt-3 text-sm font-semibold text-gray-700">Select a branch to view inventory details</p>
            <p class="mt-1 text-xs text-gray-500">Detailed stock, transfers, nearby availability, and API keys appear here.</p>
          </div>

          <template v-else>
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <h3 class="text-xl font-black text-gray-900">{{ selectedChapter.name }}</h3>
                <p class="mt-1 text-sm text-gray-500">{{ selectedChapter.location || 'Unknown location' }}</p>
              </div>
              <div class="flex flex-wrap items-center gap-2">
                <button class="rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100" @click="openTransferForm = !openTransferForm">
                  {{ openTransferForm ? 'Hide Transfer Form' : 'Request Transfer' }}
                </button>
              </div>
            </div>

            <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-3">
              <button
                v-for="tab in tabs"
                :key="tab"
                class="rounded-full px-4 py-1.5 text-xs font-bold uppercase tracking-wide transition"
                :class="activeTab === tab ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                @click="activeTab = tab"
              >
                {{ tab }}
              </button>
            </div>

            <div v-if="activeTab === 'Inventory'" class="space-y-5">
              <div class="grid gap-3 md:grid-cols-[1fr_1fr_auto]">
                <label class="block">
                  <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Blood Type</span>
                  <select v-model="inventoryFilters.blood_type" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                    <option value="">All</option>
                    <option v-for="bloodType in bloodTypes" :key="bloodType" :value="bloodType">{{ bloodType }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</span>
                  <select v-model="inventoryFilters.status" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900">
                    <option value="">All</option>
                    <option value="adequate">Adequate</option>
                    <option value="low">Low</option>
                    <option value="critical">Critical</option>
                  </select>
                </label>
                <button class="self-end rounded-xl bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-black" @click="loadSelectedInventory">
                  Search Inventory
                </button>
              </div>

              <div v-if="!inventoryRows.length" class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-600">📦</div>
                <p class="mt-3 text-sm font-semibold text-gray-700">No inventory found for this chapter</p>
              </div>

              <div v-else class="overflow-x-auto rounded-2xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Blood Type</th>
                      <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Component</th>
                      <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Units Available</th>
                      <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Status</th>
                      <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Last Updated</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="row in inventoryRows" :key="row.id">
                      <td class="px-4 py-3 font-semibold text-gray-900">{{ row.blood_type }}</td>
                      <td class="px-4 py-3 text-gray-700">{{ row.component_type }}</td>
                      <td class="px-4 py-3 font-bold text-gray-900">{{ row.units_available }}</td>
                      <td class="px-4 py-3">
                        <span class="rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide" :class="inventoryStatusBadge(row)">
                          {{ inventoryStatusText(row) }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-gray-600">{{ formatDateTime(row.last_updated || row.last_synced_at) }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-if="activeTab === 'API Keys'" class="space-y-4">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p class="text-sm font-semibold text-gray-800">Chapter API Keys</p>
                  <p class="text-xs text-gray-500">Last Synced:
                    <span class="font-semibold" :class="lastSyncedIndicatorClass(selectedChapter.last_synced_at)">
                      {{ lastSyncedIndicatorText(selectedChapter.last_synced_at) }}
                    </span>
                  </p>
                </div>
                <button class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700" :disabled="loading.createApiKey" @click="handleGenerateApiKey">
                  {{ loading.createApiKey ? 'Generating...' : 'Generate New Key' }}
                </button>
              </div>

              <div v-if="!apiKeys.length" class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-8 text-center">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-600">🔑</div>
                <p class="mt-3 text-sm font-semibold text-gray-700">No active API keys</p>
              </div>

              <div v-else class="overflow-hidden rounded-2xl border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Label</th>
                      <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Created</th>
                      <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Last Used</th>
                      <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-gray-500">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-100 bg-white">
                    <tr v-for="key in apiKeys" :key="key.id">
                      <td class="px-4 py-3 text-gray-900">{{ key.label || 'Untitled key' }}</td>
                      <td class="px-4 py-3 text-gray-600">{{ formatDateTime(key.created_at) }}</td>
                      <td class="px-4 py-3 text-gray-600">{{ key.last_used_at ? formatDateTime(key.last_used_at) : 'Never used' }}</td>
                      <td class="px-4 py-3 text-right">
                        <button class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100" @click="handleRevokeApiKey(key)">
                          Revoke
                        </button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>

            <div v-if="openTransferForm" class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <h4 class="text-sm font-black uppercase tracking-[0.16em] text-gray-700">Chapter Transfer Request</h4>
              <div class="mt-4 grid gap-3 md:grid-cols-2">
                <label class="block">
                  <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Source Chapter</span>
                  <select v-model="transferForm.source_chapter_id" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                    <option value="">Select source chapter</option>
                    <option v-for="chapter in sourceChapterOptions" :key="chapter.id" :value="chapter.id">{{ chapter.name }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Destination Chapter</span>
                  <select v-model="transferForm.destination_chapter_id" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                    <option value="">Select destination chapter</option>
                    <option v-for="chapter in chapters" :key="chapter.id" :value="chapter.id">{{ chapter.name }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Blood Type</span>
                  <select v-model="transferForm.blood_type" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                    <option value="">Select blood type</option>
                    <option v-for="bloodType in bloodTypes" :key="bloodType" :value="bloodType">{{ bloodType }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Component Type</span>
                  <select v-model="transferForm.component_type" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                    <option v-for="type in componentTypes" :key="type" :value="type">{{ type }}</option>
                  </select>
                </label>
                <label class="block">
                  <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Units Requested</span>
                  <input v-model.number="transferForm.units_requested" type="number" min="1" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm" />
                </label>
                <label class="block">
                  <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Priority</span>
                  <select v-model="transferForm.priority" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm">
                    <option value="routine">Routine</option>
                    <option value="urgent">Urgent</option>
                    <option value="emergency">Emergency</option>
                  </select>
                </label>
                <label class="block md:col-span-2">
                  <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Reason</span>
                  <textarea v-model="transferForm.reason" rows="2" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm" placeholder="Explain why this transfer is needed"></textarea>
                </label>
              </div>

              <div class="mt-4 flex flex-wrap items-center gap-2">
                <button class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100" :disabled="loading.recommendations" @click="handleRecommendSources">
                  {{ loading.recommendations ? 'Loading...' : 'Recommend Sources' }}
                </button>
                <button class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700" :disabled="loading.transfer" @click="handleRequestTransfer">
                  {{ loading.transfer ? 'Submitting...' : 'Request Transfer' }}
                </button>
              </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4">
              <h4 class="text-sm font-black uppercase tracking-[0.16em] text-gray-700">Recommended Transfer Candidates</h4>
              <p class="mt-1 text-xs text-gray-500">Displayed after clicking Recommend Sources.</p>

              <div v-if="!recommendations.length" class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-600">🧭</div>
                <p class="mt-3 text-sm font-semibold text-gray-700">No transfer recommendations found</p>
              </div>

              <div v-else class="mt-4 space-y-3">
                <div v-for="candidate in recommendations" :key="candidate.inventory_id || candidate.chapter_id" class="rounded-2xl border border-gray-200 p-4">
                  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <p class="text-sm font-bold text-gray-900">{{ candidate.chapter_name }}</p>
                      <p class="text-xs text-gray-500">{{ candidate.location || 'Unknown location' }} · {{ formatDistance(candidate.distance_km) }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                      <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-700">{{ candidate.units_available }} units</span>
                      <button class="rounded-lg border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100" @click="selectRecommendation(candidate)">
                        Select
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4">
              <h4 class="text-sm font-black uppercase tracking-[0.16em] text-gray-700">Nearby Chapter Availability</h4>
              <p class="mt-1 text-xs text-gray-500">Chapters within 100km of the selected destination.</p>

              <div v-if="!nearbyChapters.length" class="mt-4 rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-6 text-center">
                <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-gray-200 text-gray-600">📍</div>
                <p class="mt-3 text-sm font-semibold text-gray-700">No nearby chapters found</p>
              </div>

              <div v-else class="mt-4 grid gap-3 md:grid-cols-2">
                <div v-for="nearby in nearbyChapters" :key="nearby.chapter.id || nearby.chapter_id" class="rounded-2xl border border-gray-200 p-3">
                  <p class="text-sm font-bold text-gray-900">{{ nearby.chapter?.name || nearby.name }}</p>
                  <p class="text-xs text-gray-500">{{ nearby.chapter?.location || nearby.location }} · {{ formatDistance(nearby.distance_km) }}</p>
                  <div class="mt-2 flex flex-wrap gap-1.5">
                    <span
                      v-for="stock in nearby.stock_summary || []"
                      :key="`${nearby.chapter?.id || nearby.chapter_id}-${stock.blood_type}`"
                      class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700"
                    >
                      {{ stock.blood_type }}: {{ stock.units_available }}
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </section>
      </div>
    </a-spin>

    <div v-if="apiKeyModal.open" class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4" @click.self="closeApiKeyModal">
      <div class="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-red-600">New API Key</p>
        <p class="mt-2 text-sm text-gray-600">Store this key now. It will not be fully visible again.</p>
        <div class="mt-4 rounded-xl border border-gray-200 bg-gray-50 p-3">
          <p class="break-all font-mono text-sm text-gray-800">{{ apiKeyModal.key }}</p>
        </div>
        <div class="mt-4 flex justify-end gap-2">
          <button class="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-100" @click="copyApiKey">
            Copy
          </button>
          <button class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-red-700" @click="closeApiKeyModal">
            Close
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { message } from 'ant-design-vue';
import AdminPageFrame from './AdminPageFrame.vue';
import { useChapterInventory } from '../../composables/useChapterInventory';

const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const componentTypes = ['Whole Blood', 'Red Cells', 'Plasma', 'Platelets'];
const tabs = ['Inventory', 'API Keys'];

const {
  loading,
  getErrorMessage,
  fetchChapters,
  fetchChapterInventory,
  fetchRecommendations,
  createTransfer,
  fetchNearbyChapters,
  fetchApiKeys,
  generateApiKey,
  revokeApiKey,
} = useChapterInventory();

const chapters = ref([]);
const selectedChapter = ref(null);
const inventoryRows = ref([]);
const recommendations = ref([]);
const nearbyChapters = ref([]);
const apiKeys = ref([]);
const activeTab = ref('Inventory');
const openTransferForm = ref(false);
const autoRefreshHandle = ref(null);
const kpis = ref({
  totalChapters: 0,
  availableInventoryLines: 0,
  criticalAlerts: 0,
  lastSyncAt: null,
  lastSyncState: 'offline',
});

const inventoryFilters = ref({
  blood_type: '',
  status: '',
});

const transferForm = ref({
  source_chapter_id: '',
  destination_chapter_id: '',
  blood_type: '',
  component_type: 'Whole Blood',
  units_requested: 1,
  priority: 'routine',
  reason: '',
});

const globalError = ref({
  show: false,
  message: '',
});

const apiKeyModal = ref({
  open: false,
  key: '',
});

const sourceChapterOptions = computed(() => {
  if (!selectedChapter.value) return chapters.value;
  return chapters.value.filter((chapter) => chapter.id !== selectedChapter.value.id);
});

const isBusy = computed(() => {
  return Object.values(loading.value).some((state) => Boolean(state));
});

const lastSyncText = computed(() => {
  if (!kpis.value.lastSyncAt) return 'Never synced';
  return formatDateTime(kpis.value.lastSyncAt);
});

const lastSyncCardClass = computed(() => {
  if (kpis.value.lastSyncState === 'live') return 'border-emerald-200 bg-emerald-50';
  if (kpis.value.lastSyncState === 'stale') return 'border-amber-200 bg-amber-50';
  return 'border-red-200 bg-red-50';
});

const lastSyncLabelClass = computed(() => {
  if (kpis.value.lastSyncState === 'live') return 'text-emerald-700';
  if (kpis.value.lastSyncState === 'stale') return 'text-amber-700';
  return 'text-red-700';
});

const lastSyncValueClass = computed(() => {
  if (kpis.value.lastSyncState === 'live') return 'text-emerald-800';
  if (kpis.value.lastSyncState === 'stale') return 'text-amber-800';
  return 'text-red-800';
});

const chapterSummary = (chapter) => {
  const typesCount = Math.max(0, Number(chapter.blood_types_count || chapter.inventory_lines_count || 0));
  const criticalCount = Math.max(0, Number(chapter.critical_count || 0));
  return `${typesCount} blood types · ${criticalCount} critical`;
};

const syncBadgeClass = (status) => {
  if (status === 'live') return 'bg-emerald-100 text-emerald-700';
  if (status === 'stale') return 'bg-amber-100 text-amber-700';
  return 'bg-red-100 text-red-700';
};

const inventoryStatusText = (row) => {
  const units = Number(row.units_available || 0);
  if (units <= 0) return 'Critical';
  if (units <= 5) return 'Low';
  return 'Adequate';
};

const inventoryStatusBadge = (row) => {
  const units = Number(row.units_available || 0);
  if (units <= 0) return 'bg-red-100 text-red-700';
  if (units <= 5) return 'bg-amber-100 text-amber-700';
  return 'bg-emerald-100 text-emerald-700';
};

const lastSyncedIndicatorClass = (value) => {
  if (!value) return 'text-red-700';
  const hours = hoursAgo(value);
  if (hours <= 24) return 'text-emerald-700';
  if (hours <= 72) return 'text-amber-700';
  return 'text-red-700';
};

const lastSyncedIndicatorText = (value) => {
  if (!value) return 'Never';
  const hours = hoursAgo(value);
  if (hours <= 24) return `Live (${formatDateTime(value)})`;
  if (hours <= 72) return `Stale (${formatDateTime(value)})`;
  return `Offline (${formatDateTime(value)})`;
};

const setGlobalError = (error, fallback) => {
  globalError.value = {
    show: true,
    message: getErrorMessage(error, fallback),
  };
};

const dismissError = () => {
  globalError.value.show = false;
};

const loadChaptersData = async () => {
  try {
    const payload = await fetchChapters();
    const chapterList = payload.chapters || [];
    chapters.value = chapterList;

    const summary = payload.kpis || payload.summary || {};
    kpis.value = {
      totalChapters: Number(summary.total_chapters || chapterList.length || 0),
      availableInventoryLines: Number(summary.available_inventory_lines || summary.total_available_inventory_lines || 0),
      criticalAlerts: Number(summary.critical_alerts || 0),
      lastSyncAt: summary.last_sync_at || null,
      lastSyncState: summary.last_sync_state || inferSyncState(summary.last_sync_at),
    };

    if (!selectedChapter.value && chapterList.length) {
      selectedChapter.value = chapterList[0];
      transferForm.value.destination_chapter_id = chapterList[0].id;
    }
  } catch (error) {
    setGlobalError(error, 'Live chapter connection failed. Please refresh.');
  }
};

const loadSelectedInventory = async () => {
  if (!selectedChapter.value) return;

  try {
    const payload = await fetchChapterInventory(selectedChapter.value.id, inventoryFilters.value);
    inventoryRows.value = payload.inventory || [];

    const chapter = chapters.value.find((item) => item.id === selectedChapter.value.id);
    if (chapter) {
      selectedChapter.value = chapter;
    }
  } catch (error) {
    setGlobalError(error, 'Unable to load chapter inventory.');
  }
};

const loadNearby = async () => {
  if (!selectedChapter.value) return;

  try {
    nearbyChapters.value = await fetchNearbyChapters(selectedChapter.value.id, 100);
  } catch (error) {
    setGlobalError(error, 'Unable to load nearby chapter availability.');
  }
};

const loadApiKeys = async () => {
  if (!selectedChapter.value) return;

  try {
    apiKeys.value = await fetchApiKeys(selectedChapter.value.id);
  } catch (error) {
    setGlobalError(error, 'Unable to load chapter API keys.');
  }
};

const refreshAll = async () => {
  await loadChaptersData();
  if (selectedChapter.value) {
    await Promise.all([loadSelectedInventory(), loadNearby(), loadApiKeys()]);
  }
};

const selectChapter = async (chapter) => {
  selectedChapter.value = chapter;
  transferForm.value.destination_chapter_id = chapter.id;
  recommendations.value = [];
  await Promise.all([loadSelectedInventory(), loadNearby(), loadApiKeys()]);
};

const validateTransferForm = () => {
  if (!transferForm.value.source_chapter_id) return 'Source chapter is required.';
  if (!transferForm.value.destination_chapter_id) return 'Destination chapter is required.';
  if (!transferForm.value.blood_type) return 'Blood type is required.';
  if (!transferForm.value.component_type) return 'Component type is required.';
  if (!Number.isInteger(Number(transferForm.value.units_requested)) || Number(transferForm.value.units_requested) < 1) {
    return 'Units requested must be at least 1.';
  }
  if (!transferForm.value.priority) return 'Priority is required.';

  if (Number(transferForm.value.source_chapter_id) === Number(transferForm.value.destination_chapter_id)) {
    return 'Source and destination chapter must be different.';
  }

  return '';
};

const handleRequestTransfer = async () => {
  const validationMessage = validateTransferForm();
  if (validationMessage) {
    message.error(validationMessage);
    return;
  }

  try {
    await createTransfer({
      source_chapter_id: Number(transferForm.value.source_chapter_id),
      destination_chapter_id: Number(transferForm.value.destination_chapter_id),
      blood_type: transferForm.value.blood_type,
      component_type: transferForm.value.component_type,
      units_requested: Number(transferForm.value.units_requested),
      priority: transferForm.value.priority,
      reason: transferForm.value.reason || null,
    });

    message.success('Transfer request submitted successfully.');
  } catch (error) {
    message.error(getErrorMessage(error, 'Failed to submit transfer request.'));
  }
};

const handleRecommendSources = async () => {
  if (!transferForm.value.destination_chapter_id) {
    message.error('Select a destination chapter first.');
    return;
  }

  if (!transferForm.value.blood_type) {
    message.error('Select a blood type first.');
    return;
  }

  if (Number(transferForm.value.units_requested) < 1) {
    message.error('Units requested must be at least 1.');
    return;
  }

  try {
    recommendations.value = await fetchRecommendations({
      blood_type: transferForm.value.blood_type,
      units: Number(transferForm.value.units_requested),
      destination_chapter_id: Number(transferForm.value.destination_chapter_id),
    });

    if (!recommendations.value.length) {
      message.info('No recommendation candidates found.');
    }
  } catch (error) {
    message.error(getErrorMessage(error, 'Failed to load recommendations.'));
  }
};

const selectRecommendation = (candidate) => {
  transferForm.value.source_chapter_id = candidate.source_chapter_id || candidate.chapter_id;
  transferForm.value.component_type = candidate.component_type || transferForm.value.component_type;
  if (!transferForm.value.blood_type) {
    transferForm.value.blood_type = candidate.blood_type;
  }
  message.success('Source chapter was applied to transfer form.');
};

const handleGenerateApiKey = async () => {
  if (!selectedChapter.value) return;

  try {
    const payload = await generateApiKey(selectedChapter.value.id);
    const fullKey = payload?.plain_text_key || payload?.api_key || payload?.key?.api_key || '';

    if (!fullKey) {
      message.error('API key generated but no plain text value was returned.');
      await loadApiKeys();
      return;
    }

    apiKeyModal.value = {
      open: true,
      key: fullKey,
    };

    message.success('API key generated.');
    await loadApiKeys();
  } catch (error) {
    message.error(getErrorMessage(error, 'Failed to generate API key.'));
  }
};

const handleRevokeApiKey = async (key) => {
  if (!selectedChapter.value) return;

  const confirmed = window.confirm('Revoke this API key? This action cannot be undone.');
  if (!confirmed) return;

  try {
    await revokeApiKey(selectedChapter.value.id, key.id);
    message.success('API key revoked.');
    await loadApiKeys();
  } catch (error) {
    message.error(getErrorMessage(error, 'Failed to revoke API key.'));
  }
};

const closeApiKeyModal = () => {
  apiKeyModal.value = { open: false, key: '' };
};

const copyApiKey = async () => {
  try {
    await navigator.clipboard.writeText(apiKeyModal.value.key);
    message.success('API key copied to clipboard.');
  } catch (error) {
    message.error('Copy failed. Please copy manually.');
  }
};

const inferSyncState = (lastSyncAt) => {
  if (!lastSyncAt) return 'offline';
  const hours = hoursAgo(lastSyncAt);
  if (hours <= 24) return 'live';
  if (hours <= 72) return 'stale';
  return 'offline';
};

const hoursAgo = (value) => {
  const timestamp = new Date(value).getTime();
  if (!timestamp) return Number.POSITIVE_INFINITY;
  return (Date.now() - timestamp) / 3600000;
};

const formatDateTime = (value) => {
  if (!value) return 'N/A';
  return new Date(value).toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
};

const formatDistance = (value) => {
  const number = Number(value);
  if (!Number.isFinite(number) || number >= 9999) return 'Distance unavailable';
  return `${number.toFixed(1)} km`;
};

onMounted(async () => {
  await refreshAll();

  autoRefreshHandle.value = window.setInterval(async () => {
    if (selectedChapter.value) {
      await loadSelectedInventory();
    }
  }, 30000);
});

onUnmounted(() => {
  if (autoRefreshHandle.value) {
    window.clearInterval(autoRefreshHandle.value);
    autoRefreshHandle.value = null;
  }
});
</script>
