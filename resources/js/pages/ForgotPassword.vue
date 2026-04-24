<template>
  <div class="forgot-shell min-h-dvh overflow-hidden bg-slate-100 text-white">
    <div class="pointer-events-none absolute inset-0">
      <div class="forgot-soft-glow"></div>
    </div>

    <div class="relative mx-auto flex min-h-dvh w-full max-w-5xl items-start px-3 py-4 sm:px-6 sm:py-8 lg:items-center lg:px-8">
      <div class="grid w-full overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-[0_24px_60px_rgba(15,23,42,0.10)] sm:rounded-[2rem] lg:grid-cols-[0.95fr_1.05fr]">
        <section class="hidden flex-col justify-center bg-[linear-gradient(180deg,_#991b1b_0%,_#b91c1c_52%,_#7f1d1d_100%)] px-6 py-8 sm:px-8 lg:flex lg:min-h-[640px] lg:px-10">
          <div class="space-y-6">
            <div class="inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-red-100">
              <svg viewBox="0 0 64 64" aria-hidden="true" class="h-11 w-11">
                <circle cx="32" cy="24" r="18" fill="#DC2626" />
                <rect x="28" y="14" width="8" height="20" rx="2" fill="#FFFFFF" />
                <rect x="22" y="20" width="20" height="8" rx="2" fill="#FFFFFF" />
                <path d="M32 37C27.58 37 24 40.58 24 45C24 50.33 28.35 54 32 58C35.65 54 40 50.33 40 45C40 40.58 36.42 37 32 37Z" fill="#B91C1C" />
              </svg>
            </div>

            <div class="space-y-3">
              <p class="text-sm font-semibold uppercase tracking-[0.28em] text-red-100/85">Account recovery</p>
              <h1 class="text-3xl font-black tracking-tight text-white sm:text-4xl">SmartBlood</h1>
              <p class="max-w-md text-sm leading-7 text-red-50/90 sm:text-base">
                Use your registered email to receive a secure password reset link.
              </p>
            </div>

            <div class="space-y-3 rounded-3xl bg-white/10 p-5">
              <p class="text-sm font-semibold text-white">Recovery process:</p>
              <ul class="space-y-2 text-sm text-red-50/90">
                <li>Enter the email linked to your account</li>
                <li>Open the reset link sent by email</li>
                <li>Create a new secure password</li>
              </ul>
            </div>
          </div>
        </section>

        <section class="relative bg-white px-5 py-6 text-slate-900 sm:px-8 sm:py-8 lg:px-10 lg:py-10">
          <div class="mx-auto flex h-full w-full max-w-xl flex-col justify-center">
            <div class="space-y-6">
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
                  <p class="mt-1 text-sm text-slate-600">Request a password reset without the desktop split layout.</p>
                </div>
              </div>

              <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-rose-500">Secure reset</p>
                <h2 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Forgot password</h2>
                <p class="max-w-md text-sm leading-6 text-slate-600 sm:text-base">
                  Enter your email and we will send your reset instructions.
                </p>
              </div>

              <div v-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 shadow-sm">
                {{ error }}
              </div>

              <div v-if="success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 shadow-sm">
                {{ success }}
              </div>

              <form class="space-y-5" @submit.prevent="submit">
                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-800">Email Address</label>
                  <input
                    v-model="email"
                    type="email"
                    autocomplete="email"
                    placeholder="you@example.com"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                  />
                </div>

                <button
                  type="submit"
                  :disabled="loading"
                  class="inline-flex w-full items-center justify-center rounded-2xl bg-red-700 px-5 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-white transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {{ loading ? 'Sending link...' : 'Send reset link' }}
                </button>
              </form>

              <div class="border-t border-slate-200 pt-5 text-center text-sm text-slate-600">
                Remembered your password?
                <router-link to="/login" class="font-semibold text-rose-600 transition hover:text-rose-700">Back to login</router-link>
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

const email = ref('');
const loading = ref(false);
const error = ref('');
const success = ref('');

const submit = async () => {
  loading.value = true;
  error.value = '';
  success.value = '';

  try {
    const response = await fetch('/api/forgot-password', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({ email: email.value }),
    });

    const data = await response.json();

    if (!response.ok) {
      if (data.errors) {
        const firstError = Object.values(data.errors).flat()[0];
        error.value = firstError || 'Unable to send reset link.';
      } else {
        error.value = data.message || 'Unable to send reset link.';
      }
      return;
    }

    success.value = data.message || 'Password reset link sent.';
  } catch (submitError) {
    error.value = 'Connection error. Please try again.';
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.forgot-shell {
  position: relative;
}

.forgot-soft-glow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at top, rgba(251, 113, 133, 0.1), transparent 28%),
    linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
}

@media (max-width: 1023px) {
  .forgot-soft-glow {
    background:
      radial-gradient(circle at top, rgba(251, 113, 133, 0.08), transparent 24%),
      linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
  }
}
</style>
