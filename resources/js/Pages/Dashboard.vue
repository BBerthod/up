<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3'
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRealtimeUpdates } from '@/Composables/useRealtimeUpdates'
import { usePageLoading } from '@/Composables/usePageLoading'
import PageHeader from '@/Components/PageHeader.vue'
import GlassCard from '@/Components/GlassCard.vue'
import EmptyState from '@/Components/EmptyState.vue'
import SkeletonDashboard from '@/Components/SkeletonDashboard.vue'

const lastUpdated = ref<Date | null>(null)
const isRefreshing = ref(false)
const flashKey = ref(0)

const triggerRefreshAnimation = () => {
    isRefreshing.value = true
    flashKey.value++
    lastUpdated.value = new Date()
    setTimeout(() => {
        isRefreshing.value = false
    }, 600)
}

useRealtimeUpdates({
    onMonitorChecked: ['metrics'],
    onLighthouseCompleted: ['metrics'],
    onRefresh: triggerRefreshAnimation,
})

interface DownMonitor {
    id: number
    name: string
    url: string
    last_checked_at: string | null
}

interface RecentIncident {
    id: number
    monitor_name: string
    monitor_id: number
    cause: string
    started_at: string
    resolved_at: string | null
}

interface MonitorOverview {
    id: number
    name: string
    type: string
    status: string
    uptime_24h: number
    uptime_7d: number
    last_response_ms: number
}

interface LighthouseOverview {
    monitor_id: number
    monitor_name: string
    performance: number
    accessibility: number
    best_practices: number
    seo: number
    scored_at: string
}

interface ChartPoint {
    hour: string
    avg_ms: number
}

const { isLoading } = usePageLoading()

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
        recent_incidents: RecentIncident[]
        response_time_chart: ChartPoint[]
        monitors_overview: MonitorOverview[]
        lighthouse_overview: LighthouseOverview[]
    }
}>()

const timeAgo = (iso: string | null) => {
    if (!iso) return 'Never'
    const ms = Date.now() - new Date(iso).getTime()
    const m = Math.floor(ms / 60000)
    if (m < 60) return `${m}m ago`
    if (m < 1440) return `${Math.floor(m / 60)}h ago`
    return `${Math.floor(m / 1440)}d ago`
}

const formatLastUpdated = () => {
    if (!lastUpdated.value) return null
    return lastUpdated.value.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' })
}

const formatNumber = (num: number) => new Intl.NumberFormat('en-US').format(num)

const uptimeColor = (v: number) => v >= 99 ? 'text-emerald-400' : v >= 95 ? 'text-yellow-400' : 'text-red-400'

const scoreColor = (v: number) => v >= 90 ? '#0cce6b' : v >= 50 ? '#ffa400' : '#ff4e42'

// Response time chart
const chartViewBox = { w: 800, h: 200 }
const chartPad = { l: 50, r: 20, t: 15, b: 30 }
const chartW = chartViewBox.w - chartPad.l - chartPad.r
const chartH = chartViewBox.h - chartPad.t - chartPad.b

const chartPoints = computed(() => {
    if (!props.metrics.response_time_chart?.length) return []
    return props.metrics.response_time_chart.map((p, i, arr) => ({
        x: chartPad.l + (i / Math.max(arr.length - 1, 1)) * chartW,
        y: p.avg_ms,
        hour: p.hour,
    }))
})

const chartMaxY = computed(() => {
    if (!chartPoints.value.length) return 100
    return Math.max(...chartPoints.value.map(p => p.y), 1) * 1.15
})

const scaleY = (v: number) => chartPad.t + chartH - (v / chartMaxY.value) * chartH

const chartPathD = computed(() => {
    if (chartPoints.value.length === 0) return ''
    const pts = chartPoints.value.map(p => ({ x: p.x, y: scaleY(p.y) }))
    if (pts.length === 1) return `M ${pts[0].x},${pts[0].y}`
    let d = `M ${pts[0].x.toFixed(1)},${pts[0].y.toFixed(1)}`
    for (let i = 1; i < pts.length; i++) {
        const cpx = ((pts[i - 1].x + pts[i].x) / 2).toFixed(1)
        d += ` C ${cpx},${pts[i - 1].y.toFixed(1)} ${cpx},${pts[i].y.toFixed(1)} ${pts[i].x.toFixed(1)},${pts[i].y.toFixed(1)}`
    }
    return d
})

