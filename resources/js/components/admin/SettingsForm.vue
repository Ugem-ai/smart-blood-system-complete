<template>
  <AdminPageFrame
    kicker="Admin System Settings"
    title="Command Center"
    description="A centralized operational control surface for matching, escalation, notifications, security, compliance, and runtime performance. Changes apply without a page reload and are persisted through the admin settings API."
    badge="Real-time operational controls"
  >
    <template #actions>
      <div class="flex flex-wrap items-center gap-2">
        <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs text-gray-600 shadow-sm">
          <div class="font-semibold uppercase tracking-[0.16em] text-gray-500">Last updated</div>
          <div class="mt-1 text-sm font-semibold text-gray-900">{{ lastUpdatedLabel }}</div>
          <div class="text-xs text-gray-500">{{ lastUpdatedByLabel }}</div>
        </div>
        <button type="button" class="admin-button-secondary" @click="loadSettings">Reload</button>
      </div>
    </template>

    <template #metrics>
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-[1.5rem] border border-red-100 bg-white/85 p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Matching Engine</div>
          <div class="mt-2 text-2xl font-black text-gray-950">{{ form.matching.engine_enabled ? 'Enabled' : 'Paused' }}</div>
          <div class="mt-1 text-sm text-gray-600">PAST-Match {{ formatModeLabel(form.matching.active_mode) }}</div>
        </div>
        <div class="rounded-[1.5rem] border border-red-100 bg-white/85 p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Emergency Threshold</div>
          <div class="mt-2 text-2xl font-black text-gray-950">{{ form.emergency.urgency_threshold }}</div>
          <div class="mt-1 text-sm text-gray-600">Escalates after {{ form.emergency.escalation_timer_minutes }} minutes</div>
        </div>
        <div class="rounded-[1.5rem] border border-red-100 bg-white/85 p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Notifications</div>
          <div class="mt-2 text-2xl font-black text-gray-950">{{ enabledChannelCount }}/3</div>
          <div class="mt-1 text-sm text-gray-600">{{ notificationRuleLabel(form.notifications.rule) }}</div>
        </div>
        <div class="rounded-[1.5rem] border border-red-100 bg-white/85 p-4 shadow-sm">
          <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Compliance State</div>
          <div class="mt-2 text-2xl font-black text-gray-950">{{ form.audit.activity_logging ? 'Tracked' : 'Muted' }}</div>
          <div class="mt-1 text-sm text-gray-600">Retention {{ form.audit.retention_days }} days</div>
        </div>
      </div>
    </template>

    <div v-if="message" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ message }}</div>
    <div v-if="error" class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ error }}</div>

    <form class="space-y-5" @submit.prevent="requestSave">
      <div class="grid grid-cols-1 gap-5 2xl:grid-cols-2">
        <section class="admin-panel 2xl:col-span-2">
          <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-xl font-black text-gray-950">Matching Engine Control Panel</h3>
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-gray-300 text-[11px] font-bold text-gray-500" title="Control live PAST-Match execution, mode weights, donor limits, and routing constraints.">i</span>
              </div>
              <p class="mt-1 text-sm text-gray-600">Control active operating mode, weight distribution, and hard search constraints for donor ranking.</p>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="admin-button-secondary" @click="resetSection('matching')">Reset to Default</button>
              <button type="button" class="admin-button-primary" @click="previewVisible = !previewVisible">{{ previewVisible ? 'Hide Preview' : 'Preview Match Impact' }}</button>
            </div>
          </div>

          <div class="mt-5 grid grid-cols-1 gap-4 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="space-y-4 rounded-[1.75rem] border border-gray-200 bg-gray-50 p-4">
              <label class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white px-4 py-3">
                <div>
                  <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                    Enable PAST-Match Engine
                    <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-gray-300 text-[10px] font-bold text-gray-500" title="Disabling this pauses automated donor ranking and forces manual coordination or fallback paths.">i</span>
                  </div>
                  <p class="mt-1 text-xs text-gray-500">Keeps automated donor ranking available without reloading the dashboard.</p>
                </div>
                <button type="button" class="relative inline-flex h-7 w-12 items-center rounded-full transition" :class="form.matching.engine_enabled ? 'bg-red-600' : 'bg-gray-300'" @click="form.matching.engine_enabled = !form.matching.engine_enabled">
                  <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="form.matching.engine_enabled ? 'translate-x-6' : 'translate-x-1'" />
                </button>
              </label>

              <div>
                <label class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">
                  Mode Selector
                  <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-gray-300 text-[10px] font-bold text-gray-500" title="Normal maps to the baseline operational profile, Emergency to critical response weighting, and Manual Override to a high-priority admin-tuned profile.">i</span>
                </label>
                <select v-model="form.matching.active_mode" class="admin-input">
                  <option value="normal">Normal Mode</option>
                  <option value="emergency">Emergency Mode</option>
                  <option value="manual_override">Manual Override Mode</option>
                </select>
              </div>

              <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div v-for="field in weightFields" :key="field.key" class="rounded-2xl border border-gray-200 bg-white p-4">
                  <div class="flex items-center justify-between gap-3">
                    <label class="text-sm font-semibold text-gray-900">{{ field.label }}</label>
                    <span class="rounded-full bg-red-50 px-2 py-1 text-xs font-semibold text-red-700">{{ currentModeWeights[field.key] }}%</span>
                  </div>
                  <p class="mt-1 text-xs text-gray-500">{{ field.help }}</p>
                  <input v-model.number="currentModeWeights[field.key]" type="range" min="0" max="100" step="1" class="mt-3 w-full accent-red-600" />
                  <div class="mt-1 flex justify-between text-[11px] text-gray-400">
                    <span>0</span>
                    <span>100</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="space-y-4 rounded-[1.75rem] border border-gray-200 bg-white p-4">
              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                      Strict Blood Type Matching
                      <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-gray-300 text-[10px] font-bold text-gray-500" title="Prevents approximate blood compatibility fallback and excludes non-matching donors from automated ranking.">i</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Lock matching to direct transfusion compatibility requirements.</p>
                  </div>
                  <button type="button" class="relative inline-flex h-7 w-12 items-center rounded-full transition" :class="form.matching.strict_blood_type ? 'bg-red-600' : 'bg-gray-300'" @click="form.matching.strict_blood_type = !form.matching.strict_blood_type">
                    <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="form.matching.strict_blood_type ? 'translate-x-6' : 'translate-x-1'" />
                  </button>
                </div>
              </div>

              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-center justify-between gap-3">
                  <label class="text-sm font-semibold text-gray-900">Maximum Search Radius</label>
                  <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.matching.max_search_radius_km }} km</span>
                </div>
                <p class="mt-1 text-xs text-gray-500">Controls the maximum donor distance considered during automated ranking.</p>
                <input v-model.number="form.matching.max_search_radius_km" type="range" min="5" max="100" step="1" class="mt-3 w-full accent-red-600" />
              </div>

              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-center justify-between gap-3">
                  <label class="text-sm font-semibold text-gray-900">Max Donor Notifications per Request</label>
                  <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.matching.max_donor_notifications }}</span>
                </div>
                <p class="mt-1 text-xs text-gray-500">Caps wave size to avoid oversaturating the donor pool for a single request.</p>
                <input v-model.number="form.matching.max_donor_notifications" type="range" min="1" max="20" step="1" class="mt-3 w-full accent-red-600" />
              </div>

              <div class="rounded-2xl border border-dashed border-red-200 bg-red-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Live Weight Balance</div>
                <div class="mt-2 text-2xl font-black text-gray-950">{{ currentWeightTotal }}%</div>
                <p class="mt-1 text-sm text-gray-600">The API normalizes this profile on save. The preview below already uses the normalized weight distribution.</p>
              </div>
            </div>
          </div>

          <div v-if="previewVisible" class="mt-5 rounded-[1.75rem] border border-red-100 bg-red-50/70 p-4">
            <div class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between">
              <div>
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Preview Match Impact</div>
                <h4 class="mt-1 text-lg font-bold text-gray-950">Simulated donor ranking under {{ formatModeLabel(form.matching.active_mode) }}</h4>
                <p class="mt-1 text-sm text-gray-600">Preview excludes donors blocked by strict blood-type rules or the active search radius.</p>
              </div>
              <div class="text-sm text-gray-600">Search radius {{ form.matching.max_search_radius_km }} km</div>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-2">
              <div v-for="candidate in previewCandidates" :key="candidate.name" class="rounded-2xl border border-white bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <div class="text-sm font-semibold text-gray-900">{{ candidate.name }}</div>
                    <div class="mt-1 text-xs text-gray-500">{{ candidate.summary }}</div>
                  </div>
                  <div class="text-right">
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Rank {{ candidate.rank }}</div>
                    <div class="mt-1 text-xl font-black text-gray-950">{{ candidate.score }}</div>
                  </div>
                </div>
                <div class="mt-3 flex flex-wrap gap-2 text-[11px] font-semibold">
                  <span class="rounded-full bg-red-50 px-2 py-1 text-red-700">{{ candidate.deltaLabel }}</span>
                  <span class="rounded-full bg-gray-100 px-2 py-1 text-gray-600">{{ candidate.distance_km }} km</span>
                  <span class="rounded-full bg-gray-100 px-2 py-1 text-gray-600">{{ candidate.response_time }} min response</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="admin-panel">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-lg font-black text-gray-950">Emergency &amp; Escalation Settings</h3>
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-gray-300 text-[11px] font-bold text-gray-500" title="Configure how the platform escalates urgency, expands reach, and triggers emergency fallback actions.">i</span>
              </div>
              <p class="mt-1 text-sm text-gray-600">Define thresholds, timers, stage expansion, and emergency-only actions.</p>
            </div>
            <button type="button" class="admin-button-secondary" @click="resetSection('emergency')">Reset to Default</button>
          </div>

          <div class="mt-4 space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <div class="flex items-center justify-between gap-3">
                <label class="text-sm font-semibold text-gray-900">Urgency Threshold</label>
                <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.emergency.urgency_threshold }}</span>
              </div>
              <input v-model.number="form.emergency.urgency_threshold" type="range" min="1" max="100" step="1" class="mt-3 w-full accent-red-600" />
            </div>

            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <div class="flex items-center justify-between gap-3">
                <label class="text-sm font-semibold text-gray-900">Escalation Timer</label>
                <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.emergency.escalation_timer_minutes }} min</span>
              </div>
              <input v-model.number="form.emergency.escalation_timer_minutes" type="range" min="1" max="30" step="1" class="mt-3 w-full accent-red-600" />
            </div>

            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
              <div class="rounded-2xl border border-gray-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Stage 1</div>
                <div class="mt-2 text-sm font-semibold text-gray-900">{{ form.emergency.stage_1_label }}</div>
                <div class="mt-1 text-xs text-gray-500">Immediate local donor activation.</div>
              </div>
              <div class="rounded-2xl border border-gray-200 bg-white p-4">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Stage 2</div>
                    <div class="mt-2 text-sm font-semibold text-gray-900">{{ form.emergency.stage_2_label }}</div>
                  </div>
                  <span class="rounded-full bg-red-50 px-2 py-1 text-xs font-semibold text-red-700">{{ form.emergency.stage_2_radius_km }} km</span>
                </div>
                <input v-model.number="form.emergency.stage_2_radius_km" type="range" min="10" max="200" step="5" class="mt-3 w-full accent-red-600" />
              </div>
              <div class="rounded-2xl border border-gray-200 bg-white p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400">Stage 3</div>
                <div class="mt-2 text-sm font-semibold text-gray-900">{{ form.emergency.stage_3_label }}</div>
                <select v-model="form.emergency.stage_3_scope" class="admin-input mt-3">
                  <option value="regional">Regional broadcast</option>
                  <option value="national">National broadcast</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <label v-for="action in emergencyActionFields" :key="action.key" class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-white px-4 py-3">
                <div>
                  <div class="text-sm font-semibold text-gray-900">{{ action.label }}</div>
                  <div class="mt-1 text-xs text-gray-500">{{ action.help }}</div>
                </div>
                <button type="button" class="relative inline-flex h-7 w-12 items-center rounded-full transition" :class="form.emergency.actions[action.key] ? 'bg-red-600' : 'bg-gray-300'" @click="form.emergency.actions[action.key] = !form.emergency.actions[action.key]">
                  <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="form.emergency.actions[action.key] ? 'translate-x-6' : 'translate-x-1'" />
                </button>
              </label>
            </div>

            <div class="flex flex-wrap gap-2">
              <button type="button" class="admin-button-primary" :disabled="activating" @click="setEmergencyBroadcast(true, 'manual-control-panel')">{{ activating ? 'Applying...' : 'Activate Emergency Mode' }}</button>
              <button type="button" class="admin-button-danger-soft" :disabled="broadcasting" @click="setEmergencyBroadcast(true, 'regional-broadcast')">{{ broadcasting ? 'Broadcasting...' : 'Trigger Broadcast' }}</button>
              <button type="button" class="admin-button-secondary" @click="setEmergencyBroadcast(false)">Stand Down</button>
            </div>
          </div>
        </section>

        <section class="admin-panel">
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-lg font-black text-gray-950">Notification Control System</h3>
                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-gray-300 text-[11px] font-bold text-gray-500" title="Adjust donor communication channels, retry policy, batching cadence, and quiet hours.">i</span>
              </div>
              <p class="mt-1 text-sm text-gray-600">Tune channel behavior, batching, retries, and quiet-hour delivery controls.</p>
            </div>
            <button type="button" class="admin-button-secondary" @click="resetSection('notifications')">Reset to Default</button>
          </div>

          <div class="mt-4 space-y-4">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
              <label v-for="channel in notificationChannels" :key="channel.key" class="flex items-center justify-between gap-3 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                <div>
                  <div class="text-sm font-semibold text-gray-900">{{ channel.label }}</div>
                  <div class="mt-1 text-xs text-gray-500">{{ channel.help }}</div>
                </div>
                <button type="button" class="relative inline-flex h-7 w-12 items-center rounded-full transition" :class="form.notifications.channels[channel.key] ? 'bg-red-600' : 'bg-gray-300'" @click="form.notifications.channels[channel.key] = !form.notifications.channels[channel.key]">
                  <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="form.notifications.channels[channel.key] ? 'translate-x-6' : 'translate-x-1'" />
                </button>
              </label>
            </div>

            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
              <div>
                <label class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">
                  Notification Rule
                  <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-gray-300 text-[10px] font-bold text-gray-500" title="Controls whether the platform only targets high-score candidates or broadcasts more aggressively.">i</span>
                </label>
                <select v-model="form.notifications.rule" class="admin-input">
                  <option value="critical-only">High-score donors only</option>
                  <option value="balanced">Balanced routing</option>
                  <option value="broadcast-all">Broadcast all donors</option>
                  <option value="emergency-active">Emergency broadcast posture</option>
                </select>
              </div>
              <div>
                <label class="mb-2 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Notification Batching</label>
                <select v-model="form.notifications.batching" class="admin-input">
                  <option value="wave-based">Wave-based</option>
                  <option value="immediate-broadcast">Immediate broadcast</option>
                </select>
              </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <div class="flex items-center justify-between gap-3">
                <label class="text-sm font-semibold text-gray-900">Retry Failed Notifications</label>
                <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.notifications.retry_attempts }} attempts</span>
              </div>
              <input v-model.number="form.notifications.retry_attempts" type="range" min="1" max="10" step="1" class="mt-3 w-full accent-red-600" />
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <div class="text-sm font-semibold text-gray-900">Quiet Hours</div>
                  <div class="mt-1 text-xs text-gray-500">Delay non-critical donor outreach inside an approved time window.</div>
                </div>
                <button type="button" class="relative inline-flex h-7 w-12 items-center rounded-full transition" :class="form.notifications.quiet_hours.enabled ? 'bg-red-600' : 'bg-gray-300'" @click="form.notifications.quiet_hours.enabled = !form.notifications.quiet_hours.enabled">
                  <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="form.notifications.quiet_hours.enabled ? 'translate-x-6' : 'translate-x-1'" />
                </button>
              </div>
              <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <input v-model="form.notifications.quiet_hours.start" type="time" class="admin-input" :disabled="!form.notifications.quiet_hours.enabled" />
                <input v-model="form.notifications.quiet_hours.end" type="time" class="admin-input" :disabled="!form.notifications.quiet_hours.enabled" />
              </div>
            </div>
          </div>
        </section>

        <section class="admin-panel">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-black text-gray-950">User &amp; Access Management</h3>
              <p class="mt-1 text-sm text-gray-600">Control permission posture, session hardening, and access constraints for privileged roles.</p>
            </div>
            <button type="button" class="admin-button-secondary" @click="resetSection('user_access')">Reset to Default</button>
          </div>

          <div class="mt-4 space-y-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
              <div v-for="role in rolePermissionFields" :key="role.key">
                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">{{ role.label }}</label>
                <select v-model="form.user_access.role_permissions[role.key]" class="admin-input">
                  <option v-for="option in role.options" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-center justify-between gap-3">
                  <label class="text-sm font-semibold text-gray-900">Session Timeout Duration</label>
                  <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.user_access.session_timeout_minutes }} min</span>
                </div>
                <input v-model.number="form.user_access.session_timeout_minutes" type="range" min="5" max="180" step="5" class="mt-3 w-full accent-red-600" />
              </div>
              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-center justify-between gap-3">
                  <label class="text-sm font-semibold text-gray-900">Max Login Attempts Before Lock</label>
                  <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.user_access.max_login_attempts }}</span>
                </div>
                <input v-model.number="form.user_access.max_login_attempts" type="range" min="3" max="10" step="1" class="mt-3 w-full accent-red-600" />
              </div>
            </div>

            <div>
              <label class="mb-2 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">
                IP Whitelisting
                <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border border-gray-300 text-[10px] font-bold text-gray-500" title="Restrict hospital or admin access to approved source IPs or CIDR ranges, one per line.">i</span>
              </label>
              <textarea v-model="form.user_access.ip_whitelisting" rows="4" class="admin-input" placeholder="203.0.113.10&#10;198.51.100.0/24"></textarea>
            </div>
          </div>
        </section>

        <section class="admin-panel">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-black text-gray-950">Audit &amp; Compliance Settings</h3>
              <p class="mt-1 text-sm text-gray-600">Decide what gets logged, how long logs persist, and how frequently formal reports are generated.</p>
            </div>
            <button type="button" class="admin-button-secondary" @click="resetSection('audit')">Reset to Default</button>
          </div>

          <div class="mt-4 space-y-4">
            <label class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
              <div>
                <div class="text-sm font-semibold text-gray-900">Enable Activity Logging</div>
                <div class="mt-1 text-xs text-gray-500">Captures standard administrative and operational actions.</div>
              </div>
              <button type="button" class="relative inline-flex h-7 w-12 items-center rounded-full transition" :class="form.audit.activity_logging ? 'bg-red-600' : 'bg-gray-300'" @click="form.audit.activity_logging = !form.audit.activity_logging">
                <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="form.audit.activity_logging ? 'translate-x-6' : 'translate-x-1'" />
              </button>
            </label>

            <label class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
              <div>
                <div class="text-sm font-semibold text-gray-900">Enable Sensitive Action Logging</div>
                <div class="mt-1 text-xs text-gray-500">Always record security-critical state changes and override actions.</div>
              </div>
              <button type="button" class="relative inline-flex h-7 w-12 items-center rounded-full transition" :class="form.audit.sensitive_action_logging ? 'bg-red-600' : 'bg-gray-300'" @click="form.audit.sensitive_action_logging = !form.audit.sensitive_action_logging">
                <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="form.audit.sensitive_action_logging ? 'translate-x-6' : 'translate-x-1'" />
              </button>
            </label>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Log Retention Period</label>
                <select v-model.number="form.audit.retention_days" class="admin-input">
                  <option :value="30">30 days</option>
                  <option :value="90">90 days</option>
                  <option :value="365">365 days</option>
                </select>
              </div>
              <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Auto-generate Audit Reports</label>
                <select v-model="form.audit.auto_reports" class="admin-input">
                  <option value="disabled">Disabled</option>
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                </select>
              </div>
            </div>
          </div>
        </section>

        <section class="admin-panel">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-black text-gray-950">Analytics Configuration</h3>
              <p class="mt-1 text-sm text-gray-600">Set aggregation frequency, KPI expectations, and dashboard refresh behavior.</p>
            </div>
            <button type="button" class="admin-button-secondary" @click="resetSection('analytics')">Reset to Default</button>
          </div>

          <div class="mt-4 space-y-4">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
              <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Data Aggregation</label>
                <select v-model="form.analytics.aggregation" class="admin-input">
                  <option value="hourly">Hourly</option>
                  <option value="daily">Daily</option>
                  <option value="weekly">Weekly</option>
                </select>
              </div>
              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-center justify-between gap-3">
                  <label class="text-sm font-semibold text-gray-900">Matching Success Threshold</label>
                  <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.analytics.matching_success_threshold }}%</span>
                </div>
                <input v-model.number="form.analytics.matching_success_threshold" type="range" min="50" max="100" step="1" class="mt-3 w-full accent-red-600" />
              </div>
              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-center justify-between gap-3">
                  <label class="text-sm font-semibold text-gray-900">Target Response Time</label>
                  <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.analytics.target_response_time_minutes }} min</span>
                </div>
                <input v-model.number="form.analytics.target_response_time_minutes" type="range" min="1" max="120" step="1" class="mt-3 w-full accent-red-600" />
              </div>
            </div>

            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <div class="flex items-center justify-between gap-3">
                <label class="text-sm font-semibold text-gray-900">Auto-refresh Interval</label>
                <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.analytics.refresh_rate_seconds }} sec</span>
              </div>
              <input v-model.number="form.analytics.refresh_rate_seconds" type="range" min="15" max="300" step="15" class="mt-3 w-full accent-red-600" />
            </div>
          </div>
        </section>

        <section class="admin-panel">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-black text-gray-950">System Performance Controls</h3>
              <p class="mt-1 text-sm text-gray-600">Tune worker throughput, cache behavior, API throttling, and global refresh cadence.</p>
            </div>
            <button type="button" class="admin-button-secondary" @click="resetSection('performance')">Reset to Default</button>
          </div>

          <div class="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div v-for="field in performanceFields" :key="field.key" class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <div class="flex items-center justify-between gap-3">
                <label class="text-sm font-semibold text-gray-900">{{ field.label }}</label>
                <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.performance[field.key] }} {{ field.unit }}</span>
              </div>
              <input v-model.number="form.performance[field.key]" :min="field.min" :max="field.max" :step="field.step" type="range" class="mt-3 w-full accent-red-600" />
            </div>
          </div>
        </section>

        <section class="admin-panel">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-black text-gray-950">Blood Request Rules</h3>
              <p class="mt-1 text-sm text-gray-600">Enforce request minimums, expiry, duplicate prevention, and manual priority override rights.</p>
            </div>
            <button type="button" class="admin-button-secondary" @click="resetSection('blood_request_rules')">Reset to Default</button>
          </div>

          <div class="mt-4 space-y-4">
            <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-center justify-between gap-3">
                  <label class="text-sm font-semibold text-gray-900">Minimum Units per Request</label>
                  <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.blood_request_rules.minimum_units }}</span>
                </div>
                <input v-model.number="form.blood_request_rules.minimum_units" type="range" min="1" max="10" step="1" class="mt-3 w-full accent-red-600" />
              </div>
              <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div class="flex items-center justify-between gap-3">
                  <label class="text-sm font-semibold text-gray-900">Request Expiration Time</label>
                  <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.blood_request_rules.expiration_time_minutes }} min</span>
                </div>
                <input v-model.number="form.blood_request_rules.expiration_time_minutes" type="range" min="5" max="240" step="5" class="mt-3 w-full accent-red-600" />
              </div>
            </div>

            <label class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
              <div>
                <div class="text-sm font-semibold text-gray-900">Duplicate Request Prevention</div>
                <div class="mt-1 text-xs text-gray-500">Stops overlapping requests for the same blood need and facility context.</div>
              </div>
              <button type="button" class="relative inline-flex h-7 w-12 items-center rounded-full transition" :class="form.blood_request_rules.duplicate_prevention ? 'bg-red-600' : 'bg-gray-300'" @click="form.blood_request_rules.duplicate_prevention = !form.blood_request_rules.duplicate_prevention">
                <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="form.blood_request_rules.duplicate_prevention ? 'translate-x-6' : 'translate-x-1'" />
              </button>
            </label>

            <div>
              <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Priority Override Permission</label>
              <select v-model="form.blood_request_rules.priority_override_permission" class="admin-input">
                <option value="admin-only">Admin only</option>
                <option value="hospital-and-admin">Hospital and admin</option>
                <option value="disabled">Disabled</option>
              </select>
            </div>
          </div>
        </section>

        <section class="admin-panel">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-black text-gray-950">Geolocation Settings</h3>
              <p class="mt-1 text-sm text-gray-600">Set default reach, route prioritization, and traffic-aware routing behavior.</p>
            </div>
            <button type="button" class="admin-button-secondary" @click="resetSection('geolocation')">Reset to Default</button>
          </div>

          <div class="mt-4 space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <div class="flex items-center justify-between gap-3">
                <label class="text-sm font-semibold text-gray-900">Default Search Radius</label>
                <span class="rounded-full bg-white px-2 py-1 text-xs font-semibold text-gray-700">{{ form.geolocation.default_search_radius_km }} km</span>
              </div>
              <input v-model.number="form.geolocation.default_search_radius_km" type="range" min="5" max="100" step="1" class="mt-3 w-full accent-red-600" />
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Region Prioritization</label>
                <select v-model="form.geolocation.region_prioritization" class="admin-input">
                  <option value="local-first">Local first</option>
                  <option value="regional-balance">Regional balance</option>
                  <option value="national-reach">National reach</option>
                </select>
              </div>
              <label class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3">
                <div>
                  <div class="text-sm font-semibold text-gray-900">Traffic-Aware Routing</div>
                  <div class="mt-1 text-xs text-gray-500">Adjust route preferences when congestion increases travel time.</div>
                </div>
                <button type="button" class="relative inline-flex h-7 w-12 items-center rounded-full transition" :class="form.geolocation.traffic_aware_routing ? 'bg-red-600' : 'bg-gray-300'" @click="form.geolocation.traffic_aware_routing = !form.geolocation.traffic_aware_routing">
                  <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="form.geolocation.traffic_aware_routing ? 'translate-x-6' : 'translate-x-1'" />
                </button>
              </label>
            </div>
          </div>
        </section>

        <section class="admin-panel 2xl:col-span-2">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-lg font-black text-gray-950">Fail-Safe &amp; Backup System</h3>
              <p class="mt-1 text-sm text-gray-600">Define the system response when matching fails and control backup posture for operational recovery.</p>
            </div>
            <button type="button" class="admin-button-secondary" @click="resetSection('fail_safe')">Reset to Default</button>
          </div>

          <div class="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-[0.95fr_1.05fr]">
            <div class="space-y-4 rounded-[1.75rem] border border-gray-200 bg-gray-50 p-4">
              <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Matching Failure Fallback</label>
                <select v-model="form.fail_safe.matching_failure_fallback" class="admin-input">
                  <option value="manual-assignment">Manual assignment</option>
                  <option value="broadcast-all-donors">Broadcast to all donors</option>
                </select>
              </div>
              <label class="flex items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white px-4 py-3">
                <div>
                  <div class="text-sm font-semibold text-gray-900">System Safe Mode</div>
                  <div class="mt-1 text-xs text-gray-500">Reduce system behavior to the most conservative, non-disruptive control path.</div>
                </div>
                <button type="button" class="relative inline-flex h-7 w-12 items-center rounded-full transition" :class="form.fail_safe.safe_mode ? 'bg-red-600' : 'bg-gray-300'" @click="form.fail_safe.safe_mode = !form.fail_safe.safe_mode">
                  <span class="inline-block h-5 w-5 transform rounded-full bg-white transition" :class="form.fail_safe.safe_mode ? 'translate-x-6' : 'translate-x-1'" />
                </button>
              </label>
            </div>

            <div class="space-y-4 rounded-[1.75rem] border border-gray-200 bg-white p-4">
              <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                <div>
                  <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Auto Backup Frequency</label>
                  <select v-model="form.fail_safe.backup_frequency" class="admin-input">
                    <option value="daily">Daily</option>
                    <option value="weekly">Weekly</option>
                  </select>
                </div>
                <div>
                  <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-gray-500">Last Backup Timestamp</label>
                  <input v-model="form.fail_safe.last_backup_timestamp" type="datetime-local" class="admin-input" />
                </div>
              </div>
              <div class="rounded-2xl border border-dashed border-red-200 bg-red-50 p-4">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Recovery posture</div>
                <div class="mt-2 text-lg font-bold text-gray-950">{{ failSafeSummary }}</div>
                <p class="mt-1 text-sm text-gray-600">Backups remain visible here so readiness can be reviewed during emergency drills or thesis defense demonstrations.</p>
              </div>
            </div>
          </div>
        </section>
      </div>

      <div class="sticky bottom-4 z-20">
        <div class="rounded-[1.75rem] border border-gray-200 bg-white/95 p-4 shadow-xl backdrop-blur">
          <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
              <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">Apply changes</div>
              <div class="mt-1 text-sm font-semibold text-gray-900">{{ hasUnsavedChanges ? 'Unsaved control changes detected' : 'All settings are in sync' }}</div>
              <div class="mt-1 text-xs text-gray-500">Critical changes require explicit confirmation before they are applied.</div>
            </div>
            <div class="flex flex-wrap gap-2">
              <button type="button" class="admin-button-secondary" @click="loadSettings">Discard Local Changes</button>
              <button type="submit" class="admin-button-primary" :disabled="saving">{{ saving ? 'Saving Changes...' : 'Save Changes' }}</button>
            </div>
          </div>
        </div>
      </div>
    </form>

    <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/50 px-4">
      <div class="w-full max-w-2xl rounded-[2rem] bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-red-500">Confirmation required</div>
            <h3 class="mt-2 text-2xl font-black text-gray-950">Apply critical control changes?</h3>
            <p class="mt-2 text-sm text-gray-600">These updates affect live operational behavior and will be written to the system settings audit trail.</p>
          </div>
          <button type="button" class="admin-button-secondary" @click="showConfirmModal = false">Close</button>
        </div>

        <div class="mt-5 rounded-2xl border border-red-100 bg-red-50 p-4">
          <div class="text-sm font-semibold text-gray-900">Critical changes detected</div>
          <ul class="mt-3 space-y-2 text-sm text-gray-700">
            <li v-for="item in criticalChanges" :key="item">{{ item }}</li>
          </ul>
        </div>

        <div class="mt-5 flex flex-wrap justify-end gap-2">
          <button type="button" class="admin-button-secondary" @click="showConfirmModal = false">Cancel</button>
          <button type="button" class="admin-button-primary" :disabled="saving" @click="confirmSave">Confirm and Apply</button>
        </div>
      </div>
    </div>
  </AdminPageFrame>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import AdminPageFrame from './AdminPageFrame.vue';
