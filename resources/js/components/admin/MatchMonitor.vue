<template>
  <AdminPageFrame
    kicker="Decision Intelligence Layer"
    title="PAST-Match Monitoring"
    description="Real-time donor ranking, escalation tracking, and admin control for a transparent emergency matching engine."
    badge="Algorithm decision visibility"
  >
    <template #actions>
      <div class="flex w-full flex-col gap-3 xl:min-w-[38rem] xl:max-w-[42rem] sm:flex-row sm:items-center sm:justify-end">
          <div class="relative min-w-0 flex-1 sm:min-w-[18rem]">
            <label class="mb-1 block text-[11px] font-bold uppercase tracking-[0.18em] text-gray-500">Request Selector</label>
            <button type="button" class="selector-button" @click="selectorOpen = !selectorOpen">
              <span class="truncate text-left">{{ selectedRequestOption?.label || 'Select a recent request' }}</span>
              <span class="text-xs text-gray-400">{{ selectorOpen ? 'Close' : 'Browse' }}</span>
            </button>

            <div v-if="selectorOpen" class="absolute left-0 right-0 top-[calc(100%+0.5rem)] z-30 overflow-hidden rounded-3xl border border-gray-200 bg-white shadow-2xl">
              <div class="border-b border-gray-100 p-3">
                <input v-model="selectorSearch" type="text" placeholder="Search by case, hospital, blood type, or status" class="selector-input" />
              </div>
              <div v-if="loadingOptions" class="space-y-2 p-3">
                <div v-for="item in 4" :key="item" class="h-12 animate-pulse rounded-2xl bg-gray-100"></div>
              </div>
              <div v-else-if="selectorError" class="p-4 text-sm text-red-600">
                <p><strong>Error:</strong> {{ selectorError }}</p>
                <p class="mt-2 text-xs">requestOptions.length = {{ requestOptions.length }}</p>
              </div>
              <div v-else-if="requestOptions.length === 0" class="p-4 text-sm text-gray-500">
                <p><strong>No recent requests found.</strong></p>
                <p class="mt-2 text-xs text-gray-400">Backend returned 0 options. Check network tab in dev tools.</p>
              </div>
              <div v-else class="max-h-80 overflow-y-auto p-2">
                <button v-for="option in requestOptions" :key="option.id" type="button" class="selector-option" :class="selectedRequestId === option.id ? 'bg-red-50 ring-1 ring-red-200' : ''" @click="selectRequest(option)">
                  <div class="flex items-start justify-between gap-3">
                    <div>
                      <p class="font-semibold text-gray-900">{{ option.case_id || `Request #${option.id}` }}</p>
                      <p class="mt-1 text-sm text-gray-500">{{ option.hospital_name }} · {{ option.blood_type }} · {{ option.status }}</p>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide" :class="urgencyBadge(option.urgency_level)">{{ option.urgency_level || 'medium' }}</span>
                  </div>
                </button>
              </div>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            <div class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-xs font-semibold text-gray-600">
              Last updated {{ formatDateTime(dashboard?.meta?.last_updated) }}
            </div>
            <div class="rounded-2xl border px-4 py-3 text-xs font-bold uppercase tracking-[0.18em]" :class="syncStatusClass">
              {{ syncStatusLabel }}
            </div>
            <button type="button" class="rounded-2xl border border-gray-200 bg-white px-4 py-3 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="!selectedRequestId || loadingAnalysis" @click="loadAnalysis(false)">Refresh</button>
            <button type="button" class="inline-flex items-center gap-2 rounded-2xl border px-4 py-3 text-sm font-semibold transition" :class="autoRefresh ? 'border-red-200 bg-red-50 text-red-700' : 'border-gray-200 bg-white text-gray-700'" @click="autoRefresh = !autoRefresh">
              <span class="toggle-indicator" :class="autoRefresh ? 'bg-red-500' : 'bg-gray-300'"></span>
              {{ autoRefresh ? `Polling every ${refreshCountdown}s` : 'Polling off' }}
            </button>
          </div>
      </div>
    </template>

    <div v-if="error" class="rounded-3xl border border-red-200 bg-red-50 p-5 text-sm text-red-700 shadow-sm">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <p class="font-semibold text-red-800">Unable to load PAST-Match monitoring data.</p>
          <p class="mt-1">{{ error }}</p>
        </div>
        <button type="button" class="rounded-2xl bg-red-600 px-4 py-2 font-semibold text-white transition hover:bg-red-700" @click="loadAnalysis(false)">Retry</button>
      </div>
    </div>

    <div v-if="(dashboard?.notification_health || notificationHealth) && !(dashboard?.notification_health?.ready ?? notificationHealth?.ready)" class="rounded-3xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900 shadow-sm mb-6">
      <div class="flex flex-col gap-2">
        <p class="font-semibold">Notification transport not configured</p>
        <p>{{ (dashboard?.notification_health || notificationHealth)?.warnings?.join(' ') }}</p>
      </div>
    </div>

    <div v-if="!selectedRequestId && !loadingAnalysis" class="rounded-[2rem] border border-dashed border-gray-300 bg-white p-16 text-center shadow-sm">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-3xl bg-red-50 text-red-600">
        <svg viewBox="0 0 24 24" class="h-8 w-8 fill-none stroke-current" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" /></svg>
      </div>
      <h3 class="mt-5 text-xl font-bold text-gray-950">Select a request to analyze PAST-Match</h3>
      <p class="mt-2 text-sm text-gray-500">Select a request to analyze how the PAST-Match algorithm ranks donors, prioritizes urgency, and adapts through escalation strategies in real time.</p>
    </div>

    <template v-else>
      <div v-if="loadingAnalysis && !dashboard" class="space-y-6">
        <div class="h-44 animate-pulse rounded-[2rem] bg-gray-100"></div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
          <div v-for="card in 5" :key="card" class="h-28 animate-pulse rounded-[2rem] bg-gray-100"></div>
        </div>
        <div class="h-96 animate-pulse rounded-[2rem] bg-gray-100"></div>
      </div>

      <template v-else-if="dashboard">
        <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.2fr_0.8fr]">
          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">Request Context Panel</p>
                <h3 class="mt-2 text-2xl font-black tracking-tight text-gray-950">{{ requestContext.request_id }}</h3>
                <p class="mt-2 text-sm text-gray-600">{{ requestContext.hospital_name }} · {{ requestContext.hospital_location || 'Unknown location' }}</p>
              </div>
              <div class="flex flex-col items-end gap-2">
                <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" :class="urgencyBadge(requestContext.urgency_level)">{{ requestContext.urgency_level }}</span>
                <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" :class="matchingStatusClass(requestContext.matching_status)">{{ requestContext.matching_status }}</span>
              </div>
            </div>

            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
              <div class="summary-tile"><span class="summary-label">Blood Type + Component</span><span class="summary-value">{{ requestContext.blood_type }} · {{ requestContext.component }}</span></div>
              <div class="summary-tile"><span class="summary-label">Time Remaining</span><span class="summary-value">{{ countdownLabel }}</span></div>
              <div class="summary-tile"><span class="summary-label">Matching Phase</span><span class="summary-value">{{ matchingState.phase_label }}</span></div>
            </div>

            <div class="mt-6 rounded-3xl border border-gray-200 bg-gray-50 p-5">
              <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                  <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Units Required vs Fulfilled</p>
                  <p class="mt-2 text-lg font-black text-gray-950">{{ requestContext.fulfilled_units }} / {{ requestContext.units_required }} units</p>
                </div>
                <p class="text-sm font-semibold text-gray-600">{{ requestContext.units_completion_percentage.toFixed(2) }}%</p>
              </div>
              <div class="mt-4 h-4 overflow-hidden rounded-full bg-white">
                <div class="h-full rounded-full bg-gradient-to-r from-red-500 via-amber-500 to-emerald-500" :style="{ width: `${requestContext.units_completion_percentage}%` }"></div>
              </div>
            </div>
          </div>

          <div class="rounded-[2rem] border border-gray-200 bg-gray-950 p-6 text-white shadow-sm">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-red-300">Formula Visibility</p>
            <h3 class="mt-2 text-xl font-black">PAST-Match Audit Formula</h3>
            <p class="mt-3 text-sm text-gray-300">{{ dashboard.formula.note }}</p>
            <div class="mt-5 rounded-3xl border border-white/10 bg-white/5 p-4 font-mono text-sm leading-7 text-red-50">
              Total Score =
              <br />
              (0.25 × priority) +
              <br />
              (0.20 × availability) +
              <br />
              (0.25 × distance) +
              <br />
              (0.30 × time)
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
          <div v-for="card in realTimeCards" :key="card.key" class="rounded-[2rem] border p-5 shadow-sm" :class="card.shellClass">
            <p class="text-sm font-semibold text-gray-700">{{ card.label }}</p>
            <p class="mt-4 text-3xl font-black tracking-tight text-gray-950">{{ card.value }}</p>
          </div>
        </div>

        <div class="rounded-[2rem] border border-gray-200 bg-white p-3 shadow-sm">
          <div class="flex flex-wrap gap-2">
            <button v-for="tab in tabs" :key="tab.id" type="button" class="rounded-2xl px-4 py-3 text-sm font-semibold transition" :class="activeTab === tab.id ? 'bg-gray-950 text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'" @click="activeTab = tab.id">
              {{ tab.label }}
            </button>
          </div>
        </div>

        <div v-if="activeTab === 'overview'" class="grid grid-cols-1 gap-6 xl:grid-cols-[1.05fr_0.95fr]">
          <div class="space-y-6">
            <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">Real-Time Matching Status</p>
                  <h3 class="mt-2 text-xl font-black text-gray-950">System flow state</h3>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" :class="matchingStatusClass(matchingState.matching_status)">{{ matchingState.matching_status }}</span>
              </div>

              <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-4">
                <div v-for="phase in flowPhases" :key="phase.label" class="rounded-3xl border p-4" :class="phase.active ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-gray-50'">
                  <p class="text-xs font-black uppercase tracking-wide" :class="phase.active ? 'text-red-700' : 'text-gray-400'">Phase</p>
                  <p class="mt-2 font-semibold text-gray-950">{{ phase.label }}</p>
                </div>
              </div>
            </div>

            <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">Escalation Timeline</p>
                  <h3 class="mt-2 text-xl font-black text-gray-950">Decision log over time</h3>
                </div>
                <span class="rounded-full bg-gray-950 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-white">Level {{ dashboard.escalation.current_level }}</span>
              </div>

              <div class="mt-6 space-y-4">
                <div v-for="(entry, index) in dashboard.escalation_timeline" :key="`${entry.timestamp}-${index}`" class="timeline-entry">
                  <div class="timeline-dot"></div>
                  <div class="rounded-3xl border border-gray-200 bg-gray-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                      <p class="font-semibold text-gray-950">{{ entry.action_taken }}</p>
                      <span class="text-xs font-bold uppercase tracking-wide text-gray-500">{{ entry.timestamp ? formatDateTime(entry.timestamp) : 'Pending' }}</span>
                    </div>
                    <p class="mt-2 text-sm text-gray-600">Trigger condition: {{ entry.trigger_condition }}</p>
                    <p class="mt-2 text-xs font-semibold text-gray-500">Radius {{ entry.radius_km }} km · T+{{ entry.offset_minutes ?? 0 }} min</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="space-y-6">
            <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
              <div class="flex items-center justify-between gap-4">
                <div>
                  <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">System Controls</p>
                  <h3 class="mt-2 text-xl font-black text-gray-950">Admin override panel</h3>
                </div>
                <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]" :class="dashboard.controls.notifications_paused ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700'">{{ dashboard.controls.notifications_paused ? 'Notifications Paused' : 'Notifications Active' }}</span>
              </div>

              <div class="mt-5 space-y-4">
                <label class="block">
                  <span class="mb-1 block text-xs font-bold uppercase tracking-wide text-gray-500">Manual Radius Expansion (km)</span>
                  <div class="flex gap-2">
                    <input v-model.number="radiusInput" type="number" min="1" max="500" class="control-input" />
                    <button type="button" class="control-button bg-red-600 text-white hover:bg-red-700" :disabled="controlLoading" @click="runControl('expand_radius')">Expand Radius</button>
                  </div>
                </label>

                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                  <button type="button" class="control-button border-gray-200 bg-white text-gray-700 hover:bg-gray-50" :disabled="controlLoading" @click="runControl('rerun_matching')">Re-run Matching</button>
                  <button type="button" class="control-button border-red-200 bg-red-50 text-red-700 hover:bg-red-100" :disabled="controlLoading" @click="runControl('trigger_emergency_mode')">Trigger Emergency Mode</button>
                  <button v-if="!dashboard.controls.notifications_paused" type="button" class="control-button border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100" :disabled="controlLoading" @click="runControl('pause_notifications')">Pause Notifications</button>
                  <button v-else type="button" class="control-button border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100" :disabled="controlLoading" @click="runControl('resume_notifications')">Resume Notifications</button>
                </div>
              </div>
            </div>

            <div class="rounded-[2rem] border border-gray-200 bg-gray-950 p-6 text-white shadow-sm">
              <p class="text-xs font-black uppercase tracking-[0.2em] text-red-300">Transparency Notes</p>
              <h3 class="mt-2 text-xl font-black">Why this dashboard exists</h3>
              <ul class="mt-4 space-y-3 text-sm text-gray-200">
                <li>The algorithm is observable rather than opaque.</li>
                <li>Every escalation is timestamped and justified.</li>
                <li>Admins can intervene without losing the audit trail.</li>
              </ul>
            </div>
          </div>
        </div>

        <div v-else-if="activeTab === 'ranking'" class="overflow-hidden rounded-[2rem] border border-gray-200 bg-white shadow-sm">
          <div class="border-b border-gray-100 px-6 py-5">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">Donor Ranking Table</p>
            <h3 class="mt-2 text-xl font-black text-gray-950">Core engine view with expandable audit rows</h3>
          </div>
          <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-gray-500">Rank</th>
                  <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-gray-500">Donor Name / ID</th>
                  <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-gray-500">Blood Type Compatibility</th>
                  <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-gray-500">Distance (km)</th>
                  <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-gray-500">Last Donation (Eligibility)</th>
                  <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-gray-500">Availability Status</th>
                  <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-gray-500">Reliability Score</th>
                  <th class="px-4 py-3 text-left text-xs font-black uppercase tracking-wide text-gray-500">Response Status</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 bg-white">
                <template v-for="donor in dashboard.ranked_donors" :key="donor.donor_id">
                  <tr class="cursor-pointer transition hover:bg-gray-50" :class="expandedDonorId === donor.donor_id ? 'bg-red-50' : ''" @click="toggleExpanded(donor.donor_id)">
                    <td class="px-4 py-4 font-black text-gray-950">#{{ donor.rank }}</td>
                    <td class="px-4 py-4">
                      <p class="font-semibold text-gray-950">{{ donor.donor_name }}</p>
                      <p class="mt-1 text-xs text-gray-500">ID #{{ donor.donor_id }}</p>
                    </td>
                    <td class="px-4 py-4 text-gray-700">{{ donor.blood_type_compatibility }}</td>
                    <td class="px-4 py-4 text-gray-700">{{ donor.distance_km ?? 'N/A' }}</td>
                    <td class="px-4 py-4 text-gray-700">
                      <p>{{ donor.last_donation_date || 'No record' }}</p>
                      <p class="mt-1 text-xs text-gray-500">{{ donor.eligibility_label }}</p>
                    </td>
                    <td class="px-4 py-4 text-gray-700">{{ donor.availability_status }}</td>
                    <td class="px-4 py-4 text-gray-700">{{ donor.reliability_score.toFixed(2) }}</td>
                    <td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold uppercase tracking-wide" :class="responseStatusClass(donor.response_status)">{{ donor.response_status }}</span></td>
                  </tr>
                  <tr v-if="expandedDonorId === donor.donor_id" class="bg-gray-50">
                    <td colspan="8" class="px-6 py-6">
                      <div class="grid grid-cols-1 gap-6 xl:grid-cols-[0.85fr_1.15fr]">
                        <div>
                          <p class="text-xs font-black uppercase tracking-[0.18em] text-gray-500">Score Breakdown</p>
                          <div class="mt-4 space-y-4">
                            <div v-for="item in donorBreakdownItems(donor)" :key="item.key" class="space-y-2">
                              <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-semibold text-gray-700" :title="item.explanation">{{ item.label }} <span class="text-gray-400">({{ Math.round(item.weight * 100) }}%)</span></span>
                                <span class="font-black text-gray-950">{{ item.value.toFixed(2) }}</span>
                              </div>
                              <div class="h-3 overflow-hidden rounded-full bg-white">
                                <div class="h-full rounded-full" :class="item.barClass" :style="{ width: `${Math.min(item.value, 100)}%` }"></div>
                              </div>
                              <p class="text-xs text-gray-500">{{ item.explanation }}</p>
                            </div>
                          </div>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-6">
                          <div class="signal-card"><span class="signal-label">ETA</span><span class="signal-value">{{ donor.metrics.estimated_travel_minutes }} min</span></div>
                          <div class="signal-card"><span class="signal-label">Traffic</span><span class="signal-value">{{ donor.metrics.traffic_condition }}</span></div>
                          <div class="signal-card"><span class="signal-label">Accessibility</span><span class="signal-value">{{ donor.metrics.transport_accessibility_score.toFixed(2) }}</span></div>
                          <div class="signal-card"><span class="signal-label">Base Score</span><span class="signal-value">{{ donor.compatibility_score.toFixed(2) }}</span></div>
                          <div class="signal-card"><span class="signal-label">Emergency Adj</span><span class="signal-value">{{ donor.emergency_adjustment.toFixed(2) }}</span></div>
                          <div class="signal-card"><span class="signal-label">Cooldown Penalty</span><span class="signal-value">{{ (donor.cooldown_penalty ?? 0).toFixed(2) }}</span></div>
                          <div class="signal-card"><span class="signal-label">Location</span><span class="signal-value">{{ donor.metrics.location_source }} · {{ donor.metrics.location_confidence.toFixed(0) }}%</span></div>
                        </div>
                      </div>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <div v-else-if="activeTab === 'analytics'" class="grid grid-cols-1 gap-6 xl:grid-cols-2">
          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm xl:col-span-2">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">Visual Analytics</p>
            <h3 class="mt-2 text-xl font-black text-gray-950">Response rate, donor engagement, and matching efficiency</h3>
          </div>

          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
            <h4 class="text-lg font-black text-gray-950">Response Rate Chart</h4>
            <div class="mt-4 h-80"><canvas ref="responseRateCanvas"></canvas></div>
          </div>

          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
            <h4 class="text-lg font-black text-gray-950">Donor Engagement Graph</h4>
            <div class="mt-4 h-80"><canvas ref="engagementCanvas"></canvas></div>
          </div>

          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm xl:col-span-2">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h4 class="text-lg font-black text-gray-950">Matching Efficiency</h4>
                <p class="mt-1 text-sm text-gray-500">Measures how efficiently the system is converting matching activity into fulfilled units.</p>
              </div>
              <p class="text-sm font-semibold text-gray-600">{{ dashboard.analytics.matching_efficiency.fulfilled_percentage.toFixed(2) }}%</p>
            </div>
            <div class="mt-4 grid grid-cols-1 gap-6 xl:grid-cols-[0.7fr_1.3fr]">
              <div class="h-72"><canvas ref="efficiencyCanvas"></canvas></div>
              <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="signal-card"><span class="signal-label">Fulfilled</span><span class="signal-value">{{ dashboard.analytics.matching_efficiency.fulfilled_percentage.toFixed(2) }}%</span></div>
                <div class="signal-card"><span class="signal-label">Response Rate</span><span class="signal-value">{{ dashboard.analytics.matching_efficiency.response_rate_percentage.toFixed(2) }}%</span></div>
                <div class="signal-card"><span class="signal-label">Accepted vs Required</span><span class="signal-value">{{ dashboard.analytics.matching_efficiency.accepted_vs_required_percentage.toFixed(2) }}%</span></div>
              </div>
            </div>
          </div>
        </div>

        <div v-else-if="activeTab === 'logs'" class="grid grid-cols-1 gap-6 xl:grid-cols-[0.85fr_1.15fr]">
          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
            <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">Process Timeline</p>
            <h3 class="mt-2 text-xl font-black text-gray-950">Matching stage durations</h3>
            <div class="mt-5 space-y-3">
              <div v-for="stage in dashboard.timeline" :key="stage.key" class="rounded-3xl border p-4" :class="stage.delayed ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-gray-50'">
                <div class="flex items-center justify-between gap-4">
                  <p class="font-semibold text-gray-950">{{ stage.label }}</p>
                  <span class="text-xs font-bold uppercase tracking-wide" :class="stage.completed ? 'text-emerald-700' : 'text-gray-400'">{{ stage.completed ? 'Complete' : 'Pending' }}</span>
                </div>
                <p class="mt-2 text-sm text-gray-500">{{ stage.timestamp ? formatDateTime(stage.timestamp) : 'No event recorded yet' }}</p>
                <p class="mt-2 text-xs font-semibold" :class="stage.delayed ? 'text-red-600' : 'text-gray-500'">Offset {{ stage.offset_minutes ?? 'N/A' }} min · Delay {{ stage.delay_minutes }} min</p>
              </div>
            </div>
          </div>

          <div class="rounded-[2rem] border border-gray-200 bg-white p-6 shadow-sm">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-black uppercase tracking-[0.2em] text-gray-500">Real-Time Activity Log</p>
                <h3 class="mt-2 text-xl font-black text-gray-950">Operational event stream</h3>
              </div>
              <span class="rounded-full bg-gray-950 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-white">{{ dashboard.activity_feed.length }} events</span>
            </div>
            <div class="mt-5 space-y-3">
              <div v-for="(entry, index) in dashboard.activity_feed" :key="`${entry.timestamp}-${index}`" class="flex gap-4 rounded-3xl border border-gray-200 bg-gray-50 p-4">
                <div class="mt-1 h-3 w-3 flex-none rounded-full" :class="feedDotClass(entry.type)"></div>
                <div>
                  <p class="text-sm font-semibold text-gray-900">{{ entry.message }}</p>
                  <p class="mt-1 text-xs text-gray-500">{{ formatDateTime(entry.timestamp) }} · {{ entry.type }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </template>

    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-4 opacity-0" leave-active-class="transition duration-150 ease-in" leave-to-class="translate-y-4 opacity-0">
      <div v-if="toast.message" class="fixed bottom-6 right-6 z-[70] rounded-2xl px-4 py-3 text-sm font-semibold text-white shadow-xl" :class="toast.type === 'error' ? 'bg-red-600' : 'bg-gray-900'">
        {{ toast.message }}
      </div>
    </Transition>
  </AdminPageFrame>
</template>

<script setup>
import Chart from 'chart.js/auto';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import api from '../../lib/api';
import AdminPageFrame from './AdminPageFrame.vue';

const requestOptions = ref([]);
const selectedRequestId = ref(null);
const selectedRequestOption = ref(null);
const dashboard = ref(null);
const notificationHealth = ref(null);
const loadingOptions = ref(false);
const loadingAnalysis = ref(false);
const selectorOpen = ref(false);
const selectorSearch = ref('');
const selectorError = ref('');
const error = ref('');
const autoRefresh = ref(true);
const refreshCountdown = ref(20);
const requestCountdown = ref(null);
const activeTab = ref('overview');
const expandedDonorId = ref(null);
const radiusInput = ref(50);
const controlLoading = ref(false);
const toast = ref({ message: '', type: 'success' });

const responseRateCanvas = ref(null);
const engagementCanvas = ref(null);
const efficiencyCanvas = ref(null);

let selectorDebounceHandle = null;
let autoRefreshHandle = null;
let refreshCountdownHandle = null;
let requestCountdownHandle = null;
let toastHandle = null;
let responseRateChart = null;
let engagementChart = null;
let efficiencyChart = null;

const tabs = [
  { id: 'overview', label: 'Overview' },
  { id: 'ranking', label: 'Ranking' },
  { id: 'analytics', label: 'Analytics' },
  { id: 'logs', label: 'Logs' },
];

const requestContext = computed(() => dashboard.value?.request || {});
const matchingState = computed(() => dashboard.value?.matching_state || {
  phase_label: 'Initial Matching',
  matching_status: 'Pending',
  active_radius_km: 0,
  total_donors_notified: 0,
  response_rate_percentage: 0,
  accepted_donors: 0,
  required_units: 0,
  fulfilled_units: 0,
  notifications_paused: false,
  sync_status: 'standby',
});

const realTimeCards = computed(() => [
  { key: 'phase', label: 'Matching Phase', value: matchingState.value.phase_label, shellClass: 'border-blue-200 bg-blue-50' },
  { key: 'radius', label: 'Active Radius', value: `${matchingState.value.active_radius_km} km`, shellClass: 'border-amber-200 bg-amber-50' },
  { key: 'notified', label: 'Total Donors Notified', value: matchingState.value.total_donors_notified, shellClass: 'border-red-200 bg-red-50' },
  { key: 'response', label: 'Response Rate', value: `${matchingState.value.response_rate_percentage.toFixed(2)}%`, shellClass: 'border-emerald-200 bg-emerald-50' },
  { key: 'accepted', label: 'Accepted vs Required', value: `${matchingState.value.accepted_donors}/${matchingState.value.required_units}`, shellClass: 'border-gray-200 bg-gray-50' },
]);

const countdownLabel = computed(() => {
  if (requestCountdown.value === null || requestCountdown.value === undefined) return 'No expiry timer';
  const total = Math.max(0, requestCountdown.value);
  const hours = Math.floor(total / 3600);
  const minutes = Math.floor((total % 3600) / 60);
  const seconds = total % 60;
  return `${hours}h ${minutes}m ${seconds}s`;
});

const syncStatusLabel = computed(() => {
  if (loadingAnalysis.value) return 'Syncing';
  if (!dashboard.value) return 'Standby';
  if (dashboard.value.meta?.sync_status === 'notifications-paused') return 'Notifications Paused';
  return 'Live Polling';
});

const syncStatusClass = computed(() => {
  if (loadingAnalysis.value) return 'border-blue-200 bg-blue-50 text-blue-700';
  if (dashboard.value?.meta?.sync_status === 'notifications-paused') return 'border-amber-200 bg-amber-50 text-amber-700';
  return 'border-emerald-200 bg-emerald-50 text-emerald-700';
});

const flowPhases = computed(() => {
  const current = matchingState.value.phase_label || '';
  return [
    { label: 'Initial Matching', active: current === 'Initial Matching' },
    { label: 'Waiting for Response', active: current === 'Waiting for Response' },
    { label: 'Escalation Phase 1', active: current === 'Escalation Phase 1' },
    { label: 'Escalation Phase 2', active: current === 'Escalation Phase 2' },
    { label: 'Escalation Phase 3', active: current === 'Escalation Phase 3' },
  ];
});

const loadRequestOptions = async (search = '') => {
  loadingOptions.value = true;
  selectorError.value = '';
  try {
    console.log('🔍 [DEBUG] Starting API call to /admin/past-match/requests');
    
    // Debug auth
    const authSession = JSON.parse(localStorage.getItem('smartblood.auth') || 'null');
    console.log('🔐 [AUTH] Token in storage:', authSession?.token ? 'YES (exists)' : 'NO (missing)');
    
    const response = await api.get('/admin/past-match/requests', { params: { search, limit: 20 } });
    console.log('✅ [API SUCCESS] Full response:', response);
    console.log('📊 [API DATA] response.data:', response.data);
    console.log('📋 [API DATA] response.data?.data:', response.data?.data);
    
    requestOptions.value = response.data?.data || [];
    console.log('📌 [STATE] Request options set. Length:', requestOptions.value.length);
    console.log('📌 [STATE] Options:', requestOptions.value);
  } catch (err) {
    console.error('❌ [ERROR] Exception in loadRequestOptions:', {
      status: err?.response?.status,
      statusText: err?.response?.statusText,
      message: err?.message,
      fullError: err
    });
    selectorError.value = `Error (${err?.response?.status || 'unknown'}): ${err?.message || 'Check console'}`;
  } finally {
    loadingOptions.value = false;
  }
};

const loadNotificationHealth = async () => {
  try {
    const response = await api.get('/admin/dashboard');
    notificationHealth.value = response.data?.notification_health || response.data?.data?.notification_health || null;
  } catch {
    notificationHealth.value = null;
  }
};

const selectRequest = async (option) => {
  selectedRequestId.value = option.id;
  selectedRequestOption.value = option;
  selectorOpen.value = false;
  await loadAnalysis(false);
};

const loadAnalysis = async (silent = false) => {
  if (!selectedRequestId.value) return;
  if (!silent) loadingAnalysis.value = true;
  error.value = '';

  try {
    console.log('📡 [LOAD-ANALYSIS] Starting load for request:', selectedRequestId.value);
    const response = await api.get(`/admin/past-match/${selectedRequestId.value}`);
    console.log('✅ [LOAD-ANALYSIS] API Response received:', response);
    console.log('📊 [LOAD-ANALYSIS] response.data:', response.data);
    console.log('📋 [LOAD-ANALYSIS] response.data?.data:', response.data?.data);
    
    dashboard.value = response.data?.data || null;
    console.log('🎯 [LOAD-ANALYSIS] Dashboard state updated');
    console.log('  - ranked_donors count:', dashboard.value?.ranked_donors?.length || 0);
    console.log('  - ranked_donors:', dashboard.value?.ranked_donors);
    console.log('  - timeline length:', dashboard.value?.timeline?.length || 0);
    console.log('  - analytics:', dashboard.value?.analytics);
    
    radiusInput.value = Number(dashboard.value?.request?.distance_limit_km || 50);
    requestCountdown.value = dashboard.value?.request?.time_remaining_seconds ?? null;
    refreshCountdown.value = dashboard.value?.meta?.auto_refresh_seconds || 20;
    expandedDonorId.value = dashboard.value?.ranked_donors?.[0]?.donor_id || null;

    if (activeTab.value === 'analytics') {
      await nextTick();
      renderCharts();
    }
  } catch (err) {
    console.error('❌ [LOAD-ANALYSIS] Error:', {
      message: err?.message,
      status: err?.response?.status,
      data: err?.response?.data,
      fullError: err
    });
    error.value = 'The monitoring payload could not be retrieved for the selected request.';
  } finally {
    loadingAnalysis.value = false;
  }
};

const runControl = async (action) => {
  if (!selectedRequestId.value) return;
  controlLoading.value = true;

  try {
    const payload = { action };
    if (action === 'expand_radius') payload.radius_km = Number(radiusInput.value);
    if (action === 'trigger_emergency_mode') payload.trigger = 'PAST-Match admin override';

    const response = await api.post(`/admin/past-match/${selectedRequestId.value}/control`, payload);
    showToast(response.data?.message || 'Control action completed.');
    await loadAnalysis(true);
  } catch {
    showToast('Control action failed.', 'error');
  } finally {
    controlLoading.value = false;
  }
};

const toggleExpanded = (donorId) => {
  expandedDonorId.value = expandedDonorId.value === donorId ? null : donorId;
};

const donorBreakdownItems = (donor) => [
  { key: 'priority', label: 'Priority Score', value: donor.score_breakdown.priority.value, weight: donor.score_breakdown.priority.weight, explanation: donor.score_breakdown.priority.explanation, barClass: 'bg-red-500' },
  { key: 'availability', label: 'Availability Score', value: donor.score_breakdown.availability.value, weight: donor.score_breakdown.availability.weight, explanation: donor.score_breakdown.availability.explanation, barClass: 'bg-emerald-500' },
  { key: 'distance', label: 'Distance Score', value: donor.score_breakdown.distance.value, weight: donor.score_breakdown.distance.weight, explanation: donor.score_breakdown.distance.explanation, barClass: 'bg-amber-500' },
  { key: 'time', label: 'Time Score', value: donor.score_breakdown.time.value, weight: donor.score_breakdown.time.weight, explanation: donor.score_breakdown.time.explanation, barClass: 'bg-blue-500' },
  { key: 'final', label: 'Final Score', value: donor.score_breakdown.final.value, weight: donor.score_breakdown.final.weight, explanation: donor.score_breakdown.final.explanation, barClass: 'bg-gray-900' },
];

const urgencyBadge = (urgency) => {
  switch ((urgency || '').toLowerCase()) {
    case 'critical': return 'bg-red-100 text-red-700';
    case 'high': return 'bg-amber-100 text-amber-700';
    case 'medium': return 'bg-blue-100 text-blue-700';
    default: return 'bg-gray-100 text-gray-600';
  }
};

const matchingStatusClass = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'fulfilled': return 'bg-emerald-100 text-emerald-700';
    case 'escalated': return 'bg-amber-100 text-amber-700';
    case 'matching': return 'bg-blue-100 text-blue-700';
    default: return 'bg-gray-100 text-gray-600';
  }
};

