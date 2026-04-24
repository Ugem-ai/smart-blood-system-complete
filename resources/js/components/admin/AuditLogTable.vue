<template>
  <section class="admin-page">
    <div class="relative overflow-hidden rounded-[2rem] border border-slate-200 bg-[radial-gradient(circle_at_top_left,_rgba(226,232,240,0.95),_rgba(255,255,255,0.98)_40%,_rgba(248,250,252,1)_100%)] p-6 shadow-sm">
      <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <div class="xl:col-span-8">
          <p class="text-xs font-black uppercase tracking-[0.28em] text-slate-500">Compliance Monitoring</p>
          <h2 class="mt-3 text-3xl font-black tracking-tight text-slate-950">Enterprise Audit Intelligence Console</h2>
          <p class="mt-3 max-w-3xl text-sm leading-6 text-slate-600">
            Review authentication events, protected-data access, matching operations, notifications, and privileged interventions from one contained monitoring surface.
          </p>

          <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div v-for="card in summaryCards" :key="card.key" class="rounded-[1.75rem] border p-4 shadow-sm" :class="card.shellClass">
              <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">{{ card.label }}</p>
              <p class="mt-3 text-3xl font-black tracking-tight text-slate-950">{{ card.value }}</p>
              <p class="mt-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ card.caption }}</p>
            </div>
          </div>
        </div>

        <div class="xl:col-span-4">
          <div class="rounded-[1.75rem] border border-white/80 bg-white/90 p-5 shadow-sm backdrop-blur">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">System Status</p>
                <h3 class="mt-2 text-2xl font-black text-slate-950">{{ dashboard.system_status.label }}</h3>
              </div>
              <span class="rounded-full px-3 py-1 text-xs font-black uppercase tracking-[0.18em]" :class="systemToneClass(dashboard.system_status.tone)">
                {{ dashboard.system_status.label }}
              </span>
            </div>

            <p class="mt-4 text-sm leading-6 text-slate-600">{{ dashboard.system_status.message }}</p>

            <div class="mt-5 space-y-3">
              <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Last Update</p>
                <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatDateTime(dashboard.live_updates.last_updated) }}</p>
              </div>
              <div class="rounded-3xl border border-slate-200 bg-slate-50 p-4">
                <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Live Refresh</p>
                <div class="mt-3 flex items-center justify-between gap-4">
                  <p class="text-sm font-semibold text-slate-950">{{ dashboard.live_updates.poll_interval_seconds }}s interval</p>
                  <button type="button" class="rounded-full px-3 py-1 text-xs font-black uppercase tracking-[0.16em]" :class="autoRefresh ? 'bg-emerald-600 text-white' : 'bg-slate-200 text-slate-700'" @click="toggleAutoRefresh">
                    {{ autoRefresh ? 'On' : 'Off' }}
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-if="error" class="rounded-[1.75rem] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-sm">
      {{ error }}
    </div>

    <div class="relative overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
      <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
        <label class="xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">Time Window</span>
          <select v-model="filters.range" class="filter-input">
            <option v-for="option in dashboard.filters.options.ranges" :key="option.value" :value="option.value">{{ option.label }}</option>
          </select>
        </label>

        <label class="xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">Severity</span>
          <select v-model="filters.severity" class="filter-input">
            <option value="">All severities</option>
            <option v-for="severity in dashboard.filters.options.severities" :key="severity" :value="severity">{{ severity }}</option>
          </select>
        </label>

        <label class="xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">Status</span>
          <select v-model="filters.status" class="filter-input">
            <option value="">All outcomes</option>
            <option v-for="status in dashboard.filters.options.statuses" :key="status" :value="status">{{ status }}</option>
          </select>
        </label>

        <label class="xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">Category</span>
          <select v-model="filters.category" class="filter-input">
            <option value="">All categories</option>
            <option v-for="category in dashboard.filters.options.categories" :key="category" :value="category">{{ prettyLabel(category) }}</option>
          </select>
        </label>

        <label class="xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">Actor Role</span>
          <select v-model="filters.actorRole" class="filter-input">
            <option value="">All roles</option>
            <option v-for="role in dashboard.filters.options.actor_roles" :key="role" :value="role">{{ role }}</option>
          </select>
        </label>

        <label class="xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">Action</span>
          <select v-model="filters.action" class="filter-input">
            <option value="">All actions</option>
            <option v-for="action in dashboard.filters.options.actions" :key="action" :value="action">{{ action }}</option>
          </select>
        </label>

        <label v-if="filters.range === 'custom'" class="xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">Start Date</span>
          <input v-model="filters.startDate" type="date" class="filter-input" />
        </label>

        <label v-if="filters.range === 'custom'" class="xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">End Date</span>
          <input v-model="filters.endDate" type="date" class="filter-input" />
        </label>

        <label class="xl:col-span-3">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">Search</span>
          <input v-model="filters.query" type="text" class="filter-input" placeholder="Search actor, action, target, path, or IP" @keyup.enter="applyFilters" />
        </label>

        <label class="xl:col-span-2">
          <span class="mb-2 block text-xs font-black uppercase tracking-[0.18em] text-slate-500">IP Address</span>
          <input v-model="filters.ipAddress" type="text" class="filter-input" placeholder="192.168.1.10" @keyup.enter="applyFilters" />
        </label>

        <div class="flex items-end gap-3 xl:col-span-3">
          <button type="button" class="inline-flex flex-1 items-center justify-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-50" :disabled="loading" @click="applyFilters">
            {{ loading ? 'Loading...' : 'Apply Filters' }}
          </button>
          <button type="button" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="resetFilters">
            Reset
          </button>
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
      <div class="relative col-span-12 max-w-full overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm xl:col-span-8">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">High-Priority Signals</p>
            <h3 class="mt-2 text-xl font-black text-slate-950">Risk and compliance alerts</h3>
          </div>
          <div class="flex items-center gap-2">
            <button type="button" class="rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.16em]" :class="viewMode === 'table' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600'" @click="viewMode = 'table'">
              Table
            </button>
            <button type="button" class="rounded-full px-4 py-2 text-xs font-black uppercase tracking-[0.16em]" :class="viewMode === 'timeline' ? 'bg-slate-950 text-white' : 'bg-slate-100 text-slate-600'" @click="viewMode = 'timeline'">
              Timeline
            </button>
          </div>
        </div>

        <div v-if="dashboard.high_priority_alerts.length === 0" class="mt-5 rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 p-8 text-center text-sm text-slate-500">
          No high-priority anomalies are active in the current view.
        </div>

        <div v-else class="mt-5 grid grid-cols-1 gap-3 lg:grid-cols-3">
          <article v-for="alert in dashboard.high_priority_alerts" :key="alert.id" class="rounded-[1.5rem] border p-4" :class="alertToneClass(alert.tone)">
            <p class="text-xs font-black uppercase tracking-[0.16em]">{{ prettyLabel(alert.tone) }}</p>
            <h4 class="mt-3 text-base font-black">{{ alert.title }}</h4>
            <p class="mt-2 text-sm leading-6">{{ alert.detail }}</p>
          </article>
        </div>
      </div>

      <div class="relative col-span-12 max-w-full overflow-hidden rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm xl:col-span-4">
        <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Coverage Breakdown</p>
        <h3 class="mt-2 text-xl font-black text-slate-950">Tracked activity domains</h3>

        <div class="mt-5 space-y-3">
          <div v-for="item in dashboard.summary.category_breakdown" :key="item.category" class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
            <div class="flex items-center justify-between gap-4">
              <p class="text-sm font-semibold text-slate-950">{{ prettyLabel(item.category) }}</p>
              <p class="text-sm font-black text-slate-700">{{ item.count }}</p>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-white">
              <div class="h-full rounded-full bg-slate-900" :style="{ width: `${categoryWidth(item.count)}%` }"></div>
            </div>
          </div>
        </div>
      </div>

      <div class="relative col-span-12 max-w-full overflow-hidden rounded-[2rem] border border-slate-200 bg-white shadow-sm xl:col-span-12">
        <div class="flex flex-col gap-4 border-b border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Event Stream</p>
            <h3 class="mt-2 text-xl font-black text-slate-950">{{ viewMode === 'table' ? 'Audit table view' : 'Timeline view' }}</h3>
          </div>
          <div class="flex flex-wrap items-center gap-3">
            <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" @click="loadLogs({ silent: false })">
              Refresh
            </button>
            <button type="button" class="rounded-2xl bg-slate-950 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800" @click="exportReport('json')">
              Export JSON
            </button>
            <button type="button" class="rounded-2xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700" @click="exportReport('csv')">
              Export CSV
            </button>
          </div>
        </div>

        <div v-if="viewMode === 'table'" class="max-w-full overflow-x-auto">
          <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-slate-600">
              <tr>
                <th class="px-5 py-3 text-left font-black uppercase tracking-[0.16em]">Event</th>
                <th class="px-5 py-3 text-left font-black uppercase tracking-[0.16em]">Actor</th>
                <th class="px-5 py-3 text-left font-black uppercase tracking-[0.16em]">Category</th>
                <th class="px-5 py-3 text-left font-black uppercase tracking-[0.16em]">Target</th>
                <th class="px-5 py-3 text-left font-black uppercase tracking-[0.16em]">IP / Route</th>
                <th class="px-5 py-3 text-left font-black uppercase tracking-[0.16em]">Time</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 bg-white">
              <tr v-if="loading && rows.length === 0">
                <td colspan="6" class="px-5 py-10 text-center text-slate-400">Loading audit intelligence...</td>
              </tr>
              <tr v-for="row in rows" :key="row.id" class="cursor-pointer transition hover:bg-slate-50" @click="openDetails(row)">
                <td class="px-5 py-4 align-top">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.16em]" :class="severityBadgeClass(row.severity)">{{ row.severity }}</span>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.16em]" :class="statusBadgeClass(row.status)">{{ row.status }}</span>
                  </div>
                  <p class="mt-3 text-sm font-bold text-slate-950">{{ row.title }}</p>
                  <p class="mt-1 max-w-md text-sm leading-6 text-slate-600">{{ row.description }}</p>
                  <p class="mt-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{{ row.action }}</p>
                </td>
                <td class="px-5 py-4 align-top">
                  <p class="text-sm font-semibold text-slate-950">{{ row.actor.name }}</p>
                  <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ row.actor.role }}</p>
                  <p v-if="row.actor.email" class="mt-1 text-sm text-slate-500">{{ row.actor.email }}</p>
                </td>
                <td class="px-5 py-4 align-top text-sm text-slate-700">{{ prettyLabel(row.category) }}</td>
                <td class="px-5 py-4 align-top">
                  <p class="text-sm font-semibold text-slate-950">{{ row.target.label || 'Unspecified target' }}</p>
                  <p v-if="row.target.type" class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ row.target.type }}</p>
                </td>
                <td class="px-5 py-4 align-top">
                  <p class="text-sm font-semibold text-slate-950">{{ row.ip_address || 'N/A' }}</p>
                  <p class="mt-1 text-sm text-slate-500">{{ row.path || 'No route recorded' }}</p>
                </td>
                <td class="px-5 py-4 align-top">
                  <p class="text-sm font-semibold text-slate-950">{{ formatDateTime(row.timestamp) }}</p>
                  <p class="mt-1 text-sm text-slate-500">{{ formatRelativeTime(row.timestamp) }}</p>
                </td>
              </tr>
              <tr v-if="!loading && rows.length === 0">
                <td colspan="6" class="px-5 py-10 text-center text-slate-400">No audit events matched the current filters.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div v-else class="p-5">
          <div v-if="timelineEntries.length === 0" class="rounded-[1.5rem] border border-dashed border-slate-200 bg-slate-50 p-10 text-center text-sm text-slate-500">
            No timeline events matched the current filters.
          </div>
          <div v-else class="space-y-4">
            <article v-for="entry in timelineEntries" :key="entry.id" class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-5 transition hover:border-slate-300" @click="openDetails(entryById(entry.id))">
              <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                  <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.16em]" :class="severityBadgeClass(entry.severity)">{{ entry.severity }}</span>
                    <span class="rounded-full px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.16em]" :class="statusBadgeClass(entry.status)">{{ entry.status }}</span>
                    <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-black uppercase tracking-[0.16em] text-slate-600">{{ prettyLabel(entry.category) }}</span>
                  </div>
                  <h4 class="mt-3 text-lg font-black text-slate-950">{{ entry.title }}</h4>
                  <p class="mt-2 text-sm leading-6 text-slate-600">{{ entry.description }}</p>
                  <p class="mt-3 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ entry.actor_name }} · {{ entry.target_label || 'No target specified' }}</p>
                </div>
                <div class="text-sm font-semibold text-slate-500">
                  {{ formatDateTime(entry.timestamp) }}
                </div>
              </div>
            </article>
          </div>
        </div>

        <div class="flex flex-col gap-4 border-t border-slate-200 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
          <p class="text-sm text-slate-500">
            Showing {{ rows.length }} of {{ dashboard.table_view.pagination.total }} events in the current result set.
          </p>
          <div class="flex items-center gap-3">
            <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="dashboard.table_view.pagination.current_page <= 1 || loading" @click="changePage(dashboard.table_view.pagination.current_page - 1)">
              Previous
            </button>
            <span class="text-sm font-semibold text-slate-600">
              Page {{ dashboard.table_view.pagination.current_page }} of {{ Math.max(dashboard.table_view.pagination.last_page, 1) }}
            </span>
            <button type="button" class="rounded-2xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50" :disabled="dashboard.table_view.pagination.current_page >= dashboard.table_view.pagination.last_page || loading" @click="changePage(dashboard.table_view.pagination.current_page + 1)">
              Next
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="selectedLog" class="fixed inset-0 z-40 flex items-center justify-center bg-slate-950/60 p-4 backdrop-blur-sm" @click.self="selectedLog = null">
      <div class="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-[2rem] bg-white p-6 shadow-2xl">
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Event Details</p>
            <h3 class="mt-2 text-2xl font-black text-slate-950">{{ selectedLog.title }}</h3>
          </div>
          <button type="button" class="rounded-full bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-200" @click="selectedLog = null">
            Close
          </button>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-2">
          <span class="rounded-full px-3 py-1 text-xs font-black uppercase tracking-[0.16em]" :class="severityBadgeClass(selectedLog.severity)">{{ selectedLog.severity }}</span>
          <span class="rounded-full px-3 py-1 text-xs font-black uppercase tracking-[0.16em]" :class="statusBadgeClass(selectedLog.status)">{{ selectedLog.status }}</span>
          <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-black uppercase tracking-[0.16em] text-slate-700">{{ prettyLabel(selectedLog.category) }}</span>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
          <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Actor</p>
            <p class="mt-2 text-sm font-semibold text-slate-950">{{ selectedLog.actor.name }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ selectedLog.actor.email || 'No email available' }}</p>
            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ selectedLog.actor.role }}</p>
          </div>
          <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Target</p>
            <p class="mt-2 text-sm font-semibold text-slate-950">{{ selectedLog.target.label || 'Unspecified target' }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ selectedLog.target.type || 'Unknown type' }}</p>
            <p v-if="selectedLog.target.id" class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">ID {{ selectedLog.target.id }}</p>
          </div>
          <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Network Context</p>
            <p class="mt-2 text-sm font-semibold text-slate-950">{{ selectedLog.ip_address || 'No IP recorded' }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ selectedLog.path || 'No route recorded' }}</p>
            <p class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">{{ selectedLog.method || 'N/A' }}</p>
          </div>
          <div class="rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
            <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Recorded At</p>
            <p class="mt-2 text-sm font-semibold text-slate-950">{{ formatDateTime(selectedLog.timestamp) }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ formatRelativeTime(selectedLog.timestamp) }}</p>
            <p v-if="selectedLog.http_status" class="mt-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">HTTP {{ selectedLog.http_status }}</p>
          </div>
        </div>

        <div class="mt-6 rounded-[1.5rem] border border-slate-200 bg-slate-50 p-4">
          <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-500">Description</p>
          <p class="mt-3 text-sm leading-6 text-slate-700">{{ selectedLog.description }}</p>
        </div>

        <div class="mt-6 rounded-[1.5rem] border border-slate-200 bg-slate-950 p-4 text-white">
          <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-300">Raw Metadata</p>
          <pre class="mt-3 max-h-72 overflow-auto text-xs leading-6 text-slate-100">{{ prettyJson(selectedLog.details) }}</pre>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import api from '../../lib/api';

