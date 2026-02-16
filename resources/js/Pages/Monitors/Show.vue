<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useRealtimeUpdates } from '@/Composables/useRealtimeUpdates'
import LatencyHeatmap from '@/Components/LatencyHeatmap.vue'
import LighthouseHistory from '@/Components/LighthouseHistory.vue'
import LighthouseScores from '@/Components/LighthouseScores.vue'
import ResponseTimeChart from '@/Components/ResponseTimeChart.vue'
import BackLink from '@/Components/BackLink.vue'
import GlassCard from '@/Components/GlassCard.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import CopyButton from '@/Components/CopyButton.vue'

useRealtimeUpdates({
    onMonitorChecked: ['checks', 'chartData', 'uptime', 'heatmapData', 'incidents'],
    onLighthouseCompleted: ['lighthouseScore', 'lighthouseHistory'],
})

interface Check { id: number; status: 'up' | 'down'; response_time_ms: number; status_code: number; checked_at: string }
interface Incident { id: number; started_at: string; resolved_at: string | null; cause: string }

const props = defineProps<{
    monitor: any
    checks: Check[]
    incidents: Incident[]
    uptime: { day: number; week: number; month: number }
    heatmapData: Record<string, number>
    lighthouseScore: { performance: number; accessibility: number; best_practices: number; seo: number; lcp: number | null; fcp: number | null; cls: number | null; tbt: number | null; speed_index: number | null; scored_at: string } | null
    lighthouseHistory?: Array<any> | null
    chartData: Array<any>
    currentPeriod: string
}>()

const baseUrl = computed(() => typeof window !== 'undefined' ? window.location.origin : '')
const pauseForm = useForm({})

const showChannelModal = ref(false)
const selectedChannel = ref<{ id: number; name: string; type: string } | null>(null)
const showDeleteDialog = ref(false)

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

const avgMs = computed(() => {
    if (!props.checks.length) return 0
    return Math.round(props.checks.reduce((s, c) => s + c.response_time_ms, 0) / props.checks.length)
})

const activeIncidents = computed(() => props.incidents.filter(i => !i.resolved_at).length)

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
</script>

<template>
    <Head :title="monitor.name" />

    <div class="space-y-6">
        <BackLink :href="route('monitors.index')" label="Back to Monitors" />

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
                <p class="text-lg font-semibold font-mono" :class="activeIncidents > 0 ? 'text-red-400' : 'text-emerald-400'">{{ activeIncidents }}</p>
            </div>
        </div>

        <ResponseTimeChart :chart-data="chartData" :current-period="currentPeriod" :monitor-id="monitor.id" @period-change="handlePeriodChange" />

        <GlassCard title="Latency Heatmap (12 months)">
            <LatencyHeatmap :data="heatmapData" />
        </GlassCard>

        <GlassCard title="Lighthouse Scores">
            <LighthouseScores :scores="lighthouseScore" :monitor-id="monitor.id" :monitor-type="monitor.type" />
        </GlassCard>

        <GlassCard v-if="monitor.type === 'http'">
            <LighthouseHistory :history="lighthouseHistory ?? null" :monitor-id="monitor.id" />
        </GlassCard>

        <GlassCard title="Incident History">
            <div v-if="incidents.length > 0" class="overflow-x-auto">
                <table class="w-full">
                    <thead><tr class="text-left text-slate-400 text-sm border-b border-white/10"><th class="pb-3 font-medium">Cause</th><th class="pb-3 font-medium">Started</th><th class="pb-3 font-medium">Resolved</th><th class="pb-3 font-medium">Duration</th></tr></thead>
                    <tbody>
                        <tr v-for="incident in incidents" :key="incident.id" class="border-b border-white/5 last:border-0">
                            <td class="py-3"><span class="px-2 py-1 rounded-full text-xs font-medium bg-slate-500/20 text-slate-400">{{ incident.cause.replace(/_/g, ' ') }}</span></td>
                            <td class="py-3 text-slate-300 text-sm">{{ formatDate(incident.started_at) }}</td>
                            <td class="py-3 text-sm"><span v-if="incident.resolved_at" class="text-slate-300">{{ formatDate(incident.resolved_at) }}</span><span v-else class="text-red-400 font-medium">Ongoing</span></td>
                            <td class="py-3 text-slate-400 text-sm font-mono">{{ duration(incident.started_at, incident.resolved_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-slate-500 text-center py-8">No incidents recorded</p>
        </GlassCard>

        <GlassCard v-if="monitor.badge_hash" title="Status Badge">
            <div class="flex items-center gap-4">
                <img :src="`/badge/${monitor.badge_hash}.svg`" :alt="`${monitor.name} uptime badge`" />
                <code class="text-sm text-slate-400 font-mono bg-white/5 px-3 py-2 rounded">{{ badgeMarkdown }}</code>
                <CopyButton :text="badgeMarkdown" />
            </div>
        </GlassCard>
    </div>

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
                <div class="relative bg-[var(--color-surface-0)] border border-white/10 rounded-xl p-6 w-full max-w-sm shadow-2xl">
                    <button @click="closeChannelModal" aria-label="Close dialog" class="absolute top-4 right-4 text-slate-400 hover:text-white transition-colors">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <div v-if="selectedChannel" class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-white/5 border border-white/10 flex items-center justify-center">
                            <svg class="w-5 h-5 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" v-html="channelIcons[selectedChannel.type] || channelIcons.webhook" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-white">{{ selectedChannel.name }}</h3>
                            <p class="text-sm text-slate-400 uppercase">{{ selectedChannel.type }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
