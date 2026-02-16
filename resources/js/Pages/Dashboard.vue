<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'

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

const timeAgo = (iso: string | null) => {
    if (!iso) return 'Never'
    const ms = Date.now() - new Date(iso).getTime()
    const m = Math.floor(ms / 60000)
    if (m < 60) return `${m}m`
    if (m < 1440) return `${Math.floor(m / 60)}h ${m % 60}m`
    return `${Math.floor(m / 1440)}d`
}

const formatNumber = (num: number) => new Intl.NumberFormat('en-US').format(num)
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-12">
        <!-- Header Section -->
        <div>
             <h1 class="text-3xl font-bold text-white tracking-tight mb-2">Overview</h1>
             <p class="text-zinc-500">System status and metrics for the last 24 hours.</p>
        </div>

        <!-- KPI Grid - No Cards, Just Data -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-8 border-b border-white/5 pb-12">
            <!-- Total Monitors -->
            <div class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Monitors</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-semibold text-white tracking-tight">{{ metrics.total_monitors }}</span>
                    <span class="text-sm text-emerald-500" v-if="metrics.total_monitors > 0">+{{ metrics.monitors_up }} Up</span>
                </div>
            </div>

            <!-- Uptime -->
            <div class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Uptime (24h)</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-semibold text-white tracking-tight">{{ metrics.avg_uptime_24h.toFixed(1) }}%</span>
                </div>
                 <!-- Mini Sparkline visualization (CSS) -->
                <div class="flex gap-0.5 h-1 w-full max-w-[100px] mt-2 opacity-50">
                     <div v-for="i in 10" :key="i" class="flex-1 rounded-full" :class="i > 8 ? 'bg-emerald-500/30' : 'bg-emerald-500'"></div>
                </div>
            </div>

            <!-- Incidents -->
            <div class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Active Incidents</span>
                 <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-semibold tracking-tight" :class="metrics.active_incidents > 0 ? 'text-white' : 'text-zinc-500'">{{ metrics.active_incidents }}</span>
                    <div v-if="metrics.active_incidents > 0" class="flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-red-500/10 border border-red-500/20 text-red-500 text-xs font-medium">
                        <span class="relative flex h-2 w-2">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                        </span>
                        Active
                    </div>
                </div>
            </div>

             <!-- Avg Response -->
            <div class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Avg Response</span>
                <div class="flex items-baseline gap-1">
                    <span class="text-4xl font-semibold text-white tracking-tight">{{ metrics.avg_response_time_24h }}</span>
                    <span class="text-base text-zinc-500">ms</span>
                </div>
            </div>
        </div>

        <div class="grid lg:grid-cols-12 gap-12">
            <!-- Down Monitors List -->
            <div class="lg:col-span-8 space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-medium text-white">Monitor Status</h3>
                    <Link href="/monitors" class="text-sm text-emerald-500 hover:text-emerald-400 transition-colors">View All &rarr;</Link>
                </div>

                <div v-if="metrics.down_monitors.length > 0" class="space-y-2">
                    <div v-for="m in metrics.down_monitors" :key="m.id" class="group flex items-center justify-between py-3 px-4 rounded-lg bg-red-500/5 border border-red-500/10 hover:bg-red-500/10 transition-colors">
                        <div class="flex items-center gap-4">
                            <div class="flex items-center justify-center w-8 h-8 rounded-full bg-red-500/20 text-red-500">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            </div>
                            <div>
                                <p class="text-white font-medium group-hover:text-red-400 transition-colors">{{ m.name }}</p>
                                <p class="text-xs text-red-500/60 font-mono">{{ m.url }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-medium text-red-500">Down</p>
                            <p class="text-xs text-red-500/60">{{ timeAgo(m.last_checked_at) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="flex flex-col items-center justify-center py-12 text-center border border-dashed border-white/5 rounded-2xl bg-white/[0.02]">
                    <div class="p-3 mb-4 rounded-full bg-emerald-500/10">
                        <svg class="w-6 h-6 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <h4 class="text-white font-medium">All Systems Operational</h4>
                    <p class="text-sm text-zinc-500 mt-1 max-w-sm">Every monitored endpoint is responding correctly. No incidents reported.</p>
                </div>
            </div>

            <!-- Activity Feed / Stats -->
             <div class="lg:col-span-4 space-y-8">
                 <div class="space-y-4">
                    <h3 class="text-lg font-medium text-white">Checks Today</h3>
                    <div class="p-6 rounded-2xl bg-[#111113] border border-white/5">
                        <div class="flex items-baseline gap-2 mb-2">
                             <span class="text-3xl font-bold text-white tracking-tight">{{ formatNumber(metrics.total_checks_today) }}</span>
                        </div>
                        <p class="text-sm text-zinc-500">Total automated checks performed across all regions in the last 24 hours.</p>

                        <div class="mt-6 pt-6 border-t border-white/5 space-y-3">
                             <div class="flex items-center justify-between text-sm">
                                <span class="text-zinc-500">Success Rate</span>
                                <span class="text-white font-medium">
                                    {{ metrics.total_monitors > 0 ? (((metrics.monitors_up + metrics.monitors_paused) / metrics.total_monitors) * 100).toFixed(1) : 0 }}%
                                </span>
                            </div>
                            <div class="w-full bg-white/5 rounded-full h-1.5">
                                <div class="bg-emerald-500 h-1.5 rounded-full" :style="{ width: metrics.total_monitors > 0 ? `${((metrics.monitors_up + metrics.monitors_paused) / metrics.total_monitors) * 100}%` : '0%' }"></div>
                            </div>
                        </div>
                    </div>
                 </div>
            </div>
        </div>
    </div>
</template>
