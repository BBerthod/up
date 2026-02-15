<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'

interface LatestCheck {
    status: 'up' | 'down'
    response_time_ms: number
    checked_at: string
}

interface Monitor {
    id: number
    name: string
    url: string
    is_active: boolean
    latest_check: LatestCheck | null
    uptime_24h: number | null
    active_incidents_count: number
}

const props = defineProps<{
    monitors: Monitor[]
    filters: { status: string | null }
}>()

const stats = computed(() => {
    const total = props.monitors.length
    const up = props.monitors.filter(m => m.is_active && m.latest_check?.status === 'up').length
    const down = props.monitors.filter(m => m.is_active && m.latest_check?.status === 'down').length
    const withResponse = props.monitors.filter(m => m.latest_check?.response_time_ms)
    const avgMs = withResponse.length > 0
        ? Math.round(withResponse.reduce((sum, m) => sum + (m.latest_check?.response_time_ms || 0), 0) / withResponse.length)
        : 0
    return { total, up, down, avgMs }
})

const relativeTime = (dateStr: string | null): string => {
    if (!dateStr) return 'Never'
    const diffMs = Date.now() - new Date(dateStr).getTime()
    const mins = Math.floor(diffMs / 60000)
    if (mins < 1) return 'Just now'
    if (mins < 60) return `${mins}m ago`
    const hours = Math.floor(mins / 60)
    if (hours < 24) return `${hours}h ago`
    return `${Math.floor(hours / 24)}d ago`
}

const getUptimeColor = (uptime: number | null): string => {
    if (uptime === null) return 'text-slate-500'
    if (uptime > 99) return 'text-emerald-400'
    if (uptime > 95) return 'text-yellow-400'
    return 'text-red-400'
}

const filterMonitors = (status: string | null) => {
    router.get(route('monitors.index'), status ? { status } : {}, { preserveState: true })
}

const activeFilter = computed(() => props.filters.status || 'all')
</script>

<template>
    <Head title="Monitors" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-white">Monitors</h1>
            <Link :href="route('monitors.create')" title="Create a new monitor" class="py-3 px-4 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20">
                Add Monitor
            </Link>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="glass p-4" title="Total number of configured monitors"><p class="text-slate-400 text-sm">Total</p><p class="text-2xl font-bold text-white font-mono">{{ stats.total }}</p></div>
            <div class="glass p-4" title="Monitors currently online"><p class="text-slate-400 text-sm">Up</p><p class="text-2xl font-bold text-emerald-400 font-mono">{{ stats.up }}</p></div>
            <div class="glass p-4" title="Monitors currently failing"><p class="text-slate-400 text-sm">Down</p><p class="text-2xl font-bold text-red-400 font-mono">{{ stats.down }}</p></div>
            <div class="glass p-4" title="Average response time across all monitors"><p class="text-slate-400 text-sm">Avg Response</p><p class="text-2xl font-bold text-white font-mono">{{ stats.avgMs }}<span class="text-sm text-slate-400">ms</span></p></div>
        </div>

        <div class="flex gap-2">
            <button v-for="f in [{ key: 'all', label: 'All', tip: 'Show all monitors' }, { key: 'up', label: 'Up', tip: 'Show online monitors only' }, { key: 'down', label: 'Down', tip: 'Show failing monitors only' }, { key: 'paused', label: 'Paused', tip: 'Show paused monitors only' }]" :key="f.key"
                :title="f.tip"
                @click="filterMonitors(f.key === 'all' ? null : f.key)"
                :class="['px-4 py-2 rounded-lg text-sm font-medium transition-colors', activeFilter === f.key ? 'bg-white/10 text-white border border-white/20' : 'text-slate-400 hover:text-white hover:bg-white/5']">
                {{ f.label }}
            </button>
        </div>

        <div v-if="monitors.length > 0" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Link v-for="monitor in monitors" :key="monitor.id" :href="route('monitors.show', monitor.id)" class="glass glass-hover p-4 block">
                <div class="flex items-center gap-3 mb-3">
                    <div v-if="!monitor.is_active" class="status-dot" style="background-color: var(--color-muted);" />
                    <div v-else-if="monitor.latest_check?.status === 'up'" class="status-dot online" />
                    <div v-else class="status-dot offline" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-white font-medium truncate">{{ monitor.name }}</span>
                            <span v-if="!monitor.is_active" class="px-2 py-0.5 text-xs rounded-full bg-slate-500/20 text-slate-400">Paused</span>
                        </div>
                        <p class="text-slate-500 text-sm truncate">{{ monitor.url }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <span :class="['font-mono', getUptimeColor(monitor.uptime_24h)]">{{ monitor.uptime_24h !== null ? monitor.uptime_24h.toFixed(1) + '%' : '---%' }}</span>
                    <span class="font-mono text-slate-400">{{ monitor.latest_check ? monitor.latest_check.response_time_ms + 'ms' : '---ms' }}</span>
                    <span class="text-xs text-slate-500 ml-auto">{{ relativeTime(monitor.latest_check?.checked_at || null) }}</span>
                </div>
            </Link>
        </div>

        <div v-else class="glass p-12 text-center">
            <svg class="w-16 h-16 mx-auto mb-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" /></svg>
            <h3 class="text-lg font-medium text-white mb-2">No monitors yet</h3>
            <p class="text-slate-400 mb-6">Start monitoring your services by adding your first monitor.</p>
            <Link :href="route('monitors.create')" class="py-3 px-4 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20 inline-block">Create Your First Monitor</Link>
        </div>
    </div>
</template>
