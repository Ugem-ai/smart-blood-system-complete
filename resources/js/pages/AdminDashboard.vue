<template>
  <AdminLayout
    :modules="modules"
    :active-module="activeModule"
    :sidebar-collapsed="sidebarCollapsed"
    :admin-name="adminName"
    :module-subtitle="activeModuleSubtitle"
    @select-module="activeModule = $event"
    @toggle-sidebar="sidebarCollapsed = !sidebarCollapsed"
    @open-account="openAccount"
    @logout="logout"
  >
    <component :is="activeComponent" />
  </AdminLayout>
</template>

<script setup>
import { computed, defineAsyncComponent, ref } from 'vue';
import { useRouter } from 'vue-router';
import AdminLayout from '../components/admin/AdminLayout.vue';
import { currentUser, logoutSession } from '../lib/auth';

const DashboardOverview = defineAsyncComponent(() => import('../components/admin/DashboardOverview.vue'));
const BloodRequestsPage = defineAsyncComponent(() => import('../components/admin/BloodRequestsPage.vue'));
const DonorTable = defineAsyncComponent(() => import('../components/admin/DonorTable.vue'));
const HospitalTable = defineAsyncComponent(() => import('../components/admin/HospitalTable.vue'));
const MatchMonitor = defineAsyncComponent(() => import('../components/admin/MatchMonitor.vue'));
const NotificationPanel = defineAsyncComponent(() => import('../components/admin/NotificationPanel.vue'));
const AnalyticsCharts = defineAsyncComponent(() => import('../components/admin/AnalyticsCharts.vue'));
const AuditLogTable = defineAsyncComponent(() => import('../components/admin/AuditLogTable.vue'));
const SettingsForm = defineAsyncComponent(() => import('../components/admin/SettingsForm.vue'));

const router = useRouter();
const sidebarCollapsed = ref(false);
const activeModule = ref('dashboard');

const modules = [
  { id: 'dashboard', label: 'Dashboard', icon: '📊', section: 'Command Center' },
  { id: 'requests', label: 'Blood Requests', icon: '🩸', section: 'Command Center' },
  { id: 'matching', label: 'PAST-Match Monitoring', icon: '🎯', section: 'Command Center' },
  { id: 'donors', label: 'Donor Management', icon: '🧑', section: 'Network Management' },
  { id: 'hospitals', label: 'Hospital Management', icon: '🏥', section: 'Network Management' },
  { id: 'notifications', label: 'Notifications', icon: '🔔', section: 'Oversight' },
  { id: 'analytics', label: 'Analytics', icon: '📈', section: 'Oversight' },
  { id: 'logs', label: 'Audit Logs', icon: '🧾', section: 'Oversight' },
  { id: 'settings', label: 'Settings', icon: '⚙️', section: 'System Controls' },
];

const moduleSubtitleMap = {
  dashboard: 'Unified operational visibility across requests, donors, and network pressure.',
  requests: 'Monitor shortage demand, urgency levels, and request lifecycle decisions from one workspace.',
  donors: 'Coordinate donor readiness, reliability, and live match context with aligned filters and response data.',
  hospitals: 'Supervise hospital readiness, emergency demand, and network-wide coordination posture.',
  matching: 'Review PAST-Match execution, ranking behavior, and active emergency coordination signals.',
  notifications: 'Track outbound delivery, response follow-through, and escalation messaging in one queue.',
  analytics: 'Measure demand trends, response performance, and system health at an administrative level.',
  logs: 'Audit sensitive actions, role violations, and operational events with compliance-focused visibility.',
  settings: 'Control platform-wide emergency, security, matching, and governance settings.',
};

const moduleComponentMap = {
  dashboard: DashboardOverview,
  requests: BloodRequestsPage,
  donors: DonorTable,
  hospitals: HospitalTable,
  matching: MatchMonitor,
  notifications: NotificationPanel,
  analytics: AnalyticsCharts,
  logs: AuditLogTable,
  settings: SettingsForm,
};

const adminName = computed(() => currentUser()?.name || 'Admin');
const activeModuleSubtitle = computed(() => moduleSubtitleMap[activeModule.value] || moduleSubtitleMap.dashboard);

const activeComponent = computed(() => {
  return moduleComponentMap[activeModule.value] || DashboardOverview;
});

const logout = async () => {
  await logoutSession();
  router.push('/login');
};

const openAccount = () => {
  router.push('/profile');
};
</script>
