<template>
  <div class="login-shell min-h-dvh overflow-hidden bg-slate-100 text-white">
    <div class="pointer-events-none absolute inset-0">
      <div class="login-soft-glow"></div>
    </div>

    <div class="relative mx-auto flex min-h-dvh w-full max-w-5xl items-start px-3 py-4 sm:px-6 sm:py-8 lg:items-center lg:px-8">
      <div class="grid w-full overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-[0_24px_60px_rgba(15,23,42,0.10)] sm:rounded-[2rem] lg:grid-cols-[0.95fr_1.05fr]">
        <section class="hidden flex-col justify-center bg-[linear-gradient(180deg,_#991b1b_0%,_#b91c1c_52%,_#7f1d1d_100%)] px-6 py-8 sm:px-8 lg:flex lg:min-h-[640px] lg:px-10">
          <div class="space-y-6">
            <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-red-100">
              <svg
                viewBox="0 0 64 64"
                aria-hidden="true"
                class="h-11 w-11"
              >
                <circle cx="32" cy="24" r="18" fill="#DC2626" />
                <rect x="28" y="14" width="8" height="20" rx="2" fill="#FFFFFF" />
                <rect x="22" y="20" width="20" height="8" rx="2" fill="#FFFFFF" />
                <path d="M32 37C27.58 37 24 40.58 24 45C24 50.33 28.35 54 32 58C35.65 54 40 50.33 40 45C40 40.58 36.42 37 32 37Z" fill="#B91C1C" />
              </svg>
            </div>

            <div class="space-y-3">
              <p class="text-sm font-semibold uppercase tracking-[0.28em] text-red-100/85">Secure access</p>
              <h1 class="text-3xl font-black tracking-tight text-white sm:text-4xl">SmartBlood</h1>
              <p class="max-w-md text-sm leading-7 text-red-50/90 sm:text-base">
                Simple access for donor coordination, hospital requests, and daily blood service operations.
              </p>
            </div>

            <div class="space-y-3 rounded-3xl bg-white/10 p-5">
              <p class="text-sm font-semibold text-white">Use this portal to manage:</p>
              <ul class="space-y-2 text-sm text-red-50/90">
                <li>Donor and hospital account access</li>
                <li>Blood request coordination</li>
                <li>Emergency response workflows</li>
              </ul>
            </div>
          </div>
        </section>

        <section class="relative bg-white px-5 py-6 text-slate-900 sm:px-8 sm:py-8 lg:px-10 lg:py-10">
          <div class="mx-auto flex h-full w-full max-w-xl flex-col justify-center">
            <div class="space-y-8">
              <div class="flex items-center gap-3 rounded-2xl bg-red-50 px-4 py-4 lg:hidden">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-red-100">
                  <svg viewBox="0 0 64 64" aria-hidden="true" class="h-8 w-8">
                    <circle cx="32" cy="24" r="18" fill="#DC2626" />
                    <rect x="28" y="14" width="8" height="20" rx="2" fill="#FFFFFF" />
                    <rect x="22" y="20" width="20" height="8" rx="2" fill="#FFFFFF" />
                    <path d="M32 37C27.58 37 24 40.58 24 45C24 50.33 28.35 54 32 58C35.65 54 40 50.33 40 45C40 40.58 36.42 37 32 37Z" fill="#B91C1C" />
                  </svg>
                </span>
                <div class="min-w-0">
                  <p class="text-[11px] font-semibold uppercase tracking-[0.24em] text-rose-500">SmartBlood</p>
                  <p class="mt-1 text-sm text-slate-600">Access donor, hospital, and emergency coordination tools.</p>
                </div>
              </div>

              <div class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-rose-500">Secure access</p>
                <div class="space-y-2">
                  <h2 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Sign in</h2>
                  <p class="max-w-md text-sm leading-6 text-slate-600 sm:text-base">
                    Access the SmartBlood workspace.
                  </p>
                </div>
              </div>

              <div v-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 shadow-sm">
                {{ error }}
              </div>

              <div class="space-y-3">
                <div class="flex items-center justify-between gap-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-500">Quick access</p>
                  <span class="text-xs text-slate-500">Select a sample account</span>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                  <button
                    v-for="account in presetAccounts"
                    :key="account.email"
                    type="button"
                    class="group rounded-2xl border border-slate-200 bg-white px-4 py-4 text-left transition-colors duration-200 hover:border-rose-300 hover:bg-rose-50/50"
                    @click="fillDemo(account)"
                  >
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-slate-500">{{ account.role }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-950 group-hover:text-rose-600">{{ account.label }}</p>
                    <p class="mt-1 truncate text-xs text-slate-500">{{ account.email }}</p>
                  </button>
                </div>
              </div>

              <form class="space-y-5" @submit.prevent="login">
                <div class="space-y-2">
                  <label for="email" class="block text-sm font-semibold text-slate-800">Email address</label>
                  <input
                    id="email"
                    v-model="email"
                    type="email"
                    autocomplete="email"
                    placeholder="you@example.com"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                  />
                </div>

                <div class="space-y-2">
                  <div class="flex items-center justify-between gap-4">
                    <label for="password" class="block text-sm font-semibold text-slate-800">Password</label>
                    <router-link to="/forgot-password" class="text-sm font-semibold text-rose-600 transition hover:text-rose-700">
                      Forgot password?
                    </router-link>
                  </div>

                  <div class="relative">
                    <input
                      id="password"
                      v-model="password"
                      :type="showPassword ? 'text' : 'password'"
                      autocomplete="current-password"
                      placeholder="Enter your password"
                      class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 pr-20 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                    />
                    <button
                      type="button"
                      class="absolute inset-y-2 right-2 rounded-xl px-3 text-sm font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                      @click="showPassword = !showPassword"
                    >
                      {{ showPassword ? 'Hide' : 'Show' }}
                    </button>
                  </div>
                </div>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                  <label class="inline-flex items-center gap-3 text-sm text-slate-600">
                    <input
                      v-model="rememberMe"
                      type="checkbox"
                      class="h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500"
                    />
                    Keep me signed in on this device
                  </label>

                  <p class="text-xs uppercase tracking-[0.2em] text-slate-400">Protected session</p>
                </div>

                <button
                  type="submit"
                  :disabled="loading"
                  class="inline-flex w-full items-center justify-center rounded-2xl bg-red-700 px-5 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-white transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {{ loading ? 'Signing in...' : 'Sign in to SmartBlood' }}
                </button>
              </form>

              <div class="flex flex-col gap-3 border-t border-slate-200 pt-6 text-sm text-slate-600 sm:flex-row sm:items-center sm:justify-between">
                <p>
                  New to the platform?
                  <router-link to="/register" class="font-semibold text-rose-600 transition hover:text-rose-700">Create an account</router-link>
                </p>
                <p>
                  Hospital onboarding?
                  <router-link to="/register/hospital" class="font-semibold text-slate-950 transition hover:text-rose-600">Register a facility</router-link>
                </p>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { setAuthSession } from '../lib/auth';