const responseStatusClass = (status) => {
  switch ((status || '').toLowerCase()) {
    case 'accepted': return 'bg-emerald-100 text-emerald-700';
    case 'declined': return 'bg-red-100 text-red-700';
    case 'pending': return 'bg-amber-100 text-amber-700';
    default: return 'bg-gray-100 text-gray-600';
  }
};

const feedDotClass = (type) => {
  switch ((type || '').toLowerCase()) {
    case 'notification': return 'bg-amber-500';
    case 'response': return 'bg-emerald-500';
    case 'system': return 'bg-blue-500';
    default: return 'bg-red-500';
  }
};

const formatDateTime = (value) => {
  if (!value) return 'No timestamp';
  return new Date(value).toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
};

const showToast = (message, type = 'success') => {
  clearTimeout(toastHandle);
  toast.value = { message, type };
  toastHandle = setTimeout(() => {
    toast.value = { message: '', type: 'success' };
  }, 3200);
};

const destroyCharts = () => {
  [responseRateChart, engagementChart, efficiencyChart].forEach((chart) => chart?.destroy());
  responseRateChart = null;
  engagementChart = null;
  efficiencyChart = null;
};

const chartBaseOptions = () => ({
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      labels: {
        color: '#374151',
        font: { family: 'ui-sans-serif, system-ui, sans-serif', weight: '600' },
      },
    },
  },
  scales: {
    x: { ticks: { color: '#6b7280' }, grid: { color: 'rgba(229, 231, 235, 0.45)' } },
    y: { beginAtZero: true, ticks: { color: '#6b7280' }, grid: { color: 'rgba(229, 231, 235, 0.45)' } },
  },
});

