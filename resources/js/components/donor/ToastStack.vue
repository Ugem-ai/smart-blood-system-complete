<template>
  <div class="pointer-events-none fixed bottom-6 right-6 z-[90] flex w-full max-w-sm flex-col gap-3 px-4 sm:px-0">
    <transition-group name="toast">
      <article v-for="toast in toasts" :key="toast.id" class="pointer-events-auto rounded-2xl px-4 py-3 text-sm font-semibold text-white shadow-xl backdrop-blur-sm" :class="toneClass(toast.type)">
        <div class="flex items-start gap-3">
          <span class="mt-0.5">{{ iconFor(toast.type) }}</span>
          <div class="flex-1">{{ toast.message }}</div>
          <button type="button" class="text-white/80 transition hover:text-white" @click="dismiss(toast.id)">×</button>
        </div>
      </article>
    </transition-group>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { subscribeDonorToast } from '../../lib/donorToast';

const toasts = ref([]);
const timers = new Map();
let unsubscribe = null;

function iconFor(type) {
  if (type === 'error') return '⚠️';
  if (type === 'info') return 'ℹ️';
  return '✅';
}

function toneClass(type) {
  if (type === 'error') return 'bg-red-600';
  if (type === 'info') return 'bg-gray-900';
  return 'bg-emerald-600';
}

function dismiss(id) {
  window.clearTimeout(timers.get(id));
  timers.delete(id);
  toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function enqueue(toast) {
  toasts.value = [...toasts.value.slice(-3), toast];
  const timer = window.setTimeout(() => dismiss(toast.id), 3600);
  timers.set(toast.id, timer);
}

onMounted(() => {
  unsubscribe = subscribeDonorToast(enqueue);
});

onBeforeUnmount(() => {
  unsubscribe?.();
  timers.forEach((timer) => window.clearTimeout(timer));
  timers.clear();
});
</script>

<style scoped>
.toast-enter-active,
.toast-leave-active {
  transition: all 0.2s ease;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(10px);
}
</style>