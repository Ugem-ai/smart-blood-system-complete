<template>
  <div class="min-h-screen bg-gradient-to-br from-slate-900 via-red-800 to-slate-900 flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
      <div class="text-center mb-8">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-xl shadow-lg mb-4">
          <span class="text-3xl font-bold text-red-600">D</span>
        </div>
        <h1 class="text-3xl font-bold text-white mb-2">Reset Password</h1>
        <p class="text-slate-300">Choose a new secure password.</p>
      </div>

      <div class="bg-white rounded-xl shadow-2xl p-8 space-y-6">
        <div v-if="error" class="p-4 bg-red-50 border border-red-200 rounded-lg text-sm text-red-800">
          {{ error }}
        </div>

        <div v-if="success" class="p-4 bg-green-50 border border-green-200 rounded-lg text-sm text-green-800">
          {{ success }}
        </div>

        <div>
          <label class="block text-sm font-semibold text-slate-900 mb-2">Email Address</label>
          <input
            v-model="email"
            type="email"
            placeholder="you@example.com"
            class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none bg-slate-50"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-slate-900 mb-2">New Password</label>
          <input
            v-model="password"
            type="password"
            placeholder="At least 8 characters"
            class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none bg-slate-50"
          />
        </div>

        <div>
          <label class="block text-sm font-semibold text-slate-900 mb-2">Confirm Password</label>
          <input
            v-model="passwordConfirmation"
            type="password"
            placeholder="Repeat your new password"
            class="w-full px-4 py-3 border border-slate-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent outline-none bg-slate-50"
          />
        </div>

        <button
          @click="submit"
          :disabled="loading"
          class="w-full py-3 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          {{ loading ? 'Resetting...' : 'Reset Password' }}
        </button>

        <div class="text-center text-sm text-slate-600">
          <router-link to="/login" class="text-red-600 font-semibold hover:text-red-700">Back to login</router-link>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

const props = defineProps({
  token: {
    type: String,
    required: true,
  },
});

const route = useRoute();
const router = useRouter();

const email = ref((route.query.email || '').toString());
const password = ref('');
const passwordConfirmation = ref('');
const loading = ref(false);
const error = ref('');
const success = ref('');

const submit = async () => {
  loading.value = true;
  error.value = '';
  success.value = '';

  try {
    const response = await fetch('/api/reset-password', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify({
        token: props.token,
        email: email.value,
        password: password.value,
        password_confirmation: passwordConfirmation.value,
      }),
    });

    const data = await response.json();

    if (!response.ok) {
      if (data.errors) {
        const firstError = Object.values(data.errors).flat()[0];
        error.value = firstError || 'Unable to reset password.';
      } else {
        error.value = data.message || 'Unable to reset password.';
      }
      return;
    }

    success.value = data.message || 'Password reset successful.';

    setTimeout(() => {
      router.push('/login');
    }, 1200);
  } catch (submitError) {
    error.value = 'Connection error. Please try again.';
   } finally {
     loading.value = false;
   }
 };
 </script>
