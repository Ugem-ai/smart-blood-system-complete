<template>
  <Teleport to="body">
    <Transition name="modal-fade">
      <div
        v-if="visible"
        class="fixed inset-0 z-50 flex items-center justify-center p-4"
        @click.self="handleCancel"
      >
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" />

        <!-- Modal -->
        <div class="relative w-full max-w-md rounded-3xl border border-gray-200 bg-white shadow-2xl">
          <!-- Header -->
          <div class="flex items-center gap-3 border-b border-gray-100 px-6 py-5">
            <div
              class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl text-lg font-bold"
              :class="mode === 'add'
                ? 'bg-emerald-100 text-emerald-700'
                : 'bg-orange-100 text-orange-700'"
            >
              {{ mode === 'add' ? '+' : '−' }}
            </div>
            <div class="min-w-0">
              <h2 class="text-sm font-black uppercase tracking-wide text-gray-900">
                {{ mode === 'add' ? 'Add Units' : 'Remove Units' }}
              </h2>
              <p class="mt-0.5 truncate text-xs text-gray-500">
                {{ row?.blood_type }} · {{ row?.component_type || 'Unknown Component' }}
              </p>
            </div>
            <button
              class="ml-auto flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"
              @click="handleCancel"
            >
              ✕
            </button>
          </div>

          <!-- Current stock pill -->
          <div class="px-6 pt-5">
            <div class="flex items-center gap-2 rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3">
              <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current Stock</span>
              <span class="ml-auto text-sm font-black text-gray-900">
                {{ row?.units_available ?? '—' }} unit{{ row?.units_available === 1 ? '' : 's' }}
              </span>
              <span
                class="rounded-full px-2.5 py-0.5 text-xs font-bold"
                :class="currentStatusClass"
              >
                {{ currentStatus }}
              </span>
            </div>
          </div>

          <!-- Body -->
          <div class="space-y-4 px-6 py-5">
            <!-- Units input -->
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-600">
                Units to {{ mode === 'add' ? 'Add' : 'Remove' }}
              </label>
              <div class="flex items-center gap-2">
                <button
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-base font-bold text-gray-600 transition hover:border-gray-300 hover:bg-gray-50 disabled:opacity-40"
                  :disabled="units <= 1"
                  @click="units = Math.max(1, units - 1)"
                >
                  −
                </button>
                <input
                  v-model.number="units"
                  type="number"
                  min="1"
                  :max="mode === 'remove' ? (row?.units_available || 999) : 9999"
                  class="h-10 w-full rounded-xl border border-gray-200 bg-white px-3 text-center text-sm font-bold text-gray-900 shadow-sm focus:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-100"
                  @input="clampUnits"
                />
                <button
                  class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-200 bg-white text-base font-bold text-gray-600 transition hover:border-gray-300 hover:bg-gray-50"
                  @click="units = units + 1"
                >
                  +
                </button>
              </div>
              <!-- Remove warning -->
              <p
                v-if="mode === 'remove' && units > (row?.units_available || 0)"
                class="mt-2 text-xs font-semibold text-red-600"
              >
                ⚠ Exceeds current stock. Will be clamped to {{ row?.units_available }}.
              </p>
            </div>

            <!-- Preview -->
            <div
              v-if="previewUnits !== null"
              class="flex items-center justify-between rounded-2xl border px-4 py-3 text-sm"
              :class="mode === 'add'
                ? 'border-emerald-200 bg-emerald-50'
                : 'border-orange-200 bg-orange-50'"
            >
              <span class="font-semibold" :class="mode === 'add' ? 'text-emerald-700' : 'text-orange-700'">
                New total after {{ mode === 'add' ? 'adding' : 'removing' }}
              </span>
              <span class="font-black" :class="mode === 'add' ? 'text-emerald-900' : 'text-orange-900'">
                {{ previewUnits }} unit{{ previewUnits === 1 ? '' : 's' }}
              </span>
            </div>

            <!-- Error Alert -->
            <div
              v-if="error"
              class="flex items-start gap-3 rounded-2xl border border-red-200 bg-red-50 px-4 py-3"
            >
              <span class="mt-0.5 text-lg text-red-600">⚠</span>
              <div class="flex-1">
                <p class="text-sm font-semibold text-red-700">{{ error }}</p>
                <p class="mt-1 text-xs text-red-600">Please review and try again.</p>
              </div>
            </div>

            <!-- Reason -->
            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-gray-600">
                Reason <span class="font-normal normal-case text-gray-400">(optional)</span>
              </label>
              <textarea
                v-model="reason"
                rows="2"
                placeholder="e.g. Blood drive donation, Expired units, Hospital transfer…"
                class="w-full resize-none rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-100"
              />
            </div>
          </div>

          <!-- Footer -->
          <div class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
            <button
              class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50"
              @click="handleCancel"
            >
              Cancel
            </button>
            <button
              class="rounded-xl px-5 py-2.5 text-sm font-bold text-white transition disabled:opacity-50"
              :class="mode === 'add'
                ? 'bg-emerald-600 hover:bg-emerald-700'
                : 'bg-orange-500 hover:bg-orange-600'"
              :disabled="!isValid || loading"
              @click="handleConfirm"
            >
              <span v-if="loading" class="flex items-center gap-2">
                <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
                </svg>
                Saving…
              </span>
              <span v-else>
                {{ mode === 'add' ? 'Add Units' : 'Remove Units' }}
              </span>
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  visible: { type: Boolean, default: false },
  mode: { type: String, default: 'add' }, // 'add' | 'remove'
  row: { type: Object, default: null },
  loading: { type: Boolean, default: false },
  error: { type: String, default: null }, // Error message if operation failed
});

