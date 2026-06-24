<template>
  <span :class="['inline-flex items-center gap-2 rounded-full px-3 py-1 font-semibold whitespace-nowrap', badgeClass]">
    <span :class="['w-3 h-3 rounded-full shrink-0', dotClass]" aria-hidden="true"></span>
    <span class="text-sm leading-none">{{ labelText }}</span>
    <span v-if="showCount" :class="['ml-1 rounded-full bg-white/80 px-2 py-0.5 text-xs font-black', countTextClass]">{{ countDisplay }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: { type: String, default: '' },
  label: { type: String, default: '' },
  count: { type: [Number, String], default: null },
  showCount: { type: Boolean, default: true },
});

const labelText = computed(() => (props.label || props.status || '').toString());
const countDisplay = computed(() => (props.count === null || props.count === undefined ? 0 : props.count));

const normalized = (s) => (s || '').toString().toLowerCase();

const badgeClass = computed(() => {
  const s = normalized(props.status || props.label);
  if (s === 'critical') return 'bg-red-100 text-red-700';
  if (s === 'low' || s === 'idle') return 'bg-amber-100 text-amber-700';
  if (s === 'adequate' || s === 'active') return 'bg-emerald-100 text-emerald-700';
  return 'bg-gray-100 text-gray-700';
});

const dotClass = computed(() => {
  const s = normalized(props.status || props.label);
  if (s === 'critical') return 'bg-red-600';
  if (s === 'low' || s === 'idle') return 'bg-amber-500';
  if (s === 'adequate' || s === 'active') return 'bg-emerald-600';
  return 'bg-gray-400';
});

const countTextClass = computed(() => {
  const s = normalized(props.status || props.label);
  if (s === 'critical') return 'text-red-800';
  if (s === 'low' || s === 'idle') return 'text-amber-700';
  if (s === 'adequate' || s === 'active') return 'text-emerald-700';
  return 'text-gray-700';
});
</script>