const renderCharts = () => {
  destroyCharts();
  if (!dashboard.value?.analytics) return;

  if (responseRateCanvas.value) {
    responseRateChart = new Chart(responseRateCanvas.value, {
      type: 'line',
      data: {
        labels: dashboard.value.analytics.response_rate_series.map((item) => item.label),
        datasets: [{
          label: 'Response Rate %',
          data: dashboard.value.analytics.response_rate_series.map((item) => item.response_rate),
          borderColor: '#ef4444',
          backgroundColor: 'rgba(239, 68, 68, 0.18)',
          fill: true,
          tension: 0.3,
          pointRadius: 4,
        }],
      },
      options: chartBaseOptions(),
    });
  }

  if (engagementCanvas.value) {
    engagementChart = new Chart(engagementCanvas.value, {
      type: 'bar',
      data: {
        labels: dashboard.value.analytics.donor_engagement.map((item) => item.label),
        datasets: [
          { label: 'Notified', data: dashboard.value.analytics.donor_engagement.map((item) => item.notified), backgroundColor: '#f59e0b', borderRadius: 10 },
          { label: 'Responded', data: dashboard.value.analytics.donor_engagement.map((item) => item.responded), backgroundColor: '#3b82f6', borderRadius: 10 },
          { label: 'Accepted', data: dashboard.value.analytics.donor_engagement.map((item) => item.accepted), backgroundColor: '#10b981', borderRadius: 10 },
        ],
      },
      options: chartBaseOptions(),
    });
  }

  if (efficiencyCanvas.value) {
    efficiencyChart = new Chart(efficiencyCanvas.value, {
      type: 'doughnut',
      data: {
        labels: ['Fulfilled', 'Remaining'],
        datasets: [{
          data: [dashboard.value.analytics.matching_efficiency.fulfilled_percentage, Math.max(0, 100 - dashboard.value.analytics.matching_efficiency.fulfilled_percentage)],
          backgroundColor: ['#10b981', '#e5e7eb'],
          borderWidth: 0,
        }],
      },
      options: { ...chartBaseOptions(), cutout: '72%' },
    });
  }
};

