<template>
  <div class="flex min-h-dvh overflow-hidden bg-[radial-gradient(circle_at_top_left,_rgba(254,226,226,0.8),_rgba(248,250,252,0.95)_36%,_rgba(241,245,249,1)_100%)] lg:h-screen">
    <button
      v-if="mobileSidebarOpen"
      type="button"
      class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-[2px] lg:hidden"
      aria-label="Close donor navigation"
      @click="mobileSidebarOpen = false"
    ></button>

    <!-- Sidebar -->
    <Sidebar
      :modules="modules"
      :active-module="activeModule"
      :collapsed="sidebarCollapsed"
      :mobile-open="mobileSidebarOpen"
      :donor-name="donorName"
      :blood-type="bloodType"
      @select-module="selectModule"
      @toggle-sidebar="toggleSidebar"
      @logout="logout"
    />

    <!-- Main Layout -->
    <div class="flex flex-1 flex-col overflow-hidden">
      <!-- Topbar -->
      <Topbar
        :title="currentModuleLabel"
        :subtitle="currentModuleSubtitle"
        :donor-name="donorName"
        :blood-type="bloodType"
        @toggle-sidebar="toggleSidebar"
      />

      <!-- Content Area -->
      <main class="flex-1 overflow-y-auto px-4 py-5 sm:px-6 xl:px-8">
        <component
          :is="activeComponentName"
          :key="activeModule"
        />
      </main>
      <ToastStack />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import Sidebar from '../components/donor/Sidebar.vue';
import Topbar from '../components/donor/Topbar.vue';
import ToastStack from '../components/donor/ToastStack.vue';
import DashboardOverview from '../components/donor/DashboardOverview.vue';
import AvailabilityManager from '../components/donor/AvailabilityManager.vue';
import IncomingRequests from '../components/donor/IncomingRequests.vue';
import DonationHistory from '../components/donor/DonationHistory.vue';
import DonorProfile from '../components/donor/DonorProfile.vue';
import NotificationPanel from '../components/donor/NotificationPanel.vue';
import HealthEligibility from '../components/donor/HealthEligibility.vue';
import NearbyCenters from '../components/donor/NearbyCenters.vue';
import MyImpact from '../components/donor/MyImpact.vue';
import DonorSettings from '../components/donor/DonorSettings.vue';
import { getAuthSession, logoutSession } from '../lib/auth';

const router = useRouter();

// State
const activeModule = ref('dashboard');
const sidebarCollapsed = ref(false);
const mobileSidebarOpen = ref(false);
const donorName = ref('Donor');
const bloodType = ref('O+');

// Modules configuration
const modules = [
  { id: 'dashboard', label: 'Dashboard', icon: '💉' },
  { id: 'requests', label: 'Emergency Requests', icon: '🚨' },
  { id: 'notifications', label: 'Notifications', icon: '🔔' },
  { id: 'history', label: 'My Donations', icon: '📋' },
  { id: 'availability', label: 'Availability & Schedule', icon: '🕒' },
  { id: 'health', label: 'Health & Eligibility', icon: '🩺' },
  { id: 'centers', label: 'Nearby Centers', icon: '📍' },
  { id: 'impact', label: 'My Impact', icon: '📈' },
  { id: 'profile', label: 'Profile', icon: '👤' },
  { id: 'settings', label: 'Settings', icon: '⚙️' },
];

// Component mapping
const moduleComponentMap = {
  dashboard: DashboardOverview,
  availability: AvailabilityManager,
  requests: IncomingRequests,
  history: DonationHistory,
  health: HealthEligibility,
  centers: NearbyCenters,
  impact: MyImpact,
  profile: DonorProfile,
  notifications: NotificationPanel,
  settings: DonorSettings,
};

// Computed properties
const activeComponentName = computed(() => {
  return moduleComponentMap[activeModule.value] || DashboardOverview;
});

const currentModuleLabel = computed(() => {
  const module = modules.find(m => m.id === activeModule.value);
  return module ? module.label : 'Donor Dashboard';
});

const currentModuleSubtitle = computed(() => {
  const subtitles = {
    dashboard: 'Mission-critical snapshot of your donor readiness and nearby emergencies',
    requests: 'Respond quickly to active blood requests matched to your donor profile',
    notifications: 'Track alerts, reminders, and response updates from the emergency system',
    history: 'Review completed, scheduled, and cancelled donation activity',
    availability: 'Control your readiness status and preferred donation schedule windows',
    health: 'Check verified donor status, screening context, and current eligibility',
    centers: 'Locate hospitals and donation centers in your active emergency network',
    impact: 'Measure lives impacted, response performance, and donation streaks',
    profile: 'Review your donor identity, medical context, and location record',
    settings: 'Tune alert preferences and panel behavior for faster emergency response',
  };

  return subtitles[activeModule.value] || 'Donor emergency operations panel';
});

// Methods
const logout = async () => {
  await logoutSession();
  router.push('/login');
};

const toggleSidebar = () => {
  if (window.innerWidth < 1024) {
    mobileSidebarOpen.value = !mobileSidebarOpen.value;
    return;
  }

  sidebarCollapsed.value = !sidebarCollapsed.value;
};

const selectModule = (moduleId) => {
  activeModule.value = moduleId;

  if (window.innerWidth < 1024) {
    mobileSidebarOpen.value = false;
  }
};

// Lifecycle
onMounted(() => {
  const user = getAuthSession();
  if (user && user.name) {
    donorName.value = user.name;
  }
  if (user && user.blood_type) {
    bloodType.value = user.blood_type;
  }
});
</script>

<style scoped>
/* Module transitions fade smoothly */
.v-enter-active, .v-leave-active {
  transition: opacity 0.2s ease;
}

.v-enter-from, .v-leave-to {
  opacity: 0;
}
</style>