import api from '../../lib/api';

const defaultModeWeights = () => ({
  normal: { priority: 25, distance: 25, availability: 20, time: 30 },
  emergency: { priority: 28, distance: 21, availability: 16, time: 35 },
  manual_override: { priority: 27, distance: 22, availability: 18, time: 33 },
});

const defaultForm = () => ({
  matching: {
    engine_enabled: true,
    active_mode: 'normal',
    mode_weights: defaultModeWeights(),
    strict_blood_type: true,
    max_search_radius_km: 35,
    max_donor_notifications: 8,
  },
  emergency: {
    urgency_threshold: 70,
    escalation_timer_minutes: 5,
    stage_1_label: 'Nearby donors',
    stage_2_label: 'Expand radius',
    stage_3_label: 'Regional/National broadcast',
    stage_2_radius_km: 60,
    stage_3_scope: 'regional',
    actions: {
      increase_priority_weight: true,
      expand_search_radius: true,
      trigger_sms_fallback: true,
    },
  },
  notifications: {
    channels: {
      sms: true,
      email: true,
      in_app: true,
    },
    rule: 'critical-only',
    retry_attempts: 3,
    batching: 'wave-based',
    quiet_hours: {
      enabled: false,
      start: '22:00',
      end: '06:00',
    },
  },
  user_access: {
    role_permissions: {
      admin: 'full-control',
      hospital: 'request-and-coordinate',
      donor: 'respond-and-update',
    },
    session_timeout_minutes: 30,
    max_login_attempts: 5,
    ip_whitelisting: '',
  },
  audit: {
    activity_logging: true,
    sensitive_action_logging: true,
    retention_days: 90,
    auto_reports: 'weekly',
  },
  analytics: {
    aggregation: 'daily',
    matching_success_threshold: 85,
    target_response_time_minutes: 15,
    refresh_rate_seconds: 60,
  },
  performance: {
    queue_processing_limit: 250,
    cache_duration_minutes: 10,
    api_rate_limit: 120,
    global_auto_refresh_seconds: 45,
  },
  blood_request_rules: {
    minimum_units: 1,
    expiration_time_minutes: 45,
    duplicate_prevention: true,
    priority_override_permission: 'admin-only',
  },
  geolocation: {
    default_search_radius_km: 35,
    region_prioritization: 'local-first',
    traffic_aware_routing: true,
  },
  fail_safe: {
    matching_failure_fallback: 'manual-assignment',
    safe_mode: false,
    backup_frequency: 'daily',
    last_backup_timestamp: '',
  },
});

