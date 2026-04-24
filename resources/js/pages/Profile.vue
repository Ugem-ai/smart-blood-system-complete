<template>
  <div class="mx-auto max-w-3xl px-6 py-8">
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
      <h1 class="text-2xl font-bold text-slate-900">Profile Settings</h1>
      <p class="mt-2 text-sm text-slate-600">Manage account details used by the Vue SPA.</p>

      <div v-if="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ error }}
      </div>

      <div v-if="feedback" class="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ feedback }}
      </div>

      <form class="mt-6 space-y-5" @submit.prevent="saveProfile">
        <div>
          <label class="mb-1 block text-sm font-semibold text-slate-800">Name</label>
          <input
            v-model="form.name"
            type="text"
            class="w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 outline-none focus:border-red-500"
            required
          />
        </div>

        <div>
          <label class="mb-1 block text-sm font-semibold text-slate-800">Email</label>
          <input
            v-model="form.email"
            type="email"
            class="w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 outline-none focus:border-red-500"
            required
          />
        </div>

        <div>
          <label class="mb-1 block text-sm font-semibold text-slate-800">Role</label>
          <input
            :value="user?.role || 'unknown'"
            type="text"
            disabled
            class="w-full cursor-not-allowed rounded-lg border border-slate-200 bg-slate-100 px-4 py-2.5 text-slate-500"
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="rounded-lg bg-red-600 px-4 py-2.5 font-semibold text-white hover:bg-red-700 disabled:opacity-50"
        >
          {{ loading ? 'Saving...' : 'Save Changes' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { authHeaders, currentUser, getAuthSession, setAuthSession } from '../lib/auth';

const user = ref(currentUser());
const loading = ref(false);
const error = ref('');
const feedback = ref('');

const form = ref({
  name: '',
  email: '',
});

const hydrateForm = () => {
  form.value.name = user.value?.name || '';
  form.value.email = user.value?.email || '';
};

const loadUser = async () => {
  const response = await fetch('/api/me', {
    headers: authHeaders(),
  });

  if (!response.ok) {
    throw new Error('Unable to load user profile.');
  }

  const payload = await response.json();
  user.value = payload.user || null;

  const session = getAuthSession() || {};
  setAuthSession({
    ...session,
    user: user.value,
    token: session.token,
  });

  hydrateForm();
};

const saveProfile = async () => {
  loading.value = true;
  error.value = '';
  feedback.value = '';

  try {
    const response = await fetch('/api/me', {
      method: 'PATCH',
      headers: authHeaders({
        'Content-Type': 'application/json',
      }),
      body: JSON.stringify(form.value),
    });

    if (!response.ok) {
      throw new Error('Unable to update profile.');
    }

    await loadUser();
    feedback.value = 'Profile updated successfully.';
  } catch (saveError) {
    error.value = saveError.message || 'Unable to update profile.';
  } finally {
    loading.value = false;
  }
};

onMounted(async () => {
  try {
    await loadUser();
  } catch (loadError) {
    error.value = loadError.message || 'Unable to load profile.';
  }
});
</script>
