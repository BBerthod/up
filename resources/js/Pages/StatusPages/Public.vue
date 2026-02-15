<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import StatusPageLayout from '@/Layouts/StatusPageLayout.vue'
import { computed } from 'vue'

defineOptions({ layout: StatusPageLayout })

interface DayStatus { date: string; status: 'up' | 'down' | 'partial' | 'no-data' }

interface Monitor {
    id: number
    name: string
    current_status: 'up' | 'down' | 'unknown'
    response_time_ms: number | null
    uptime_90d: number
    daily_breakdown: DayStatus[]
}

interface Incident {
    id: number
    monitor_name: string
    cause: string
    started_at: string
}

const props = defineProps<{
    statusPage: { name: string; description: string | null; theme: 'dark' | 'light' }
    monitors: Monitor[]
    activeIncidents: Incident[]
}>()

const allUp = computed(() => props.monitors.length > 0 && props.monitors.every(m => m.current_status === 'up'))
const anyDown = computed(() => props.monitors.some(m => m.current_status === 'down'))

const isDark = computed(() => props.statusPage.theme === 'dark')

const blockColor = (status: string) => {
    if (status === 'up') return 'bg-emerald-500'
    if (status === 'down') return 'bg-red-500'
    if (status === 'partial') return 'bg-yellow-500'
    return isDark.value ? 'bg-slate-700' : 'bg-gray-300'
}

const formatTime = (d: string) => {
    const ms = Date.now() - new Date(d).getTime()
    const m = Math.floor(ms / 60000)
    if (m < 1) return 'Just now'
    if (m < 60) return `${m}m ago`
    return `${Math.floor(m / 60)}h ago`
}
</script>

<template>
    <Head :title="statusPage.name + ' Status'" />

    <div class="space-y-8">
        <div class="text-center">
            <h1 class="text-3xl font-bold" :class="isDark ? 'text-white' : 'text-gray-900'">{{ statusPage.name }}</h1>
            <p v-if="statusPage.description" class="mt-2" :class="isDark ? 'text-slate-400' : 'text-gray-600'">{{ statusPage.description }}</p>
        </div>

        <!-- Overall status -->
        <div class="rounded-xl p-6 text-center" :class="isDark ? 'bg-white/5' : 'bg-white shadow'">
            <div class="flex items-center justify-center gap-3">
                <div :class="['h-4 w-4 rounded-full', allUp ? 'bg-emerald-500' : anyDown ? 'bg-red-500' : 'bg-gray-500']" />
                <span class="text-xl font-semibold" :class="isDark ? 'text-white' : 'text-gray-900'">
                    {{ allUp ? 'All Systems Operational' : anyDown ? 'System Degradation' : 'Unknown' }}
                </span>
            </div>
        </div>

        <!-- Active incidents -->
        <div v-if="activeIncidents.length > 0" class="space-y-3">
            <h2 class="text-lg font-semibold" :class="isDark ? 'text-white' : 'text-gray-900'">Active Incidents</h2>
            <div v-for="incident in activeIncidents" :key="incident.id" class="rounded-lg p-4 border" :class="isDark ? 'bg-red-500/10 border-red-500/30' : 'bg-red-50 border-red-200'">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium" :class="isDark ? 'text-white' : 'text-gray-900'">{{ incident.monitor_name }}</p>
                        <p class="text-sm mt-0.5" :class="isDark ? 'text-slate-400' : 'text-gray-600'">{{ incident.cause.replace(/_/g, ' ') }}</p>
                    </div>
                    <span class="text-xs" :class="isDark ? 'text-slate-500' : 'text-gray-500'">{{ formatTime(incident.started_at) }}</span>
                </div>
            </div>
        </div>

        <!-- Monitors -->
        <div class="space-y-4">
            <div v-for="monitor in monitors" :key="monitor.id" class="rounded-xl p-5" :class="isDark ? 'bg-white/5' : 'bg-white shadow'">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div :class="['h-3 w-3 rounded-full', monitor.current_status === 'up' ? 'bg-emerald-500' : monitor.current_status === 'down' ? 'bg-red-500' : 'bg-gray-500']" />
                        <span class="font-semibold" :class="isDark ? 'text-white' : 'text-gray-900'">{{ monitor.name }}</span>
                    </div>
                    <div class="text-right">
                        <span :class="['text-xs font-medium px-2 py-0.5 rounded-full', monitor.current_status === 'up' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400']">
                            {{ monitor.current_status === 'up' ? 'Operational' : 'Down' }}
                        </span>
                    </div>
                </div>

                <!-- 90-day bar -->
                <div class="mb-2">
                    <div class="flex gap-px">
                        <div v-for="(day, i) in monitor.daily_breakdown" :key="i"
                            :class="['h-6 flex-1 rounded-[1px] hover:opacity-80 transition-opacity', blockColor(day.status)]"
                            :title="day.date" />
                    </div>
                    <div class="flex justify-between mt-1">
                        <span class="text-xs" :class="isDark ? 'text-slate-500' : 'text-gray-400'">90 days ago</span>
                        <span class="text-xs font-mono" :class="isDark ? 'text-cyan-400' : 'text-cyan-600'">{{ monitor.uptime_90d.toFixed(2) }}% uptime</span>
                        <span class="text-xs" :class="isDark ? 'text-slate-500' : 'text-gray-400'">Today</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