const emptyDashboard = () => ({
  system_status: {
    label: 'Stable',
    tone: 'stable',
    message: 'No major compliance or security anomalies detected.',
    last_event_at: null,
    open_alerts_count: 0,
  },
  summary: {
    total_events: 0,
    critical_events: 0,
    failed_actions: 0,
    unauthorized_attempts: 0,
    admin_overrides: 0,
    severity_breakdown: [],
    category_breakdown: [],
  },
  filters: {
    applied: {
      range: '24h',
      start_date: '',
      end_date: '',
      severity: '',
      status: '',
      category: '',
      actor_role: '',
      action: '',
      ip_address: '',
      query: '',
    },
    options: {
      ranges: [
        { value: '24h', label: 'Last 24 hours' },
        { value: '7d', label: 'Last 7 days' },
        { value: '30d', label: 'Last 30 days' },
        { value: '90d', label: 'Last 90 days' },
        { value: 'custom', label: 'Custom range' },
      ],
      severities: ['critical', 'high', 'medium', 'low', 'info'],
      statuses: ['success', 'failed', 'warning', 'blocked'],
      categories: ['authentication', 'access', 'blood_requests', 'matching', 'notifications', 'admin', 'system', 'data_access', 'operations'],
      actor_roles: [],
      actions: [],
      ip_addresses: [],
    },
  },
  high_priority_alerts: [],
  table_view: {
    data: [],
    pagination: {
      current_page: 1,
      last_page: 1,
      per_page: 20,
      total: 0,
    },
  },
  timeline_view: [],
  export: {
    available_formats: ['json', 'csv'],
    file_name: 'audit-log-report',
    generated_at: null,
  },
  live_updates: {
    enabled: true,
    poll_interval_seconds: 20,
    last_updated: null,
  },
});

