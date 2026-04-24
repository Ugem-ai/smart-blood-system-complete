<template>
  <div class="space-y-8">
    <!-- Page Heading -->
    <div>
      <h2 class="text-2xl font-bold text-gray-900">Create Blood Request</h2>
      <p class="mt-1 text-sm text-gray-500">
        Submit an urgent or scheduled blood request to the PAST-Match system.
        Fields marked <span class="text-red-500 font-semibold">*</span> are required.
      </p>
    </div>

    <!-- Feedback / Error banners -->
    <div v-if="feedback" class="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800">
      <span class="text-lg">✅</span>
      <div>
        <p class="font-semibold">Request submitted</p>
        <p>{{ feedback }}</p>
      </div>
    </div>
    <div v-if="error" class="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
      <span class="text-lg">❌</span>
      <div>
        <p class="font-semibold">Submission failed</p>
        <p>{{ error }}</p>
      </div>
    </div>

    <!-- ── Emergency Banner ──────────────────────────────────────────────── -->
    <div v-if="form.is_emergency || form.urgency_level === 'critical'" class="rounded-lg border border-red-300 bg-red-50 p-4">
      <div class="flex items-center gap-2 text-red-800 font-bold text-sm">
        <span class="text-xl">🚨</span>
        EMERGENCY REQUEST — This request will be escalated immediately and trigger mass donor notification.
      </div>
    </div>

    <!-- ── Section 1: Patient / Case Context ─────────────────────────────── -->
    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
      <div class="border-b border-gray-200 px-6 py-4">
        <h3 class="font-semibold text-gray-900">🩸 Patient / Case Context</h3>
      </div>
      <div class="grid grid-cols-1 gap-6 p-6 sm:grid-cols-2 lg:grid-cols-3">

        <!-- Blood Type -->
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-1">Blood Type <span class="text-red-500">*</span></label>
          <select v-model="form.blood_type" :class="fieldClass(v$.blood_type.$error)">
            <option value="">Select blood type</option>
            <option v-for="t in bloodTypes" :key="t" :value="t">{{ t }}</option>
          </select>
          <p v-if="v$.blood_type.$error" class="mt-1 text-xs text-red-600">{{ v$.blood_type.$errors[0].$message }}</p>
        </div>

        <!-- Component -->
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-1">Blood Component</label>
          <select v-model="form.component" class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100">
            <option value="">Any / Not specified</option>
            <option v-for="c in components" :key="c" :value="c">{{ c }}</option>
          </select>
          <p class="mt-1 text-xs text-gray-400">Leave blank if any component is acceptable.</p>
        </div>

        <!-- Units Required -->
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-1">Units Required <span class="text-red-500">*</span></label>
          <input
            v-model.number="form.units_required"
            type="number" min="1" max="20"
            :class="fieldClass(v$.units_required.$error)"
            placeholder="e.g., 2"
          />
          <p v-if="v$.units_required.$error" class="mt-1 text-xs text-red-600">{{ v$.units_required.$errors[0].$message }}</p>
        </div>

        <!-- Urgency Level -->
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-1">Urgency Level <span class="text-red-500">*</span></label>
          <select v-model="form.urgency_level" :class="urgencySelectClass">
            <option value="">Select urgency</option>
            <option value="low">Low — Scheduled / elective</option>
            <option value="medium">Medium — Within 24 hours</option>
            <option value="high">High — Immediate (< 6 hrs)</option>
            <option value="critical">⚠️ Critical — Active life threat</option>
          </select>
          <p v-if="v$.urgency_level.$error" class="mt-1 text-xs text-red-600">{{ v$.urgency_level.$errors[0].$message }}</p>
        </div>

        <!-- Reason -->
        <div class="sm:col-span-2">
          <label class="block text-sm font-semibold text-gray-900 mb-1">Clinical Reason (optional)</label>
          <input
            v-model="form.reason"
            type="text"
            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
            placeholder="e.g., surgery, trauma, dengue, post-partum haemorrhage"
            maxlength="100"
          />
        </div>

        <!-- Is Emergency toggle -->
        <div class="flex items-center gap-3 pt-2">
          <button
            type="button"
            @click="form.is_emergency = !form.is_emergency"
            :class="[
              'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 transition-colors duration-200',
              form.is_emergency ? 'border-red-600 bg-red-600' : 'border-gray-300 bg-gray-200'
            ]"
            role="switch"
            :aria-checked="form.is_emergency"
          >
            <span
              :class="[
                'inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200',
                form.is_emergency ? 'translate-x-5' : 'translate-x-0'
              ]"
            />
          </button>
          <div>
            <span class="text-sm font-semibold text-gray-900">Mark as Emergency</span>
            <p class="text-xs text-gray-500">Forces priority escalation and mass notification regardless of urgency level.</p>
          </div>
        </div>

      </div>
    </section>

    <!-- ── Section 2: Hospital Contact Overrides ──────────────────────────── -->
    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
      <div class="border-b border-gray-200 px-6 py-4">
        <h3 class="font-semibold text-gray-900">🏥 Hospital Contact (optional override)</h3>
        <p class="text-xs text-gray-500 mt-0.5">Leave blank to use your registered hospital contact details.</p>
      </div>
      <div class="grid grid-cols-1 gap-6 p-6 sm:grid-cols-2">

        <!-- Contact Person -->
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-1">Contact Person</label>
          <input
            v-model="form.contact_person"
            type="text"
            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
            placeholder="e.g., Dr. Maria Santos"
            maxlength="150"
          />
        </div>

        <!-- Contact Number -->
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-1">Contact Number</label>
          <input
            v-model="form.contact_number"
            type="tel"
            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
            placeholder="e.g., 09171234567"
            maxlength="30"
          />
        </div>

      </div>
    </section>

    <!-- ── Section 3: Location ────────────────────────────────────────────── -->
    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
      <div class="border-b border-gray-200 px-6 py-4">
        <h3 class="font-semibold text-gray-900">📍 Location & Search Radius</h3>
      </div>
      <div class="grid grid-cols-1 gap-6 p-6 sm:grid-cols-2 lg:grid-cols-3">

        <!-- City -->
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-1">City <span class="text-red-500">*</span></label>
          <input
            v-model="form.city"
            type="text"
            :class="fieldClass(v$.city.$error)"
            placeholder="e.g., Manila"
            maxlength="255"
          />
          <p v-if="v$.city.$error" class="mt-1 text-xs text-red-600">{{ v$.city.$errors[0].$message }}</p>
        </div>

        <!-- Province -->
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-1">Province</label>
          <input
            v-model="form.province"
            type="text"
            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
            placeholder="e.g., Metro Manila"
            maxlength="100"
          />
        </div>

        <!-- Distance Limit -->
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-1">Search Radius (km) <span class="text-red-500">*</span></label>
          <input
            v-model.number="form.distance_limit_km"
            type="number" min="1" max="500"
            :class="fieldClass(v$.distance_limit_km.$error)"
            placeholder="e.g., 50"
          />
          <p v-if="v$.distance_limit_km.$error" class="mt-1 text-xs text-red-600">{{ v$.distance_limit_km.$errors[0].$message }}</p>
          <p class="mt-1 text-xs text-gray-400">Emergency mode may automatically expand this radius.</p>
        </div>

        <!-- Coordinates (collapsible) -->
        <div class="sm:col-span-2 lg:col-span-3">
          <button type="button" @click="showCoords = !showCoords" class="text-xs text-blue-600 underline">
            {{ showCoords ? 'Hide' : 'Add' }} GPS coordinates (optional — improves distance accuracy)
          </button>
          <div v-if="showCoords" class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">Latitude</label>
              <input
                v-model.number="form.latitude"
                type="number" step="any" min="-90" max="90"
                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
                placeholder="e.g., 14.5995"
              />
            </div>
            <div>
              <label class="block text-sm font-semibold text-gray-900 mb-1">Longitude</label>
              <input
                v-model.number="form.longitude"
                type="number" step="any" min="-180" max="180"
                class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
                placeholder="e.g., 120.9842"
              />
            </div>
          </div>
        </div>

      </div>
    </section>

    <!-- ── Section 4: Time Constraints ───────────────────────────────────── -->
    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
      <div class="border-b border-gray-200 px-6 py-4">
        <h3 class="font-semibold text-gray-900">⏰ Time Constraints</h3>
      </div>
      <div class="grid grid-cols-1 gap-6 p-6 sm:grid-cols-2 lg:grid-cols-3">

        <!-- Required On -->
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-1">Required By (Date)</label>
          <input
            v-model="form.required_on"
            type="date"
            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
          />
          <p class="mt-1 text-xs text-gray-400">Leave blank for immediate / ASAP requests.</p>
        </div>

        <!-- Expiry Time -->
        <div>
          <label class="block text-sm font-semibold text-gray-900 mb-1">Request Expiry (optional)</label>
          <input
            v-model="form.expiry_time"
            type="datetime-local"
            class="w-full rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm focus:border-red-500 focus:outline-none focus:ring-2 focus:ring-red-100"
          />
          <p class="mt-1 text-xs text-gray-400">After this time the request will be automatically closed.</p>
        </div>

      </div>
    </section>

    <!-- ── Action Buttons ────────────────────────────────────────────────── -->
    <div class="flex flex-wrap items-center gap-4">
      <button
        @click="submitRequest"
        :disabled="loading || !canSubmit"
        class="inline-flex items-center gap-2 rounded-lg px-6 py-2.5 text-sm font-semibold text-white transition-colors"
        :class="canSubmit && !loading ? 'bg-red-600 hover:bg-red-700' : 'bg-red-300 cursor-not-allowed'"
      >
        <span v-if="loading" class="inline-block animate-spin">⏳</span>
        <span v-else>✅</span>
        {{ loading ? 'Submitting…' : 'Submit Blood Request' }}
      </button>

      <button
        @click="resetForm"
        type="button"
        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 px-6 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition-colors"
      >
        🔄 Clear Form
      </button>
    </div>

    <!-- ── Info Card ─────────────────────────────────────────────────────── -->
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-5">
      <h3 class="font-semibold text-blue-900">ℹ️ How the PAST-Match Algorithm Works</h3>
      <ul class="mt-2 space-y-1 text-sm text-blue-800">
        <li>✓ Your request is analysed immediately upon submission</li>
        <li>✓ Donors are ranked by compatibility, distance, availability, and reliability score</li>
        <li>✓ Critical / emergency requests trigger mass notification and expand the search radius</li>
        <li>✓ The 56-day inter-donation interval is automatically enforced</li>
        <li>✓ Real-time updates arrive as donors respond and accept</li>
      </ul>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { useVuelidate } from '@vuelidate/core';