import { registerDeviceToken } from '../lib/deviceToken';

const router = useRouter();
const email = ref('test@example.com');
const password = ref('password');
const rememberMe = ref(false);
const loading = ref(false);
const error = ref('');
const showPassword = ref(false);

const presetAccounts = [
  {
    role: 'Admin',
    label: 'Administrator',
    email: 'test@example.com',
    password: 'password',
  },
  {
    role: 'Donor',
    label: 'Donor Account',
    email: 'donor@example.com',
    password: 'password',
  },
  {
    role: 'Hospital',
    label: 'Hospital Account',
    email: 'hospital@example.com',
    password: 'password',
  },
];

const fillDemo = (account) => {
  email.value = account.email;
  password.value = account.password;
  error.value = '';
};

const login = async () => {
  loading.value = true;
  error.value = '';

  try {
    // Call the app login endpoint
    const response = await fetch('/api/login', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({
        email: email.value,
        password: password.value,
      })
    });

    const data = await response.json();

    if (!response.ok) {
      error.value = data.message || 'Login failed. Please check your credentials.';
      return;
    }

    setAuthSession(data);

    try {
      await registerDeviceToken();
    } catch (tokenError) {
      console.warn('Device token registration failed:', tokenError);
    }

    router.push(`/${data.user.role}/dashboard`);
  } catch (err) {
    error.value = 'Connection error. Please try again.';
    console.error('Login error:', err);
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.login-shell {
  position: relative;
}

.login-soft-glow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at top, rgba(251, 113, 133, 0.1), transparent 28%),
    linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
}

@media (max-width: 1023px) {
  .login-soft-glow {
    background:
      radial-gradient(circle at top, rgba(251, 113, 133, 0.08), transparent 24%),
      linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
  }
}
</style>
