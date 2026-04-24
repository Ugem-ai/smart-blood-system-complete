<template>
  <div class="flex min-h-[calc(100vh-72px)] items-center justify-center px-4 py-10">
    <div class="w-full max-w-md rounded-xl border border-slate-200 bg-white p-8 shadow-sm">
      <h1 class="text-2xl font-bold text-slate-900">Confirm Password</h1>
      <p class="mt-2 text-sm text-slate-600">Please confirm your password before continuing.</p>

      <div v-if="error" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ error }}
      </div>

      <form class="mt-6 space-y-4" @submit.prevent="submit">
        <div>
          <label class="mb-1 block text-sm font-semibold text-slate-800">Password</label>
          <input
            v-model="password"
            type="password"
            class="w-full rounded-lg border border-slate-300 bg-slate-50 px-4 py-2.5 outline-none focus:border-red-500"
            required
          />
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full rounded-lg bg-red-600 px-4 py-2.5 font-semibold text-white hover:bg-red-700 disabled:opacity-50"
        >
          {{ loading ? 'Confirming...' : 'Confirm Password' }}
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { authHeaders } from '../lib/auth';

const router = useRouter();
const password = ref('');
const loading = ref(false);
const error = ref('');

const submit = async () => {
  loading.value = true;
  error.value = '';

  try {
    const response = await fetch('/confirm-password', {
      method: 'POST',
      headers: authHeaders({
        'Content-Type': 'application/json',
      }),
      body: JSON.stringify({
        password: password.value,
      }),
    });

    if (!response.ok) {
      throw new Error('Password confirmation failed.');
    }

    router.push('/');
  } catch (submitError) {
    error.value = submitError.message || 'Password confirmation failed.';
  } finally {
    loading.value = false;
  }
};
</script>