import { required, integer, minValue, maxValue, helpers } from '@vuelidate/validators';
import api from '../../lib/api';

// ── Static options ────────────────────────────────────────────────────────────
const bloodTypes  = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
const components  = ['Whole Blood', 'PRBC', 'Platelets', 'Plasma'];

// ── Form state ────────────────────────────────────────────────────────────────
const defaultForm = () => ({
  // Patient / case
  blood_type:      '',
  component:       '',
  units_required:  1,
  urgency_level:   '',
  reason:          '',
  is_emergency:    false,

  // Hospital contact override
  contact_person:  '',
  contact_number:  '',

  // Location
  city:             '',
  province:         '',
  latitude:         null,
  longitude:        null,
  distance_limit_km: 50,

  // Time
  required_on:  '',
  expiry_time:  '',
});

const form        = ref(defaultForm());
const loading     = ref(false);
const error       = ref('');
const feedback    = ref('');
const showCoords  = ref(false);

// ── Vuelidate rules ───────────────────────────────────────────────────────────
const rules = {
  blood_type:       { required: helpers.withMessage('Blood type is required.', required) },
  units_required:   {
    required: helpers.withMessage('Units is required.', required),
    integer:  helpers.withMessage('Must be a whole number.', integer),
    min:      helpers.withMessage('At least 1 unit required.', minValue(1)),
    max:      helpers.withMessage('Maximum 20 units per request.', maxValue(20)),
  },
  urgency_level:    { required: helpers.withMessage('Urgency level is required.', required) },
  city:             { required: helpers.withMessage('City is required.', required) },
  distance_limit_km:{
    required: helpers.withMessage('Search radius is required.', required),
    min:      helpers.withMessage('Minimum radius is 1 km.', minValue(1)),
    max:      helpers.withMessage('Maximum radius is 500 km.', maxValue(500)),
  },
};

