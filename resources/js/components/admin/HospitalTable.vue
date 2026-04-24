<template>
  <AdminPageFrame
    kicker="Control Tower"
    title="Hospital Management"
    description="Monitor hospital demand, activity, and emergency readiness across the network."
    badge="Network readiness overview"
  >
    <template #actions>
        <div class="rounded-full border border-gray-200 bg-white px-3 py-1 text-xs font-semibold text-gray-600">
          Last updated {{ formatDateTime(context.last_updated) }}
        </div>
        <div class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
          Refresh in {{ refreshCountdown }}s
        </div>
        <button class="admin-button-secondary" @click="loadHospitals(currentPage)">
          Refresh
        </button>
    </template>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
      <button
        v-for="card in metricCards"
        :key="card.key"
        type="button"
        class="w-full rounded-2xl border p-5 text-left shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4"
        :class="[card.shellClass, card.focusClass, isMetricCardActive(card.key) ? card.activeClass : '']"
        :title="card.tooltip"
        :aria-label="`${card.label}: ${card.value}. ${card.tooltip}`"
        @click="applyMetricFilter(card.key)"
      >
        <div v-if="loading && !hospitals.length" class="space-y-4 animate-pulse">
          <div class="flex items-center gap-3">
            <div class="h-10 w-10 rounded-xl bg-white/70"></div>
            <div class="h-4 w-32 rounded bg-white/70"></div>
          </div>
          <div class="h-9 w-24 rounded bg-white/70"></div>
          <div class="h-3 w-36 rounded bg-white/70"></div>
        </div>
        <div v-else class="space-y-4">
          <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/80 p-2 shadow-sm ring-1 ring-white/70 text-2xl leading-none opacity-90" :class="card.iconShellClass">{{ card.icon }}</div>
            <div class="min-w-0">
              <p class="text-sm font-semibold leading-tight text-gray-800">{{ card.label }}</p>
            </div>
          </div>
          <div>
            <p class="text-4xl font-black tracking-tight text-gray-950">{{ card.value }}</p>
            <p class="mt-1 text-xs font-medium uppercase tracking-[0.16em] text-gray-500">{{ card.detail }}</p>
          </div>
        </div>
      </button>
    </div>

    <div class="admin-panel">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h3 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-500">Hospital Status Overview</h3>
          <p class="mt-1 text-sm text-gray-500">Immediate visual awareness of operational demand across the network.</p>
        </div>
        <div class="flex flex-wrap gap-2 text-sm">
          <span class="inline-flex items-center gap-2 rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-700">🟢 Active</span>
          <span class="inline-flex items-center gap-2 rounded-full bg-amber-100 px-3 py-1 font-semibold text-amber-700">🟡 Idle</span>
          <span class="inline-flex items-center gap-2 rounded-full bg-red-100 px-3 py-1 font-semibold text-red-700">🔴 Critical</span>
        </div>
      </div>
    </div>

    <div class="admin-panel">
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h3 class="text-sm font-semibold uppercase tracking-[0.22em] text-gray-500">Advanced Filter Panel</h3>
            <p class="mt-1 text-sm text-gray-500">Filter hospitals by live operational demand and emergency posture.</p>
          </div>
          <button v-if="hasActiveFilters" class="self-start rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100" @click="resetFilters">
            Reset filters
          </button>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-5">
          <label class="block xl:col-span-2">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Search</span>
            <input v-model="filters.search" type="text" placeholder="Search hospital name" class="filter-field" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Status</span>
            <select v-model="filters.status" class="filter-field">
              <option value="">All</option>
              <option value="active">Active</option>
              <option value="idle">Idle</option>
              <option value="critical">Critical</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Location</span>
            <input v-model="filters.location" type="text" placeholder="City or region" class="filter-field" />
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Blood Demand</span>
            <select v-model="filters.bloodDemand" class="filter-field">
              <option value="">All blood types</option>
              <option v-for="bloodType in bloodTypes" :key="bloodType" :value="bloodType">{{ bloodType }}</option>
            </select>
          </label>
          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Request Urgency</span>
            <select v-model="filters.requestUrgency" class="filter-field">
              <option value="">All urgencies</option>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
          </label>
        </div>
      </div>
    </div>

    <div v-if="loadError" class="rounded-2xl border border-red-200 bg-red-50 p-6 shadow-sm">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 class="text-base font-semibold text-red-800">Unable to load hospitals</h3>
          <p class="mt-1 text-sm text-red-700">The control tower could not refresh operational hospital data.</p>
        </div>
        <button class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700" @click="loadHospitals(currentPage)">Retry</button>
      </div>
    </div>

    <div class="admin-surface">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Hospital Name</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Location</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Status Badge</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Active Requests Count</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Critical Requests Count</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Avg Response Time</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Last Activity Timestamp</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-gray-500">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 bg-white">
            <template v-if="loading && !hospitals.length">
              <tr v-for="row in 8" :key="row" class="animate-pulse">
                <td v-for="cell in 8" :key="cell" class="px-4 py-4"><div class="h-4 rounded bg-gray-100"></div></td>
              </tr>
            </template>

            <tr v-else-if="!hospitals.length">
              <td colspan="8" class="px-6 py-16 text-center">
                <div class="mx-auto max-w-md">
                  <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                    <svg viewBox="0 0 24 24" class="h-7 w-7 fill-none stroke-current" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19h16M5 19V9l7-4 7 4v10M9 13h6" /></svg>
                  </div>
                  <h3 class="mt-4 text-lg font-semibold text-gray-900">No hospitals found</h3>
                  <p class="mt-2 text-sm text-gray-500">Add hospital to begin monitoring</p>
                </div>
              </td>
            </tr>

            <tr v-for="hospital in hospitals" v-else :key="hospital.id" class="transition hover:bg-gray-50" :class="rowHighlightClass(hospital)">
              <td class="px-4 py-4 align-top">
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <p class="font-semibold text-gray-900">{{ hospital.name }}</p>
                    <span v-if="hospital.disabled" class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-gray-500">Disabled</span>
                    <span v-if="highlightState.changedIds.includes(hospital.id)" class="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-blue-700">Updated</span>
                  </div>
                  <p class="mt-1 text-xs text-gray-500">Demand: {{ (hospital.blood_types_needed || []).join(', ') || 'No active demand' }}</p>
                </div>
              </td>
              <td class="px-4 py-4 align-top text-sm text-gray-700">{{ hospital.location || 'Unknown location' }}</td>
              <td class="px-4 py-4 align-top">
                <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide" :class="statusBadgeClass(hospital.operational_status)">
                  {{ statusIcon(hospital.operational_status) }} {{ hospital.operational_status }}
                </span>
              </td>
              <td class="px-4 py-4 align-top text-sm font-semibold text-gray-900">{{ hospital.active_requests_count }}</td>
              <td class="px-4 py-4 align-top text-sm font-semibold" :class="hospital.critical_requests_count > 0 ? 'text-red-700' : 'text-gray-900'">{{ hospital.critical_requests_count }}</td>
              <td class="px-4 py-4 align-top text-sm text-gray-700">{{ hospital.avg_response_time }} min</td>
              <td class="px-4 py-4 align-top text-sm text-gray-700">{{ formatDateTime(hospital.last_activity) }}</td>
              <td class="px-4 py-4 align-top">
                <div class="flex flex-wrap justify-end gap-2">
                  <button class="action-button border-gray-200 text-gray-700 hover:bg-gray-50" @click="openDetails(hospital)">View Details</button>
                  <button class="action-button border-blue-200 text-blue-700 hover:bg-blue-50" @click="openRequests(hospital)">View Requests</button>
                  <button class="action-button border-amber-200 text-amber-700 hover:bg-amber-50" @click="openAction('alert', hospital)">Send Alert</button>
                  <button class="action-button" :class="hospital.disabled ? 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' : 'border-red-200 text-red-700 hover:bg-red-50'" @click="openAction('toggle', hospital)">
                    {{ hospital.disabled ? 'Enable Hospital' : 'Disable Hospital' }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-500">Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} hospitals</p>
        <div class="flex items-center gap-2">
          <button class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40" :disabled="currentPage <= 1 || loading" @click="loadHospitals(currentPage - 1)">Prev</button>
          <span class="text-sm font-semibold text-gray-700">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</span>
          <button class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40" :disabled="currentPage >= pagination.lastPage || loading" @click="loadHospitals(currentPage + 1)">Next</button>
        </div>
      </div>
    </div>

    <div v-if="modal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeModal">
      <div class="max-h-[90vh] w-full max-w-5xl overflow-y-auto rounded-3xl bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-gray-100 px-6 py-5">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-red-500">{{ modal.mode === 'requests' ? 'Hospital Requests' : 'Hospital Details' }}</p>
            <h3 class="mt-1 text-2xl font-black tracking-tight text-gray-950">{{ modal.title }}</h3>
          </div>
          <button class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="closeModal">Close</button>
        </div>

        <div v-if="modal.loading" class="space-y-6 p-6 animate-pulse">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div v-for="item in 6" :key="item" class="h-24 rounded-2xl bg-gray-100"></div>
          </div>
          <div class="h-72 rounded-2xl bg-gray-100"></div>
        </div>

        <div v-else-if="modal.mode === 'details' && modal.data" class="space-y-6 p-6">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Operational Status</p>
              <p class="mt-2 text-lg font-black text-gray-950">{{ modal.data.operational_status }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reliability Score</p>
              <p class="mt-2 text-2xl font-black text-gray-950">{{ modal.data.system_intelligence?.reliability_score }}%</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Successful Matches</p>
              <p class="mt-2 text-2xl font-black text-emerald-700">{{ modal.data.activity_metrics?.successful_matches || 0 }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Failed Matches</p>
              <p class="mt-2 text-2xl font-black text-red-700">{{ modal.data.activity_metrics?.failed_matches || 0 }}</p>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="space-y-6">
              <div class="rounded-2xl border border-gray-200 p-5">
                <h4 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-500">General Info</h4>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Name</dt><dd class="mt-1 text-sm font-semibold text-gray-900">{{ modal.data.name }}</dd></div>
                  <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Address</dt><dd class="mt-1 text-sm text-gray-700">{{ modal.data.address }}</dd></div>
                  <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Contact Person</dt><dd class="mt-1 text-sm text-gray-700">{{ modal.data.contact_person }}</dd></div>
                  <div><dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Phone / Email</dt><dd class="mt-1 text-sm text-gray-700">{{ modal.data.phone }} / {{ modal.data.email }}</dd></div>
                </dl>
              </div>

              <div class="rounded-2xl border border-gray-200 p-5">
                <h4 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-500">Activity Metrics</h4>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                  <div class="rounded-2xl bg-blue-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Total Requests Made</p><p class="mt-2 text-2xl font-black text-blue-900">{{ modal.data.activity_metrics?.total_requests || 0 }}</p></div>
                  <div class="rounded-2xl bg-emerald-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Successful Matches</p><p class="mt-2 text-2xl font-black text-emerald-900">{{ modal.data.activity_metrics?.successful_matches || 0 }}</p></div>
                  <div class="rounded-2xl bg-red-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-red-700">Failed Matches</p><p class="mt-2 text-2xl font-black text-red-900">{{ modal.data.activity_metrics?.failed_matches || 0 }}</p></div>
                  <div class="rounded-2xl bg-gray-100 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-gray-700">Avg Matching Time</p><p class="mt-2 text-2xl font-black text-gray-900">{{ modal.data.activity_metrics?.avg_response_time || 0 }}m</p></div>
                </div>
              </div>

              <div class="rounded-2xl border border-gray-200 p-5">
                <h4 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-500">Real-Time Data</h4>
                <div class="mt-4 space-y-4">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current Active Requests</p>
                    <div v-if="modal.data.real_time_data?.current_active_requests?.length" class="mt-2 space-y-2">
                      <div v-for="request in modal.data.real_time_data.current_active_requests" :key="request.id" class="rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                          <span class="font-semibold text-gray-900">{{ request.blood_type }} · {{ request.units_required }} units</span>
                          <span class="rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide" :class="request.urgency_level === 'critical' ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700'">{{ request.urgency_level }}</span>
                        </div>
                        <p class="mt-1 text-xs text-gray-500">{{ request.status }} · {{ request.city }}{{ request.province ? `, ${request.province}` : '' }}</p>
                      </div>
                    </div>
                    <p v-else class="mt-2 text-sm text-gray-500">No active requests.</p>
                  </div>
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Blood Types Needed</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                      <span v-for="bloodType in modal.data.real_time_data?.blood_types_needed || []" :key="bloodType" class="rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700">{{ bloodType }}</span>
                      <span v-if="!(modal.data.real_time_data?.blood_types_needed || []).length" class="text-sm text-gray-500">No current blood demand</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <div class="space-y-6">
              <div class="rounded-2xl border border-gray-200 p-5">
                <h4 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-500">System Intelligence</h4>
                <div class="mt-4 space-y-4">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reliability Score</p>
                    <p class="mt-2 text-3xl font-black text-gray-950">{{ modal.data.system_intelligence?.reliability_score }}%</p>
                  </div>
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Flags</p>
                    <div v-if="modal.data.system_intelligence?.flags?.length" class="mt-2 flex flex-wrap gap-2">
                      <span v-for="flag in modal.data.system_intelligence.flags" :key="flag" class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">{{ flag }}</span>
                    </div>
                    <p v-else class="mt-2 text-sm text-emerald-700">No critical flags detected</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-else-if="modal.mode === 'requests' && modal.data" class="space-y-6 p-6">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-2xl bg-amber-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Pending Requests</p><p class="mt-2 text-2xl font-black text-amber-900">{{ modal.data.pending_requests?.length || 0 }}</p></div>
            <div class="rounded-2xl bg-emerald-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Completed Requests</p><p class="mt-2 text-2xl font-black text-emerald-900">{{ modal.data.completed_requests?.length || 0 }}</p></div>
            <div class="rounded-2xl bg-red-50 p-4"><p class="text-xs font-semibold uppercase tracking-wide text-red-700">Failed Requests</p><p class="mt-2 text-2xl font-black text-red-900">{{ modal.data.failed_requests?.length || 0 }}</p></div>
          </div>
          <div class="overflow-hidden rounded-2xl border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Case</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Blood Type</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Urgency</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Status</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Units</th>
                  <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Updated</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100 bg-white">
                <tr v-for="request in modal.data.all_requests || []" :key="request.id">
                  <td class="px-4 py-3 text-xs font-mono text-gray-600">{{ request.case_id || `REQ-${request.id}` }}</td>
                  <td class="px-4 py-3 font-semibold text-gray-900">{{ request.blood_type }}</td>
                  <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide" :class="request.urgency_level === 'critical' ? 'bg-red-100 text-red-700' : request.urgency_level === 'high' ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600'">{{ request.urgency_level }}</span></td>
                  <td class="px-4 py-3 text-gray-700">{{ request.status }}</td>
                  <td class="px-4 py-3 text-gray-700">{{ request.units_required }}</td>
                  <td class="px-4 py-3 text-gray-700">{{ formatDateTime(request.updated_at) }}</td>
                </tr>
                <tr v-if="!(modal.data.all_requests || []).length"><td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No requests available.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>

    <div v-if="confirmModal.open" class="fixed inset-0 z-[60] flex items-center justify-center bg-black/50 p-4" @click.self="closeAction">
      <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-red-500">Confirm Action</p>
        <h3 class="mt-2 text-xl font-black tracking-tight text-gray-950">{{ confirmModal.title }}</h3>
        <p class="mt-3 text-sm text-gray-600">{{ confirmModal.message }}</p>
        <div class="mt-6 flex justify-end gap-3">
          <button class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="closeAction">Cancel</button>
          <button class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-50" :disabled="confirmModal.loading" @click="confirmAction">
            {{ confirmModal.loading ? 'Processing...' : 'Confirm' }}
          </button>
        </div>
      </div>
    </div>

    <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-4 opacity-0" leave-active-class="transition duration-150 ease-in" leave-to-class="translate-y-4 opacity-0">
      <div v-if="toast.message" class="fixed bottom-6 right-6 z-[70] rounded-2xl px-4 py-3 text-sm font-semibold text-white shadow-xl" :class="toast.type === 'error' ? 'bg-red-600' : 'bg-gray-900'">
        {{ toast.message }}
      </div>
    </Transition>
  </AdminPageFrame>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import api from '../../lib/api';
import AdminPageFrame from './AdminPageFrame.vue';

const hospitals = ref([]);
const loading = ref(false);
const loadError = ref(false);
const currentPage = ref(1);
const pagination = ref({ currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const summary = ref({ total_hospitals: 0, active_hospitals: 0, critical_requests: 0, avg_response_time: 0 });
const context = ref({ refresh_interval_seconds: 45, last_updated: null });
const filters = ref({ search: '', status: '', location: '', bloodDemand: '', requestUrgency: '' });
const modal = ref({ open: false, mode: 'details', loading: false, title: '', data: null, hospitalId: null });
const confirmModal = ref({ open: false, loading: false, action: '', hospital: null, title: '', message: '' });
const toast = ref({ message: '', type: 'success' });
const refreshCountdown = ref(45);
const highlightState = ref({ changedIds: [], criticalIds: [] });
const previousSnapshot = ref(new Map());

const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

let pollingHandle = null;
let countdownHandle = null;
let toastHandle = null;
let filterDebounce = null;
let suppressFilterWatch = false;

const hasActiveFilters = computed(() => Object.values(filters.value).some((value) => `${value}`.trim() !== ''));

const metricCards = computed(() => [
  {
    key: 'total',
    label: 'Total Hospitals',
    value: summary.value.total_hospitals,
    detail: 'Registered institutions',
    tooltip: 'View the full hospital network enrolled in the platform.',
    shellClass: 'border-blue-200 bg-blue-50',
    focusClass: 'focus:ring-blue-200/80',
    activeClass: 'border-blue-300 ring-2 ring-blue-200 shadow-md',
    iconShellClass: 'text-blue-700',
    icon: iconMarkup('building'),
  },
  {
    key: 'active',
    label: 'Active Hospitals',
    value: summary.value.active_hospitals,
    detail: 'Operationally engaged',
    tooltip: 'Active hospitals are currently engaged in live coordination and blood-demand operations.',
    shellClass: 'border-emerald-200 bg-emerald-50',
    focusClass: 'focus:ring-emerald-200/80',
    activeClass: 'border-emerald-300 ring-2 ring-emerald-200 shadow-md',
    iconShellClass: 'text-emerald-700',
    icon: iconMarkup('pulse'),
  },
  {
    key: 'critical',
    label: 'Critical Requests',
    value: summary.value.critical_requests,
    detail: 'Escalated demand cases',
    tooltip: 'Critical requests indicate urgent blood demand requiring immediate coordination attention.',
    shellClass: 'border-red-200 bg-red-50',
    focusClass: 'focus:ring-red-200/80',
    activeClass: 'border-red-300 ring-2 ring-red-200 shadow-md',
    iconShellClass: 'text-red-700',
    icon: iconMarkup('alert'),
  },
  {
    key: 'avg',
    label: 'Avg Response Time',
    value: `${summary.value.avg_response_time} min`,
    detail: 'Coordination speed',
    tooltip: 'Average response time reflects how quickly hospital activity is acknowledged across the network.',
    shellClass: 'border-amber-200 bg-amber-50',
    focusClass: 'focus:ring-amber-200/80',
    activeClass: 'border-amber-300 ring-2 ring-amber-200 shadow-md',
    iconShellClass: 'text-amber-700',
    icon: iconMarkup('clock'),
  },
]);

const iconMarkup = (name) => {
  const icons = {
    building: '🏥',
    pulse: '🟢',
    alert: '🚨',
    clock: '⏱️',
  };

  return icons[name] || '•';
};

const isMetricCardActive = (cardKey) => {
  if (cardKey === 'active') return filters.value.status === 'active' && !filters.value.requestUrgency;
  if (cardKey === 'critical') return filters.value.status === 'critical' && filters.value.requestUrgency === 'critical';
  if (cardKey === 'avg') return filters.value.requestUrgency === 'high' && !filters.value.status;
  return !filters.value.status && !filters.value.requestUrgency;
};

const applyMetricFilter = async (cardKey) => {
  suppressFilterWatch = true;
  filters.value = {
    ...filters.value,
    status: cardKey === 'active' ? 'active' : cardKey === 'critical' ? 'critical' : '',
    requestUrgency: cardKey === 'critical' ? 'critical' : cardKey === 'avg' ? 'high' : '',
  };
  suppressFilterWatch = false;
  currentPage.value = 1;
  await loadHospitals(1);
};

const filterParams = () => {
  const params = { page: currentPage.value, per_page: 10 };
  if (filters.value.search) params.search = filters.value.search;
  if (filters.value.status) params.status = filters.value.status;
  if (filters.value.location) params.location = filters.value.location;
  if (filters.value.bloodDemand) params.blood_demand = filters.value.bloodDemand;
  if (filters.value.requestUrgency) params.request_urgency = filters.value.requestUrgency;
  return params;
};

const captureHighlights = (rows) => {
  const changedIds = [];
  const criticalIds = [];

  rows.forEach((hospital) => {
    const previous = previousSnapshot.value.get(hospital.id);
    if (previous && (previous.operational_status !== hospital.operational_status || previous.critical_requests_count !== hospital.critical_requests_count)) {
      changedIds.push(hospital.id);
    }
    if (!previous && hospital.critical_requests_count > 0) {
      criticalIds.push(hospital.id);
    }
    if (previous && previous.critical_requests_count < hospital.critical_requests_count && hospital.critical_requests_count > 0) {
      criticalIds.push(hospital.id);
    }
  });

  previousSnapshot.value = new Map(rows.map((hospital) => [hospital.id, { operational_status: hospital.operational_status, critical_requests_count: hospital.critical_requests_count }]));
  highlightState.value = { changedIds, criticalIds };

  if (changedIds.length || criticalIds.length) {
    window.setTimeout(() => {
      highlightState.value = { changedIds: [], criticalIds: [] };
    }, 8000);
  }
};

const loadHospitals = async (page = 1) => {
  loading.value = true;
  loadError.value = false;
  currentPage.value = page;

  try {
    const response = await api.get('/admin/hospitals', { params: filterParams() });
    const payload = response.data?.success !== undefined ? response.data.data : response.data;
    const rows = Array.isArray(payload?.data) ? payload.data : [];

    hospitals.value = rows;
    captureHighlights(rows);
    summary.value = payload?.summary || summary.value;
    context.value = payload?.context || context.value;
    pagination.value = {
      currentPage: payload?.current_page ?? 1,
      lastPage: payload?.last_page ?? 1,
      total: payload?.total ?? rows.length,
      from: payload?.from ?? (rows.length ? 1 : 0),
      to: payload?.to ?? rows.length,
    };
    refreshCountdown.value = context.value.refresh_interval_seconds || 45;
  } catch (error) {
    loadError.value = true;
  } finally {
    loading.value = false;
  }
};

const openDetails = async (hospital) => {
  modal.value = { open: true, mode: 'details', loading: true, title: hospital.name, data: null, hospitalId: hospital.id };
  try {
    const response = await api.get(`/admin/hospitals/${hospital.id}`);
    modal.value.data = response.data?.data || null;
  } finally {
    modal.value.loading = false;
  }
};

const openRequests = async (hospital) => {
  modal.value = { open: true, mode: 'requests', loading: true, title: `${hospital.name} Requests`, data: null, hospitalId: hospital.id };
  try {
    const response = await api.get(`/admin/hospitals/${hospital.id}/requests`);
    modal.value.data = response.data?.data || null;
  } finally {
    modal.value.loading = false;
  }
};

const closeModal = () => {
  modal.value = { open: false, mode: 'details', loading: false, title: '', data: null, hospitalId: null };
};

const openAction = (action, hospital) => {
  confirmModal.value = {
    open: true,
    loading: false,
    action,
    hospital,
    title: action === 'alert' ? 'Send Alert' : hospital.disabled ? 'Enable Hospital' : 'Disable Hospital',
    message: action === 'alert'
      ? `Send an emergency coordination alert to ${hospital.name}?`
      : `${hospital.disabled ? 'Enable' : 'Disable'} ${hospital.name} for platform operations?`,
  };
};

const closeAction = () => {
  confirmModal.value = { open: false, loading: false, action: '', hospital: null, title: '', message: '' };
};

const confirmAction = async () => {
  if (!confirmModal.value.hospital) return;

  confirmModal.value.loading = true;
  try {
    if (confirmModal.value.action === 'alert') {
      await api.post(`/admin/hospitals/${confirmModal.value.hospital.id}/alert`);
      showToast('Hospital alert sent successfully.');
    } else {
      await api.post(`/admin/hospitals/${confirmModal.value.hospital.id}/toggle-status`);
      showToast('Hospital status updated successfully.');
    }

    closeAction();
    await loadHospitals(currentPage.value);
    if (modal.value.open && modal.value.hospitalId === confirmModal.value.hospital?.id && modal.value.mode === 'details') {
      await openDetails(confirmModal.value.hospital);
    }
  } catch (error) {
    showToast('Action failed. Please try again.', 'error');
    confirmModal.value.loading = false;
  }
};

const resetFilters = async () => {
  filters.value = { search: '', status: '', location: '', bloodDemand: '', requestUrgency: '' };
  currentPage.value = 1;
  await loadHospitals(1);
};

const showToast = (message, type = 'success') => {
  clearTimeout(toastHandle);
  toast.value = { message, type };
  toastHandle = setTimeout(() => {
    toast.value = { message: '', type: 'success' };
  }, 3500);
};

const statusIcon = (status) => (status === 'critical' ? '🔴' : status === 'active' ? '🟢' : '🟡');
const statusBadgeClass = (status) => (status === 'critical' ? 'bg-red-100 text-red-700' : status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700');
const rowHighlightClass = (hospital) => {
  if (highlightState.value.criticalIds.includes(hospital.id)) return 'bg-red-50';
  if (highlightState.value.changedIds.includes(hospital.id)) return 'bg-blue-50';
  return '';
};

const formatDateTime = (value) => {
  if (!value) return 'No recent activity';
  return new Date(value).toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
};

const setupPolling = () => {
  clearInterval(pollingHandle);
  clearInterval(countdownHandle);
  const intervalSeconds = context.value.refresh_interval_seconds || 45;
  refreshCountdown.value = intervalSeconds;

  pollingHandle = setInterval(() => {
    loadHospitals(currentPage.value);
  }, intervalSeconds * 1000);

  countdownHandle = setInterval(() => {
    refreshCountdown.value = refreshCountdown.value > 1 ? refreshCountdown.value - 1 : intervalSeconds;
  }, 1000);
};

watch(
  () => [filters.value.status, filters.value.bloodDemand, filters.value.requestUrgency],
  async () => {
    if (suppressFilterWatch) return;
    currentPage.value = 1;
    await loadHospitals(1);
  },
);

watch(
  () => [filters.value.search, filters.value.location],
  () => {
    if (suppressFilterWatch) return;
    clearTimeout(filterDebounce);
    filterDebounce = setTimeout(async () => {
      currentPage.value = 1;
      await loadHospitals(1);
    }, 350);
  },
);

watch(() => context.value.refresh_interval_seconds, () => {
  setupPolling();
});

onMounted(async () => {
  await loadHospitals(1);
  setupPolling();
});

onUnmounted(() => {
  clearInterval(pollingHandle);
  clearInterval(countdownHandle);
  clearTimeout(toastHandle);
  clearTimeout(filterDebounce);
});
</script>

<style scoped>
.filter-field {
  width: 100%;
  border-radius: 0.75rem;
  border: 1px solid rgb(229 231 235);
  background: rgb(249 250 251);
  padding: 0.625rem 1rem;
  font-size: 0.875rem;
  outline: none;
  transition: 150ms ease;
}

.filter-field:focus {
  border-color: rgb(248 113 113);
  background: white;
  box-shadow: 0 0 0 4px rgb(254 226 226);
}

.action-button {
  border-radius: 0.75rem;
  border-width: 1px;
  padding: 0.5rem 0.75rem;
  font-size: 0.75rem;
  font-weight: 600;
  transition: 150ms ease;
}
</style>
