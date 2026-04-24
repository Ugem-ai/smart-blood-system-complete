<template>
  <AdminPageFrame
    kicker="Operational Intelligence"
    title="Donor Management"
    description="Monitor, evaluate, and manage donor readiness in real time."
    badge="Live donor intelligence"
  >
    <template #actions>
        <div class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">
          Live refresh in {{ refreshCountdown }}s
        </div>
        <button
          type="button"
          class="admin-button-secondary"
          @click="loadDonors(currentPage)"
        >
          Refresh
        </button>
    </template>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
      <button
        v-for="card in metricCards"
        :key="card.key"
        type="button"
        class="w-full overflow-hidden rounded-2xl border p-5 text-left shadow-sm transition duration-200 hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-4"
        :class="[card.shellClass, card.focusClass, isMetricCardActive(card.key) ? card.activeClass : '']"
        :title="card.tooltip"
        :aria-label="`${card.label}: ${card.value}. ${card.tooltip}`"
        @click="applyMetricFilter(card.key)"
      >
        <div v-if="loading && !donors.length" class="space-y-4 animate-pulse">
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
      <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h3 class="text-sm font-semibold uppercase tracking-[0.22em] text-gray-500">Advanced Filter Panel</h3>
            <p class="mt-1 text-sm text-gray-500">Turn donor records into match-ready operational views.</p>
          </div>
          <button
            v-if="hasActiveFilters"
            type="button"
            class="self-start rounded-xl border border-red-200 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 transition hover:bg-red-100"
            @click="resetFilters"
          >
            Reset filters
          </button>
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Search</span>
            <input
              v-model="filters.search"
              type="text"
              placeholder="Search name or phone"
              class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm outline-none transition focus:border-red-400 focus:bg-white focus:ring-4 focus:ring-red-100"
            />
          </label>

          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Blood Type</span>
            <select v-model="filters.bloodType" class="filter-select">
              <option value="">All blood types</option>
              <option v-for="bloodType in bloodTypes" :key="bloodType" :value="bloodType">{{ bloodType }}</option>
            </select>
          </label>

          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Eligibility Status</span>
            <select v-model="filters.eligibility" class="filter-select">
              <option value="">All</option>
              <option value="eligible">Eligible</option>
              <option value="cooldown">Cooldown</option>
            </select>
          </label>

          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Reliability Score</span>
            <select v-model="filters.reliability" class="filter-select">
              <option value="">All</option>
              <option value="high">High</option>
              <option value="medium">Medium</option>
              <option value="low">Low</option>
            </select>
          </label>

          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Location</span>
            <input
              v-model="filters.location"
              type="text"
              placeholder="City"
              class="w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm outline-none transition focus:border-red-400 focus:bg-white focus:ring-4 focus:ring-red-100"
            />
          </label>

          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Radius</span>
            <select v-model="filters.radius" class="filter-select">
              <option value="">Any radius</option>
              <option value="10">Within 10 km</option>
              <option value="25">Within 25 km</option>
              <option value="50">Within 50 km</option>
              <option value="100">Within 100 km</option>
            </select>
          </label>

          <label class="block">
            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Availability Status</span>
            <select v-model="filters.availability" class="filter-select">
              <option value="">All</option>
              <option value="available">Available</option>
              <option value="busy">Busy</option>
              <option value="unavailable">Unavailable</option>
            </select>
          </label>

          <div class="rounded-2xl border border-dashed border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-500">
            <p class="font-semibold text-gray-700">PAST-Match context</p>
            <p class="mt-1">Current active request: {{ context.active_request_blood_type || 'None' }}{{ context.active_request_city ? ` in ${context.active_request_city}` : '' }}</p>
            <p class="mt-1">Match radius threshold: {{ context.match_distance_limit_km || 50 }} km</p>
          </div>
        </div>
      </div>
    </div>

    <div v-if="loadError" class="rounded-2xl border border-red-200 bg-red-50 p-6 shadow-sm">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h3 class="text-base font-semibold text-red-800">Unable to load donor intelligence</h3>
          <p class="mt-1 text-sm text-red-700">Check your connection or API status and try again.</p>
        </div>
        <button
          type="button"
          class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700"
          @click="loadDonors(currentPage)"
        >
          Retry
        </button>
      </div>
    </div>

    <div class="admin-surface">
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Name</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Blood Type</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Eligibility Status</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Reliability Score</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Last Donation Date</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Availability Status</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Distance</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-left text-xs font-bold uppercase tracking-wide text-gray-500">Match Ready</th>
              <th class="sticky top-0 z-10 bg-gray-50 px-4 py-3 text-right text-xs font-bold uppercase tracking-wide text-gray-500">Actions</th>
            </tr>
          </thead>

          <tbody class="divide-y divide-gray-100 bg-white">
            <template v-if="loading && !donors.length">
              <tr v-for="row in 8" :key="row" class="animate-pulse">
                <td v-for="cell in 9" :key="cell" class="px-4 py-4">
                  <div class="h-4 rounded bg-gray-100"></div>
                </td>
              </tr>
            </template>

            <tr v-else-if="!donors.length">
              <td colspan="9" class="px-6 py-16 text-center">
                <div class="mx-auto max-w-md">
                  <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-red-50 text-red-600">
                    <svg viewBox="0 0 24 24" class="h-7 w-7 fill-none stroke-current" stroke-width="1.8">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M12 3c4 3.5 6 6.9 6 10a6 6 0 1 1-12 0c0-3.1 2-6.5 6-10Z" />
                    </svg>
                  </div>
                  <h3 class="mt-4 text-lg font-semibold text-gray-900">No donors match the current operational view</h3>
                  <p class="mt-2 text-sm text-gray-500">Adjust your filters or refresh to pull the latest donor intelligence.</p>
                </div>
              </td>
            </tr>

            <tr
              v-for="donor in donors"
              v-else
              :key="donor.id"
              class="transition hover:bg-gray-50"
            >
              <td class="px-4 py-4 align-top">
                <div class="flex items-start gap-3">
                  <div class="flex h-11 w-11 flex-shrink-0 items-center justify-center rounded-2xl bg-red-50 font-bold text-red-700">
                    {{ initialsFor(donor.name) }}
                  </div>
                  <div>
                    <div class="flex flex-wrap items-center gap-2">
                      <p class="font-semibold text-gray-900">{{ donor.name }}</p>
                      <span v-if="donor.prioritized" class="rounded-full bg-blue-100 px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide text-blue-700">Priority</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">{{ donor.contact_info?.phone || 'No phone on record' }}</p>
                    <p class="mt-1 text-xs text-gray-400">{{ donor.city }}</p>
                  </div>
                </div>
              </td>

              <td class="px-4 py-4 align-top">
                <span class="inline-flex rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-700 ring-1 ring-red-200">{{ donor.blood_type }}</span>
              </td>

              <td class="px-4 py-4 align-top">
                <span
                  class="inline-flex rounded-full px-3 py-1 text-xs font-semibold"
                  :class="donor.eligibility_status?.is_eligible ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'"
                >
                  {{ donor.eligibility_status?.label }}
                </span>
              </td>

              <td class="px-4 py-4 align-top">
                <div class="space-y-1">
                  <div class="flex items-center gap-2">
                    <span class="font-semibold text-gray-900">{{ donor.reliability_score }}%</span>
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-bold uppercase tracking-wide" :class="reliabilityBadgeClass(donor.reliability_band)">
                      {{ donor.reliability_band }}
                    </span>
                  </div>
                  <div class="h-2 w-28 rounded-full bg-gray-100">
                    <div class="h-2 rounded-full" :class="reliabilityBarClass(donor.reliability_band)" :style="{ width: `${Math.min(100, donor.reliability_score || 0)}%` }"></div>
                  </div>
                </div>
              </td>

              <td class="px-4 py-4 align-top text-sm text-gray-700">{{ formatDate(donor.last_donation_date) }}</td>

              <td class="px-4 py-4 align-top">
                <span class="rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wide" :class="availabilityBadgeClass(donor.availability_status)">
                  {{ donor.availability_status }}
                </span>
              </td>

              <td class="px-4 py-4 align-top text-sm text-gray-700">{{ donor.distance == null ? 'Unknown' : `${donor.distance} km` }}</td>

              <td class="px-4 py-4 align-top">
                <span
                  class="rounded-full px-3 py-1 text-[11px] font-bold uppercase tracking-[0.16em]"
                  :class="donor.match_ready ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'"
                >
                  {{ donor.match_ready ? 'Match Ready' : 'Not Ready' }}
                </span>
              </td>

              <td class="px-4 py-4 align-top">
                <div class="flex flex-wrap justify-end gap-2">
                  <button class="action-button border-gray-200 text-gray-700 hover:bg-gray-50" @click="openProfile(donor)">View Profile</button>
                  <button class="action-button border-blue-200 text-blue-700 hover:bg-blue-50" @click="openActionModal('notify', donor)">Notify Donor</button>
                  <button class="action-button border-red-200 text-red-700 hover:bg-red-50" @click="openActionModal('suspend', donor)">Suspend Donor</button>
                  <button class="action-button border-amber-200 text-amber-700 hover:bg-amber-50" @click="openActionModal('prioritize', donor)">Prioritize Donor</button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="flex flex-col gap-3 border-t border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-gray-500">
          Showing {{ pagination.from }} to {{ pagination.to }} of {{ pagination.total }} donors
        </p>
        <div class="flex items-center gap-2">
          <button class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40" :disabled="currentPage <= 1 || loading" @click="loadDonors(currentPage - 1)">
            Prev
          </button>
          <span class="text-sm font-semibold text-gray-700">Page {{ pagination.currentPage }} of {{ pagination.lastPage }}</span>
          <button class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40" :disabled="currentPage >= pagination.lastPage || loading" @click="loadDonors(currentPage + 1)">
            Next
          </button>
        </div>
      </div>
    </div>

    <div v-if="profileModal.open" class="fixed inset-0 z-40 flex items-center justify-center bg-black/50 p-4" @click.self="closeProfileModal">
      <div class="max-h-[90vh] w-full max-w-4xl overflow-y-auto rounded-3xl bg-white shadow-2xl">
        <div class="flex items-start justify-between border-b border-gray-100 px-6 py-5">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-red-500">Donor Profile</p>
            <h3 class="mt-1 text-2xl font-black tracking-tight text-gray-950">{{ profileModal.data?.full_name || 'Loading donor profile' }}</h3>
            <p class="mt-1 text-sm text-gray-500">Full operational and performance intelligence for PAST-Match decision support.</p>
          </div>
          <button class="rounded-xl border border-gray-200 px-3 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="closeProfileModal">Close</button>
        </div>

        <div v-if="profileModal.loading" class="space-y-6 p-6 animate-pulse">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            <div v-for="block in 6" :key="block" class="h-24 rounded-2xl bg-gray-100"></div>
          </div>
          <div class="h-52 rounded-2xl bg-gray-100"></div>
        </div>

        <div v-else-if="profileModal.data" class="space-y-6 p-6">
          <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Blood Type</p>
              <p class="mt-2 text-2xl font-black text-red-700">{{ profileModal.data.blood_type }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Eligibility</p>
              <p class="mt-2 text-sm font-semibold text-gray-900">{{ profileModal.data.eligibility_status?.label }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Availability</p>
              <p class="mt-2 text-sm font-semibold text-gray-900">{{ profileModal.data.availability_status }}</p>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reliability</p>
              <p class="mt-2 text-2xl font-black text-gray-950">{{ profileModal.data.system_intelligence?.reliability_score }}%</p>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-6 xl:grid-cols-[1.2fr_0.8fr]">
            <div class="space-y-6">
              <div class="rounded-2xl border border-gray-200 p-5">
                <h4 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-500">Profile Details</h4>
                <dl class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Full Name</dt>
                    <dd class="mt-1 text-sm font-semibold text-gray-900">{{ profileModal.data.full_name }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Phone</dt>
                    <dd class="mt-1 text-sm text-gray-700">{{ profileModal.data.contact_info?.phone || 'Not available' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Email</dt>
                    <dd class="mt-1 text-sm text-gray-700">{{ profileModal.data.contact_info?.email || 'Not available' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Last Donation Date</dt>
                    <dd class="mt-1 text-sm text-gray-700">{{ formatDate(profileModal.data.last_donation_date) }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Address / Location</dt>
                    <dd class="mt-1 text-sm text-gray-700">{{ profileModal.data.address?.city || 'Unknown city' }}</dd>
                  </div>
                  <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500">Coordinates</dt>
                    <dd class="mt-1 text-sm text-gray-700">
                      {{ formatCoordinates(profileModal.data.address?.coordinates) }}
                    </dd>
                  </div>
                </dl>
              </div>

              <div class="rounded-2xl border border-gray-200 p-5">
                <h4 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-500">Performance Metrics</h4>
                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                  <div class="rounded-2xl bg-blue-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-blue-600">Total Requests Received</p>
                    <p class="mt-2 text-2xl font-black text-blue-900">{{ profileModal.data.performance_metrics?.total_requests_received || 0 }}</p>
                  </div>
                  <div class="rounded-2xl bg-emerald-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-emerald-600">Accepted Requests</p>
                    <p class="mt-2 text-2xl font-black text-emerald-900">{{ profileModal.data.performance_metrics?.accepted_requests || 0 }}</p>
                  </div>
                  <div class="rounded-2xl bg-amber-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-amber-600">Ignored Requests</p>
                    <p class="mt-2 text-2xl font-black text-amber-900">{{ profileModal.data.performance_metrics?.ignored_requests || 0 }}</p>
                  </div>
                  <div class="rounded-2xl bg-gray-100 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">Average Response Time</p>
                    <p class="mt-2 text-2xl font-black text-gray-900">{{ profileModal.data.performance_metrics?.average_response_time_minutes || 0 }}m</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="space-y-6">
              <div class="rounded-2xl border border-gray-200 p-5">
                <h4 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-500">System Intelligence</h4>
                <div class="mt-4 space-y-4">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Reliability Band</p>
                    <span class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide" :class="reliabilityBadgeClass(profileModal.data.system_intelligence?.reliability_band)">
                      {{ profileModal.data.system_intelligence?.reliability_band }}
                    </span>
                  </div>
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Match Ready</p>
                    <span class="mt-2 inline-flex rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wide" :class="profileModal.data.match_ready ? 'bg-emerald-100 text-emerald-700' : 'bg-gray-100 text-gray-500'">
                      {{ profileModal.data.match_ready ? 'Match Ready' : 'Needs Review' }}
                    </span>
                  </div>
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Risk Flags</p>
                    <div v-if="profileModal.data.system_intelligence?.risk_flags?.length" class="mt-2 flex flex-wrap gap-2">
                      <span v-for="flag in profileModal.data.system_intelligence.risk_flags" :key="flag" class="rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">{{ flag }}</span>
                    </div>
                    <p v-else class="mt-2 text-sm text-emerald-700">No active risk flags</p>
                  </div>
                </div>
              </div>

              <div class="rounded-2xl border border-gray-200 p-5">
                <h4 class="text-sm font-bold uppercase tracking-[0.18em] text-gray-500">Operational Notes</h4>
                <ul class="mt-4 space-y-2 text-sm text-gray-600">
                  <li>Availability state refreshes every {{ context.refresh_interval_seconds || 45 }} seconds.</li>
                  <li>Match Ready requires eligibility, high reliability, active availability, and distance compliance.</li>
                  <li>Prioritize Donor places the donor in the emergency shortlist for 24 hours.</li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="confirmModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeActionModal">
      <div class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-red-500">Confirm Action</p>
        <h3 class="mt-2 text-xl font-black tracking-tight text-gray-950">{{ confirmModal.title }}</h3>
        <p class="mt-3 text-sm text-gray-600">{{ confirmModal.message }}</p>
        <div class="mt-6 flex justify-end gap-3">
          <button class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="closeActionModal">Cancel</button>
          <button class="rounded-xl bg-red-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-50" :disabled="confirmModal.loading" @click="confirmAction">
            {{ confirmModal.loading ? 'Processing...' : 'Confirm' }}
          </button>
        </div>
      </div>
    </div>

    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="translate-y-4 opacity-0"
      leave-active-class="transition duration-150 ease-in"
      leave-to-class="translate-y-4 opacity-0"
    >
      <div v-if="toast.message" class="fixed bottom-6 right-6 z-[60] rounded-2xl px-4 py-3 text-sm font-semibold text-white shadow-xl" :class="toast.type === 'error' ? 'bg-red-600' : 'bg-gray-900'">
        {{ toast.message }}
      </div>
    </Transition>
  </AdminPageFrame>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import api from '../../lib/api';
import AdminPageFrame from './AdminPageFrame.vue';

const donors = ref([]);
const loading = ref(false);
const loadError = ref(false);
const currentPage = ref(1);
const pagination = ref({ currentPage: 1, lastPage: 1, total: 0, from: 0, to: 0 });
const summary = ref({ total_donors: 0, eligible_donors: 0, high_reliability_donors: 0, inactive_donors: 0 });
const context = ref({ refresh_interval_seconds: 45, match_distance_limit_km: 50, active_request_blood_type: null, active_request_city: null });
const filters = ref({
  search: '',
  bloodType: '',
  eligibility: '',
  reliability: '',
  location: '',
  radius: '',
  availability: '',
});
const profileModal = ref({ open: false, loading: false, data: null, donorId: null });
const confirmModal = ref({ open: false, loading: false, action: '', donor: null, title: '', message: '' });
const toast = ref({ message: '', type: 'success' });
const refreshCountdown = ref(45);

const bloodTypes = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

let pollingHandle = null;
let countdownHandle = null;
let toastHandle = null;
let searchDebounce = null;
let suppressFilterWatch = false;

const hasActiveFilters = computed(() => Object.values(filters.value).some((value) => `${value}`.trim() !== ''));

const metricCards = computed(() => [
  {
    key: 'total',
    label: 'Total Donors',
    value: summary.value.total_donors,
    detail: 'Registered donors',
    tooltip: 'View the full donor pool registered in the platform.',
    shellClass: 'border-blue-200 bg-blue-50',
    focusClass: 'focus:ring-blue-200/80',
    activeClass: 'border-blue-300 ring-2 ring-blue-200 shadow-md',
    iconShellClass: 'text-blue-700',
    icon: iconMarkup('users'),
  },
  {
    key: 'eligible',
    label: 'Eligible Donors',
    value: summary.value.eligible_donors,
    detail: 'Cleared for donation',
    tooltip: 'Eligible donors are those who passed screening and are within the safe donation interval.',
    shellClass: 'border-emerald-200 bg-emerald-50',
    focusClass: 'focus:ring-emerald-200/80',
    activeClass: 'border-emerald-300 ring-2 ring-emerald-200 shadow-md',
    iconShellClass: 'text-emerald-700',
    icon: iconMarkup('check'),
  },
  {
    key: 'reliable',
    label: 'High Reliability Donors',
    value: summary.value.high_reliability_donors,
    detail: 'Trusted high responders',
    tooltip: 'High reliability donors consistently respond and support emergency matching performance.',
    shellClass: 'border-amber-200 bg-amber-50',
    focusClass: 'focus:ring-amber-200/80',
    activeClass: 'border-amber-300 ring-2 ring-amber-200 shadow-md',
    iconShellClass: 'text-amber-700',
    icon: iconMarkup('star'),
  },
  {
    key: 'inactive',
    label: 'Inactive Donors',
    value: summary.value.inactive_donors,
    detail: 'Currently unavailable',
    tooltip: 'Inactive donors are currently unavailable for operational matching and need re-engagement or review.',
    shellClass: 'border-rose-200 bg-rose-50',
    focusClass: 'focus:ring-rose-200/80',
    activeClass: 'border-rose-300 ring-2 ring-rose-200 shadow-md',
    iconShellClass: 'text-rose-700',
    icon: iconMarkup('inactive'),
  },
]);

const iconMarkup = (name) => {
  const icons = {
    users: '👥',
    check: '✅',
    star: '⭐',
    inactive: '⛔',
  };

  return icons[name] || '•';
};

const isMetricCardActive = (cardKey) => {
  if (cardKey === 'eligible') return filters.value.eligibility === 'eligible' && !filters.value.reliability && !filters.value.availability;
  if (cardKey === 'reliable') return filters.value.reliability === 'high' && !filters.value.eligibility && !filters.value.availability;
  if (cardKey === 'inactive') return filters.value.availability === 'unavailable' && !filters.value.eligibility && !filters.value.reliability;
  return !filters.value.eligibility && !filters.value.reliability && !filters.value.availability;
};

const applyMetricFilter = async (cardKey) => {
  suppressFilterWatch = true;
  filters.value = {
    ...filters.value,
    eligibility: cardKey === 'eligible' ? 'eligible' : '',
    reliability: cardKey === 'reliable' ? 'high' : '',
    availability: cardKey === 'inactive' ? 'unavailable' : '',
  };
  suppressFilterWatch = false;
  currentPage.value = 1;
  await loadDonors(1);
};

const filterParams = () => {
  const params = { page: currentPage.value, per_page: 12 };

  if (filters.value.search) params.search = filters.value.search;
  if (filters.value.bloodType) params.blood_type = filters.value.bloodType;
  if (filters.value.eligibility) params.eligibility_status = filters.value.eligibility;
  if (filters.value.reliability) params.reliability_score = filters.value.reliability;
  if (filters.value.location) params.location = filters.value.location;
  if (filters.value.radius) params.radius_km = filters.value.radius;
  if (filters.value.availability) params.availability_status = filters.value.availability;

  return params;
};

const loadDonors = async (page = 1) => {
  loading.value = true;
  loadError.value = false;
  currentPage.value = page;

  try {
    const response = await api.get('/admin/donors', { params: filterParams() });
    const payload = response.data?.success !== undefined ? response.data.data : response.data;

    donors.value = Array.isArray(payload?.data) ? payload.data : [];
    pagination.value = {
      currentPage: payload?.current_page ?? 1,
      lastPage: payload?.last_page ?? 1,
      total: payload?.total ?? donors.value.length,
      from: payload?.from ?? (donors.value.length ? 1 : 0),
      to: payload?.to ?? donors.value.length,
    };
    summary.value = payload?.summary || summary.value;
    context.value = payload?.context || context.value;
    refreshCountdown.value = context.value.refresh_interval_seconds || 45;
  } catch (loadDonorsError) {
    loadError.value = true;
  } finally {
    loading.value = false;
  }
};

const fetchDonorProfile = async (donorId) => {
  profileModal.value.loading = true;

  try {
    const response = await api.get(`/admin/donors/${donorId}`);
    profileModal.value.data = response.data?.data || null;
  } finally {
    profileModal.value.loading = false;
  }
};

const openProfile = async (donor) => {
  profileModal.value = { open: true, loading: true, data: null, donorId: donor.id };
  await fetchDonorProfile(donor.id);
};

const closeProfileModal = () => {
  profileModal.value = { open: false, loading: false, data: null, donorId: null };
};

const openActionModal = (action, donor) => {
  const content = {
    notify: {
      title: 'Notify donor',
      message: `Send a readiness notification to ${donor.name}?`,
    },
    suspend: {
      title: 'Suspend donor',
      message: `Suspend ${donor.name} from the active donor pool?`,
    },
    prioritize: {
      title: 'Prioritize donor',
      message: `Prioritize ${donor.name} for emergency PAST-Match operations for the next 24 hours?`,
    },
  };

  confirmModal.value = {
    open: true,
    loading: false,
    action,
    donor,
    title: content[action].title,
    message: content[action].message,
  };
};

const closeActionModal = () => {
  confirmModal.value = { open: false, loading: false, action: '', donor: null, title: '', message: '' };
};

const confirmAction = async () => {
  if (!confirmModal.value.donor || !confirmModal.value.action) return;

  confirmModal.value.loading = true;

  try {
    const { donor, action } = confirmModal.value;
    await api.post(`/admin/donors/${donor.id}/${action}`);
    showToast(`${donor.name} ${action === 'notify' ? 'has been notified' : action === 'suspend' ? 'has been suspended' : 'has been prioritized'}.`);
    closeActionModal();
    await loadDonors(currentPage.value);

    if (profileModal.value.open && profileModal.value.donorId === donor.id) {
      await fetchDonorProfile(donor.id);
    }
  } catch (actionError) {
    showToast('Action failed. Please try again.', 'error');
    confirmModal.value.loading = false;
  }
};

const resetFilters = async () => {
  filters.value = {
    search: '',
    bloodType: '',
    eligibility: '',
    reliability: '',
    location: '',
    radius: '',
    availability: '',
  };
  currentPage.value = 1;
  await loadDonors(1);
};

const showToast = (message, type = 'success') => {
  clearTimeout(toastHandle);
  toast.value = { message, type };
  toastHandle = setTimeout(() => {
    toast.value = { message: '', type: 'success' };
  }, 3500);
};

const initialsFor = (name) => {
  return `${name || ''}`
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0])
    .join('')
    .toUpperCase();
};

const formatDate = (value) => {
  if (!value) return 'No record';
  return new Date(value).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
};

const formatCoordinates = (coordinates) => {
  if (!coordinates) return 'Coordinates unavailable';
  return `${coordinates.latitude}, ${coordinates.longitude}`;
};

const reliabilityBadgeClass = (band) => {
  if (band === 'high') return 'bg-emerald-100 text-emerald-700';
  if (band === 'medium') return 'bg-amber-100 text-amber-700';
  return 'bg-red-100 text-red-700';
};

const reliabilityBarClass = (band) => {
  if (band === 'high') return 'bg-emerald-500';
  if (band === 'medium') return 'bg-amber-500';
  return 'bg-red-500';
};

const availabilityBadgeClass = (status) => {
  if (status === 'available') return 'bg-emerald-100 text-emerald-700';
  if (status === 'busy') return 'bg-amber-100 text-amber-700';
  return 'bg-red-100 text-red-700';
};

const setupPolling = () => {
  clearInterval(pollingHandle);
  clearInterval(countdownHandle);

  const intervalSeconds = context.value.refresh_interval_seconds || 45;
  refreshCountdown.value = intervalSeconds;

  pollingHandle = setInterval(() => {
    loadDonors(currentPage.value);
  }, intervalSeconds * 1000);

  countdownHandle = setInterval(() => {
    refreshCountdown.value = refreshCountdown.value > 1 ? refreshCountdown.value - 1 : intervalSeconds;
  }, 1000);
};

watch(
  () => [filters.value.bloodType, filters.value.eligibility, filters.value.reliability, filters.value.radius, filters.value.availability],
  async () => {
    if (suppressFilterWatch) return;
    currentPage.value = 1;
    await loadDonors(1);
  },
);

watch(
  () => [filters.value.search, filters.value.location],
  () => {
    if (suppressFilterWatch) return;
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(async () => {
      currentPage.value = 1;
      await loadDonors(1);
    }, 350);
  },
);

watch(
  () => context.value.refresh_interval_seconds,
  () => {
    setupPolling();
  },
);

onMounted(async () => {
  await loadDonors(1);
  setupPolling();
});

onUnmounted(() => {
  clearInterval(pollingHandle);
  clearInterval(countdownHandle);
  clearTimeout(toastHandle);
  clearTimeout(searchDebounce);
});
</script>

<style scoped>
.filter-select {
  @apply w-full rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-sm outline-none transition focus:border-red-400 focus:bg-white focus:ring-4 focus:ring-red-100;
}

.action-button {
  @apply rounded-xl border px-3 py-2 text-xs font-semibold transition;
}
</style>