const weightFields = [
  { key: 'priority', label: 'Priority Weight', help: 'Amplifies urgency and operational criticality in ranking.' },
  { key: 'distance', label: 'Distance Weight', help: 'Pushes closer donors higher in the match order.' },
  { key: 'availability', label: 'Availability Weight', help: 'Rewards donors with current availability and eligibility readiness.' },
  { key: 'time', label: 'Response Time Weight', help: 'Favors donors with faster expected response time.' },
];

const emergencyActionFields = [
  { key: 'increase_priority_weight', label: 'Increase priority weight', help: 'Boost urgency influence during escalations.' },
  { key: 'expand_search_radius', label: 'Expand search radius', help: 'Allow broader donor reach under pressure.' },
  { key: 'trigger_sms_fallback', label: 'Trigger SMS fallback', help: 'Escalate to SMS when primary notifications stall.' },
];

const notificationChannels = [
  { key: 'sms', label: 'SMS', help: 'For urgent donor outreach and fallback escalation.' },
  { key: 'email', label: 'Email', help: 'For lower-urgency or informational communication.' },
  { key: 'in_app', label: 'In-App', help: 'Primary control channel for signed-in users.' },
];

const rolePermissionFields = [
  {
    key: 'admin',
    label: 'Admin Permissions',
    options: [
      { value: 'full-control', label: 'Full control' },
      { value: 'audit-only', label: 'Audit only' },
    ],
  },
  {
    key: 'hospital',
    label: 'Hospital Permissions',
    options: [
      { value: 'request-and-coordinate', label: 'Request and coordinate' },
      { value: 'request-only', label: 'Request only' },
      { value: 'view-only', label: 'View only' },
    ],
  },
  {
    key: 'donor',
    label: 'Donor Permissions',
    options: [
      { value: 'respond-and-update', label: 'Respond and update' },
      { value: 'respond-only', label: 'Respond only' },
      { value: 'view-only', label: 'View only' },
    ],
  },
];

