<template>
  <header class="border-b border-red-100/80 bg-white/90 px-4 py-4 shadow-sm backdrop-blur sm:px-6">
    <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
      <div class="flex min-w-0 items-start gap-4 sm:items-center">
      <button
        @click="$emit('toggleSidebar')"
        class="mt-0.5 flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-lg text-gray-500 transition-colors hover:bg-gray-100 sm:mt-0"
        title="Toggle sidebar"
      >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>
      <div class="min-w-0">
        <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-red-500">Donor panel</p>
        <h1 class="text-xl font-black tracking-tight text-gray-950 sm:text-2xl">{{ title }}</h1>
        <p v-if="subtitle" class="mt-1 max-w-2xl text-sm leading-6 text-gray-600">{{ subtitle }}</p>
      </div>
    </div>

      <div class="rounded-[1.25rem] border border-red-100 bg-red-50 px-4 py-3 sm:hidden">
        <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-red-500">Verified donor</div>
        <div class="mt-1 text-sm font-semibold text-gray-900">{{ donorName }}</div>
        <div class="text-xs text-gray-500">Blood type {{ bloodType }}</div>
      </div>

      <div class="hidden gap-3 sm:grid sm:grid-cols-2 xl:w-[34rem] xl:grid-cols-3">
        <div class="rounded-[1.5rem] border border-red-100 bg-[linear-gradient(135deg,_rgba(254,242,242,1),_rgba(255,255,255,1))] px-4 py-3">
          <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-red-500">Response posture</div>
          <div class="mt-1 text-sm font-semibold text-gray-900">Mission ready</div>
          <div class="text-xs text-gray-500">Emergency requests surface instantly for your blood type.</div>
        </div>
        <div class="rounded-[1.5rem] border border-gray-200 bg-white px-4 py-3">
          <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500">Verified donor</div>
          <div class="mt-1 text-sm font-semibold text-gray-900">{{ donorName }}</div>
          <div class="text-xs text-gray-500">Blood type {{ bloodType }}</div>
        </div>
        <div class="rounded-[1.5rem] border border-gray-200 bg-white px-4 py-3">
          <div class="text-[11px] font-semibold uppercase tracking-[0.18em] text-gray-500">Today</div>
          <div class="mt-1 text-sm font-semibold text-gray-900">{{ todayLabel }}</div>
          <div class="text-xs text-gray-500">Fast-action emergency dashboard</div>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { computed } from 'vue';

defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  donorName: {
    type: String,
    default: 'Donor',
  },
  bloodType: {
    type: String,
    default: 'O+',
  },
});

defineEmits(['toggleSidebar']);

const todayLabel = computed(() => new Date().toLocaleDateString('en-PH', {
  month: 'short',
  day: 'numeric',
  year: 'numeric',
}));
</script>
