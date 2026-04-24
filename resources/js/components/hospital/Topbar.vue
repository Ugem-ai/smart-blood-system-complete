<template>
  <header class="border-b border-gray-100 bg-white px-4 py-4 shadow-sm sm:px-6">
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
          <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-red-500">Hospital command center</p>
          <h1 class="text-lg font-semibold text-gray-900 sm:text-xl">{{ title }}</h1>
          <p v-if="subtitle" class="mt-1 max-w-2xl text-xs leading-5 text-gray-500 sm:text-sm">{{ subtitle }}</p>
        </div>
      </div>

      <div class="rounded-[1.25rem] border border-red-100 bg-red-50 px-4 py-3 sm:hidden">
        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-red-500">Hospital</div>
        <div class="mt-1 text-sm font-semibold text-gray-900">{{ hospitalName }}</div>
      </div>

      <div class="hidden gap-3 sm:grid sm:grid-cols-2 xl:w-[28rem]">
        <div class="rounded-[1.25rem] border border-red-100 bg-red-50 px-4 py-3">
          <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-red-500">Coordination posture</div>
          <div class="mt-1 text-sm font-semibold text-gray-900">Emergency-ready</div>
          <div class="text-xs text-gray-500">Critical requests, donors, and escalation controls stay visible from one workspace.</div>
        </div>
        <div class="rounded-[1.25rem] border border-gray-200 bg-white px-4 py-3">
          <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-gray-500">Hospital</div>
          <div class="mt-1 text-sm font-semibold text-gray-900">{{ hospitalName }}</div>
          <div class="text-xs text-gray-500">Smart emergency blood coordination system</div>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  hospitalName: {
    type: String,
    default: 'Hospital',
  },
});

defineEmits(['toggleSidebar']);
</script>