const chartAreaD = computed(() => {
    if (!chartPathD.value) return ''
    const pts = chartPoints.value.map(p => ({ x: p.x, y: scaleY(p.y) }))
    const bottomY = chartPad.t + chartH
    return `${chartPathD.value} L ${pts[pts.length - 1].x.toFixed(1)},${bottomY} L ${pts[0].x.toFixed(1)},${bottomY} Z`
})

const chartYTicks = computed(() => {
    const max = chartMaxY.value
    const step = Math.ceil(max / 4 / 50) * 50
    const ticks = []
    for (let i = 0; i <= 4; i++) ticks.push(i * step)
    return ticks
})

const chartXLabels = computed(() => {
    const pts = chartPoints.value
    if (pts.length === 0) return []
    const count = Math.min(6, pts.length)
    const labels: { x: number; label: string }[] = []
    for (let i = 0; i < count; i++) {
        const idx = Math.round((i / (count - 1)) * (pts.length - 1))
        const date = new Date(pts[idx].hour)
        labels.push({ x: pts[idx].x, label: `${String(date.getHours()).padStart(2, '0')}:00` })
    }
    return labels
})

interface ChartTooltipData {
    x: number
    y: number
    hour: string
    avg_ms: number
}

const chartTooltip = ref({
    show: false,
    x: 0,
    y: 0,
    svgX: 0,
    svgY: 0,
    data: null as ChartTooltipData | null,
})

const chartSvgRef = ref<SVGSVGElement | null>(null)
const isTouchDevice = ref(false)
const touchTimeout = ref<number | null>(null)

onMounted(() => {
    isTouchDevice.value = 'ontouchstart' in window || navigator.maxTouchPoints > 0
    document.addEventListener('click', handleDocumentClick)
})

onUnmounted(() => {
    document.removeEventListener('click', handleDocumentClick)
    if (touchTimeout.value) clearTimeout(touchTimeout.value)
})

function handleDocumentClick(event: MouseEvent | TouchEvent) {
    if (chartTooltip.value.show && chartSvgRef.value && !chartSvgRef.value.contains(event.target as Node)) {
        hideChartTooltip()
    }
}

function getChartTooltipPosition(event: MouseEvent | TouchEvent) {
    const clientX = 'touches' in event ? event.touches[0].clientX : event.clientX
    const clientY = 'touches' in event ? event.touches[0].clientY : event.clientY

    const vw = window.innerWidth
    const vh = window.innerHeight

    let x = clientX + 16
    let y = clientY - 20

    if (x + 200 > vw) x = clientX - 200
    if (y < 10) y = clientY + 20
    if (y + 120 > vh) y = vh - 130

    return { x, y }
}

function showChartTooltip(event: MouseEvent | TouchEvent, point: { x: number; y: number; hour: string }) {
    const pos = getChartTooltipPosition(event)
    chartTooltip.value = {
        show: true,
        x: pos.x,
        y: pos.y,
        svgX: point.x,
        svgY: scaleY(point.y),
        data: { x: point.x, y: point.y, hour: point.hour, avg_ms: point.y },
    }
}

function updateChartTooltipPosition(event: MouseEvent | TouchEvent) {
    const pos = getChartTooltipPosition(event)
    chartTooltip.value.x = pos.x
    chartTooltip.value.y = pos.y
}

function hideChartTooltip() {
    chartTooltip.value.show = false
    chartTooltip.value.data = null
}

function handleChartMouseEnter(event: MouseEvent, point: { x: number; y: number; hour: string }) {
    showChartTooltip(event, point)
}

function handleChartMouseMove(event: MouseEvent, point: { x: number; y: number; hour: string }) {
    if (chartTooltip.value.data?.hour !== point.hour) {
        showChartTooltip(event, point)
    }
    updateChartTooltipPosition(event)
}

function handleChartMouseLeave() {
    if (!isTouchDevice.value) {
        hideChartTooltip()
    }
}

function handleChartTouchStart(event: TouchEvent, point: { x: number; y: number; hour: string }) {
    event.preventDefault()
    if (touchTimeout.value) clearTimeout(touchTimeout.value)
    showChartTooltip(event, point)
}

function handleChartTouchMove(event: TouchEvent, point: { x: number; y: number; hour: string }) {
    event.preventDefault()
    if (touchTimeout.value) clearTimeout(touchTimeout.value)
    if (chartTooltip.value.data?.hour !== point.hour) {
        showChartTooltip(event, point)
    }
    updateChartTooltipPosition(event)
}

function handleChartTouchEnd() {
    touchTimeout.value = window.setTimeout(hideChartTooltip, 2000)
}

