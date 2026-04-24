<template>
  <section class="admin-page">
    <div class="admin-hero">
      <div class="admin-hero-row">
        <div class="min-w-0 flex-1">
          <p v-if="kicker" class="admin-kicker">{{ kicker }}</p>
          <h2 class="admin-title">{{ title }}</h2>
          <p v-if="description" class="admin-description">{{ description }}</p>

          <div v-if="$slots.metrics" class="mt-6">
            <slot name="metrics" />
          </div>
        </div>

        <div v-if="$slots.actions || badge" class="admin-toolbar">
          <div v-if="badge" class="rounded-[1.25rem] border border-gray-200 bg-white px-4 py-3 text-xs font-semibold text-gray-600 shadow-sm">
            {{ badge }}
          </div>
          <slot name="actions" />
        </div>
      </div>
    </div>

    <slot />
  </section>
</template>

<script setup>
defineProps({
  kicker: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    required: true,
  },
  description: {
    type: String,
    default: '',
  },
  badge: {
    type: String,
    default: '',
  },
});
</script>