const dashboard = ref(emptyDashboard());
const loading = ref(false);
const error = ref('');
const selectedLog = ref(null);
const autoRefresh = ref(true);
const viewMode = ref('table');
const filters = ref({
  range: '24h',
  startDate: '',
  endDate: '',
  severity: '',
  status: '',
  category: '',
  actorRole: '',
  action: '',
  ipAddress: '',
  query: '',
  page: 1,
});

let refreshTimer = null;

const rows = computed(() => dashboard.value.table_view.data || []);
const timelineEntries = computed(() => dashboard.value.timeline_view || []);

const summaryCards = computed(() => ([
  {
    key: 'events',
    label: 'Total Events',
    value: dashboard.value.summary.total_events,
    caption: 'Events in current filter window',
    shellClass: 'border-slate-200 bg-white',
  },
  {
    key: 'critical',
    label: 'Critical Events',
    value: dashboard.value.summary.critical_events,
    caption: 'High-risk activity requiring review',
    shellClass: 'border-rose-200 bg-rose-50',
  },
  {
    key: 'failed',
    label: 'Failed Actions',
    value: dashboard.value.summary.failed_actions,
    caption: 'Authentication and blocked outcomes',
    shellClass: 'border-amber-200 bg-amber-50',
  },
  {
    key: 'unauthorized',
    label: 'Unauthorized Attempts',
    value: dashboard.value.summary.unauthorized_attempts,
    caption: 'Denied role or route access attempts',
    shellClass: 'border-sky-200 bg-sky-50',
  },
]));