const setupAutoRefresh = () => {
  clearInterval(autoRefreshHandle);
  clearInterval(refreshCountdownHandle);
  if (!autoRefresh.value) return;

  const intervalSeconds = dashboard.value?.meta?.auto_refresh_seconds || 20;
  refreshCountdown.value = intervalSeconds;

  autoRefreshHandle = setInterval(() => {
    if (selectedRequestId.value) {
      loadAnalysis(true);
    }
  }, intervalSeconds * 1000);

  refreshCountdownHandle = setInterval(() => {
    refreshCountdown.value = refreshCountdown.value > 1 ? refreshCountdown.value - 1 : intervalSeconds;
  }, 1000);
};

const setupRequestCountdown = () => {
  clearInterval(requestCountdownHandle);
  if (requestCountdown.value === null || requestCountdown.value === undefined) return;

  requestCountdownHandle = setInterval(() => {
    requestCountdown.value = requestCountdown.value > 0 ? requestCountdown.value - 1 : 0;
  }, 1000);
};

watch(selectorSearch, (value) => {
  clearTimeout(selectorDebounceHandle);
  selectorDebounceHandle = setTimeout(() => loadRequestOptions(value.trim()), 250);
});

watch(autoRefresh, setupAutoRefresh);
watch(() => dashboard.value?.meta?.auto_refresh_seconds, setupAutoRefresh);
watch(requestCountdown, setupRequestCountdown);

