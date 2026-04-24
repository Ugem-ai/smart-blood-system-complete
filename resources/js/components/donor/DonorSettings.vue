<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Donor settings</div>
          <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950">Tune how this panel supports your emergency response workflow</h2>
          <p class="mt-2 text-sm text-gray-600">These preferences are now stored on your donor account and follow you across sessions.</p>
        </div>
        <button type="button" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="loading || saving" @click="saveSettings">{{ saving ? 'Saving...' : 'Save preferences' }}</button>
      </div>

      <div v-if="message" class="mt-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ message }}</div>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Alert delivery</div>
        <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Notification preferences</h3>
        <div v-if="loading" class="mt-5 space-y-3 animate-pulse">
          <div v-for="index in 4" :key="index" class="h-20 rounded-[1.5rem] bg-gray-100"></div>
        </div>
        <div v-else class="mt-5 space-y-3">
          <label v-for="setting in alertSettings" :key="setting.key" class="flex items-center justify-between rounded-[1.5rem] border border-gray-200 bg-gray-50 px-4 py-4">
            <div>
              <div class="text-sm font-semibold text-gray-900">{{ setting.label }}</div>
              <div class="mt-1 text-sm text-gray-600">{{ setting.detail }}</div>
            </div>
            <input v-model="preferences[setting.key]" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500">
          </label>
        </div>
      </div>

      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Panel behavior</div>
        <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Workflow preferences</h3>
        <div v-if="loading" class="mt-5 space-y-4 animate-pulse">
          <div class="h-24 rounded-[1.5rem] bg-gray-100"></div>
          <div class="h-24 rounded-[1.5rem] bg-gray-100"></div>
          <div class="h-20 rounded-[1.5rem] bg-gray-100"></div>
        </div>
        <div v-else class="mt-5 space-y-4">
          <label>
            <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Default request view</div>
            <select v-model="preferences.defaultRequestFilter" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100">
              <option value="all">All requests</option>
              <option value="critical">Critical only</option>
              <option value="unresponded">Unresponded queue</option>
            </select>
          </label>
          <label>
            <div class="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Maximum travel radius</div>
            <select v-model="preferences.maxRadius" class="w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100">
              <option value="10">10 km</option>
              <option value="25">25 km</option>
              <option value="50">50 km</option>
              <option value="all">No limit</option>
            </select>
          </label>
          <label class="flex items-center justify-between rounded-[1.5rem] border border-gray-200 bg-gray-50 px-4 py-4">
            <div>
              <div class="text-sm font-semibold text-gray-900">Show mission summary on dashboard</div>
              <div class="mt-1 text-sm text-gray-600">Keep the command-style overview card visible above the dashboard feed.</div>
            </div>
            <input v-model="preferences.showMissionSummary" type="checkbox" class="h-5 w-5 rounded border-gray-300 text-red-600 focus:ring-red-500">
          </label>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import { fetchDonorSettings, updateDonorSettings } from '../../lib/donorPanel';
import { showDonorToast } from '../../lib/donorToast';

const message = ref('');
const loading = ref(true);
const saving = ref(false);
const preferences = reactive({
  smsAlerts: true,
  emailAlerts: true,
  urgentOnly: false,
  availabilityReminders: true,
  defaultRequestFilter: 'all',
  maxRadius: '25',
  showMissionSummary: true,
});

const alertSettings = [
  { key: 'smsAlerts', label: 'SMS alerts', detail: 'Receive emergency request prompts through text messaging when enabled by the system.' },
  { key: 'emailAlerts', label: 'Email alerts', detail: 'Keep a secondary alert channel for request summaries and reminders.' },
  { key: 'urgentOnly', label: 'Urgent alerts only', detail: 'Suppress lower-priority notifications and focus on emergency requests.' },
  { key: 'availabilityReminders', label: 'Eligibility reminders', detail: 'Surface reminders when you become eligible to donate again.' },
];

async function loadSettings() {
  try {
    const payload = await fetchDonorSettings();
    Object.assign(preferences, payload);
  } catch {
    showDonorToast('Unable to load donor settings.', 'error');
  } finally {
    loading.value = false;
  }
}

async function saveSettings() {
  saving.value = true;

  try {
    const payload = await updateDonorSettings({ ...preferences });
    Object.assign(preferences, payload);
    message.value = 'Donor panel preferences saved to your account.';
    showDonorToast(message.value);
    window.setTimeout(() => {
      message.value = '';
    }, 2500);
  } catch {
    showDonorToast('Unable to save donor settings.', 'error');
  } finally {
    saving.value = false;
  }
}

onMounted(loadSettings);
</script>