<template>
  <div class="mx-auto flex max-w-[96rem] flex-col gap-6">
    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div v-if="loading" class="space-y-4 animate-pulse">
        <div class="h-4 w-24 rounded-full bg-gray-100"></div>
        <div class="h-10 w-64 rounded-2xl bg-gray-100"></div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
          <div class="h-32 rounded-[1.5rem] bg-gray-100"></div>
          <div class="h-32 rounded-[1.5rem] bg-gray-100"></div>
          <div class="h-32 rounded-[1.5rem] bg-gray-100"></div>
        </div>
      </div>

      <template v-else>
        <div v-if="message" class="mb-5 rounded-[1.5rem] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">{{ message }}</div>
        <div v-if="error" class="mb-5 rounded-[1.5rem] border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ error }}</div>

        <div class="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
          <div class="flex min-w-0 items-start gap-4">
            <div class="inline-flex h-16 w-16 flex-shrink-0 items-center justify-center rounded-[1.5rem] bg-red-50 text-2xl font-bold text-red-600">{{ initials }}</div>
            <div class="min-w-0">
              <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Donor identity</div>
              <h2 class="mt-2 text-3xl font-black tracking-tight text-gray-950 sm:text-4xl">{{ profile.name || 'Donor profile' }}</h2>
              <p class="mt-2 break-words text-sm leading-6 text-gray-600">{{ profile.email || 'No email on file' }}{{ profile.phone ? ` • ${profile.phone}` : '' }}</p>
            </div>
          </div>

          <div class="flex flex-col gap-3 xl:w-[28rem]">
            <div class="flex flex-wrap justify-end gap-2">
              <button v-if="!editing" type="button" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700" @click="startEditing">Edit Profile</button>
              <template v-else>
                <button type="button" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" :disabled="saving" @click="cancelEditing">Cancel</button>
                <button type="button" class="rounded-xl bg-red-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50" :disabled="saving" @click="saveProfile">{{ saving ? 'Saving...' : 'Save Changes' }}</button>
              </template>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <article class="rounded-[1.5rem] border border-red-100 bg-red-50 p-4 text-center">
              <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Blood type</div>
              <div class="mt-2 text-3xl font-black tracking-tight text-red-700">{{ profile.blood_type || 'N/A' }}</div>
            </article>
            <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4 text-center">
              <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Reliability</div>
              <div class="mt-2 text-3xl font-black tracking-tight text-gray-950">{{ profile.reliability_score || 0 }}%</div>
            </article>
            </div>
          </div>
        </div>
      </template>
    </section>

    <section class="grid grid-cols-1 gap-6 xl:grid-cols-2">
      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Personal and contact</div>
        <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Core identity details</h3>
        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label v-for="item in personalFields" :key="item.key" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">{{ item.label }}</div>
            <template v-if="editing">
              <input
                v-if="item.type !== 'select'"
                v-model="form[item.key]"
                :type="item.type"
                :step="item.step"
                class="mt-3 w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100"
              >
              <select
                v-else
                v-model="form[item.key]"
                class="mt-3 w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100"
              >
                <option v-for="option in bloodTypeOptions" :key="option" :value="option">{{ option }}</option>
              </select>
            </template>
            <div v-else class="mt-2 break-words text-sm font-semibold text-gray-900">{{ item.display }}</div>
          </label>
        </div>
      </div>

      <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Medical and readiness</div>
        <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Operational donor profile</h3>
        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-2">
          <label v-for="item in medicalFields" :key="item.key" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">{{ item.label }}</div>
            <template v-if="editing && item.editable">
              <input
                v-model="form[item.key]"
                :type="item.type"
                :step="item.step"
                class="mt-3 w-full rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-sm font-medium text-gray-900 outline-none transition focus:border-red-400 focus:ring-4 focus:ring-red-100"
              >
            </template>
            <div v-else class="mt-2 break-words text-sm font-semibold text-gray-900">{{ item.display }}</div>
          </label>
        </div>
      </div>
    </section>

    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Reliability metrics</div>
      <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Response performance indicators</h3>
      <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
        <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-5 text-center">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Reliability score</div>
          <div class="mt-3 text-3xl font-black tracking-tight text-gray-950">{{ profile.reliability_score || 0 }}%</div>
          <div class="mt-2 text-sm text-gray-500">{{ profile.reliability_label || 'Unrated donor profile' }}</div>
        </article>
        <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-5 text-center">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Response rate</div>
          <div class="mt-3 text-3xl font-black tracking-tight text-gray-950">{{ profile.response_rate || 0 }}%</div>
          <div class="mt-2 text-sm text-gray-500">Based on accepted and declined requests.</div>
        </article>
        <article class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-5 text-center">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Completion rate</div>
          <div class="mt-3 text-3xl font-black tracking-tight text-gray-950">{{ profile.completion_rate || 0 }}%</div>
          <div class="mt-2 text-sm text-gray-500">Accepted requests converted into completed donation activity.</div>
        </article>
      </div>
    </section>

    <section class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
      <div class="text-xs font-semibold uppercase tracking-[0.18em] text-red-500">Response history</div>
      <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">Recent donor request decisions</h3>
      <div v-if="profile.response_history?.length" class="mt-5 space-y-3">
        <article v-for="entry in profile.response_history" :key="entry.id" class="rounded-[1.5rem] border border-gray-200 bg-gray-50 p-4">
          <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="entry.response === 'accepted' ? 'bg-emerald-100 text-emerald-700' : 'bg-red-100 text-red-700'">{{ entry.response_label }}</span>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em]" :class="entry.urgency_level === 'critical' ? 'bg-red-50 text-red-700 ring-1 ring-red-200' : 'bg-gray-100 text-gray-600'">{{ entry.urgency_label }}</span>
              </div>
              <div class="mt-3 text-sm font-semibold text-gray-900">{{ entry.hospital_name }} requested {{ entry.blood_type }}</div>
              <div class="mt-1 text-sm text-gray-600">{{ entry.city }} • Request {{ entry.request_status }}</div>
            </div>
            <div class="text-sm font-semibold text-gray-500 sm:text-right">{{ entry.responded_at_label }}</div>
          </div>
        </article>
      </div>
      <div v-else class="mt-5 rounded-[1.5rem] border border-dashed border-gray-200 bg-gray-50 px-5 py-10 text-center text-sm text-gray-500">No accepted or declined responses are recorded yet.</div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { fetchDonorProfile, formatDate, updateDonorProfile } from '../../lib/donorPanel';
