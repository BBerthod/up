<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch, computed } from 'vue'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Tag from 'primevue/tag'
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
    if (!resolvedAt) return 'Ongoing'
    const seconds = Math.floor((new Date(resolvedAt).getTime() - new Date(startedAt).getTime()) / 1000)
    if (seconds < 60) return `${seconds}s`
    if (seconds < 3600) return `${Math.floor(seconds / 60)}m ${seconds % 60}s`
    const hours = Math.floor(seconds / 3600)
    const mins = Math.floor((seconds % 3600) / 60)
    return `${hours}h ${mins}m`
}

const formatDate = (date: string) => {
    return new Date(date).toLocaleString()
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

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-white">Incidents</h1>
                <Tag v-if="activeCount > 0" severity="danger" :value="`${activeCount} active`" rounded class="bg-red-500/20 text-red-400 border border-red-500/30" />
            </div>
            <div class="flex gap-2">
                <a :href="exportUrl('csv')" class="btn-secondary text-sm">
                    Export CSV
                </a>
                <a :href="exportUrl('json')" class="btn-secondary text-sm">
                    Export JSON
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3">
            <Select v-model="filters.status" :options="statusOptions" optionLabel="label" optionValue="value" placeholder="All Status" showClear class="w-36" />

            <Select v-model="filters.cause" :options="causeOptions" optionLabel="label" optionValue="value" placeholder="All Causes" showClear class="w-44" />

            <Select v-model="filters.monitor_id" :options="monitorOptions" optionLabel="label" optionValue="value" placeholder="All Monitors" showClear class="w-48" />

            <input v-model="filters.from" type="date" class="form-input text-sm w-40" placeholder="From" />
            <input v-model="filters.to" type="date" class="form-input text-sm w-40" placeholder="To" />
        </div>

        <!-- DataTable -->
         <DataTable :value="incidents.data" tableStyle="min-width: 50rem" class="glass overflow-hidden rounded-xl">
             <template #empty>
                 <div class="p-8 text-center text-text-muted">No incidents found</div>
             </template>

            <Column field="status" header="Status">
                <template #body="slotProps">
                     <Tag :severity="slotProps.data.resolved_at ? 'success' : 'danger'" :value="slotProps.data.resolved_at ? 'Resolved' : 'Active'" rounded :class="slotProps.data.resolved_at ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'">
                         <template #icon>
                             <span :class="['w-1.5 h-1.5 rounded-full mr-2', slotProps.data.resolved_at ? 'bg-emerald-400' : 'bg-red-400 animate-pulse']"></span>
                         </template>
                     </Tag>
                </template>
            </Column>

            <Column field="monitor_name" header="Monitor" sortable>
                <template #body="slotProps">
                    <Link :href="route('monitors.show', slotProps.data.monitor_id)" class="text-white hover:text-cyan-400 transition-colors font-medium">
                        {{ slotProps.data.monitor_name }}
                    </Link>
                    <span class="ml-2 text-xs text-text-muted uppercase">{{ typeLabels[slotProps.data.monitor_type] || slotProps.data.monitor_type }}</span>
                </template>
            </Column>

            <Column field="cause" header="Cause">
                <template #body="slotProps">
                    <span class="text-xs px-2 py-0.5 rounded bg-[rgba(255,255,255,0.08)] text-text-secondary">{{ slotProps.data.cause?.replace('_', ' ') }}</span>
                </template>
            </Column>

            <Column field="started_at" header="Started" sortable>
                 <template #body="slotProps">
                    <span class="text-sm text-text-secondary">{{ formatDate(slotProps.data.started_at) }}</span>
                </template>
            </Column>

            <Column field="resolved_at" header="Resolved" sortable>
                <template #body="slotProps">
                    <span class="text-sm text-text-secondary">{{ slotProps.data.resolved_at ? formatDate(slotProps.data.resolved_at) : '---' }}</span>
                </template>
            </Column>

            <Column header="Duration">
                <template #body="slotProps">
                    <span class="text-sm text-text-secondary">{{ formatDuration(slotProps.data.started_at, slotProps.data.resolved_at) }}</span>
                </template>
            </Column>
        </DataTable>

        <!-- Pagination -->
        <div v-if="incidents.last_page > 1" class="flex justify-center gap-1">
             <template v-for="link in incidents.links" :key="link.label">
                <Link v-if="link.url" :href="link.url"
                    :class="['px-3 py-1.5 text-sm rounded-lg transition-colors',
                        link.active ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' : 'text-text-muted hover:text-white hover:bg-[rgba(255,255,255,0.08)]']"
                    v-html="link.label" />
                <span v-else class="px-3 py-1.5 text-sm text-text-muted" v-html="link.label" />
            </template>
        </div>
    </div>
</template>