const performanceFields = [
  { key: 'queue_processing_limit', label: 'Queue Processing Limit', min: 25, max: 1000, step: 25, unit: 'jobs' },
  { key: 'cache_duration_minutes', label: 'Cache Duration', min: 1, max: 180, step: 1, unit: 'min' },
  { key: 'api_rate_limit', label: 'API Rate Limit', min: 10, max: 500, step: 10, unit: 'rpm' },
  { key: 'global_auto_refresh_seconds', label: 'Auto-refresh Interval', min: 15, max: 300, step: 15, unit: 'sec' },
];

const previewDataset = [
  { name: 'Donor A', bloodCompatible: true, distance_km: 8, response_time: 7, priority: 88, availability: 94, distance: 92, time: 89 },
  { name: 'Donor B', bloodCompatible: true, distance_km: 26, response_time: 12, priority: 79, availability: 87, distance: 74, time: 76 },
  { name: 'Donor C', bloodCompatible: false, distance_km: 6, response_time: 5, priority: 93, availability: 83, distance: 95, time: 92 },
  { name: 'Donor D', bloodCompatible: true, distance_km: 31, response_time: 18, priority: 74, availability: 90, distance: 68, time: 71 },
  { name: 'Donor E', bloodCompatible: true, distance_km: 14, response_time: 9, priority: 82, availability: 79, distance: 84, time: 81 },
];

