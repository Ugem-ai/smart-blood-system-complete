<template>
  <div class="register-shell min-h-dvh overflow-hidden bg-slate-100 text-white">
    <div class="pointer-events-none absolute inset-0">
      <div class="register-soft-glow"></div>
    </div>

    <div class="relative mx-auto flex min-h-dvh w-full max-w-5xl items-start px-3 py-4 sm:px-6 sm:py-8 lg:items-center lg:px-8">
      <div class="grid w-full overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-[0_24px_60px_rgba(15,23,42,0.10)] sm:rounded-[2rem] lg:grid-cols-[0.95fr_1.05fr]">
        <section class="hidden flex-col justify-center bg-[linear-gradient(180deg,_#991b1b_0%,_#b91c1c_52%,_#7f1d1d_100%)] px-6 py-8 sm:px-8 lg:flex lg:min-h-[680px] lg:px-10">
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
              <p class="text-sm font-semibold uppercase tracking-[0.28em] text-red-100/85">Account registration</p>
              <h1 class="text-3xl font-black tracking-tight text-white sm:text-4xl">SmartBlood</h1>
              <p class="max-w-md text-sm leading-7 text-red-50/90 sm:text-base">
                Create your access profile for donor or institutional blood service coordination.
              </p>
            </div>

            <div class="space-y-3 rounded-3xl bg-white/10 p-5">
              <p class="text-sm font-semibold text-white">Registration includes:</p>
              <ul class="space-y-2 text-sm text-red-50/90">
                <li>Identity and contact setup</li>
                <li>Role-specific profile information</li>
                <li>Secure sign in credentials</li>
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
                  <p class="mt-1 text-sm text-slate-600">Create a donor or hospital account from your phone without the split-screen desktop layout.</p>
                </div>
              </div>

              <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-rose-500">Secure registration</p>
                <h2 class="text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">Create account</h2>
              </div>

              <div v-if="error" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 shadow-sm">
                {{ error }}
              </div>

              <div v-if="success" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 shadow-sm">
                {{ success }}
              </div>

              <form class="space-y-5" @submit.prevent="register">
                <div class="grid gap-4 md:grid-cols-2">
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-800">Full Name</label>
                    <input
                      v-model="form.name"
                      type="text"
                      placeholder="Your full name"
                      class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                    />
                  </div>

                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-800">Email</label>
                    <input
                      v-model="form.email"
                      type="email"
                      placeholder="you@example.com"
                      class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                    />
                  </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-800">Password</label>
                    <input
                      v-model="form.password"
                      type="password"
                      placeholder="At least 8 characters"
                      class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                    />
                  </div>

                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-800">Confirm Password</label>
                    <input
                      v-model="form.password_confirmation"
                      type="password"
                      placeholder="Repeat your password"
                      class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                    />
                  </div>
                </div>

                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-800">Role</label>
                  <select
                    v-model="form.role"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                  >
                    <option value="donor">Donor</option>
                    <option value="hospital">Hospital</option>
                  </select>
                </div>

                <div v-if="form.role === 'donor'" class="grid gap-4 md:grid-cols-2">
                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-800">Blood Type</label>
                    <select
                      v-model="form.blood_type"
                      class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                    >
                      <option value="">Select blood type</option>
                      <option v-for="group in bloodTypes" :key="group" :value="group">{{ group }}</option>
                    </select>
                  </div>

                  <div>
                    <label class="mb-2 block text-sm font-semibold text-slate-800">City</label>
                    <input
                      v-model="form.city"
                      type="text"
                      placeholder="Your city"
                      class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                    />
                  </div>
                </div>

                <div v-if="form.role === 'hospital'" class="grid gap-4 md:grid-cols-2">
                  <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold text-slate-800">Hospital Name</label>
                    <input
                      v-model="form.hospital_name"
                      type="text"
                      placeholder="Hospital name"
                      class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                    />
                  </div>

                  <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold text-slate-800">Address</label>
                    <input
                      v-model="form.address"
                      type="text"
                      placeholder="Hospital address"
                      class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                    />
                  </div>

                  <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold text-slate-800">Hospital Registration Code</label>
                    <input
                      v-model="form.hospital_registration_code"
                      type="password"
                      placeholder="Enter code provided by PRC"
                      class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                    />
                  </div>
                </div>

                <div>
                  <label class="mb-2 block text-sm font-semibold text-slate-800">Contact Number</label>
                  <input
                    v-model="form.contact_number"
                    type="text"
                    placeholder="09XXXXXXXXX"
                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3.5 text-slate-900 shadow-sm outline-none transition focus:border-rose-400 focus:ring-4 focus:ring-rose-100"
                  />
                </div>

                <label v-if="form.role === 'donor'" class="inline-flex items-start gap-3 text-sm text-slate-600">
                  <input v-model="form.privacy_consent" type="checkbox" class="mt-1 h-4 w-4 rounded border-slate-300 text-rose-600 focus:ring-rose-500" />
                  <span>I agree to the privacy and data processing policy for donor matching.</span>
                </label>

                <button
                  type="submit"
                  :disabled="loading"
                  class="inline-flex w-full items-center justify-center rounded-2xl bg-red-700 px-5 py-4 text-sm font-semibold uppercase tracking-[0.24em] text-white transition hover:bg-red-800 disabled:cursor-not-allowed disabled:opacity-60"
                >
                  {{ loading ? 'Creating account...' : 'Create account' }}
                </button>
              </form>

              <div class="border-t border-slate-200 pt-5 text-center text-sm text-slate-600">
                Already have an account?
                <router-link to="/login" class="font-semibold text-rose-600 transition hover:text-rose-700">Sign in</router-link>
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