watch(activeTab, async (tab) => {
  if (tab === 'analytics' && dashboard.value) {
    await nextTick();
    renderCharts();
  }
});

watch(() => dashboard.value?.analytics, async () => {
  if (activeTab.value === 'analytics' && dashboard.value) {
    await nextTick();
    renderCharts();
  }
});

watch(() => requestOptions.value, async (options) => {
  // Auto-select first request when options load, but only if nothing selected yet
  if (options.length > 0 && !selectedRequestId.value) {
    const firstRequest = options[0];
    console.log('⚡ [AUTO-SELECT] Selecting first request:', firstRequest);
    selectedRequestId.value = firstRequest.id;
    selectedRequestOption.value = firstRequest;
    await loadAnalysis(false);
  }
});

watch(() => dashboard.value, (newVal) => {
  console.log('📊 [WATCH-DASHBOARD] Dashboard value changed:');
  console.log('  - Has data:', !!newVal);
  console.log('  - ranked_donors:', newVal?.ranked_donors?.length || 0);
  console.log('  - timeline:', newVal?.timeline?.length || 0);
  console.log('  - Full dashboard:', newVal);
});

watch(() => selectorOpen.value, async (isOpen) => {
  if (isOpen && requestOptions.value.length === 0) {
    console.log('📂 [WATCH] Dropdown opened, loading options...');
    await loadRequestOptions('');
  }
});