const buildParams = (page = filters.value.page) => {
  const params = {
    range: filters.value.range,
    severity: filters.value.severity || undefined,
    status: filters.value.status || undefined,
    category: filters.value.category || undefined,
    actor_role: filters.value.actorRole || undefined,
    action: filters.value.action || undefined,
    ip_address: filters.value.ipAddress || undefined,
    query: filters.value.query || undefined,
    page,
  };

  if (filters.value.range === 'custom') {
    params.start_date = filters.value.startDate || undefined;
    params.end_date = filters.value.endDate || undefined;
  }

  return params;
};

const syncFiltersFromPayload = () => {
  const applied = dashboard.value.filters.applied;
  filters.value.range = applied.range || '24h';
  filters.value.startDate = applied.start_date || '';
  filters.value.endDate = applied.end_date || '';
  filters.value.severity = applied.severity || '';
  filters.value.status = applied.status || '';
  filters.value.category = applied.category || '';
  filters.value.actorRole = applied.actor_role || '';
  filters.value.action = applied.action || '';
  filters.value.ipAddress = applied.ip_address || '';
  filters.value.query = applied.query || '';
  filters.value.page = dashboard.value.table_view.pagination.current_page || 1;
};

const loadLogs = async ({ silent = false, page = filters.value.page } = {}) => {
  if (!silent) {
    loading.value = true;
  }

  error.value = '';

  try {
    const response = await api.get('/admin/logs', { params: buildParams(page) });
    dashboard.value = response.data?.data || emptyDashboard();
    filters.value.page = dashboard.value.table_view.pagination.current_page || 1;
    syncFiltersFromPayload();
    setupRefreshTimer();
  } catch (loadError) {
    error.value = 'Unable to load audit monitoring data right now.';
  } finally {
    loading.value = false;
  }
};