const props = defineProps({
  initialRole: {
    type: String,
    default: 'donor',
  },
});

const router = useRouter();
const loading = ref(false);
const error = ref('');
const success = ref('');

const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

const form = ref({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: props.initialRole || 'donor',
  blood_type: '',
  city: '',
  contact_number: '',
  privacy_consent: false,
  hospital_name: '',
  address: '',
  hospital_registration_code: '',
});

const register = async () => {
  loading.value = true;
  error.value = '';
  success.value = '';

  try {
    const payload = {
      name: form.value.name,
      email: form.value.email,
      password: form.value.password,
      password_confirmation: form.value.password_confirmation,
      role: form.value.role,
      contact_number: form.value.contact_number,
    };

    if (form.value.role === 'donor') {
      payload.blood_type = form.value.blood_type;
      payload.city = form.value.city;
      payload.privacy_consent = form.value.privacy_consent;
    }

    if (form.value.role === 'hospital') {
      payload.hospital_name = form.value.hospital_name;
      payload.address = form.value.address;
      payload.hospital_registration_code = form.value.hospital_registration_code;
    }

    const response = await fetch('/api/register', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok) {
      if (data.errors) {
        const firstError = Object.values(data.errors).flat()[0];
        error.value = firstError || 'Registration failed.';
      } else {
        error.value = data.message || 'Registration failed.';
      }
      return;
    }

    setAuthSession(data);
    success.value = 'Account created successfully.';
    router.push(`/${data.user.role}/dashboard`);
  } catch (err) {
    error.value = 'Connection error. Please try again.';
  } finally {
    loading.value = false;
  }
};
</script>

<style scoped>
.register-shell {
  position: relative;
}

.register-soft-glow {
  position: absolute;
  inset: 0;
  background:
    radial-gradient(circle at top, rgba(251, 113, 133, 0.1), transparent 28%),
    linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
}

@media (max-width: 1023px) {
  .register-soft-glow {
    background:
      radial-gradient(circle at top, rgba(251, 113, 133, 0.08), transparent 24%),
      linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
  }
}
</style>