onMounted(async () => {
  await loadRequestOptions('');
  await loadNotificationHealth();
  setupAutoRefresh();
});

onUnmounted(() => {
  clearTimeout(selectorDebounceHandle);
  clearTimeout(toastHandle);
  clearInterval(autoRefreshHandle);
  clearInterval(refreshCountdownHandle);
  clearInterval(requestCountdownHandle);
  destroyCharts();
});
</script>

<style scoped>
.selector-button {
  display: flex;
  width: 100%;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  border-radius: 1.25rem;
  border: 1px solid rgb(229 231 235);
  background: white;
  padding: 0.9rem 1rem;
  font-size: 0.875rem;
  font-weight: 600;
  color: rgb(17 24 39);
  transition: 160ms ease;
}

.selector-button:hover {
  border-color: rgb(252 165 165);
}

.selector-input,
.control-input {
  width: 100%;
  border-radius: 1rem;
  border: 1px solid rgb(229 231 235);
  background: rgb(249 250 251);
  padding: 0.8rem 1rem;
  font-size: 0.875rem;
  outline: none;
}

.selector-input:focus,
.control-input:focus {
  border-color: rgb(248 113 113);
  background: white;
  box-shadow: 0 0 0 4px rgb(254 226 226);
}

.selector-option {
  display: block;
  width: 100%;
  border-radius: 1.25rem;
  padding: 0.9rem 1rem;
  text-align: left;
  transition: 160ms ease;
}

