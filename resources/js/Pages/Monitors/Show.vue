<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useRealtimeUpdates } from '@/Composables/useRealtimeUpdates'
import { useFocusTrap } from '@/Composables/useFocusTrap'
import { usePageLoading } from '@/Composables/usePageLoading'
import LatencyHeatmap from '@/Components/LatencyHeatmap.vue'
import LighthouseHistory from '@/Components/LighthouseHistory.vue'
import LighthouseScores from '@/Components/LighthouseScores.vue'
import ResponseTimeChart from '@/Components/ResponseTimeChart.vue'
import BackLink from '@/Components/BackLink.vue'
import GlassCard from '@/Components/GlassCard.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import CopyButton from '@/Components/CopyButton.vue'
import SkeletonMonitorShow from '@/Components/SkeletonMonitorShow.vue'
import IncidentTimeline from '@/Components/IncidentTimeline.vue'
import PurgeDialog from '@/Components/PurgeDialog.vue'
import FunctionalChecks from '@/Components/FunctionalChecks.vue'

useRealtimeUpdates({
    onMonitorChecked: ['monitor', 'checks', 'chartData', 'uptime', 'heatmapData', 'incidents', 'incidentStats', 'incidentTimeline'],
    onLighthouseCompleted: ['lighthouseScore', 'lighthouseHistory'],
})

const { isLoading } = usePageLoading()

interface Check { id: number; status: 'up' | 'down'; response_time_ms: number; status_code: number; checked_at: string }
interface Incident { id: number; started_at: string; resolved_at: string | null; cause: string }
interface PaginatedIncidents {
    data: Incident[]
    links: { first: string; last: string; prev: string | null; next: string | null }
    meta: { current_page: number; from: number; last_page: number; per_page: number; to: number; total: number }
}
interface IncidentStats {
    total: number
    active: number
    active_incident: { id: number; started_at: string; cause: string } | null
    mttr_minutes: number
    downtime_30d_minutes: number
}

const props = defineProps<{
    monitor: any
    checks: Check[]
    incidents: PaginatedIncidents
    incidentStats: IncidentStats
    incidentTimeline: Incident[]
    incidentSort: string
    incidentDir: string
    uptime: { day: number; week: number; month: number }
    heatmapData: Record<string, number>
    lighthouseScore: { performance: number; accessibility: number; best_practices: number; seo: number; lcp: number | null; fcp: number | null; cls: number | null; tbt: number | null; speed_index: number | null; scored_at: string } | null
    lighthouseHistory?: Array<any> | null
    chartData: Array<any>
    currentPeriod: string
    functionalChecks: Array<any>
}>()

const baseUrl = computed(() => typeof window !== 'undefined' ? window.location.origin : '')
const pauseForm = useForm({})

const showChannelModal = ref(false)
const channelModalRef = ref<HTMLElement | null>(null)
const selectedChannel = ref<{ id: number; name: string; type: string } | null>(null)
const showDeleteDialog = ref(false)
const showPurgeChecks = ref(false)
const showPurgeIncidents = ref(false)
const showPurgeLighthouse = ref(false)

useFocusTrap(channelModalRef, showChannelModal)

const monitorStatus = computed((): 'up' | 'down' | 'paused' => {
    if (!props.monitor.is_active) return 'paused'
    return props.checks[0]?.status === 'up' ? 'up' : 'down'
})

const togglePause = () => {
    if (props.monitor.is_active) {
        pauseForm.post(route('monitors.pause', props.monitor.id))
    } else {
        pauseForm.post(route('monitors.resume', props.monitor.id))
    }
}

const confirmDelete = () => {
    router.delete(route('monitors.destroy', props.monitor.id))
}

const formatDate = (d: string) => new Date(d).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })

const duration = (start: string, end: string | null): string => {
    const ms = (end ? new Date(end).getTime() : Date.now()) - new Date(start).getTime()
    const h = Math.floor(ms / 3600000), m = Math.floor((ms % 3600000) / 60000)
    return h > 24 ? `${Math.floor(h / 24)}d ${h % 24}h` : `${h}h ${m}m`
}