const form = ref(defaultForm());
const initialSnapshot = ref('');
const saving = ref(false);
const activating = ref(false);
const broadcasting = ref(false);
const previewVisible = ref(true);
const showConfirmModal = ref(false);
const error = ref('');
const message = ref('');
const metadata = ref({ updated_at: null, updated_by_name: null, updated_by: null });

const currentModeWeights = computed(() => form.value.matching.mode_weights[form.value.matching.active_mode]);
const currentWeightTotal = computed(() => Object.values(currentModeWeights.value).reduce((sum, value) => sum + Number(value || 0), 0));
const enabledChannelCount = computed(() => Object.values(form.value.notifications.channels).filter(Boolean).length);
const hasUnsavedChanges = computed(() => initialSnapshot.value !== serializeForm(form.value));

const lastUpdatedLabel = computed(() => {
  if (!metadata.value.updated_at) return 'Not yet saved';

  return new Intl.DateTimeFormat('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(new Date(metadata.value.updated_at));
});

const lastUpdatedByLabel = computed(() => metadata.value.updated_by_name || (metadata.value.updated_by ? `User #${metadata.value.updated_by}` : 'Awaiting first change'));

const failSafeSummary = computed(() => {
  const modeLabel = form.value.fail_safe.safe_mode ? 'Safe Mode enabled' : 'Safe Mode disabled';
  const fallbackLabel = form.value.fail_safe.matching_failure_fallback === 'manual-assignment' ? 'manual assignment' : 'broadcast all donors';

  return `${modeLabel}; fallback is ${fallbackLabel}.`;
});

const criticalChanges = computed(() => {
  if (!initialSnapshot.value) return [];

  const baseline = JSON.parse(initialSnapshot.value);
  const changes = [];

  if (baseline.matching.engine_enabled !== form.value.matching.engine_enabled) changes.push('Automated PAST-Match engine state changes.');
  if (baseline.matching.active_mode !== form.value.matching.active_mode) changes.push(`Matching mode switches to ${formatModeLabel(form.value.matching.active_mode)}.`);
  if (baseline.notifications.rule !== form.value.notifications.rule) changes.push('Notification routing rule changes.');
  if (baseline.user_access.max_login_attempts !== form.value.user_access.max_login_attempts) changes.push('Login lock threshold changes.');
  if (baseline.user_access.ip_whitelisting !== form.value.user_access.ip_whitelisting) changes.push('Privileged IP whitelist changes.');
  if (baseline.fail_safe.safe_mode !== form.value.fail_safe.safe_mode) changes.push('System Safe Mode state changes.');
  if (baseline.blood_request_rules.priority_override_permission !== form.value.blood_request_rules.priority_override_permission) changes.push('Priority override permission changes.');

  return changes;
});

const previewCandidates = computed(() => {
  const activeWeights = normalizeWeights(currentModeWeights.value);
  const baselineWeights = normalizeWeights(form.value.matching.mode_weights.normal);

  const scored = previewDataset
    .filter((candidate) => !form.value.matching.strict_blood_type || candidate.bloodCompatible)
    .filter((candidate) => candidate.distance_km <= form.value.matching.max_search_radius_km)
    .map((candidate) => {
      const currentScore = weightedScore(candidate, activeWeights);
      const baselineScore = weightedScore(candidate, baselineWeights);

      return {
        ...candidate,
        score: currentScore.toFixed(1),
        baselineScore,
        currentScore,
        summary: candidate.bloodCompatible ? 'Eligible under current compatibility rules.' : 'Excluded when strict blood matching is enabled.',
      };
    })
    .sort((left, right) => right.currentScore - left.currentScore)
    .slice(0, form.value.matching.max_donor_notifications)
    .map((candidate, index, list) => {
      const baselineRank = [...list].sort((left, right) => right.baselineScore - left.baselineScore).findIndex((entry) => entry.name === candidate.name) + 1;
      const delta = baselineRank - (index + 1);

      return {
        ...candidate,
        rank: index + 1,
        deltaLabel: delta === 0 ? 'No rank change' : delta > 0 ? `Up ${delta} position${delta > 1 ? 's' : ''}` : `Down ${Math.abs(delta)} position${Math.abs(delta) > 1 ? 's' : ''}`,
      };
    });

  return scored;
});

function weightedScore(candidate, weights) {
  return (
    (candidate.priority * weights.priority) +
    (candidate.availability * weights.availability) +
    (candidate.distance * weights.distance) +
    (candidate.time * weights.time)
  );
}

function normalizeWeights(weights) {
  const total = Object.values(weights).reduce((sum, value) => sum + Number(value || 0), 0);

  if (total <= 0) {
    return { priority: 0.25, distance: 0.25, availability: 0.2, time: 0.3 };
  }

  return Object.fromEntries(
    Object.entries(weights).map(([key, value]) => [key, Number(value || 0) / total])
  );
}

function toPercentWeights(weights) {
  return Object.fromEntries(
    Object.entries(weights || {}).map(([key, value]) => [key, Math.round(Number(value || 0) * 100)])
  );
}

function lowProfileFromNormal(normalWeights) {
  const weighted = {
    priority: normalWeights.priority * 0.82,
    availability: normalWeights.availability * 1.14,
    distance: normalWeights.distance * 1.1,
    time: normalWeights.time * 0.92,
  };

  return normalizeWeights(weighted);
}

function buildPayload() {
  const normalWeights = normalizeWeights(form.value.matching.mode_weights.normal);
  const emergencyWeights = normalizeWeights(form.value.matching.mode_weights.emergency);
  const manualWeights = normalizeWeights(form.value.matching.mode_weights.manual_override);

  const payload = {
    urgency_threshold: form.value.emergency.urgency_threshold,
    notification_rule: form.value.notifications.rule,
    weights: normalWeights,
    weight_profiles: {
      low: lowProfileFromNormal(normalWeights),
      medium: normalWeights,
      high: manualWeights,
      critical: emergencyWeights,
    },
    control_center: {
      matching: {
        engine_enabled: form.value.matching.engine_enabled,
        active_mode: form.value.matching.active_mode,
        mode_weights: {
          normal: normalWeights,
          emergency: emergencyWeights,
          manual_override: manualWeights,
        },
        strict_blood_type: form.value.matching.strict_blood_type,
        max_search_radius_km: form.value.matching.max_search_radius_km,
        max_donor_notifications: form.value.matching.max_donor_notifications,
      },
      emergency: {
        urgency_threshold: form.value.emergency.urgency_threshold,
        escalation_timer_minutes: form.value.emergency.escalation_timer_minutes,
        stage_1_label: form.value.emergency.stage_1_label,
        stage_2_label: form.value.emergency.stage_2_label,
        stage_3_label: form.value.emergency.stage_3_label,
        stage_2_radius_km: form.value.emergency.stage_2_radius_km,
        stage_3_scope: form.value.emergency.stage_3_scope,
        actions: { ...form.value.emergency.actions },
      },
      notifications: {
        channels: { ...form.value.notifications.channels },
        rule: form.value.notifications.rule,
        retry_attempts: form.value.notifications.retry_attempts,
        batching: form.value.notifications.batching,
        quiet_hours: { ...form.value.notifications.quiet_hours },
      },
      user_access: {
        role_permissions: { ...form.value.user_access.role_permissions },
        session_timeout_minutes: form.value.user_access.session_timeout_minutes,
        max_login_attempts: form.value.user_access.max_login_attempts,
        ip_whitelisting: form.value.user_access.ip_whitelisting,
      },
      audit: { ...form.value.audit },
      analytics: { ...form.value.analytics },
      performance: { ...form.value.performance },
      blood_request_rules: { ...form.value.blood_request_rules },
      geolocation: { ...form.value.geolocation },
      fail_safe: {
        ...form.value.fail_safe,
        last_backup_timestamp: form.value.fail_safe.last_backup_timestamp ? new Date(form.value.fail_safe.last_backup_timestamp).toISOString() : null,
      },
    },
  };

  return payload;
}

function hydrateForm(payload) {
  const defaults = defaultForm();
  const controlCenter = payload.control_center || {};
  const hydrated = {
    ...defaults,
    ...controlCenter,
    matching: {
      ...defaults.matching,
      ...controlCenter.matching,
      mode_weights: {
        normal: toPercentWeights(controlCenter.matching?.mode_weights?.normal || payload.past_match_weight_profiles?.medium || normalizeWeights(defaults.matching.mode_weights.normal)),
        emergency: toPercentWeights(controlCenter.matching?.mode_weights?.emergency || payload.past_match_weight_profiles?.critical || normalizeWeights(defaults.matching.mode_weights.emergency)),
        manual_override: toPercentWeights(controlCenter.matching?.mode_weights?.manual_override || payload.past_match_weight_profiles?.high || normalizeWeights(defaults.matching.mode_weights.manual_override)),
      },
    },
    emergency: {
      ...defaults.emergency,
      ...controlCenter.emergency,
      actions: {
        ...defaults.emergency.actions,
        ...(controlCenter.emergency?.actions || {}),
      },
    },
    notifications: {
      ...defaults.notifications,
      ...controlCenter.notifications,
      channels: {
        ...defaults.notifications.channels,
        ...(controlCenter.notifications?.channels || {}),
      },
      quiet_hours: {
        ...defaults.notifications.quiet_hours,
        ...(controlCenter.notifications?.quiet_hours || {}),
      },
    },
    user_access: {
      ...defaults.user_access,
      ...controlCenter.user_access,
      role_permissions: {
        ...defaults.user_access.role_permissions,
        ...(controlCenter.user_access?.role_permissions || {}),
      },
    },
    audit: { ...defaults.audit, ...(controlCenter.audit || {}) },
    analytics: { ...defaults.analytics, ...(controlCenter.analytics || {}) },
    performance: { ...defaults.performance, ...(controlCenter.performance || {}) },
    blood_request_rules: { ...defaults.blood_request_rules, ...(controlCenter.blood_request_rules || {}) },
    geolocation: { ...defaults.geolocation, ...(controlCenter.geolocation || {}) },
    fail_safe: {
      ...defaults.fail_safe,
      ...(controlCenter.fail_safe || {}),
      last_backup_timestamp: controlCenter.fail_safe?.last_backup_timestamp ? toLocalDateTime(controlCenter.fail_safe.last_backup_timestamp) : '',
    },
  };

  form.value = hydrated;
  metadata.value = {
    updated_at: payload.updated_at || null,
    updated_by_name: payload.updated_by_name || null,
    updated_by: payload.updated_by || null,
  };
  initialSnapshot.value = serializeForm(form.value);
}

function serializeForm(value) {
  return JSON.stringify(value);
}

function toLocalDateTime(value) {
  const date = new Date(value);

  if (Number.isNaN(date.getTime())) return '';

  const pad = (segment) => String(segment).padStart(2, '0');

  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function notificationRuleLabel(value) {
  if (value === 'critical-only') return 'High-score donors only';
  if (value === 'broadcast-all') return 'Broadcast all donors';
  if (value === 'emergency-active') return 'Emergency broadcast posture';
  return 'Balanced routing';
}

function formatModeLabel(mode) {
  if (mode === 'manual_override') return 'Manual Override';
  if (mode === 'emergency') return 'Emergency';
  return 'Normal';
}

function resetSection(section) {
  const defaults = defaultForm();
  form.value[section] = defaults[section];
}

function validateForm() {
  const issues = [];

  if (!enabledChannelCount.value) {
    issues.push('At least one notification channel must remain enabled.');
  }

  if (form.value.notifications.quiet_hours.enabled && form.value.notifications.quiet_hours.start === form.value.notifications.quiet_hours.end) {
    issues.push('Quiet hours start and end must not be identical.');
  }

  ['normal', 'emergency', 'manual_override'].forEach((mode) => {
    const total = Object.values(form.value.matching.mode_weights[mode]).reduce((sum, value) => sum + Number(value || 0), 0);
    if (total <= 0) {
      issues.push(`${formatModeLabel(mode)} mode weights must be greater than zero.`);
    }
  });

  if (form.value.matching.max_search_radius_km < form.value.geolocation.default_search_radius_km) {
    issues.push('Matching max search radius should not be lower than the default geolocation search radius.');
  }

  const ipEntries = form.value.user_access.ip_whitelisting
    .split(/\r?\n|,/)
    .map((entry) => entry.trim())
    .filter(Boolean);

  const invalidIpEntry = ipEntries.find((entry) => !/^((\d{1,3}\.){3}\d{1,3})(\/\d{1,2})?$/.test(entry));
  if (invalidIpEntry) {
    issues.push(`Invalid IP whitelist entry: ${invalidIpEntry}`);
  }

  return issues;
}

async function loadSettings() {
  error.value = '';
  message.value = '';

  try {
    const response = await api.get('/admin/settings');
    hydrateForm(response.data?.data || {});
  } catch {
    error.value = 'Unable to load settings.';
  }
}

function requestSave() {
  error.value = '';
  message.value = '';

  const issues = validateForm();
  if (issues.length) {
    error.value = issues[0];
    return;
  }

  if (criticalChanges.value.length) {
    showConfirmModal.value = true;
    return;
  }

  void saveSettings();
}

function confirmSave() {
  showConfirmModal.value = false;
  void saveSettings();
}

async function saveSettings() {
  saving.value = true;
  error.value = '';
  message.value = '';

  try {
    const response = await api.patch('/admin/settings', buildPayload());
    hydrateForm(response.data?.data || {});
    message.value = response.data?.message || 'System settings saved successfully.';
  } catch {
    error.value = 'Unable to save settings.';
  } finally {
    saving.value = false;
  }
}

async function setEmergencyBroadcast(enabled, trigger = null) {
  error.value = '';
  message.value = '';

  if (enabled && trigger === 'regional-broadcast') {
    broadcasting.value = true;
  } else {
    activating.value = true;
  }

  try {
    await api.patch('/admin/emergency-mode', {
      enabled,
      trigger,
    });
    message.value = enabled ? 'Emergency mode command applied.' : 'Emergency mode deactivated.';
  } catch {
    error.value = enabled ? 'Unable to apply emergency mode.' : 'Unable to deactivate emergency mode.';
  } finally {
    activating.value = false;
    broadcasting.value = false;
  }
}

onMounted(loadSettings);
</script>
