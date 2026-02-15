<script setup lang="ts">
import { Head } from '@inertiajs/vue3'

interface DownMonitor {
    id: number
    name: string
    url: string
    last_checked_at: string | null
}

const props = defineProps<{
    metrics: {
        total_monitors: number
        monitors_up: number
        monitors_down: number
        monitors_paused: number
        avg_uptime_24h: number
        avg_response_time_24h: number
        total_checks_today: number
        active_incidents: number
        down_monitors: DownMonitor[]
    }
}>()

const uptimeColor = (v: number) => v > 99 ? 'text-emerald-400' : v > 95 ? 'text-yellow-400' : 'text-red-400'
const truncateUrl = (url: string) => url.replace(/^https?:\/\//, '').slice(0, 40)
const timeAgo = (iso: string | null) => {
    if (!iso) return 'Never'
    const ms = Date.now() - new Date(iso).getTime()
    const m = Math.floor(ms / 60000)
    if (m < 60) return `${m}m ago`
    return `${Math.floor(m / 60)}h ${m % 60}m ago`
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-6">
        <h1 class="text-2xl font-bold text-white">Dashboard</h1>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="glass p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2 rounded-lg bg-cyan-500/10">
                        <svg class="w-5 h-5 text-cyan-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </div>
                    <span class="text-sm text-slate-400">Monitors</span>
                </div>
                <p class="text-3xl font-bold text-white font-mono">{{ metrics.total_monitors }}</p>
            </div>

            <div class="glass p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2 rounded-lg bg-emerald-500/10">
                        <svg class="w-5 h-5 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <span class="text-sm text-slate-400">Uptime 24h</span>
                </div>
                <p class="text-3xl font-bold font-mono" :class="uptimeColor(metrics.avg_uptime_24h)">{{ metrics.avg_uptime_24h.toFixed(1) }}<span class="text-lg">%</span></p>
            </div>

            <div class="glass p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2 rounded-lg bg-blue-500/10">
                        <svg class="w-5 h-5 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <span class="text-sm text-slate-400">Avg Response</span>
                </div>
                <p class="text-3xl font-bold text-white font-mono">{{ metrics.avg_response_time_24h }}<span class="text-lg text-slate-400">ms</span></p>
            </div>

            <div class="glass p-5">
                <div class="flex items-center gap-3 mb-3">
                    <div class="p-2 rounded-lg" :class="metrics.active_incidents > 0 ? 'bg-red-500/10' : 'bg-emerald-500/10'">
                        <svg class="w-5 h-5" :class="metrics.active_incidents > 0 ? 'text-red-400' : 'text-emerald-400'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </div>
                    <span class="text-sm text-slate-400">Incidents</span>
                </div>
                <p class="text-3xl font-bold font-mono" :class="metrics.active_incidents > 0 ? 'text-red-400' : 'text-emerald-400'">{{ metrics.active_incidents }}</p>
            </div>
        </div>

        <div class="grid lg:grid-cols-2 gap-6">
            <div class="glass p-6">
                <h3 class="text-white font-medium mb-4">Monitor Status</h3>
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-emerald-500" />
                            <span class="text-sm text-slate-300">Up</span>
                        </div>
                        <span class="text-sm font-mono text-white">{{ metrics.monitors_up }}</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-white/5 overflow-hidden">
                        <div class="h-full bg-emerald-500 rounded-full transition-all" :style="{ width: metrics.total_monitors ? `${(metrics.monitors_up / metrics.total_monitors) * 100}%` : '0%' }" />
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-red-500" />
                            <span class="text-sm text-slate-300">Down</span>
                        </div>
                        <span class="text-sm font-mono text-white">{{ metrics.monitors_down }}</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-white/5 overflow-hidden">
                        <div class="h-full bg-red-500 rounded-full transition-all" :style="{ width: metrics.total_monitors ? `${(metrics.monitors_down / metrics.total_monitors) * 100}%` : '0%' }" />
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-slate-500" />
                            <span class="text-sm text-slate-300">Paused</span>
                        </div>
                        <span class="text-sm font-mono text-white">{{ metrics.monitors_paused }}</span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-white/5 overflow-hidden">
                        <div class="h-full bg-slate-500 rounded-full transition-all" :style="{ width: metrics.total_monitors ? `${(metrics.monitors_paused / metrics.total_monitors) * 100}%` : '0%' }" />
                    </div>
                </div>
            </div>

            <div class="glass p-6">
                <h3 class="text-white font-medium mb-4">Down Monitors</h3>
                <div v-if="metrics.down_monitors.length > 0" class="space-y-3">
                    <div v-for="m in metrics.down_monitors" :key="m.id" class="flex items-center justify-between py-2 border-b border-white/5 last:border-0">
                        <div>
                            <p class="text-sm font-medium text-white">{{ m.name }}</p>
                            <p class="text-xs text-slate-500 font-mono">{{ truncateUrl(m.url) }}</p>
                        </div>
                        <span class="text-xs text-slate-400">{{ timeAgo(m.last_checked_at) }}</span>
                    </div>
                </div>
                <div v-else class="flex flex-col items-center py-8">
                    <svg class="w-10 h-10 text-emerald-400 mb-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <p class="text-emerald-400 font-medium">All systems operational</p>
                </div>
            </div>
        </div>

        <div class="glass p-6">
            <h3 class="text-white font-medium mb-2">Today's Activity</h3>
            <p class="text-3xl font-bold text-white font-mono">{{ metrics.total_checks_today.toLocaleString() }} <span class="text-lg text-slate-400 font-sans font-normal">checks performed</span></p>
        </div>
    </div>
</template>
