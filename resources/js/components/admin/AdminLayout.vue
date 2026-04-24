<template>
  <div class="flex min-h-dvh overflow-hidden bg-[radial-gradient(circle_at_top_left,_rgba(254,226,226,0.92),_rgba(248,250,252,1)_40%,_rgba(255,255,255,1)_100%)] lg:h-screen">
    <button
      v-if="mobileSidebarOpen"
      type="button"
      class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-[2px] lg:hidden"
      aria-label="Close admin navigation"
      @click="mobileSidebarOpen = false"
    ></button>

    <Sidebar
      :modules="modules"
      :active-module="activeModule"
      :collapsed="sidebarCollapsed"
      :mobile-open="mobileSidebarOpen"
      :admin-name="adminName"
      @select="selectModule"
      @toggle-sidebar="toggleSidebar"
      @open-account="$emit('open-account')"
      @logout="$emit('logout')"
    />

    <div class="flex min-w-0 flex-1 flex-col overflow-hidden">
      <Topbar
        :title="currentModuleLabel"
        :subtitle="moduleSubtitle"
        :admin-name="adminName"
        @toggle-sidebar="toggleSidebar"
      />
      <main class="flex-1 overflow-y-auto px-4 py-5 sm:px-6 sm:py-6 lg:px-8 lg:py-8">
        <div class="mx-auto w-full max-w-[96rem]">
          <slot />
        </div>
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import Sidebar from './Sidebar.vue';
import Topbar from './Topbar.vue';

const props = defineProps({
  modules: {
    type: Array,
    required: true,
  },
  activeModule: {
    type: String,
    required: true,
  },
  sidebarCollapsed: {
    type: Boolean,
    required: true,
  },
  adminName: {
    type: String,
    default: 'Admin',
  },
  moduleSubtitle: {
    type: String,
    default: '',
  },
});

const emit = defineEmits(['select-module', 'toggle-sidebar', 'open-account', 'logout']);
const mobileSidebarOpen = ref(false);

const currentModuleLabel = computed(() => {
  if (props.activeModule === 'settings') return 'Settings';
  return props.modules.find((module) => module.id === props.activeModule)?.label || 'Dashboard';
});

const toggleSidebar = () => {
  if (window.innerWidth < 1024) {
    mobileSidebarOpen.value = !mobileSidebarOpen.value;
    return;
  }

  emit('toggle-sidebar');
};

const selectModule = (moduleId) => {
  emit('select-module', moduleId);

  if (window.innerWidth < 1024) {
    mobileSidebarOpen.value = false;
  }
};
</script>