function formatChartTooltipDate(hour: string): string {
    const date = new Date(hour)
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec']
    const month = months[date.getMonth()]
    const day = date.getDate()
    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')
    return `${month} ${day}, ${hours}:${minutes}`
}
</script>

<template>
    <Head title="Dashboard" />

    <div class="space-y-12">
        <div class="flex items-end justify-between">
            <PageHeader title="Overview" description="System status and metrics for the last 24 hours." />
            <div v-if="lastUpdated" class="flex items-center gap-2 text-xs text-zinc-500">
                <span :class="{ 'animate-pulse text-emerald-400': isRefreshing }">Last updated: {{ formatLastUpdated() }}</span>
            </div>
        </div>

        <!-- KPI Grid -->
        <SkeletonDashboard v-if="isLoading" />
        <div v-else :key="flashKey" class="grid grid-cols-2 md:grid-cols-4 gap-8 border-b border-white/5 pb-12 transition-opacity duration-300" :class="{ 'opacity-80': isRefreshing }">
            <div class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Monitors</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-semibold text-white tracking-tight">{{ metrics.total_monitors }}</span>
                    <span class="text-sm text-emerald-500" v-if="metrics.total_monitors > 0">+{{ metrics.monitors_up }} Up</span>
                </div>
            </div>

            <div class="space-y-1">
                <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">Uptime (24h)</span>
                <div class="flex items-baseline gap-2">
                    <span class="text-4xl font-semibold text-white tracking-tight">{{ metrics.avg_uptime_24h.toFixed(1) }}%</span>
                </div>
                <div class="flex gap-0.5 h-1 w-full max-w-[100px] mt-2 opacity-50">
                     <div v-for="i in 10" :key="i" class="flex-1 rounded-full" :class="i > 8 ? 'bg-emerald-500/30' : 'bg-emerald-500'"></div>
                </div>
            </div>

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

                <EmptyState v-else title="All Systems Operational" description="Every monitored endpoint is responding correctly. No incidents reported." icon="check" icon-color="emerald" />
            </div>

            <!-- Checks Today -->
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

        <!-- Response Time Chart (24h) -->
        <GlassCard>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-white">Response Time (24h)</h3>
                <span v-if="isRefreshing" class="text-xs text-emerald-400 animate-pulse">Updating...</span>
            </div>
            <div v-if="metrics.response_time_chart.length > 0" :key="'chart-' + flashKey" class="transition-opacity duration-300" :class="{ 'opacity-70': isRefreshing }">
                <svg ref="chartSvgRef" :viewBox="`0 0 ${chartViewBox.w} ${chartViewBox.h}`" class="w-full h-auto" preserveAspectRatio="xMidYMid meet">
                    <defs>
                        <linearGradient id="dashAreaGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#10b981" stop-opacity="0.2" />
                            <stop offset="100%" stop-color="#10b981" stop-opacity="0" />
                        </linearGradient>
                        <linearGradient id="dashVerticalLineGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                            <stop offset="0%" stop-color="#10b981" stop-opacity="0.6" />
                            <stop offset="50%" stop-color="#10b981" stop-opacity="0.3" />
                            <stop offset="100%" stop-color="#10b981" stop-opacity="0" />
                        </linearGradient>
                    </defs>
                    <line v-for="tick in chartYTicks" :key="'g-' + tick" :x1="chartPad.l" :x2="chartViewBox.w - chartPad.r" :y1="scaleY(tick)" :y2="scaleY(tick)" stroke="white" stroke-opacity="0.05" stroke-dasharray="4 4" />
                    <text v-for="tick in chartYTicks" :key="'yt-' + tick" :x="chartPad.l - 8" :y="scaleY(tick) + 4" text-anchor="end" style="font-size: 10px; fill: #64748b">{{ tick }}ms</text>
                    <text v-for="label in chartXLabels" :key="'xl-' + label.label" :x="label.x" :y="chartViewBox.h - 5" text-anchor="middle" style="font-size: 10px; fill: #64748b">{{ label.label }}</text>
                    <path :d="chartAreaD" fill="url(#dashAreaGrad)" />
                    <path :d="chartPathD" stroke="#10b981" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" />

                    <line
                        v-if="chartTooltip.show && chartTooltip.data"
                        :x1="chartTooltip.svgX"
                        :y1="chartPad.t"
                        :x2="chartTooltip.svgX"
                        :y2="chartViewBox.h - chartPad.b"
                        stroke="url(#dashVerticalLineGrad)"
                        stroke-width="1"
                        stroke-dasharray="4 2"
                        class="transition-opacity duration-150"
                    />

                    <circle
                        v-if="chartTooltip.show && chartTooltip.data"
                        :cx="chartTooltip.svgX"
                        :cy="chartTooltip.svgY"
                        r="6"
                        fill="#10b981"
                        stroke="rgba(255,255,255,0.3)"
                        stroke-width="2"
                        class="transition-opacity duration-150"
                    />

                    <rect
                        v-for="(point, index) in chartPoints"
                        :key="'hit-' + index"
                        :x="point.x - Math.max(8, chartW / chartPoints.length / 2)"
                        :y="chartPad.t"
                        :width="Math.max(16, chartW / chartPoints.length)"
                        :height="chartH"
                        fill="transparent"
                        class="cursor-pointer"
                        @mouseenter="handleChartMouseEnter($event, point)"
                        @mousemove="handleChartMouseMove($event, point)"
                        @mouseleave="handleChartMouseLeave"
                        @touchstart.passive="handleChartTouchStart($event, point)"
                        @touchmove.passive="handleChartTouchMove($event, point)"
                        @touchend="handleChartTouchEnd"
                    />
                </svg>
            </div>
            <p v-else class="text-slate-500 text-center py-8">No data yet</p>
        </GlassCard>

        <Teleport to="body">
            <Transition
                enter-active-class="transition ease-out duration-150"
                enter-from-class="opacity-0 scale-95"
                enter-to-class="opacity-100 scale-100"
                leave-active-class="transition ease-in duration-100"
                leave-from-class="opacity-100 scale-100"
                leave-to-class="opacity-0 scale-95"
            >
                <div
                    v-if="chartTooltip.show && chartTooltip.data"
                    class="fixed z-50 px-4 py-3 rounded-xl pointer-events-none shadow-2xl min-w-[160px]"
                    :style="{
                        left: `${chartTooltip.x}px`,
                        top: `${chartTooltip.y}px`,
                        background: 'linear-gradient(135deg, rgba(26, 26, 29, 0.95) 0%, rgba(17, 17, 19, 0.98) 100%)',
                        backdropFilter: 'blur(12px)',
                        border: '1px solid rgba(255, 255, 255, 0.1)',
                        boxShadow: '0 8px 32px rgba(0, 0, 0, 0.4), inset 0 1px 0 rgba(255, 255, 255, 0.05)',
                    }"
                >
                    <div class="text-xs font-medium text-slate-400 mb-2 tracking-wide uppercase">
                        {{ formatChartTooltipDate(chartTooltip.data.hour) }}
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-xs text-slate-500">Response</span>
                        <span class="text-sm font-semibold font-mono text-white">{{ Math.round(chartTooltip.data.avg_ms) }}<span class="text-slate-500 text-xs ml-0.5">ms</span></span>
                    </div>
                </div>
            </Transition>
        </Teleport>

        <!-- Monitors Overview -->
        <GlassCard>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-medium text-white">All Monitors</h3>
                <div class="flex items-center gap-3">
                    <span v-if="isRefreshing" class="text-xs text-emerald-400 animate-pulse">Updating...</span>
                    <span class="text-sm text-slate-400">{{ metrics.monitors_overview.length }} active</span>
                </div>
            </div>
            <div v-if="metrics.monitors_overview.length > 0" :key="'monitors-' + flashKey" class="overflow-x-auto transition-opacity duration-300" :class="{ 'opacity-70': isRefreshing }">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-slate-400 border-b border-white/10">
                            <th class="pb-3 font-medium">Monitor</th>
                            <th class="pb-3 font-medium text-center">Type</th>
                            <th class="pb-3 font-medium text-center">Status</th>
                            <th class="pb-3 font-medium text-center">Uptime 24h</th>
                            <th class="pb-3 font-medium text-center">Uptime 7d</th>
                            <th class="pb-3 font-medium text-center">Last Response</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="m in metrics.monitors_overview" :key="m.id" class="border-b border-white/5 hover:bg-white/5 transition-colors">
                            <td class="py-3">
                                <Link :href="route('monitors.show', m.id)" class="text-white hover:text-emerald-400 transition-colors font-medium">{{ m.name }}</Link>
                            </td>
                            <td class="py-3 text-center">
                                <span class="px-2 py-0.5 rounded-full text-xs font-medium bg-white/10 text-slate-300 uppercase">{{ m.type }}</span>
                            </td>
                            <td class="py-3 text-center">
                                <span class="inline-flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full" :class="m.status === 'up' ? 'bg-emerald-400' : m.status === 'down' ? 'bg-red-400' : 'bg-slate-400'"></span>
                                    <span :class="m.status === 'up' ? 'text-emerald-400' : m.status === 'down' ? 'text-red-400' : 'text-slate-400'" class="text-xs font-medium uppercase">{{ m.status }}</span>
                                </span>
                            </td>
                            <td class="py-3 text-center font-mono" :class="uptimeColor(m.uptime_24h)">{{ m.uptime_24h.toFixed(1) }}%</td>
                            <td class="py-3 text-center font-mono" :class="uptimeColor(m.uptime_7d)">{{ m.uptime_7d.toFixed(1) }}%</td>
                            <td class="py-3 text-center font-mono text-slate-300">{{ m.last_response_ms }}ms</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-slate-500 text-center py-8">No active monitors</p>
        </GlassCard>

        <!-- Recent Incidents + Lighthouse -->
        <div class="grid lg:grid-cols-12 gap-8">
            <!-- Recent Incidents -->
            <div class="lg:col-span-8">
                <div class="glass p-6 h-full">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-medium text-white">Recent Incidents</h3>
                        <Link :href="route('incidents.index')" class="text-sm text-emerald-500 hover:text-emerald-400 transition-colors">View All &rarr;</Link>
                    </div>
                    <div v-if="metrics.recent_incidents.length > 0" class="space-y-3">
                        <div v-for="incident in metrics.recent_incidents" :key="incident.id"
                            class="flex items-center justify-between py-3 px-4 rounded-lg bg-white/[0.02] border border-white/5">
                            <div class="flex items-center gap-3">
                                <span class="w-2 h-2 rounded-full" :class="incident.resolved_at ? 'bg-emerald-400' : 'bg-red-400'"></span>
                                <div>
                                    <Link :href="route('monitors.show', incident.monitor_id)" class="text-white text-sm font-medium hover:text-emerald-400 transition-colors">{{ incident.monitor_name }}</Link>
                                    <span class="ml-2 px-2 py-0.5 rounded-full text-xs bg-white/10 text-slate-400">{{ incident.cause.replace(/_/g, ' ') }}</span>
                                </div>
                            </div>
                            <div class="text-right">
                                <span v-if="incident.resolved_at" class="text-xs text-emerald-400">Resolved</span>
                                <span v-else class="text-xs text-red-400 font-medium">Ongoing</span>
                                <p class="text-xs text-slate-500 mt-0.5">{{ timeAgo(incident.started_at) }}</p>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-slate-500 text-center py-8">No incidents recorded</p>
                </div>
            </div>

            <!-- Lighthouse Scores -->
            <div class="lg:col-span-4">
                <div class="glass p-6 h-full">
                    <h3 class="text-lg font-medium text-white mb-4">Lighthouse Scores</h3>
                    <div v-if="metrics.lighthouse_overview.length > 0" class="space-y-4">
                        <div v-for="lh in metrics.lighthouse_overview" :key="lh.monitor_id" class="p-3 rounded-lg bg-white/[0.03] border border-white/5">
                            <Link :href="route('monitors.show', lh.monitor_id)" class="text-sm text-white font-medium hover:text-emerald-400 transition-colors block mb-2">{{ lh.monitor_name }}</Link>
                            <div class="grid grid-cols-4 gap-2 text-center">
                                <div>
                                    <span class="text-lg font-bold font-mono" :style="{ color: scoreColor(lh.performance) }">{{ lh.performance }}</span>
                                    <p class="text-[10px] text-slate-500 mt-0.5">Perf</p>
                                </div>
                                <div>
                                    <span class="text-lg font-bold font-mono" :style="{ color: scoreColor(lh.accessibility) }">{{ lh.accessibility }}</span>
                                    <p class="text-[10px] text-slate-500 mt-0.5">A11y</p>
                                </div>
                                <div>
                                    <span class="text-lg font-bold font-mono" :style="{ color: scoreColor(lh.best_practices) }">{{ lh.best_practices }}</span>
                                    <p class="text-[10px] text-slate-500 mt-0.5">BP</p>
                                </div>
                                <div>
                                    <span class="text-lg font-bold font-mono" :style="{ color: scoreColor(lh.seo) }">{{ lh.seo }}</span>
                                    <p class="text-[10px] text-slate-500 mt-0.5">SEO</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <p v-else class="text-slate-500 text-center py-8">No Lighthouse data yet</p>
                </div>
            </div>
        </div>
    </div>
</template>
