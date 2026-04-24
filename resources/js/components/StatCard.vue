<template>
  <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-sm font-medium text-slate-500">{{ title }}</p>
        <p class="mt-2 text-2xl font-bold text-slate-900">{{ value }}</p>
      </div>
      <div class="text-xl">{{ icon }}</div>
    </div>
    <p class="mt-3 text-xs font-semibold" :class="trendClass">{{ trend }}</p>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  value: {
    type: String,
    required: true,
  },
  trend: {
    type: String,
    default: '',
  },
  icon: {
    type: String,
    default: '•',
  },
  color: {
    type: String,
    default: 'blue',
  },
});

const trendClass = computed(() => {
  const classes = {
    blue: 'text-blue-600',
    green: 'text-emerald-600',
    red: 'text-red-600',
    purple: 'text-violet-600',
  };

  return classes[props.color] || 'text-slate-600';
});
</script>
