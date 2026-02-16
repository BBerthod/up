<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import DataView from 'primevue/dataview'
import SelectButton from 'primevue/selectbutton'
import Button from 'primevue/button'
import Tag from 'primevue/tag'

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
    if (uptime === null) return 'text-text-muted'
    if (uptime > 99) return 'text-emerald-400'
    if (uptime > 95) return 'text-yellow-400'
    return 'text-red-400'
}

const filterMonitors = (status: string | null) => {
    router.get(route('monitors.index'), status ? { status } : {}, { preserveState: true })
}

const activeFilter = ref(props.filters.status || 'all')

const filterOptions = [
    { key: 'all', label: 'All' },
    { key: 'up', label: 'Up' },
    { key: 'down', label: 'Down' },
    { key: 'paused', label: 'Paused' }
]
</script>

<template>
    <Head title="Monitors" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-white">Monitors</h1>
            <Link :href="route('monitors.create')">
                <Button label="Add Monitor" icon="pi pi-plus" severity="primary" class="font-semibold" />
            </Link>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="glass p-4" title="Total number of configured monitors"><p class="text-text-secondary text-sm">Total</p><p class="text-2xl font-bold text-white font-mono">{{ stats.total }}</p></div>
            <div class="glass p-4" title="Monitors currently online"><p class="text-text-secondary text-sm">Up</p><p class="text-2xl font-bold text-emerald-400 font-mono">{{ stats.up }}</p></div>
            <div class="glass p-4" title="Monitors currently failing"><p class="text-text-secondary text-sm">Down</p><p class="text-2xl font-bold text-red-400 font-mono">{{ stats.down }}</p></div>
            <div class="glass p-4" title="Average response time across all monitors"><p class="text-text-secondary text-sm">Avg Response</p><p class="text-2xl font-bold text-white font-mono">{{ stats.avgMs }}<span class="text-sm text-text-secondary">ms</span></p></div>
        </div>

        <div class="flex gap-2">
            <SelectButton v-model="activeFilter" :options="filterOptions" optionLabel="label" optionValue="key" @change="filterMonitors(activeFilter)" :allowEmpty="false" />
        </div>

        <DataView :value="monitors" :layout="'grid'" :paginator="monitors.length > 12" :rows="12">
            <template #grid="slotProps">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 w-full">
                    <Link v-for="(monitor, index) in slotProps.items" :key="index" :href="route('monitors.show', monitor.id)" class="glass glass-hover p-4 block h-full">
                        <div class="flex items-center gap-3 mb-3">
                             <Tag :severity="!monitor.is_active ? 'secondary' : (monitor.latest_check?.status === 'up' ? 'success' : 'danger')" rounded style="width: 12px; height: 12px; padding: 0;" :class="{'animate-pulse': monitor.is_active && monitor.latest_check?.status === 'down'}">
                                 <span class="w-full h-full rounded-full"></span>
                             </Tag>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-white font-medium truncate">{{ monitor.name }}</span>
                                    <Tag v-if="!monitor.is_active" value="Paused" severity="secondary" rounded class="text-xs py-0.5 px-2 h-auto" />
                                </div>
                                <p class="text-text-muted text-sm truncate">{{ monitor.url }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 text-sm">
                            <span :class="['font-mono', getUptimeColor(monitor.uptime_24h)]">{{ monitor.uptime_24h !== null ? monitor.uptime_24h.toFixed(1) + '%' : '---%' }}</span>
                            <span class="font-mono text-text-secondary">{{ monitor.latest_check ? monitor.latest_check.response_time_ms + 'ms' : '---ms' }}</span>
                            <span class="text-xs text-text-muted ml-auto">{{ relativeTime(monitor.latest_check?.checked_at || null) }}</span>
                        </div>
                    </Link>
                </div>
            </template>
            <template #empty>
                 <div class="glass p-12 text-center">
                    <i class="pi pi-server text-4xl text-text-muted mb-4 block" />
                    <h3 class="text-lg font-medium text-white mb-2">No monitors yet</h3>
                    <p class="text-text-secondary mb-6">Start monitoring your services by adding your first monitor.</p>
                     <Link :href="route('monitors.create')">
                         <Button label="Create Your First Monitor" icon="pi pi-plus" severity="primary" class="font-semibold" />
                     </Link>
                </div>
            </template>
        </DataView>
    </div>
</template>
