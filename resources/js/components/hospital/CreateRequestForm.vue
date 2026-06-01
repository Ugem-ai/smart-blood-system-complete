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

    <!-- ── Critical Priority Alert & Status ──────────────────────────────── -->
    <div v-if="form.is_emergency || form.urgency_level === 'critical'" class="rounded-lg border border-red-300 bg-red-50 p-4">
      <div class="flex items-center gap-2 text-red-800 font-bold text-sm mb-3">
        <span class="text-xl">🚨</span>
        CRITICAL REQUEST — Maximum priority escalation active
      </div>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 text-xs text-red-700">
        <div class="flex items-start gap-2">
          <span>📈</span>
          <div>
            <strong>Prioritization Boost:</strong> +20% base priority, +35% time sensitivity
          </div>
        </div>
        <div class="flex items-start gap-2">
          <span>📍</span>
          <div>
            <strong>Search Radius:</strong> Automatically expanded as needed
          </div>
        </div>
        <div class="flex items-start gap-2">
          <span>⏱️</span>
          <div>
            <strong>Escalation:</strong> 3-stage auto-escalation if no response in 5 min
          </div>
        </div>
        <div class="flex items-start gap-2">
          <span>📢</span>
          <div>
            <strong>Notification:</strong> Mass SMS + push + email to all compatible donors
          </div>
        </div>
      </div>
    </div>

    <!-- ── System Status Banner ───────────────────────────────────────────── -->
    <div v-if="systemStatus.emergencyModeActive" class="rounded-lg border border-orange-300 bg-orange-50 p-4">
      <div class="flex items-center gap-2 text-orange-800 font-bold text-sm">
        <span class="text-lg">⚠️</span>
        System-wide Emergency Broadcast Mode Active
      </div>
      <p class="mt-2 text-xs text-orange-700">
        All requests are being processed with expanded donor pools and accelerated notification timelines.
        Trigger: <span class="font-semibold">{{ systemStatus.emergencyTrigger }}</span>
        (expires at <span class="font-semibold">{{ systemStatus.expiresAt }}</span>)
      </p>
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
          
          <!-- Urgency Info -->
          <div v-if="form.urgency_level" class="mt-3 rounded-lg bg-gray-50 p-3 border border-gray-200">
            <p class="text-xs font-semibold text-gray-700 mb-2">
              📊 Prioritization Impact:
            </p>
            <div class="grid grid-cols-2 gap-2 text-xs">
              <div class="flex items-center gap-1">
                <span v-if="form.urgency_level === 'low'" class="text-gray-600">Priority: -18%</span>
                <span v-else-if="form.urgency_level === 'medium'" class="text-yellow-600">Priority: Baseline</span>
                <span v-else-if="form.urgency_level === 'high'" class="text-orange-600">Priority: +12%</span>
                <span v-else-if="form.urgency_level === 'critical'" class="font-bold text-red-600">Priority: +20%</span>
              </div>
              <div class="flex items-center gap-1">
                <span v-if="form.urgency_level === 'low'" class="text-gray-600">Time: -8%</span>
                <span v-else-if="form.urgency_level === 'medium'" class="text-yellow-600">Time: Baseline</span>
                <span v-else-if="form.urgency_level === 'high'" class="text-orange-600">Time: +18%</span>
                <span v-else-if="form.urgency_level === 'critical'" class="font-bold text-red-600">Time: +35%</span>
              </div>
            </div>
          </div>
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

    <!-- ── Multi-Critical Prioritization Guide ───────────────────────────────── -->
    <div class="rounded-lg border border-purple-200 bg-purple-50 p-5">
      <h3 class="font-semibold text-purple-900">🎯 Handling Multiple Critical Cases Simultaneously</h3>
      <p class="mt-2 text-sm text-purple-800 mb-3">
        When multiple life-threatening requests occur at the same time (e.g., mass casualty incident), the system manages them using intelligent parallel processing:
      </p>

      <!-- Escalation Stages -->
      <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="rounded-lg border border-purple-300 bg-white p-3">
          <div class="text-xs font-bold text-purple-900 mb-1">🔵 STAGE 1: Immediate (0 min)</div>
          <p class="text-xs text-purple-700">Top 5 closest donors notified simultaneously via SMS + push</p>
        </div>
        <div class="rounded-lg border border-purple-300 bg-white p-3">
          <div class="text-xs font-bold text-purple-900 mb-1">🟠 STAGE 2: Expand (5 min)</div>
          <p class="text-xs text-purple-700">Widen search radius if needed; alert next 10 donors in wider zone</p>
        </div>
        <div class="rounded-lg border border-purple-300 bg-white p-3">
          <div class="text-xs font-bold text-purple-900 mb-1">🔴 STAGE 3: Broadcast (10 min)</div>
          <p class="text-xs text-purple-700">Regional/national mass broadcast to all compatible donors if still no response</p>
        </div>
      </div>

      <!-- Key Features -->
      <div class="mt-4 space-y-2 text-xs text-purple-800">
        <div class="flex items-start gap-2">
          <span class="font-bold">🔀 Parallel Processing:</span>
          <span>Each critical request (O+, B-, A+, etc.) is ranked independently & simultaneously. No waiting between requests.</span>
        </div>
        <div class="flex items-start gap-2">
          <span class="font-bold">⚖️ Fair Rotation:</span>
          <span>Even during critical situations, donors matched in last 72h receive cooldown penalty to prevent burnout and maintain pool sustainability.</span>
        </div>
        <div class="flex items-start gap-2">
          <span class="font-bold">🚀 Emergency Boost:</span>
          <span>During system-wide disasters, all critical requests get +15% additional priority boost and expanded search radius.</span>
        </div>
      </div>

      <!-- Example -->
      <div class="mt-4 rounded-lg bg-white border border-purple-300 p-3">
        <p class="text-xs font-semibold text-purple-900 mb-2">📋 Example: 6-Victim Multi-Casualty Incident</p>
        <div class="grid grid-cols-2 gap-2 text-xs text-purple-700">
          <div>5:15 PM: 6 trauma patients arrive</div>
          <div>5:16 PM: Create 3 critical requests (O+, B-, A+)</div>
          <div>5:17 PM: All 3 ranked in parallel (~3 sec)</div>
          <div>5:17 PM: 30 donors notified simultaneously</div>
          <div>5:21 PM: O+ confirmed (3 units)</div>
          <div>5:23 PM: All units secured (7 minutes)</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
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
const systemStatus = ref({
  emergencyModeActive: false,
  emergencyTrigger: null,
  expiresAt: null,
});

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

// ── Fetch system status on mount ────────────────────────────────────────────────
onMounted(async () => {
  try {
    const response = await api.get('/admin/system/emergency-broadcast-status');
    if (response.data?.data) {
      const data = response.data.data;
      systemStatus.value = {
        emergencyModeActive: data.enabled ?? false,
        emergencyTrigger: data.trigger ?? null,
        expiresAt: data.expires_at ?? null,
      };
    }
  } catch (err) {
    // Silently fail if endpoint doesn't exist or user not authorized
    console.debug('Could not fetch emergency broadcast status:', err.message);
  }
});
</script>
