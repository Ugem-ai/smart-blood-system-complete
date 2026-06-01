<template>
  <!-- Dashboard routes own their full layout; guest/auth pages get the plain wrapper -->
  <router-view v-if="isDashboard" />
  <div v-else class="min-h-screen bg-slate-100 text-slate-900">
    <header v-if="showShell" class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white">
      <div class="flex w-full items-center justify-between px-6 py-4">
        <router-link to="/" class="inline-flex items-center justify-center gap-3 text-xl font-bold text-red-600">
          <img src="/images/logo.png" alt="SmartBlood Logo" class="h-8 w-8 self-center object-center" />
          <span>SmartBlood</span>
        </router-link>
      </div>
    </header>

    <main>
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const dashboardPaths = ['/admin/dashboard', '/hospital/dashboard', '/donor/dashboard'];
const guestOnlyPaths = ['/login', '/register', '/register/hospital', '/forgot-password'];

const isDashboard = computed(() => dashboardPaths.some(p => route.path.startsWith(p)));

const showShell = computed(() => {
  if (guestOnlyPaths.includes(route.path)) return false;
  return !route.path.startsWith('/reset-password/');
});
</script>