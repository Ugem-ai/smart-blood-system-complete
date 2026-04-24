<template>
  <div class="flex min-h-[calc(100vh-72px)] items-center justify-center px-6">
    <div class="text-center">
      <p class="text-sm font-semibold uppercase tracking-wide text-red-600">404</p>
      <h1 class="mt-2 text-3xl font-bold text-slate-900">Page Not Found</h1>
      <p class="mt-2 text-slate-600">The page you requested does not exist in the Vue router.</p>
      <router-link to="/" class="mt-6 inline-flex rounded-lg bg-red-600 px-4 py-2.5 font-semibold text-white hover:bg-red-700">
        Go Home
      </router-link>
    </div>
  </div>
</template>
