<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import PageHeader from '@/Components/PageHeader.vue'
import { ref, watch, computed } from 'vue'
import { useRealtimeUpdates } from '@/Composables/useRealtimeUpdates'
import Tag from 'primevue/tag'

useRealtimeUpdates({
    onMonitorChecked: ['incidents', 'activeCount'],
})
import Select from 'primevue/select'

const props = defineProps<{
    incidents: any
    activeCount: number
    monitors: Array<{ id: number; name: string }>
    causes: Array<{ value: string; label: string }>
    filters: Record<string, string>
}>()

const filters = ref({
    status: props.filters.status || '',
    cause: props.filters.cause || '',
    monitor_id: props.filters.monitor_id || '',
    from: props.filters.from || '',
    to: props.filters.to || '',
    sort: props.filters.sort || 'started_at',
    dir: props.filters.dir || 'desc',
})

const applyFilters = () => {
    const params: Record<string, string> = {}
    Object.entries(filters.value).forEach(([k, v]) => { if (v) params[k] = v })
    router.get(route('incidents.index'), params, { preserveState: true })
}

watch(filters, applyFilters, { deep: true })

const statusOptions = [
    { label: 'Active', value: 'active' },
    { label: 'Resolved', value: 'resolved' },
]

const causeOptions = computed(() => props.causes)

const monitorOptions = computed(() =>
    props.monitors.map(m => ({ label: m.name, value: String(m.id) }))
)

const formatDuration = (startedAt: string, resolvedAt: string | null) => {
    if (!resolvedAt) return '-'
    const seconds = Math.floor((new Date(resolvedAt).getTime() - new Date(startedAt).getTime()) / 1000)
    if (seconds < 60) return `${seconds}s`
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`
    const hours = Math.floor(seconds / 3600)
    const mins = Math.floor((seconds % 3600) / 60)
    return `${hours}h ${mins}m`
}

const formatDate = (date: string) => {
    return new Date(date).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false })
}

const exportUrl = (format: string) => {
    const params = new URLSearchParams()
    params.set('format', format)
    Object.entries(filters.value).forEach(([k, v]) => { if (v && k !== 'sort' && k !== 'dir') params.set(k, v) })
    return route('incidents.export') + '?' + params.toString()
}

const typeLabels: Record<string, string> = {
    http: 'HTTP',
    ping: 'Ping',
    port: 'Port',
    dns: 'DNS',
}
</script>

<template>
    <Head title="Incidents" />

    <div class="space-y-8">
        <PageHeader title="Incidents" description="History of downtime and alerts.">
            <template #actions>
                <a :href="exportUrl('csv')" class="px-3 py-2 rounded-lg text-xs font-medium bg-white/5 hover:bg-white/10 text-white transition-colors border border-white/5">
                    Export CSV
                </a>
            </template>
        </PageHeader>

        <!-- Linear Filters -->
        <div class="flex flex-wrap items-center gap-3 pb-6 border-b border-white/5">
            <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="Status" showClear class="w-32 !bg-transparent !border-white/10" />
            <Select v-model="filters.cause" :options="causeOptions" optionLabel="label" optionValue="value" placeholder="Cause" showClear class="w-40 !bg-transparent !border-white/10" />
            <Select v-model="filters.monitor_id" :options="monitorOptions" optionLabel="label" optionValue="value" placeholder="Monitor" showClear class="w-48 !bg-transparent !border-white/10" />

            <div class="h-4 w-px bg-white/10 mx-2 hidden md:block"></div>

            <input v-model="filters.from" type="date" class="bg-transparent border border-white/10 rounded-md px-3 py-2 text-sm text-zinc-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-colors" placeholder="From" />
            <span class="text-zinc-600">-</span>
            <input v-model="filters.to" type="date" class="bg-transparent border border-white/10 rounded-md px-3 py-2 text-sm text-zinc-300 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 outline-none transition-colors" placeholder="To" />
        </div>

        <!-- Linear List Header -->
        <div class="hidden md:grid grid-cols-12 gap-4 text-xs font-semibold text-zinc-500 uppercase tracking-wider px-4">
            <div class="col-span-1">Status</div>
            <div class="col-span-4">Monitor</div>
            <div class="col-span-2">Cause</div>
            <div class="col-span-3">Started</div>
            <div class="col-span-2 text-right">Duration</div>
        </div>

        <!-- List Items -->
        <div class="space-y-1">
             <div v-for="incident in incidents.data" :key="incident.id" class="group grid grid-cols-1 md:grid-cols-12 gap-4 items-center py-3 px-4 rounded-lg bg-transparent hover:bg-white/[0.02] transition-colors border-b border-white/5 last:border-0">

                <!-- Status -->
                <div class="col-span-1 flex items-center gap-2">
                     <div v-if="!incident.resolved_at" class="relative flex h-2.5 w-2.5">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500"></span>
                    </div>
                    <div v-else class="h-2.5 w-2.5 rounded-full bg-emerald-500/50"></div>
                    <span class="md:hidden text-sm font-medium text-white">{{ incident.resolved_at ? 'Resolved' : 'Active' }}</span>
                </div>

                <!-- Monitor -->
                <div class="col-span-4 min-w-0">
                    <Link :href="route('monitors.show', incident.monitor_id)" class="block text-sm font-medium text-white hover:text-emerald-400 transition-colors truncate">
                        {{ incident.monitor_name }}
                    </Link>
                    <span class="text-[10px] text-zinc-600 font-mono">{{ typeLabels[incident.monitor_type] || incident.monitor_type }}</span>
                </div>

                <!-- Cause -->
                <div class="col-span-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded textxs font-medium bg-white/5 text-zinc-400 border border-white/5">
                        {{ incident.cause?.replace('_', ' ') }}
                    </span>
                </div>

                <!-- Time -->
                <div class="col-span-3 font-mono text-sm text-zinc-400">
                    {{ formatDate(incident.started_at) }}
                </div>

                <!-- Duration -->
                <div class="col-span-2 text-right font-mono text-sm" :class="incident.resolved_at ? 'text-zinc-500' : 'text-zinc-300'">
                    {{ formatDuration(incident.started_at, incident.resolved_at) }}
                </div>
            </div>

             <!-- Empty State -->
            <div v-if="incidents.data.length === 0" class="py-12 text-center">
                 <p class="text-zinc-500">No incidents found matching current filters.</p>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="incidents.last_page > 1" class="flex justify-center gap-2 pt-8">
             <template v-for="link in incidents.links" :key="link.label">
                <Link v-if="link.url" :href="link.url"
                    :class="['px-3 py-1.5 text-xs font-medium rounded-md transition-colors font-mono',
                        link.active ? 'bg-emerald-500/10 text-emerald-500 border border-emerald-500/20' : 'text-zinc-500 hover:text-zinc-300 hover:bg-white/5']"
                    v-html="link.label" />
                <span v-else class="px-3 py-1.5 text-xs font-medium text-zinc-700 font-mono" v-html="link.label" />
            </template>
        </div>
    </div>
</template>
