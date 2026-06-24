<template>
  <div class="space-y-6">
    <AdminPageFrame
      title="Blood Inventory"
      description="Manage blood inventory for PRC Cavite Chapter"
      badge="PRC Cavite Chapter"
    >
      <template #actions>
        <div class="flex items-center gap-2">
          <button class="rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="refreshInventory">
            Refresh
          </button>
        </div>
      </template>

      <template #metrics>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
          <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Total Units</p>
            <p class="mt-3 text-3xl font-black text-blue-900">{{ stats.totalUnits }}</p>
          </div>
          <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Adequate Stock</p>
            <p class="mt-3 text-3xl font-black text-emerald-900">{{ stats.adequateCount }}</p>
          </div>
          <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Low Stock</p>
            <p class="mt-3 text-3xl font-black text-amber-900">{{ stats.lowCount }}</p>
          </div>
          <div class="rounded-2xl border border-red-200 bg-red-50 p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Critical Stock</p>
            <p class="mt-3 text-3xl font-black text-red-900">{{ stats.criticalCount }}</p>
          </div>
          <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Last Updated</p>
            <p class="mt-3 text-sm font-black text-gray-900">{{ lastUpdatedText }}</p>
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
          <button class="rounded-lg border border-red-300 bg-white px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100" @click="refreshInventory">
            Refresh
          </button>
          <button class="rounded-lg border border-red-200 bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-200" @click="dismissError">
            Dismiss
          </button>
        </div>
      </div>
    </div>

    <a-spin :spinning="loading.inventory">
      <!-- Filters -->
      <section class="space-y-5 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
        <div>
          <h3 class="text-sm font-black uppercase tracking-[0.16em] text-gray-700">Filter Inventory</h3>
          <p class="mt-1 text-xs text-gray-500">Search and filter blood inventory by blood type, component, and status.</p>
        </div>

        <div class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_auto]">
          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Blood Type</span>
            <select v-model="filters.blood_type" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900">
              <option value="">All Types</option>
              <option v-for="type in bloodTypes" :key="type" :value="type">{{ type }}</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Component</span>
            <select v-model="filters.component" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900">
              <option value="">All Components</option>
              <option v-for="type in componentTypes" :key="type" :value="type">{{ type }}</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</span>
            <select v-model="filters.status" class="w-full rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900">
              <option value="">All Status</option>
              <option value="adequate">Adequate</option>
              <option value="low">Low</option>
              <option value="critical">Critical</option>
            </select>
          </label>
          <button class="self-end rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700" @click="applyFilters">
            Search
          </button>
        </div>
      </section>

      <!-- Table -->
      <section class="space-y-5 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
        <div>
          <h3 class="text-sm font-black uppercase tracking-[0.16em] text-gray-700">Current Inventory</h3>
          <p class="mt-1 text-xs text-gray-500">{{ inventoryRows.length }} blood type(s) in stock</p>
        </div>

        <div v-if="!inventoryRows.length" class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-12 text-center">
          <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-gray-200 text-gray-600">📦</div>
          <p class="mt-3 text-sm font-semibold text-gray-700">No inventory items found</p>
          <p class="mt-1 text-xs text-gray-500">Try adjusting your filters or add new inventory.</p>
        </div>

        <div v-else class="overflow-x-auto rounded-2xl border border-gray-200">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-600">Blood Type</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-600">Component</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-gray-600">Units Available</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-600">Status</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-600">Last Updated</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-gray-600">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr v-for="row in inventoryRows" :key="row.id">
                <td class="px-4 py-3 font-bold text-gray-900">{{ row.blood_type }}</td>
                <td class="px-4 py-3 text-gray-700">{{ row.component_type || '—' }}</td>
                <td class="px-4 py-3 text-center font-bold text-gray-900">{{ row.units_available }}</td>
                <td class="px-4 py-3 align-middle">
                  <span :class="['inline-flex items-center gap-2 rounded-full px-3 py-1 font-semibold whitespace-nowrap', getStatusBadgeClass(row)]">
                    <span :class="['w-3 h-3 rounded-full shrink-0', getStatusDotClass(row)]" aria-hidden="true"></span>
                    <span class="text-sm leading-none">{{ getStatusText(row) }}</span>
                    <span :class="['ml-1 rounded-full bg-white/80 px-2 py-0.5 text-xs font-black', getStatusCountTextClass(row)]">{{ Number(row.units_available) || 0 }}</span>
                  </span>
                </td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ formatDateTime(row.last_synced_at || row.updated_at) }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="flex items-center justify-center gap-2">
                    <button @click="handleAddUnits(row)" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1.5 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 hover:border-emerald-300" title="Add units">
                      +
                    </button>
                    <button @click="handleRemoveUnits(row)" class="rounded-lg border border-orange-200 bg-orange-50 px-2 py-1.5 text-xs font-semibold text-orange-700 transition hover:bg-orange-100 hover:border-orange-300" title="Remove units">
                      −
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Alerts -->
      <section v-if="alertItems.length > 0" class="space-y-5 rounded-3xl border border-amber-200 bg-amber-50 p-5 shadow-sm">
        <div>
          <h3 class="text-sm font-black uppercase tracking-[0.16em] text-amber-900">Inventory Alerts</h3>
          <p class="mt-1 text-xs text-amber-700">{{ alertItems.length }} blood type(s) require attention</p>
        </div>

        <div class="grid gap-3 md:grid-cols-2">
          <div v-for="item in alertItems" :key="item.id" class="rounded-2xl border border-amber-300 bg-white p-4">
            <div class="flex items-start justify-between gap-3">
              <div>
                <p class="text-sm font-bold text-gray-900">{{ item.blood_type }} - {{ item.component_type }}</p>
                <p class="mt-2 text-sm font-semibold" :class="item.units_available <= 0 ? 'text-red-700' : 'text-amber-700'">
                  {{ item.units_available <= 0 ? 'CRITICAL: Out of Stock' : `LOW: Only ${item.units_available} unit(s)` }}
                </p>
                <p class="mt-1 text-xs text-gray-600">Last updated: {{ formatDateTime(item.last_synced_at || item.updated_at) }}</p>
              </div>
              <span class="rounded-full px-3 py-1 text-xs font-bold" :class="item.units_available <= 0 ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'">
                {{ item.units_available }} units
              </span>
            </div>
          </div>
        </div>
      </section>

      <!-- Recent Adjustments -->
      <section v-if="recentAdjustments.length > 0" class="space-y-5 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
        <div>
          <h3 class="text-sm font-black uppercase tracking-[0.16em] text-gray-700">Recent Inventory Adjustments</h3>
          <p class="mt-1 text-xs text-gray-500">Latest stock additions and deductions</p>
        </div>

        <div class="overflow-x-auto rounded-2xl border border-gray-200">
          <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
              <tr>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-600">Blood Type</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-600">Type</th>
                <th class="px-4 py-3 text-center text-xs font-bold uppercase tracking-wide text-gray-600">Units</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-600">Reason</th>
                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-600">Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
              <tr v-for="adj in recentAdjustments" :key="adj.id">
                <td class="px-4 py-3 font-bold text-gray-900">{{ adj.blood_type }}</td>
                <td class="px-4 py-3">
                  <span class="rounded-full px-2.5 py-1 text-xs font-bold" :class="adj.type === 'addition' ? 'bg-emerald-100 text-emerald-700' : 'bg-orange-100 text-orange-700'">
                    {{ adj.type === 'addition' ? '+ Added' : '− Removed' }}
                  </span>
                </td>
                <td class="px-4 py-3 text-center font-bold text-gray-900">{{ adj.units }}</td>
                <td class="px-4 py-3 text-gray-700">{{ adj.reason || '—' }}</td>
                <td class="px-4 py-3 text-gray-600">{{ formatDateTime(adj.date) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </a-spin>
  </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { message } from 'ant-design-vue';
import AdminPageFrame from './AdminPageFrame.vue';
import { useChapterInventory } from '../../composables/useChapterInventory';
import api from '../../lib/api';

const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const componentTypes = ['Whole Blood', 'Red Cells', 'Plasma', 'Platelets'];

const { fetchChapterInventory } = useChapterInventory();

// Data
const inventoryRows = ref([]);
const recentAdjustments = ref([]);
const autoRefreshHandle = ref(null);

const loading = ref({ inventory: false });

const filters = ref({ blood_type: '', component: '', status: '' });

const globalError = ref({ show: false, message: '' });

// PRC Cavite Chapter ID
const CAVITE_CHAPTER_ID = 1;

const stats = computed(() => {
  const totalUnits = inventoryRows.value.reduce((s, r) => s + (Number(r.units_available) || 0), 0);
  const adequateCount = inventoryRows.value.filter(r => Number(r.units_available) > 5).length;
  const lowCount = inventoryRows.value.filter(r => { const u = Number(r.units_available) || 0; return u > 0 && u <= 5; }).length;
  const criticalCount = inventoryRows.value.filter(r => Number(r.units_available) <= 0).length;
  return { totalUnits, adequateCount, lowCount, criticalCount };
});

const lastUpdatedText = computed(() => {
  if (!inventoryRows.value.length) return 'N/A';
  const newest = inventoryRows.value.reduce((latest, current) => {
    const l = new Date(latest.last_synced_at || latest.updated_at || 0);
    const c = new Date(current.last_synced_at || current.updated_at || 0);
    return c > l ? current : latest;
  });
  return formatDateTime(newest.last_synced_at || newest.updated_at);
});

const alertItems = computed(() => inventoryRows.value.filter(r => (Number(r.units_available) || 0) <= 5).sort((a, b) => (Number(a.units_available) || 0) - (Number(b.units_available) || 0)));

const getStatusText = row => { const u = Number(row.units_available) || 0; if (u <= 0) return 'Critical'; if (u <= 5) return 'Low'; return 'Adequate'; };
const getStatusBadgeClass = row => { const u = Number(row.units_available) || 0; if (u <= 0) return 'bg-red-100 text-red-700'; if (u <= 5) return 'bg-amber-100 text-amber-700'; return 'bg-emerald-100 text-emerald-700'; };
const getStatusDotClass = row => { const u = Number(row.units_available) || 0; if (u <= 0) return 'bg-red-600'; if (u <= 5) return 'bg-amber-500'; return 'bg-emerald-600'; };
const getStatusCountTextClass = row => { const u = Number(row.units_available) || 0; if (u <= 0) return 'text-red-800'; if (u <= 5) return 'text-amber-700'; return 'text-emerald-700'; };

const dismissError = () => { globalError.value.show = false; };
const setGlobalError = (error, fallback) => { globalError.value = { show: true, message: error?.response?.data?.message || fallback || 'An error occurred' }; };

const loadInventory = async () => {
  loading.value.inventory = true;
  try {
    const response = await fetchChapterInventory(CAVITE_CHAPTER_ID, { blood_type: filters.value.blood_type, status: filters.value.status });
    let items = response.inventory || [];
    if (filters.value.component) items = items.filter(r => r.component_type === filters.value.component);
    inventoryRows.value = items;
    recentAdjustments.value = recentAdjustments.value.length ? recentAdjustments.value : [];
  } catch (err) {
    setGlobalError(err, 'Unable to load inventory data.');
  } finally {
    loading.value.inventory = false;
  }
};

const applyFilters = async () => { await loadInventory(); };
const refreshInventory = async () => { await loadInventory(); message.success('Inventory refreshed'); };

const formatDateTime = value => { if (!value) return 'N/A'; const d = new Date(value); if (isNaN(d.getTime())) return 'N/A'; return d.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' }); };

onMounted(async () => {
  await loadInventory();
  autoRefreshHandle.value = window.setInterval(async () => { await loadInventory(); }, 30000);
});
onUnmounted(() => { if (autoRefreshHandle.value) { window.clearInterval(autoRefreshHandle.value); autoRefreshHandle.value = null; } });

// Handlers for adjusting inventory
const handleAddUnits = async (row) => {
  const input = window.prompt(`Add units to ${row.blood_type} (${row.component_type || '—'}). Enter number of units to add:`,'1');
  if (!input) return;
  const add = parseInt(input, 10);
  if (Number.isNaN(add) || add <= 0) { message.error('Invalid unit amount'); return; }

  const reason = window.prompt('Reason (optional):', 'Manual stock adjustment');

  const newUnits = (Number(row.units_available) || 0) + add;
  try {
    const resp = await api.put(`/admin/chapters/${CAVITE_CHAPTER_ID}/inventory/${row.id}`, { units_available: newUnits });
    const updated = resp.data?.data || null;
    if (updated) {
      inventoryRows.value = inventoryRows.value.map(r => r.id === updated.id ? updated : r);
    } else {
      // fallback: update local row
      row.units_available = newUnits;
    }

    // prepend recent adjustment
    recentAdjustments.value.unshift({ id: Date.now(), blood_type: row.blood_type, component_type: row.component_type, type: 'addition', units: add, reason: reason || '', date: new Date() });
    message.success('Inventory updated');
  } catch (err) {
    message.error('Failed to update inventory');
  }
};

const handleRemoveUnits = async (row) => {
  const input = window.prompt(`Remove units from ${row.blood_type} (${row.component_type || '—'}). Enter number of units to remove:`,'1');
  if (!input) return;
  const remove = parseInt(input, 10);
  if (Number.isNaN(remove) || remove <= 0) { message.error('Invalid unit amount'); return; }

  const reason = window.prompt('Reason (optional):', 'Manual stock adjustment');

  const current = Number(row.units_available) || 0;
  const newUnits = Math.max(0, current - remove);
  try {
    const resp = await api.put(`/admin/chapters/${CAVITE_CHAPTER_ID}/inventory/${row.id}`, { units_available: newUnits });
    const updated = resp.data?.data || null;
    if (updated) {
      inventoryRows.value = inventoryRows.value.map(r => r.id === updated.id ? updated : r);
    } else {
      row.units_available = newUnits;
    }

    recentAdjustments.value.unshift({ id: Date.now(), blood_type: row.blood_type, component_type: row.component_type, type: 'removal', units: remove, reason: reason || '', date: new Date() });
    message.success('Inventory updated');
  } catch (err) {
    message.error('Failed to update inventory');
  }
};

</script>