const formatMttr = (minutes: number): string => {
    if (minutes === 0) return '-'
    if (minutes < 60) return `${minutes}m`
    const h = Math.floor(minutes / 60), m = minutes % 60
    return m > 0 ? `${h}h ${m}m` : `${h}h`
}

const formatDowntime = (minutes: number): string => {
    if (minutes === 0) return '0m'
    if (minutes < 60) return `${minutes}m`
    const h = Math.floor(minutes / 60), m = minutes % 60
    return m > 0 ? `${h}h ${m}m` : `${h}h`
}

const avgMs = computed(() => {
    if (!props.checks.length) return 0
    return Math.round(props.checks.reduce((s, c) => s + c.response_time_ms, 0) / props.checks.length)
})

const uptimeColor = (v: number) => v > 99 ? 'text-emerald-400' : v > 95 ? 'text-yellow-400' : 'text-red-400'

const handlePeriodChange = (period: string) => {
    router.visit(route('monitors.show', props.monitor.id) + '?period=' + period, {
        only: ['chartData', 'currentPeriod'],
        preserveState: true,
        preserveScroll: true,
    })
}

const openChannelModal = (channel: { id: number; name: string; type: string }) => {
    selectedChannel.value = channel
    showChannelModal.value = true
}

const closeChannelModal = () => {
    showChannelModal.value = false
    selectedChannel.value = null
}

const badgeMarkdown = computed(() => `![Uptime](${baseUrl.value}/badge/${props.monitor.badge_hash}.svg)`)

const channelIcons: Record<string, string> = {
    email: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
    slack: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 4l4 4-4 4M6 10h.01M17 14h.01"/>',
    discord: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>',
    webhook: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>',
    telegram: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>',
    push: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>',
}

