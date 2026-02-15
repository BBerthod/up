<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { computed } from 'vue'

interface Check { id: number; status: 'up' | 'down'; response_time_ms: number; status_code: number; checked_at: string }
interface Incident { id: number; started_at: string; resolved_at: string | null; cause: string }
interface Channel { id: number; name: string; type: string }

const props = defineProps<{
    monitor: any
    checks: Check[]
    incidents: Incident[]
    uptime: { day: number; week: number; month: number }
}>()

const pauseForm = useForm({})

const togglePause = () => {
    if (props.monitor.is_active) {
        pauseForm.post(route('monitors.pause', props.monitor.id))
    } else {
        pauseForm.post(route('monitors.resume', props.monitor.id))
    }
}

const deleteMonitor = () => {
    if (confirm('Are you sure you want to delete this monitor?')) {
        router.delete(route('monitors.destroy', props.monitor.id))
    }
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

const maxMs = computed(() => Math.max(...props.checks.map(c => c.response_time_ms), 1))

const activeIncidents = computed(() => props.incidents.filter(i => !i.resolved_at).length)

const uptimeColor = (v: number) => v > 99 ? 'text-emerald-400' : v > 95 ? 'text-yellow-400' : 'text-red-400'
</script>

<template>
    <Head :title="monitor.name" />

    <div class="space-y-6">
        <Link :href="route('monitors.index')" class="inline-flex items-center gap-2 text-slate-400 hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            Back to Monitors
        </Link>

        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-2xl font-bold text-white">{{ monitor.name }}</h1>
                    <span v-if="monitor.is_active && checks[0]?.status === 'up'" class="px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-400">UP</span>
                    <span v-else-if="monitor.is_active && checks[0]?.status === 'down'" class="px-3 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-400">DOWN</span>
                    <span v-else class="px-3 py-1 rounded-full text-xs font-medium bg-slate-500/20 text-slate-400">PAUSED</span>
                </div>
                <p class="text-slate-400 mt-1">{{ monitor.url }}</p>
            </div>
            <div class="flex items-center gap-3">
                <Link :href="route('monitors.edit', monitor.id)" class="px-4 py-2 rounded-lg text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">Edit</Link>
                <button @click="togglePause" class="px-4 py-2 rounded-lg text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">{{ monitor.is_active ? 'Pause' : 'Resume' }}</button>
                <button @click="deleteMonitor" class="px-4 py-2 rounded-lg text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 transition-colors">Delete</button>
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

        <div class="glass p-6">
            <h3 class="text-white font-medium mb-4">Response Time (Last {{ checks.length }} checks)</h3>
            <div class="h-32">
                <svg class="w-full h-full" preserveAspectRatio="none">
                    <rect v-for="(check, i) in checks" :key="check.id"
                        :x="`${(i / checks.length) * 100}%`"
                        :y="`${100 - Math.max(4, (check.response_time_ms / maxMs) * 100)}%`"
                        :width="`${Math.max(100 / checks.length - 0.5, 1)}%`"
                        :height="`${Math.max(4, (check.response_time_ms / maxMs) * 100)}%`"
                        :fill="check.status === 'up' ? '#10b981' : '#ef4444'" rx="1" />
                </svg>
            </div>
        </div>

        <div class="glass p-6">
            <h3 class="text-white font-medium mb-4">Incident History</h3>
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
        </div>

        <div class="glass p-6">
            <h3 class="text-white font-medium mb-4">Notification Channels</h3>
            <div v-if="monitor.notification_channels?.length" class="flex flex-wrap gap-3">
                <div v-for="ch in monitor.notification_channels" :key="ch.id" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/5">
                    <span class="text-white text-sm">{{ ch.name }}</span>
                    <span class="text-xs text-slate-500 uppercase">{{ ch.type }}</span>
                </div>
            </div>
            <p v-else class="text-slate-500 text-center py-8">No notification channels configured</p>
        </div>
    </div>
</template>