import { showDonorToast } from '../../lib/donorToast';

const profile = ref({});
const form = ref({});
const loading = ref(true);
const saving = ref(false);
const editing = ref(false);
const error = ref('');
const message = ref('');

const bloodTypeOptions = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

const initials = computed(() => (profile.value.name || 'Donor')
  .split(' ')
  .filter(Boolean)
  .slice(0, 2)
  .map((part) => part[0])
  .join('')
  .toUpperCase());

const personalFields = computed(() => [
  { key: 'name', label: 'Full name', type: 'text', display: profile.value.name || 'Not available' },
  { key: 'email', label: 'Email', type: 'email', display: profile.value.email || 'Not available' },
  { key: 'phone', label: 'Phone', type: 'tel', display: profile.value.phone || 'Not available' },
  { key: 'city', label: 'City', type: 'text', display: profile.value.city || 'Not available' },
  { key: 'blood_type', label: 'Blood type', type: 'select', display: profile.value.blood_type || 'Not available' },
  { key: 'contact_number', label: 'Contact backup', type: 'tel', display: profile.value.contact_number || profile.value.phone || 'Not available' },
]);

const medicalFields = computed(() => [
  { key: 'last_donation_date', label: 'Last donation', type: 'date', editable: true, display: formatDate(profile.value.last_donation_date) },
  { key: 'latitude', label: 'Latitude', type: 'number', step: '0.0000001', editable: true, display: profile.value.latitude ?? 'Not available' },
  { key: 'longitude', label: 'Longitude', type: 'number', step: '0.0000001', editable: true, display: profile.value.longitude ?? 'Not available' },
  { key: 'privacy_consent_at', label: 'Privacy consent', editable: false, display: formatDate(profile.value.privacy_consent_at) },
  { key: 'eligibility', label: 'Eligibility', editable: false, display: profile.value.donation_eligibility?.is_eligible ? 'Eligible to donate' : `Eligible on ${formatDate(profile.value.donation_eligibility?.next_eligible_date)}` },
  { key: 'screening', label: 'Screening status', editable: false, display: profile.value.donation_eligibility?.last_screening_result || 'Not available' },
]);

function syncForm() {
  form.value = {
    name: profile.value.name || '',
    email: profile.value.email || '',
    phone: profile.value.phone || '',
    contact_number: profile.value.contact_number || profile.value.phone || '',
    city: profile.value.city || '',
    blood_type: profile.value.blood_type || 'O+',
    last_donation_date: profile.value.last_donation_date || '',
    latitude: profile.value.latitude ?? '',
    longitude: profile.value.longitude ?? '',
  };
}

function startEditing() {
  error.value = '';
  message.value = '';
  syncForm();
  editing.value = true;
}

function cancelEditing() {
  editing.value = false;
  error.value = '';
  message.value = '';
  syncForm();
}

async function saveProfile() {
  saving.value = true;
  error.value = '';
  message.value = '';

  try {
    await updateDonorProfile({
      ...form.value,
      latitude: form.value.latitude === '' ? null : Number(form.value.latitude),
      longitude: form.value.longitude === '' ? null : Number(form.value.longitude),
      last_donation_date: form.value.last_donation_date || null,
    });

    await loadProfile();
    editing.value = false;
    message.value = 'Your donor profile has been updated.';
    showDonorToast(message.value);
  } catch (requestError) {
    error.value = requestError.response?.data?.message || 'Unable to update your donor profile.';
    showDonorToast(error.value, 'error');
  } finally {
    saving.value = false;
  }
}

async function loadProfile() {
  loading.value = true;
  try {
    profile.value = await fetchDonorProfile();
    syncForm();
  } finally {
    loading.value = false;
  }
}

onMounted(loadProfile);
</script>
