<template>
  <aside :class="[
    effectiveCollapsed ? 'lg:w-16' : 'lg:w-80',
    mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
    'fixed inset-y-0 left-0 z-50 w-[min(20rem,calc(100vw-1rem))] flex flex-col border-r border-red-950/80 bg-[linear-gradient(180deg,_#991b1b_0%,_#b91c1c_52%,_#7f1d1d_100%)] transition-all duration-300 overflow-hidden shadow-[0_24px_80px_rgba(127,29,29,0.24)] lg:static lg:z-auto lg:flex-shrink-0'
  ]">
    <!-- Brand -->
    <div class="border-b border-white/10" :class="effectiveCollapsed ? 'px-2 py-4' : 'px-5 py-5'">
      <div class="flex" :class="effectiveCollapsed ? 'flex-col items-center gap-3' : 'items-center gap-3'">
        <a href="#" class="flex min-w-0 items-center justify-center gap-3" :class="effectiveCollapsed ? 'justify-center' : 'flex-1'">
        <img src="/images/logo.png" alt="SmartBlood Logo" class="h-10 w-10 self-center flex-shrink-0 rounded-xl bg-white shadow-sm ring-1 ring-red-100 object-center" />
        <div v-show="!effectiveCollapsed" class="min-w-0">
          <div class="text-lg font-black tracking-tight text-white">SmartBlood</div>
        </div>
      </a>
      </div>

    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 overflow-y-auto px-3 py-5">
      <div v-show="!collapsed" class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-[0.18em] text-red-100/70">Operations</div>
      <button
        v-for="module in primaryModules"
        :key="module.id"
        @click="$emit('selectModule', module.id)"
        :class="[
          'mb-1 w-full flex items-center rounded-2xl py-2.5 text-sm font-medium transition-colors',
          effectiveCollapsed ? 'gap-0' : 'gap-3 px-3',
          activeModule === module.id
            ? 'bg-white text-red-700 shadow-sm'
            : 'text-red-50/90 hover:bg-white/10 hover:text-white'
        ]"
        :style="effectiveCollapsed ? 'justify-content:center; padding-left:0; padding-right:0;' : ''"
      >
        <span :class="effectiveCollapsed ? 'w-full text-center text-base leading-none' : 'w-6 text-center text-base leading-none'">{{ module.icon }}</span>
        <span v-show="!effectiveCollapsed">{{ module.label }}</span>
      </button>

      <div v-show="!collapsed" class="px-3 pb-2 pt-4 text-[11px] font-semibold uppercase tracking-[0.18em] text-red-100/70">Account</div>
      <button
        v-for="module in secondaryModules"
        :key="module.id"
        @click="$emit('selectModule', module.id)"
        :class="[
          'mb-1 w-full flex items-center rounded-2xl py-2.5 text-sm font-medium transition-colors',
          effectiveCollapsed ? 'gap-0' : 'gap-3 px-3',
          activeModule === module.id
            ? 'bg-white text-red-700 shadow-sm'
            : 'text-red-50/90 hover:bg-white/10 hover:text-white'
        ]"
        :style="effectiveCollapsed ? 'justify-content:center; padding-left:0; padding-right:0;' : ''"
      >
        <span :class="effectiveCollapsed ? 'w-full text-center text-base leading-none' : 'w-6 text-center text-base leading-none'">{{ module.icon }}</span>
        <span v-show="!effectiveCollapsed">{{ module.label }}</span>
      </button>
    </nav>

    <!-- Footer Controls -->
    <div class="flex-shrink-0 border-t border-white/10 px-3 py-4 space-y-3">
      <!-- Donor Info -->
      <div class="flex rounded-[1.5rem] border border-white/10 bg-white/10 px-3 py-3 backdrop-blur-sm" :class="effectiveCollapsed ? 'flex-col items-center gap-2 px-0' : 'items-center justify-between'">
        <div class="flex min-w-0 items-center gap-3" :class="effectiveCollapsed ? 'justify-center' : ''">
          <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full bg-white text-xs font-black text-red-700">{{ bloodTypeInitial }}</div>
          <div v-show="!effectiveCollapsed" class="min-w-0">
            <div class="truncate text-sm font-semibold text-white">{{ donorName }}</div>
            <div class="truncate text-xs text-red-100/80">Verified {{ bloodType }} donor</div>
          </div>
        </div>
        <button
          @click="$emit('logout')"
          class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-red-50/90 transition-colors hover:bg-white/10 hover:text-white"
          :class="effectiveCollapsed ? 'ml-0' : 'ml-2'"
          title="Logout"
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
    default: false,
  },
  mobileOpen: {
    type: Boolean,
    default: false,
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

defineEmits(['selectModule', 'toggleSidebar', 'logout']);

const effectiveCollapsed = computed(() => props.collapsed && !props.mobileOpen);

const bloodTypeInitial = computed(() => {
  return props.bloodType?.charAt(0).toUpperCase() || 'O';
});

const primaryModules = computed(() => props.modules.slice(0, 7));
const secondaryModules = computed(() => props.modules.slice(7));
</script>