const applyFilters = () => {
  filters.value.page = 1;
  loadLogs({ silent: false, page: 1 });
};

const resetFilters = () => {
  filters.value = {
    range: '24h',
    startDate: '',
    endDate: '',
    severity: '',
    status: '',
    category: '',
    actorRole: '',
    action: '',
    ipAddress: '',
    query: '',
    page: 1,
  };
  loadLogs({ silent: false, page: 1 });
};

const changePage = (page) => {
  filters.value.page = page;
  loadLogs({ silent: false, page });
};

const toggleAutoRefresh = () => {
  autoRefresh.value = !autoRefresh.value;
  setupRefreshTimer();
};

const setupRefreshTimer = () => {
  if (refreshTimer) {
    window.clearInterval(refreshTimer);
    refreshTimer = null;
  }

  if (!autoRefresh.value || !dashboard.value.live_updates.enabled) {
    return;
  }

  const intervalMs = Math.max(10, Number(dashboard.value.live_updates.poll_interval_seconds || 20)) * 1000;
  refreshTimer = window.setInterval(() => {
    loadLogs({ silent: true, page: filters.value.page });
  }, intervalMs);
};

const exportReport = (format) => {
  const payload = rows.value;
  const fileName = `${dashboard.value.export.file_name || 'audit-log-report'}.${format}`;

  if (format === 'json') {
    downloadFile(fileName, JSON.stringify(payload, null, 2), 'application/json;charset=utf-8');
    return;
  }

  const csvHeader = ['timestamp', 'severity', 'status', 'category', 'title', 'action', 'actor_name', 'actor_role', 'target_label', 'ip_address', 'path'];
  const csvRows = payload.map((row) => [
    row.timestamp,
    row.severity,
    row.status,
    row.category,
    row.title,
    row.action,
    row.actor.name,
    row.actor.role,
    row.target.label || '',
    row.ip_address || '',
    row.path || '',
  ]);

  const csv = [csvHeader, ...csvRows]
    .map((columns) => columns.map((value) => `"${String(value ?? '').replaceAll('"', '""')}"`).join(','))
    .join('\n');

  downloadFile(fileName, csv, 'text/csv;charset=utf-8');
};

