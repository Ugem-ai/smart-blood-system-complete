<template>
  <div class="space-y-6">
    <AdminPageFrame
      title="PRC Chapter Inventory Sync"
      description="Monitor chapter stock levels, identify critical shortages, and coordinate transfers between PRC branches."
      badge="Inventory Sync"
    >
      <template #actions>
        <div class="flex flex-wrap items-center gap-3">
          <button
            type="button"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            @click="loadChapters"
          >
            Refresh
          </button>
          <span
            :class="{
              'rounded-full px-3 py-1 text-xs font-semibold': true,
              'bg-emerald-100 text-emerald-800': streamStatus === 'connected',
              'bg-slate-100 text-slate-700': streamStatus === 'connecting',
              'bg-amber-100 text-amber-700': streamStatus === 'disconnected',
              'bg-rose-100 text-rose-700': streamStatus === 'unsupported',
            }"
          >
            {{ streamStatus === 'connected' ? 'Live updates connected' : streamStatus === 'connecting' ? 'Connecting live updates...' : streamStatus === 'disconnected' ? 'Live updates disconnected' : 'Live updates unavailable' }}
          </span>
        </div>
      </template>

      <template #metrics>
        <div class="grid gap-4 sm:grid-cols-3">
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">PRC Chapters</p>
            <p class="mt-3 text-3xl font-semibold text-slate-900">{{ chapters.length }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Available Inventory Lines</p>
            <p class="mt-3 text-3xl font-semibold text-slate-900">{{ availableLines }}</p>
          </div>
          <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-sm font-semibold text-slate-500">Critical Alerts</p>
            <p class="mt-3 text-3xl font-semibold text-rose-600">{{ criticalCount }}</p>
          </div>
        </div>
      </template>
    </AdminPageFrame>

    <div class="grid gap-6 lg:grid-cols-[1.85fr_1.15fr]">
      <section class="space-y-6">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">Chapter Inventory Overview</h3>
              <p class="mt-2 text-sm text-slate-500">Browse inventory across all PRC chapters and inspect current stock signals.</p>
            </div>
            <div class="grid gap-3 sm:grid-cols-3">
              <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Blood Type</label>
                <select v-model="filters.blood_type" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900">
                  <option value="">All types</option>
                  <option v-for="type in bloodTypes" :key="type" :value="type">{{ type }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Region</label>
                <input
                  v-model="filters.region"
                  type="text"
                  placeholder="Metro Manila, Visayas..."
                  class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900"
                />
              </div>
              <button
                type="button"
                class="inline-flex items-center justify-center rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                @click="searchChapterInventory"
              >
                Search Inventory
              </button>
            </div>
          </div>

          <div class="mt-6 overflow-hidden rounded-3xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
              <thead class="bg-slate-50 text-slate-500">
                <tr>
                  <th class="px-4 py-3">Chapter</th>
                  <th class="px-4 py-3">Location</th>
                  <th class="px-4 py-3">Type</th>
                  <th class="px-4 py-3">Units</th>
                  <th class="px-4 py-3">Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 bg-white">
                <tr v-for="chapter in filteredChapters" :key="chapter.id" class="cursor-pointer hover:bg-slate-50" @click="selectChapter(chapter)">
                  <td class="px-4 py-4 font-medium text-slate-900">{{ chapter.name }}</td>
                  <td class="px-4 py-4 text-slate-500">{{ chapter.location || 'N/A' }}</td>
                  <td class="px-4 py-4 text-slate-500">{{ chapter.primary_blood_type || 'Mixed' }}</td>
                  <td class="px-4 py-4 text-slate-900">{{ chapter.inventory_count || 0 }}</td>
                  <td class="px-4 py-4">
                    <span
                      :class="chapter.status === 'critical' ? 'rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700' : 'rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700'"
                    >
                      {{ chapter.status || 'active' }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <div v-if="selectedChapter" class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">Selected Chapter: {{ selectedChapter.name }}</h3>
              <p class="text-sm text-slate-500">{{ selectedChapter.location || 'Location unavailable' }}</p>
            </div>
            <button
              type="button"
              class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
              @click="loadSelectedChapterDetails"
            >
              Refresh Chapter Data
            </button>
          </div>

          <div class="mt-6 grid gap-4 sm:grid-cols-3">
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
              <p class="text-xs uppercase tracking-wide text-slate-500">Total Inventory Lines</p>
              <p class="mt-2 text-2xl font-semibold text-slate-900">{{ selectedChapter.inventory_count || 0 }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
              <p class="text-xs uppercase tracking-wide text-slate-500">Critical Stock</p>
              <p class="mt-2 text-2xl font-semibold text-rose-600">{{ selectedChapter.critical_count || 0 }}</p>
            </div>
            <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
              <p class="text-xs uppercase tracking-wide text-slate-500">Low Stock Lines</p>
              <p class="mt-2 text-2xl font-semibold text-amber-600">{{ selectedChapter.low_stock_count || 0 }}</p>
            </div>
          </div>

          <div class="mt-6 overflow-hidden rounded-3xl border border-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
              <thead class="bg-slate-50 text-slate-500">
                <tr>
                  <th class="px-4 py-3">Blood Type</th>
                  <th class="px-4 py-3">Inventory</th>
                  <th class="px-4 py-3">Status</th>
                  <th class="px-4 py-3">Reorder</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-200 bg-white">
                <tr v-for="item in selectedChapter.inventory || []" :key="item.id">
                  <td class="px-4 py-4 text-slate-900">{{ item.blood_type }}</td>
                  <td class="px-4 py-4 text-slate-900">{{ item.available_units }}</td>
                  <td class="px-4 py-4 text-slate-900">{{ item.status }}</td>
                  <td class="px-4 py-4 text-slate-500">{{ item.reorder_level }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <aside class="space-y-6">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <h3 class="text-lg font-semibold text-slate-900">Search Results</h3>
          <p class="mt-2 text-sm text-slate-500">Quickly locate inventory lines that match your filter criteria.</p>

          <div class="mt-5 space-y-3">
            <div v-if="searchResults.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
              No matching inventory lines found yet. Use the filters and click Search Inventory.
            </div>
            <div v-for="result in searchResults" :key="result.id" class="rounded-3xl border border-slate-200 p-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <p class="font-semibold text-slate-900">{{ result.blood_type }} at {{ result.chapter.name }}</p>
                  <p class="text-sm text-slate-500">{{ result.chapter.location || 'Unknown branch' }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ result.available_units }} u</span>
              </div>
              <p class="mt-3 text-xs uppercase tracking-wide text-slate-500">Status</p>
              <p class="text-sm text-slate-700">{{ result.status }}</p>
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="text-lg font-semibold text-slate-900">Chapter Transfer Request</h3>
              <p class="mt-2 text-sm text-slate-500">Create a transfer from one PRC chapter to the selected destination chapter.</p>
            </div>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">Destination</span>
          </div>

          <div class="mt-5 space-y-4">
            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Source Chapter</label>
                <select v-model="transferForm.source_chapter_id" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900">
                  <option value="">Select source chapter</option>
                  <option v-for="chapter in sourceChapterOptions" :key="chapter.id" :value="chapter.id">{{ chapter.chapter_name || chapter.name }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Blood Type</label>
                <select v-model="transferForm.blood_type" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900">
                  <option value="">Select blood type</option>
                  <option v-for="type in bloodTypes" :key="type" :value="type">{{ type }}</option>
                </select>
              </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Component Type</label>
                <select v-model="transferForm.component_type" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900">
                  <option v-for="type in componentTypes" :key="type" :value="type">{{ type }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Units Requested</label>
                <input type="number" min="1" v-model.number="transferForm.units_requested" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900" />
              </div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Priority</label>
                <select v-model="transferForm.priority_level" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900">
                  <option v-for="level in priorityLevels" :key="level" :value="level">{{ level }}</option>
                </select>
              </div>
              <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Reason</label>
                <input v-model="transferForm.reason_for_transfer" type="text" placeholder="Optional explanation" class="w-full rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-900" />
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <button type="button" class="rounded-xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800" @click="requestTransfer">Request Transfer</button>
              <button type="button" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="recommendTransferCandidates">Recommend Sources</button>
            </div>

            <div v-if="transferError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ transferError }}</div>
            <div v-if="transferStatus" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ transferStatus }}</div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <h3 class="text-lg font-semibold text-slate-900">Recommended Transfer Candidates</h3>
          <p class="mt-2 text-sm text-slate-500">Potential source chapters based on selected blood type and requested units.</p>

          <div class="mt-5 space-y-4">
            <div v-if="recommendationResults.length === 0" class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
              Use the form above to search for nearby transfer recommendations.
            </div>
            <div v-for="candidate in recommendationResults" :key="candidate.id" class="rounded-3xl border border-slate-200 p-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <p class="font-semibold text-slate-900">{{ candidate.chapter.chapter_name || candidate.chapter.name }}</p>
                  <p class="text-sm text-slate-500">{{ candidate.chapter.region || candidate.chapter.location || 'Unknown region' }}</p>
                </div>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800">{{ candidate.units_available }} units</span>
              </div>
              <div class="mt-3 grid gap-2 sm:grid-cols-2 text-sm text-slate-600">
                <span>Blood type: {{ candidate.blood_type }}</span>
                <span>Component: {{ candidate.component_type }}</span>
              </div>
            </div>
          </div>
        </div>

        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
          <h3 class="text-lg font-semibold text-slate-900">Nearby Chapter Availability</h3>
          <p class="mt-2 text-sm text-slate-500">Selected chapter transfer candidates within 100km.</p>

          <div v-if="nearbyInventory.length === 0" class="mt-6 rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
            Select a chapter to preview nearby inventory availability.
          </div>
          <div class="mt-4 space-y-4">
            <div v-for="item in nearbyInventory" :key="item.id" class="rounded-3xl border border-slate-200 p-4">
              <p class="font-semibold text-slate-900">{{ item.chapter.name }}</p>
              <p class="text-sm text-slate-500">{{ item.chapter.location || 'Unknown' }}</p>
              <div class="mt-3 flex flex-wrap gap-2 text-xs">
                <span class="rounded-full bg-emerald-100 px-2 py-1 text-emerald-800">{{ item.available_units }} units</span>
                <span class="rounded-full bg-slate-100 px-2 py-1 text-slate-700">{{ item.blood_type }}</span>
              </div>
            </div>
          </div>
        </div>
      </aside>
    </div>

    <div v-if="errorMessage" class="rounded-3xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
      {{ errorMessage }}
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AdminPageFrame from './AdminPageFrame.vue';
import { fetchChapters, fetchChapterDetails, searchInventory, fetchNearbyInventory, fetchRecommendations, createInventoryTransfer } from '../../lib/prcInventory';
import { createInventoryStream } from '../../lib/prcInventoryStream';

const chapters = ref([]);
const selectedChapter = ref(null);
const nearbyInventory = ref([]);
const searchResults = ref([]);
const recommendationResults = ref([]);
const transferStatus = ref('');
const transferError = ref('');
const errorMessage = ref('');
const inventoryStream = ref(null);
const streamStatus = ref('connecting');
const filters = ref({ blood_type: '', region: '' });
const transferForm = ref({
  source_chapter_id: '',
  blood_type: '',
  component_type: 'Whole Blood',
  units_requested: 1,
  priority_level: 'routine',
  reason_for_transfer: '',
});

const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const componentTypes = ['Whole Blood', 'Red Cells', 'Plasma', 'Platelets'];
const priorityLevels = ['routine', 'urgent', 'emergency'];

const loadChapters = async () => {
  errorMessage.value = '';
  try {
    const { chapters: chapterList } = await fetchChapters();
    chapters.value = chapterList.map((chapter) => ({
      ...chapter,
      inventory_count: chapter.inventory?.length || 0,
      critical_count: chapter.inventory?.filter((item) => item.status === 'critical').length || 0,
      low_stock_count: chapter.inventory?.filter((item) => item.status === 'low_stock').length || 0,
      primary_blood_type: chapter.inventory?.[0]?.blood_type || 'Mixed',
    }));

    if (!selectedChapter.value && chapters.value.length) {
      selectedChapter.value = chapters.value[0];
      loadSelectedChapterDetails();
    }
  } catch (error) {
    errorMessage.value = 'Unable to load chapter inventory. Please try again.';
  }
};

const loadSelectedChapterDetails = async () => {
  if (!selectedChapter.value) {
    return;
  }

  errorMessage.value = '';
  transferError.value = '';
  transferStatus.value = '';
  recommendationResults.value = [];

  try {
    const data = await fetchChapterDetails(selectedChapter.value.id);
    selectedChapter.value = {
      ...selectedChapter.value,
      ...data,
      inventory_count: data.inventory?.length || 0,
      critical_count: data.inventory?.filter((item) => item.status === 'critical').length || 0,
      low_stock_count: data.inventory?.filter((item) => item.status === 'low_stock').length || 0,
    };

    nearbyInventory.value = await fetchNearbyInventory(selectedChapter.value.id, 100);
    transferForm.value.source_chapter_id = chapters.value.find((chapter) => chapter.id !== selectedChapter.value.id)?.id || '';
  } catch (error) {
    errorMessage.value = 'Unable to load selected chapter details. Please try again.';
  }
};

const handleInventoryStreamUpdate = async (payload) => {
  if (!payload || !payload.chapter_id) {
    return;
  }

  if (selectedChapter.value && selectedChapter.value.id === payload.chapter_id) {
    await loadSelectedChapterDetails();
  }

  await loadChapters();
};

const handleInventoryStreamError = (event) => {
  console.error('Inventory stream error', event);
  streamStatus.value = 'disconnected';
  errorMessage.value = 'Real-time inventory connection lost. Please refresh the page or use the manual refresh button.';
};

const handleInventoryStreamOpen = () => {
  streamStatus.value = 'connected';
  errorMessage.value = '';
};

const connectInventoryStream = () => {
  disconnectInventoryStream();

  inventoryStream.value = createInventoryStream({
    onOpen: handleInventoryStreamOpen,
    onUpdate: handleInventoryStreamUpdate,
    onError: handleInventoryStreamError,
  });

  if (!inventoryStream.value) {
    streamStatus.value = 'unsupported';
  }
};

const disconnectInventoryStream = () => {
  if (inventoryStream.value) {
    inventoryStream.value.close();
    inventoryStream.value = null;
  }
};

const selectChapter = async (chapter) => {
  selectedChapter.value = chapter;
  await loadSelectedChapterDetails();
};

const searchChapterInventory = async () => {
  errorMessage.value = '';

  try {
    const results = await searchInventory(filters.value);
    searchResults.value = results;
  } catch (error) {
    errorMessage.value = 'Search failed. Please verify your parameters and try again.';
  }
};

const sourceChapterOptions = computed(() => chapters.value.filter((chapter) => chapter.id !== selectedChapter.value?.id));

const requestTransfer = async () => {
  transferStatus.value = '';
  transferError.value = '';
  if (!selectedChapter.value) {
    transferError.value = 'Please select a destination chapter.';
    return;
  }

  if (!transferForm.value.source_chapter_id) {
    transferError.value = 'A source chapter is required.';
    return;
  }

  if (!transferForm.value.blood_type) {
    transferError.value = 'Blood type is required.';
    return;
  }

  if (transferForm.value.units_requested < 1) {
    transferError.value = 'Units requested must be at least 1.';
    return;
  }

  try {
    await createInventoryTransfer({
      source_chapter_id: transferForm.value.source_chapter_id,
      destination_chapter_id: selectedChapter.value.id,
      blood_type: transferForm.value.blood_type,
      component_type: transferForm.value.component_type,
      units_requested: transferForm.value.units_requested,
      priority_level: transferForm.value.priority_level,
      reason_for_transfer: transferForm.value.reason_for_transfer,
    });

    transferStatus.value = 'Transfer request submitted successfully.';
    loadSelectedChapterDetails();
  } catch (error) {
    transferError.value = error?.response?.data?.message || 'Failed to submit transfer request. Please try again.';
  }
};

const recommendTransferCandidates = async () => {
  recommendationResults.value = [];
  transferError.value = '';
  transferStatus.value = '';

  if (!selectedChapter.value) {
    transferError.value = 'Please select a destination chapter first.';
    return;
  }

  if (!transferForm.value.blood_type) {
    transferError.value = 'Select a blood type to search for recommendations.';
    return;
  }

  try {
    const response = await fetchRecommendations(selectedChapter.value.id, {
      blood_type: transferForm.value.blood_type,
      component_type: transferForm.value.component_type,
      units_required: transferForm.value.units_requested,
    });
    recommendationResults.value = response.recommended_transfers || [];
  } catch (error) {
    transferError.value = error?.response?.data?.message || 'Unable to load recommendations.';
  }
};

const filteredChapters = computed(() => {
  if (!filters.value.region && !filters.value.blood_type) {
    return chapters.value;
  }

  return chapters.value.filter((chapter) => {
    const matchesRegion = filters.value.region ? chapter.location?.toLowerCase().includes(filters.value.region.toLowerCase()) : true;
    const matchesType = filters.value.blood_type ? chapter.primary_blood_type === filters.value.blood_type : true;
    return matchesRegion && matchesType;
  });
});

const availableLines = computed(() => chapters.value.reduce((total, chapter) => total + (chapter.inventory_count || 0), 0));
const criticalCount = computed(() => chapters.value.reduce((total, chapter) => total + (chapter.critical_count || 0), 0));

onMounted(() => {
  loadChapters();
  connectInventoryStream();
});

onUnmounted(() => {
  disconnectInventoryStream();
});
</script>