// Incident cause config
const causeConfig: Record<string, { label: string; cls: string; icon: string }> = {
    timeout: {
        label: 'Timeout',
        cls: 'text-orange-400 bg-orange-500/10 border border-orange-500/20',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    },
    status_code: {
        label: 'Status Code',
        cls: 'text-red-400 bg-red-500/10 border border-red-500/20',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>',
    },
    ssl: {
        label: 'SSL Error',
        cls: 'text-purple-400 bg-purple-500/10 border border-purple-500/20',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>',
    },
    keyword: {
        label: 'Keyword',
        cls: 'text-blue-400 bg-blue-500/10 border border-blue-500/20',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>',
    },
    error: {
        label: 'Error',
        cls: 'text-red-400 bg-red-500/10 border border-red-500/20',
        icon: '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    },
}

const getCauseConfig = (cause: string) => causeConfig[cause] ?? { label: cause.replace(/_/g, ' '), cls: 'text-slate-400 bg-slate-500/10 border border-slate-500/20', icon: '' }

// Incident sorting
const handleIncidentSort = (column: string) => {
    const newDir = props.incidentSort === column && props.incidentDir === 'desc' ? 'asc' : 'desc'
    router.visit(route('monitors.show', props.monitor.id), {
        data: { incident_sort: column, incident_dir: newDir, period: props.currentPeriod },
        only: ['incidents', 'incidentSort', 'incidentDir'],
        preserveState: true,
        preserveScroll: true,
    })
}

const sortIcon = (column: string): string => {
    if (props.incidentSort !== column) return 'pi pi-sort-alt'
    return props.incidentDir === 'asc' ? 'pi pi-sort-amount-up-alt' : 'pi pi-sort-amount-down-alt'
}

// Incident pagination
const handleIncidentPage = (page: number) => {
    router.visit(route('monitors.show', props.monitor.id), {
        data: { incident_page: page, incident_sort: props.incidentSort, incident_dir: props.incidentDir, period: props.currentPeriod },
        only: ['incidents'],
        preserveState: true,
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="monitor.name" />

    <SkeletonMonitorShow v-if="isLoading" />

    <div v-else class="space-y-6">
        <BackLink :href="route('monitors.index')" label="Back to Monitors" />

        <!-- Ongoing incident banner -->
        <Transition name="slide-down">
            <div v-if="incidentStats.active > 0 && incidentStats.active_incident" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-300">
                <span class="relative flex h-2.5 w-2.5 shrink-0">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                </span>
                <span class="font-medium text-red-200">Incident in progress</span>
                <span class="text-sm text-red-400">
                    {{ getCauseConfig(incidentStats.active_incident.cause).label }} · since {{ formatDate(incidentStats.active_incident.started_at) }}
                </span>
            </div>
        </Transition>

        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-white">{{ monitor.name }}</h1>
                    <StatusBadge :status="monitorStatus" size="md" />
                </div>
                <div class="flex items-center gap-2 mt-1">
                    <p class="text-slate-400">{{ monitor.url }}</p>
                    <CopyButton :text="monitor.url" />
                </div>
                <div v-if="monitor.notification_channels?.length" class="flex flex-wrap items-center gap-2 mt-2">
                    <button v-for="ch in monitor.notification_channels" :key="ch.id" @click="openChannelModal(ch)" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs bg-white/5 border border-white/10 text-slate-300 hover:text-white hover:bg-white/10 hover:border-white/20 transition-colors cursor-pointer" :aria-label="`View ${ch.name} channel details`">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="channelIcons[ch.type] || channelIcons.webhook" />
                        <span>{{ ch.name }}</span>
                    </button>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <Link :href="route('monitors.edit', monitor.id)" aria-label="Edit monitor settings" class="px-4 py-2 rounded-lg text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">Edit</Link>
                <button @click="togglePause" :aria-label="monitor.is_active ? 'Pause monitoring' : 'Resume monitoring'" class="px-4 py-2 rounded-lg text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">{{ monitor.is_active ? 'Pause' : 'Resume' }}</button>
                <button @click="showDeleteDialog = true" aria-label="Delete this monitor" class="px-4 py-2 rounded-lg text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 transition-colors">Delete</button>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="glass p-4">
                <p class="text-slate-400 text-sm mb-1">Status</p>
                <div class="flex items-center gap-2">
                    <div :class="['status-dot', checks[0]?.status === 'up' ? 'online' : 'offline']" />
                    <span class="text-lg font-semibold text-white">{{ checks[0]?.status?.toUpperCase() || 'UNKNOWN' }}</span>
                </div>
            </div>
            <div class="glass p-4">
                <p class="text-slate-400 text-sm mb-1">Uptime</p>
                <div class="flex items-center gap-3 font-mono text-sm">
                    <span :class="uptimeColor(uptime.day)">24h: {{ uptime.day.toFixed(1) }}%</span>
                    <span :class="uptimeColor(uptime.week)">7d: {{ uptime.week.toFixed(1) }}%</span>
                </div>
            </div>
            <div class="glass p-4">
                <p class="text-slate-400 text-sm mb-1">Avg Response</p>
                <p class="text-lg font-semibold text-white font-mono">{{ avgMs }}<span class="text-sm text-slate-400">ms</span></p>
            </div>
            <div class="glass p-4">
                <p class="text-slate-400 text-sm mb-1">Active Incidents</p>
                <p class="text-lg font-semibold font-mono" :class="incidentStats.active > 0 ? 'text-red-400' : 'text-emerald-400'">{{ incidentStats.active }}</p>
            </div>
        </div>

        <ResponseTimeChart :chart-data="chartData" :current-period="currentPeriod" :monitor-id="monitor.id" @period-change="handlePeriodChange">
            <template #actions>
                <button @click="showPurgeChecks = true" class="px-3 py-1.5 rounded-lg text-xs text-slate-400 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Purge
                </button>
            </template>
        </ResponseTimeChart>

        <GlassCard title="Latency Heatmap (12 months)">
            <LatencyHeatmap :data="heatmapData" />
        </GlassCard>

        <GlassCard title="Lighthouse Scores">
            <template #actions>
                <button @click="showPurgeLighthouse = true" class="px-3 py-1.5 rounded-lg text-xs text-slate-400 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Purge
                </button>
            </template>
            <LighthouseScores :scores="lighthouseScore" :monitor-id="monitor.id" :monitor-type="monitor.type" />
        </GlassCard>

        <GlassCard v-if="monitor.type === 'http'">
            <LighthouseHistory :history="lighthouseHistory ?? null" :monitor-id="monitor.id" />
        </GlassCard>

        <!-- Incident History -->
        <GlassCard title="Incident History">
            <template #actions>
                <button @click="showPurgeIncidents = true" class="px-3 py-1.5 rounded-lg text-xs text-slate-400 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    Purge
                </button>
            </template>
            <!-- Timeline (solution 7) -->
            <IncidentTimeline :incidents="incidentTimeline" class="mb-6" />

            <!-- Stats row (solution 3) -->
            <div class="grid grid-cols-3 gap-4 mb-6 p-4 rounded-lg bg-white/[0.03] border border-white/5">
                <div class="text-center">
                    <p class="text-2xl font-bold font-mono text-white">{{ incidentStats.total }}</p>
                    <p class="text-xs text-slate-500 mt-0.5 uppercase tracking-wider">Total incidents</p>
                </div>
                <div class="text-center border-x border-white/5">
                    <p class="text-2xl font-bold font-mono" :class="incidentStats.mttr_minutes > 0 ? 'text-yellow-400' : 'text-emerald-400'">
                        {{ formatMttr(incidentStats.mttr_minutes) }}
                    </p>
                    <p class="text-xs text-slate-500 mt-0.5 uppercase tracking-wider">Avg resolution</p>
                </div>
                <div class="text-center">
                    <p class="text-2xl font-bold font-mono" :class="incidentStats.downtime_30d_minutes > 0 ? 'text-red-400' : 'text-emerald-400'">
                        {{ formatDowntime(incidentStats.downtime_30d_minutes) }}
                    </p>
                    <p class="text-xs text-slate-500 mt-0.5 uppercase tracking-wider">Downtime (30d)</p>
                </div>
            </div>

            <!-- Table -->
            <div v-if="incidents.data.length > 0" class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-slate-400 text-sm border-b border-white/10">
                            <!-- Sortable: cause -->
                            <th class="pb-3 font-medium">
                                <button @click="handleIncidentSort('cause')" class="inline-flex items-center gap-1.5 hover:text-white transition-colors">
                                    Cause
                                    <i :class="[sortIcon('cause'), 'text-xs']" />
                                </button>
                            </th>
                            <!-- Sortable: started_at -->
                            <th class="pb-3 font-medium">
                                <button @click="handleIncidentSort('started_at')" class="inline-flex items-center gap-1.5 hover:text-white transition-colors">
                                    Started
                                    <i :class="[sortIcon('started_at'), 'text-xs']" />
                                </button>
                            </th>
                            <!-- Sortable: resolved_at -->
                            <th class="pb-3 font-medium">
                                <button @click="handleIncidentSort('resolved_at')" class="inline-flex items-center gap-1.5 hover:text-white transition-colors">
                                    Resolved
                                    <i :class="[sortIcon('resolved_at'), 'text-xs']" />
                                </button>
                            </th>
                            <th class="pb-3 font-medium">Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="incident in incidents.data" :key="incident.id" class="border-b border-white/5 last:border-0">
                            <td class="py-3">
                                <span :class="['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium', getCauseConfig(incident.cause).cls]">
                                    <svg v-if="getCauseConfig(incident.cause).icon" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="getCauseConfig(incident.cause).icon" />
                                    {{ getCauseConfig(incident.cause).label }}
                                </span>
                            </td>
                            <td class="py-3 text-slate-300 text-sm">{{ formatDate(incident.started_at) }}</td>
                            <td class="py-3 text-sm">
                                <span v-if="incident.resolved_at" class="text-slate-300">{{ formatDate(incident.resolved_at) }}</span>
                                <span v-else class="inline-flex items-center gap-1.5 text-red-400 font-medium">
                                    <span class="w-1.5 h-1.5 rounded-full bg-red-400 animate-pulse"></span>
                                    Ongoing
                                </span>
                            </td>
                            <td class="py-3 text-slate-400 text-sm font-mono">{{ duration(incident.started_at, incident.resolved_at) }}</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Pagination (solution 4) -->
                <div v-if="incidents.meta.last_page > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-white/5">
                    <p class="text-sm text-slate-500">
                        {{ incidents.meta.from }}–{{ incidents.meta.to }} of {{ incidents.meta.total }} incidents
                    </p>
                    <div class="flex items-center gap-1">
                        <button
                            @click="handleIncidentPage(incidents.meta.current_page - 1)"
                            :disabled="incidents.meta.current_page === 1"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                            aria-label="Previous page"
                        >
                            <i class="pi pi-chevron-left text-xs" />
                        </button>

                        <template v-for="page in incidents.meta.last_page" :key="page">
                            <button
                                v-if="page === 1 || page === incidents.meta.last_page || Math.abs(page - incidents.meta.current_page) <= 1"
                                @click="handleIncidentPage(page)"
                                :class="['w-8 h-8 rounded-lg text-sm transition-colors', page === incidents.meta.current_page ? 'bg-white/10 text-white font-medium' : 'text-slate-400 hover:text-white hover:bg-white/5']"
                            >
                                {{ page }}
                            </button>
                            <span v-else-if="page === incidents.meta.current_page - 2 || page === incidents.meta.current_page + 2" class="text-slate-600 px-1">…</span>
                        </template>

                        <button
                            @click="handleIncidentPage(incidents.meta.current_page + 1)"
                            :disabled="incidents.meta.current_page === incidents.meta.last_page"
                            class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 disabled:opacity-30 disabled:cursor-not-allowed transition-colors"
                            aria-label="Next page"
                        >
                            <i class="pi pi-chevron-right text-xs" />
                        </button>
                    </div>
                </div>
            </div>
            <p v-else class="text-slate-500 text-center py-8">No incidents recorded</p>
        </GlassCard>

        <!-- Functional Checks Section -->
        <GlassCard title="Functional Checks">
            <FunctionalChecks :monitor-id="monitor.id" :checks="functionalChecks" />
        </GlassCard>

        <GlassCard v-if="monitor.badge_hash" title="Status Badge">
            <div class="flex items-center gap-4">
                <img :src="`/badge/${monitor.badge_hash}.svg`" :alt="`${monitor.name} uptime badge`" />
                <code class="text-sm text-slate-400 font-mono bg-white/5 px-3 py-2 rounded">{{ badgeMarkdown }}</code>
                <CopyButton :text="badgeMarkdown" />
            </div>
        </GlassCard>
    </div>

    <PurgeDialog
        v-model:show="showPurgeChecks"
        :monitor-id="monitor.id"
        :monitor-name="monitor.name"
        target="checks"
    />
    <PurgeDialog
        v-model:show="showPurgeIncidents"
        :monitor-id="monitor.id"
        :monitor-name="monitor.name"
        target="incidents"
    />
    <PurgeDialog
        v-model:show="showPurgeLighthouse"
        :monitor-id="monitor.id"
        :monitor-name="monitor.name"
        target="lighthouse"
    />

    <ConfirmDialog
        v-model:show="showDeleteDialog"
        title="Delete Monitor"
        :message="`Are you sure you want to delete '${monitor.name}'? This action cannot be undone.`"
        confirm-label="Delete"
        variant="danger"
        @confirm="confirmDelete"
    />

    <Teleport to="body">
        <Transition name="fade">
            <div v-if="showChannelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closeChannelModal">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeChannelModal" />
                <div ref="channelModalRef" class="relative bg-[var(--color-surface-0)] border border-white/10 rounded-xl p-6 w-full max-w-sm shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="channel-modal-title">
                    <button @click="closeChannelModal" aria-label="Close dialog" class="absolute top-4 right-4 text-slate-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <div v-if="selectedChannel" class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center">
                            <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="channelIcons[selectedChannel.type] || channelIcons.webhook" />
                        </div>
                        <div>
                            <h3 id="channel-modal-title" class="text-lg font-semibold text-white">{{ selectedChannel.name }}</h3>
                            <p class="text-sm text-slate-400 uppercase">{{ selectedChannel.type }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.slide-down-enter-active,
.slide-down-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.slide-down-enter-from,
.slide-down-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