const downloadFile = (fileName, content, mimeType) => {
  const blob = new Blob([content], { type: mimeType });
  const url = URL.createObjectURL(blob);
  const anchor = document.createElement('a');
  anchor.href = url;
  anchor.download = fileName;
  anchor.click();
  URL.revokeObjectURL(url);
};

const openDetails = (row) => {
  selectedLog.value = row || null;
};

const entryById = (id) => rows.value.find((row) => row.id === id) || null;

const formatDateTime = (value) => {
  if (!value) return 'Unknown';
  return new Date(value).toLocaleString();
};

const formatRelativeTime = (value) => {
  if (!value) return 'Unknown';

  const diffMs = Date.now() - new Date(value).getTime();
  const diffMinutes = Math.round(diffMs / 60000);

  if (diffMinutes < 1) return 'Just now';
  if (diffMinutes < 60) return `${diffMinutes}m ago`;

  const diffHours = Math.round(diffMinutes / 60);
  if (diffHours < 24) return `${diffHours}h ago`;

  const diffDays = Math.round(diffHours / 24);
  return `${diffDays}d ago`;
};

const prettyLabel = (value) => {
  if (!value) return 'Unknown';
  return String(value).replaceAll('_', ' ').replaceAll('-', ' ').replace(/\b\w/g, (character) => character.toUpperCase());
};

