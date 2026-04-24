<template>
  <aside :class="[
    effectiveCollapsed ? 'lg:w-16' : 'lg:w-80',
    mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
    'fixed inset-y-0 left-0 z-50 w-[min(20rem,calc(100vw-1rem))] flex flex-col border-r border-red-950/80 bg-[linear-gradient(180deg,_#991b1b_0%,_#b91c1c_52%,_#7f1d1d_100%)] shadow-[0_24px_80px_rgba(127,29,29,0.24)] transition-all duration-200 overflow-hidden lg:static lg:z-auto lg:flex-shrink-0'
  ]">
    <div class="border-b border-white/10" :class="effectiveCollapsed ? 'px-2 py-4' : 'px-5 py-5'">
      <div class="flex" :class="effectiveCollapsed ? 'flex-col items-center gap-3' : 'items-center gap-3'">
        <div class="flex items-center gap-2.5 min-w-0" :class="effectiveCollapsed ? 'justify-center' : 'flex-1'">
          <span class="inline-flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-red-100">
            <svg viewBox="0 0 64 64" aria-hidden="true" class="h-6 w-6">
              <circle cx="32" cy="24" r="18" fill="#DC2626" />
              <rect x="28" y="14" width="8" height="20" rx="2" fill="#FFFFFF" />
              <rect x="22" y="20" width="20" height="8" rx="2" fill="#FFFFFF" />
              <path d="M32 37C27.58 37 24 40.58 24 45C24 50.33 28.35 54 32 58C35.65 54 40 50.33 40 45C40 40.58 36.42 37 32 37Z" fill="#B91C1C" />
            </svg>
          </span>
          <div v-show="!effectiveCollapsed" class="min-w-0">
            <div class="text-base font-bold text-white">SmartBlood</div>
            <div class="text-[11px] uppercase tracking-[0.22em] text-red-100/80">Admin Control Center</div>
          </div>
        </div>
      </div>

    </div>

    <nav class="flex-1 overflow-y-auto px-3 py-5">
      <template v-for="group in groupedModules" :key="group.title">
        <div v-show="!effectiveCollapsed" class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-red-100/70">{{ group.title }}</div>
        <button
          v-for="item in group.modules"
          :key="item.id"
          type="button"
          class="mb-1 flex w-full items-center rounded-2xl py-2.5 text-left text-sm font-medium transition-colors"
          :style="effectiveCollapsed ? 'justify-content:center; padding-left:0; padding-right:0;' : ''"
          :class="[
            effectiveCollapsed ? 'gap-0' : 'gap-3 px-3',
            activeModule === item.id ? 'bg-white text-red-700 shadow-sm' : 'text-red-50/90 hover:bg-white/10 hover:text-white'
          ]"
          @click="$emit('select', item.id)"
        >
          <span :class="effectiveCollapsed ? 'w-full text-center' : 'w-6 text-center'">{{ item.icon }}</span>
          <span v-show="!effectiveCollapsed">{{ item.label }}</span>
        </button>
      </template>
    </nav>

    <div class="border-t border-white/10 px-3 py-4">
      <div class="mt-1 flex rounded-[1.5rem] border border-white/10 bg-white/10 px-3 py-3 backdrop-blur-sm" :class="effectiveCollapsed ? 'flex-col items-center gap-2 px-0' : 'items-center justify-between'">
        <button
          type="button"
          class="flex min-w-0 items-center gap-3"
          :class="effectiveCollapsed ? 'justify-center' : ''"
          @click="$emit('open-account')"
        >
          <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-white text-sm font-black text-red-700">A</div>
          <div v-show="!effectiveCollapsed" class="min-w-0 text-left">
            <div class="truncate text-sm font-semibold text-white">{{ adminName }}</div>
            <div class="truncate text-xs text-red-100/80">Administrator</div>
          </div>
        </button>

        <button
          type="button"
          class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-red-100/80 transition-colors hover:bg-white/10 hover:text-white"
          :class="effectiveCollapsed ? 'ml-0' : 'ml-2'"
          title="Logout"
          @click="$emit('logout')"
        >
          <span v-show="!effectiveCollapsed" class="text-sm font-semibold">Logout</span>
          <svg viewBox="0 0 20 20" class="h-4 w-4 fill-current">
            <path fill-rule="evenodd" d="M3 3a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h7v-2H4V5h6V3H3zm9.293 4.293a1 1 0 0 1 1.414 0l3 3a1 1 0 0 1 0 1.414l-3 3a1 1 0 0 1-1.414-1.414L13.586 11H7a1 1 0 1 1 0-2h6.586l-1.293-1.293a1 1 0 0 1 0-1.414z" clip-rule="evenodd"/>
          </svg>
        </button>
      </div>
    </div>
  </aside>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  modules: {
    type: Array,
    required: true,
  },
  activeModule: {
    type: String,
    required: true,
  },
  collapsed: {
    type: Boolean,
    required: true,
  },
  mobileOpen: {
    type: Boolean,
    default: false,
  },
  adminName: {
    type: String,
    default: 'Admin',
  },
});

defineEmits(['select', 'open-account', 'logout', 'toggle-sidebar']);

const effectiveCollapsed = computed(() => props.collapsed && !props.mobileOpen);

const groupedModules = computed(() => props.modules.reduce((groups, module) => {
  const title = module.section || 'Workspace';
  const existingGroup = groups.find((group) => group.title === title);

  if (existingGroup) {
    existingGroup.modules.push(module);
    return groups;
  }

  groups.push({ title, modules: [module] });
  return groups;
}, []));
</script>
