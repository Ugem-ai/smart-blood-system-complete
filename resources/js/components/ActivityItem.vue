<template>
  <div class="flex items-start gap-4 rounded-lg border border-slate-100 bg-slate-50 p-4">
    <div class="mt-0.5 h-2.5 w-2.5 rounded-full" :class="dotClass"></div>
    <div class="flex-1">
      <div class="flex items-center justify-between gap-4">
        <p class="font-semibold text-slate-900">{{ title }}</p>
        <p class="text-xs text-slate-500">{{ time }}</p>
      </div>
      <p class="mt-1 text-sm text-slate-600">{{ description }}</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    required: true,
  },
  time: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'info',
  },
});

const dotClass = computed(() => {
  const classes = {
    request: 'bg-red-500',
    donation: 'bg-emerald-500',
    alert: 'bg-amber-500',
    info: 'bg-blue-500',
  };

  return classes[props.type] || classes.info;
});
</script>
