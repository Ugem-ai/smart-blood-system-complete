<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Audit logs</div>
      <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950 sm:text-4xl">Hospital-scoped compliance and traceability view</h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">Review hospital-related request events, matching activity, and user actions from the live audit stream.</p>
    </section>

    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <input v-model="search" type="text" placeholder="Filter by action or detail" class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100" />
        <select v-model="category" class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100">
          <option value="">All categories</option>
          <option value="blood_requests">Blood requests</option>
          <option value="matching">Matching</option>
          <option value="notifications">Notifications</option>
          <option value="authentication">Authentication</option>
        </select>
        <select v-model="severity" class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100">
          <option value="">All severities</option>
          <option value="critical">Critical</option>
          <option value="high">High</option>
          <option value="medium">Medium</option>
          <option value="low">Low</option>
          <option value="info">Info</option>
        </select>
        <button type="button" class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="loadLogs">Refresh log stream</button>
      </div>

      <div class="mt-6 overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead>
            <tr class="text-left text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">
              <th class="pb-3 pr-4">User</th>
              <th class="pb-3 pr-4">Action</th>
              <th class="pb-3 pr-4">Affected Request</th>
              <th class="pb-3 pr-4">Timestamp</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="log in filteredLogs" :key="log.id">
              <td class="py-4 pr-4 text-gray-700">{{ log.actor }}</td>
              <td class="py-4 pr-4">
                <div class="font-semibold text-gray-900">{{ log.title }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ log.category }} • {{ log.severity }}</div>
              </td>
              <td class="py-4 pr-4 text-gray-700">{{ log.request_id || log.detail }}</td>
              <td class="py-4 pr-4 text-gray-700">{{ log.timestamp_label }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { fetchHospitalActivityLog, normalizeActivityLog } from '../../lib/hospitalPanel';

const logs = ref([]);
const search = ref('');
const category = ref('');
const severity = ref('');

const filteredLogs = computed(() => logs.value.filter((log) => {
  if (category.value && log.category !== category.value) return false;
  if (severity.value && log.severity !== severity.value) return false;
  if (search.value) {
    const haystack = `${log.title} ${log.detail} ${log.action}`.toLowerCase();
    return haystack.includes(search.value.toLowerCase());
  }
  return true;
}));

async function loadLogs() {
  const rows = await fetchHospitalActivityLog({ per_page: 50 });
  logs.value = rows.map(normalizeActivityLog);
}

onMounted(loadLogs);
</script>