const v$ = useVuelidate(rules, form);

// ── Computed ──────────────────────────────────────────────────────────────────
const canSubmit = computed(() =>
  form.value.blood_type &&
  form.value.units_required >= 1 &&
  form.value.urgency_level &&
  form.value.city &&
  form.value.distance_limit_km >= 1
);

const urgencySelectClass = computed(() => {
  const base = 'w-full rounded-lg border px-4 py-2.5 text-sm focus:outline-none focus:ring-2';
  if (form.value.urgency_level === 'critical') return `${base} border-red-500 bg-red-50 text-red-900 focus:ring-red-100`;
  if (form.value.urgency_level === 'high')     return `${base} border-orange-400 bg-orange-50 text-orange-900 focus:ring-orange-100`;
  return `${base} border-gray-300 bg-white text-gray-900 focus:border-red-500 focus:ring-red-100`;
});

// ── Helpers ───────────────────────────────────────────────────────────────────
const fieldClass = (hasError) => [
  'w-full rounded-lg border px-4 py-2.5 text-sm focus:outline-none focus:ring-2',
  hasError
    ? 'border-red-400 bg-red-50 focus:border-red-500 focus:ring-red-100'
    : 'border-gray-300 bg-white focus:border-red-500 focus:ring-red-100',
];

// ── Submit ────────────────────────────────────────────────────────────────────
const submitRequest = async () => {
  const valid = await v$.value.$validate();
  if (!valid) return;

  loading.value = true;
  error.value   = '';
  feedback.value = '';

  try {
    const payload = {
      blood_type:        form.value.blood_type,
      component:         form.value.component || undefined,
      units_required:    Number(form.value.units_required),
      urgency_level:     form.value.urgency_level,
      reason:            form.value.reason || undefined,
      is_emergency:      form.value.is_emergency,

      contact_person:    form.value.contact_person || undefined,
      contact_number:    form.value.contact_number || undefined,

      city:              form.value.city,
      province:          form.value.province || undefined,
      latitude:          form.value.latitude  ?? undefined,
      longitude:         form.value.longitude ?? undefined,
      distance_limit_km: Number(form.value.distance_limit_km),

      required_on:       form.value.required_on || undefined,
      expiry_time:       form.value.expiry_time  || undefined,
    };

    const response = await api.post('/hospital/requests', payload);

    if (response.status === 201) {
      const req = response.data?.data;
      const mode = response.data?.operational_mode ?? {};
      feedback.value = `Request ${req?.case_id ?? '#' + req?.id} created. ` +
        `PAST-Match is searching within ${mode.expanded_radius_km ?? payload.distance_limit_km} km` +
        (mode.is_emergency ? ' (EMERGENCY mode active).' : '.');
      resetForm();
      setTimeout(() => { feedback.value = ''; }, 8000);
    }
  } catch (err) {
    const data = err.response?.data;
    if (data?.errors) {
      // Laravel validation error bag
      error.value = Object.values(data.errors).flat().join(' ');
    } else {
      error.value = data?.message ?? 'Failed to submit request. Please try again.';
    }
  } finally {
    loading.value = false;
  }
};

// ── Reset ─────────────────────────────────────────────────────────────────────
const resetForm = () => {
  form.value = defaultForm();
  v$.value.$reset();
  showCoords.value = false;
};
</script>