.selector-option:hover {
  background: rgb(249 250 251);
}

.toggle-indicator {
  display: inline-block;
  height: 0.75rem;
  width: 0.75rem;
  border-radius: 9999px;
}

.summary-tile,
.signal-card {
  display: flex;
  min-height: 6rem;
  flex-direction: column;
  justify-content: space-between;
  border-radius: 1.5rem;
  border: 1px solid rgb(229 231 235);
  background: rgb(249 250 251);
  padding: 1rem;
}

.summary-label,
.signal-label {
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgb(107 114 128);
}

.summary-value,
.signal-value {
  margin-top: 0.75rem;
  font-size: 0.95rem;
  font-weight: 700;
  color: rgb(17 24 39);
}

.control-button {
  border-radius: 1rem;
  border: 1px solid transparent;
  padding: 0.8rem 1rem;
  font-size: 0.875rem;
  font-weight: 700;
  transition: 150ms ease;
}

.control-button:disabled {
  cursor: not-allowed;
  opacity: 0.5;
}

.timeline-entry {
  position: relative;
  padding-left: 1.5rem;
}

.timeline-entry::before {
  position: absolute;
  left: 0.35rem;
  top: 1rem;
  bottom: -1.75rem;
  width: 2px;
  background: linear-gradient(180deg, rgba(248,113,113,0.7), rgba(229,231,235,0.6));
  content: '';
}

.timeline-entry:last-child::before {
  display: none;
}

.timeline-dot {
  position: absolute;
  left: 0;
  top: 1rem;
  height: 0.75rem;
  width: 0.75rem;
  border-radius: 9999px;
  background: rgb(239 68 68);
}
</style>