const categoryWidth = (count) => {
  const total = Math.max(dashboard.value.summary.total_events, 1);
  return Math.max(6, Math.min(100, (count / total) * 100));
};

const systemToneClass = (tone) => {
  if (tone === 'critical') return 'bg-rose-600 text-white';
  if (tone === 'warning') return 'bg-amber-500 text-white';
  return 'bg-emerald-600 text-white';
};

const severityBadgeClass = (severity) => {
  if (severity === 'critical') return 'bg-rose-600 text-white';
  if (severity === 'high') return 'bg-rose-100 text-rose-700';
  if (severity === 'medium') return 'bg-amber-100 text-amber-700';
  if (severity === 'low') return 'bg-sky-100 text-sky-700';
  return 'bg-slate-100 text-slate-700';
};

const statusBadgeClass = (status) => {
  if (status === 'failed') return 'bg-rose-100 text-rose-700';
  if (status === 'blocked') return 'bg-amber-100 text-amber-700';
  if (status === 'warning') return 'bg-orange-100 text-orange-700';
  return 'bg-emerald-100 text-emerald-700';
};

const alertToneClass = (tone) => {
  if (tone === 'critical') return 'border-rose-200 bg-rose-50 text-rose-900';
  if (tone === 'warning') return 'border-amber-200 bg-amber-50 text-amber-900';
  return 'border-sky-200 bg-sky-50 text-sky-900';
};

const prettyJson = (value) => JSON.stringify(value || {}, null, 2);

onMounted(() => {
  loadLogs({ silent: false });
});

onUnmounted(() => {
  if (refreshTimer) {
    window.clearInterval(refreshTimer);
  }
});
</script>

<style scoped>
.filter-input {
  width: 100%;
  border-radius: 1rem;
  border: 1px solid rgb(226 232 240);
  background: rgb(248 250 252);
  padding: 0.85rem 1rem;
  font-size: 0.95rem;
  color: rgb(15 23 42);
}

.filter-input:focus {
  outline: none;
  border-color: rgb(15 23 42);
  box-shadow: 0 0 0 3px rgba(15, 23, 42, 0.08);
}
</style>
