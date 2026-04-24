<template>
  <div class="flex min-h-dvh overflow-hidden bg-gray-50 lg:h-screen">
    <button
      v-if="mobileSidebarOpen"
      type="button"
      class="fixed inset-0 z-40 bg-slate-950/45 backdrop-blur-[2px] lg:hidden"
      aria-label="Close hospital navigation"
      @click="mobileSidebarOpen = false"
    ></button>

    <Sidebar
      :modules="modules"
      :active-module="activeModule"
      :collapsed="sidebarCollapsed"
      :mobile-open="mobileSidebarOpen"
      :hospital-name="hospitalName"
      @select-module="selectModule"
      @toggle-sidebar="toggleSidebar"
      @logout="logout"
    />

    <div class="flex flex-1 flex-col overflow-hidden">
      <Topbar
        :title="currentModuleLabel"
        :subtitle="currentModuleSubtitle"
        :hospital-name="hospitalName"
        @toggle-sidebar="toggleSidebar"
      />

      <main class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6">
        <component
          :is="activeComponentName"
          :key="activeModule"
          @navigate="activeModule = $event"
        />
      </main>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import Sidebar from '../components/hospital/Sidebar.vue';
import Topbar from '../components/hospital/Topbar.vue';
import DashboardOverview from '../components/hospital/DashboardOverview.vue';
import CreateRequestForm from '../components/hospital/CreateRequestForm.vue';
import ActiveRequestsTable from '../components/hospital/ActiveRequestsTable.vue';
import MatchedDonorsList from '../components/hospital/MatchedDonorsList.vue';
import ResponseTracker from '../components/hospital/ResponseTracker.vue';
import NotificationPanel from '../components/hospital/NotificationPanel.vue';
import EmergencyEscalation from '../components/hospital/EmergencyEscalation.vue';
import BroadcastRequests from '../components/hospital/BroadcastRequests.vue';
import AnalyticsPanel from '../components/hospital/AnalyticsPanel.vue';
import PastMatchInsights from '../components/hospital/PastMatchInsights.vue';
import AuditLogPanel from '../components/hospital/AuditLogPanel.vue';
import SettingsPanel from '../components/hospital/SettingsPanel.vue';
import { getAuthSession, logoutSession } from '../lib/auth';

const router = useRouter();
const activeModule = ref('dashboard');
const sidebarCollapsed = ref(false);
const mobileSidebarOpen = ref(false);
const hospitalName = ref('Hospital');

const modules = [
  { id: 'dashboard', label: 'Dashboard', icon: '⌘', section: 'Main Operations' },
  { id: 'create-request', label: 'Create Blood Request', icon: '✚', section: 'Main Operations' },
  { id: 'active-requests', label: 'Active Requests', icon: '🩸', section: 'Main Operations' },
  { id: 'matched-donors', label: 'Matched Donors', icon: '◎', section: 'Main Operations' },
  { id: 'response-tracking', label: 'Response Tracking', icon: '◔', section: 'Main Operations' },
  { id: 'emergency-escalation', label: 'Emergency Escalation', icon: '⚠', section: 'Main Operations' },
  { id: 'notifications', label: 'Notifications', icon: '◉', section: 'Communication' },
  { id: 'broadcast-requests', label: 'Broadcast Requests', icon: '↗', section: 'Communication' },
  { id: 'analytics', label: 'Analytics', icon: '▣', section: 'Analytics & Monitoring' },
  { id: 'past-match-insights', label: 'PAST-Match Insights', icon: '◌', section: 'Analytics & Monitoring' },
  { id: 'audit-logs', label: 'Audit Logs', icon: '☰', section: 'Compliance & System' },
  { id: 'settings', label: 'Settings', icon: '⚙', section: 'Compliance & System' },
];

const moduleComponentMap = {
  dashboard: DashboardOverview,
  'create-request': CreateRequestForm,
  'active-requests': ActiveRequestsTable,
  'matched-donors': MatchedDonorsList,
  'response-tracking': ResponseTracker,
  'emergency-escalation': EmergencyEscalation,
  notifications: NotificationPanel,
  'broadcast-requests': BroadcastRequests,
  analytics: AnalyticsPanel,
  'past-match-insights': PastMatchInsights,
  'audit-logs': AuditLogPanel,
  settings: SettingsPanel,
};

const moduleSubtitleMap = {
  dashboard: 'Hospital command center for real-time blood coordination',
  'create-request': 'Fast request creation with immediate matching dispatch',
  'active-requests': 'Live monitoring, escalation, and fulfillment tracking',
  'matched-donors': 'Ranked donor candidates from PAST-Match',
  'response-tracking': 'Donor engagement, acceptance, and timing metrics',
  'emergency-escalation': 'Critical controls for aggressive emergency coordination',
  notifications: 'Outgoing alerts and request-linked notification monitoring',
  'broadcast-requests': 'Mass donor dispatch workflow for urgent shortages',
  analytics: 'Operational trends and fulfillment intelligence',
  'past-match-insights': 'Explainable AI view of donor ranking decisions',
  'audit-logs': 'Hospital-scoped compliance and traceability events',
  settings: 'Hospital-visible system rules and emergency policies',
};

const activeComponentName = computed(() => moduleComponentMap[activeModule.value] || DashboardOverview);
const currentModuleLabel = computed(() => modules.find((module) => module.id === activeModule.value)?.label || 'Hospital Dashboard');
const currentModuleSubtitle = computed(() => moduleSubtitleMap[activeModule.value] || 'Hospital Emergency Blood Coordination');

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

onMounted(() => {
  const user = getAuthSession();
  if (user?.user?.hospital_profile?.hospital_name) {
    hospitalName.value = user.user.hospital_profile.hospital_name;
  } else if (user?.hospital_profile?.hospital_name) {
    hospitalName.value = user.hospital_profile.hospital_name;
  } else if (user?.user?.name) {
    hospitalName.value = user.user.name;
  } else if (user?.hospital_name) {
    hospitalName.value = user.hospital_name;
  }
});
</script>

<style scoped>
.v-enter-active, .v-leave-active {
  transition: opacity 0.2s ease;
}

.v-enter-from, .v-leave-to {
  opacity: 0;
}
</style>