const emit = defineEmits(['confirm', 'cancel']);

const units = ref(1);
const reason = ref('');

// Reset when modal opens
watch(() => props.visible, (val) => {
  if (val) {
    units.value = 1;
    reason.value = '';
  }
});

const clampUnits = () => {
  if (units.value < 1 || isNaN(units.value)) units.value = 1;
};

const currentStatus = computed(() => {
  const u = Number(props.row?.units_available) || 0;
  if (u <= 0) return 'Critical';
  if (u <= 5) return 'Low';
  return 'Adequate';
});

const currentStatusClass = computed(() => {
  const u = Number(props.row?.units_available) || 0;
  if (u <= 0) return 'bg-red-100 text-red-700';
  if (u <= 5) return 'bg-amber-100 text-amber-700';
  return 'bg-emerald-100 text-emerald-700';
});

const previewUnits = computed(() => {
  if (!props.row || isNaN(units.value) || units.value < 1) return null;
  const current = Number(props.row.units_available) || 0;
  if (props.mode === 'add') return current + units.value;
  return Math.max(0, current - units.value);
});

const isValid = computed(() => units.value >= 1 && !isNaN(units.value));

const handleConfirm = () => {
  if (!isValid.value) return;
  const effectiveUnits = props.mode === 'remove'
    ? Math.min(units.value, Number(props.row?.units_available) || 0)
    : units.value;
  emit('confirm', { units: effectiveUnits, reason: reason.value.trim() });
};

const handleCancel = () => {
  emit('cancel');
};
</script>

<style scoped>
.modal-fade-enter-active,
.modal-fade-leave-active {
  transition: opacity 0.18s ease;
}
.modal-fade-enter-from,
.modal-fade-leave-to {
  opacity: 0;
}
.modal-fade-enter-active .relative,
.modal-fade-leave-active .relative {
  transition: transform 0.18s ease, opacity 0.18s ease;
}
.modal-fade-enter-from .relative,
.modal-fade-leave-to .relative {
  transform: translateY(12px) scale(0.97);
  opacity: 0;
}
</style>