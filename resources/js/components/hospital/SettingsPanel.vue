<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Settings</div>
      <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950 sm:text-4xl">Hospital-visible system configuration</h2>
      <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-600">This view exposes the active urgency thresholds, notification rules, PAST-Match weights, and auto-escalation policies. These values are centrally managed by administrators.</p>
    </section>

    <section v-if="snapshot" class="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-sm font-semibold text-gray-900">Emergency and notification policies</div>
        <div class="mt-5 space-y-3">
          <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Urgency threshold</div>
            <div class="mt-2 text-2xl font-black tracking-tight text-gray-950">{{ snapshot.system_settings?.urgency_threshold }}</div>
          </article>
          <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Notification rule</div>
            <div class="mt-2 text-lg font-bold text-gray-950">{{ snapshot.system_settings?.notification_rule }}</div>
          </article>
          <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Auto-escalation timer</div>
            <div class="mt-2 text-lg font-bold text-gray-950">{{ snapshot.system_settings?.control_center?.emergency?.escalation_timer_minutes }} minutes</div>
          </article>
        </div>
      </div>

      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-sm font-semibold text-gray-900">PAST-Match weights</div>
        <div class="mt-5 space-y-3">
          <article v-for="item in weights" :key="item.label" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="flex items-center justify-between gap-3">
              <div class="text-sm text-gray-600">{{ item.label }}</div>
              <div class="text-sm font-semibold text-gray-900">{{ Math.round(item.value * 100) }}%</div>
            </div>
          </article>
        </div>
        <div class="mt-6 rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600">
          Last updated {{ snapshot.system_settings?.updated_at ? formatDateTime(snapshot.system_settings.updated_at) : 'not recorded' }} by {{ snapshot.system_settings?.updated_by_name || 'system default' }}.
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { fetchHospitalSettingsSnapshot, formatDateTime } from '../../lib/hospitalPanel';

const snapshot = ref(null);
const weights = computed(() => Object.entries(snapshot.value?.system_settings?.past_match_weights || {}).map(([label, value]) => ({ label, value })));

async function loadSnapshot() {
  snapshot.value = await fetchHospitalSettingsSnapshot();
}

onMounted(loadSnapshot);
</script>
