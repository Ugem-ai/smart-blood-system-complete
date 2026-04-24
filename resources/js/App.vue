<template>
  <!-- Dashboard routes own their full layout; guest/auth pages get the plain wrapper -->
  <router-view v-if="isDashboard" />
  <div v-else class="min-h-screen bg-slate-100 text-slate-900">
    <header v-if="showShell" class="sticky top-0 z-40 w-full border-b border-slate-200 bg-white">
      <div class="flex w-full items-center justify-between px-6 py-4">
        <router-link to="/" class="inline-flex items-center gap-3 text-xl font-bold text-red-600">
          <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-white shadow-sm ring-1 ring-red-100">
            <svg viewBox="0 0 64 64" aria-hidden="true" class="h-7 w-7">
              <circle cx="32" cy="24" r="18" fill="#DC2626" />
              <rect x="28" y="14" width="8" height="20" rx="2" fill="#FFFFFF" />
              <rect x="22" y="20" width="20" height="8" rx="2" fill="#FFFFFF" />
              <path d="M32 37C27.58 37 24 40.58 24 45C24 50.33 28.35 54 32 58C35.65 54 40 50.33 40 45C40 40.58 36.42 37 32 37Z" fill="#B91C1C" />
            </svg>
          </span>
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
