<template>
  <header>
    <div class="flex flex-col gap-4 border border-white/80 bg-white/85 px-4 py-4 shadow-sm backdrop-blur sm:px-6 xl:flex-row xl:items-center xl:justify-between">
      <div class="flex min-w-0 items-start gap-4 sm:items-center">
        <button
          type="button"
          class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 transition hover:border-red-200 hover:text-red-600"
          title="Toggle sidebar"
          @click="$emit('toggle-sidebar')"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        <div class="min-w-0">
          <p class="text-[11px] font-black uppercase tracking-[0.22em] text-red-500">Admin Workspace</p>
          <h1 class="mt-1 text-lg font-black tracking-tight text-slate-950 sm:text-xl">{{ title }}</h1>
          <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-500 sm:text-sm">{{ subtitle }}</p>
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
    default: 'Smart Cloud-Based Emergency Blood Donation Monitoring and Matching System',
  },
  adminName: {
    type: String,
    default: 'Admin',
  },
});

defineEmits(['toggle-sidebar']);
</script>
