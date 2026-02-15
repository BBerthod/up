<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

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

const sortBy = (column: string) => {
    if (filters.value.sort === column) {
        filters.value.dir = filters.value.dir === 'asc' ? 'desc' : 'asc'
    } else {
        filters.value.sort = column
        filters.value.dir = 'desc'
    }
}

const sortIcon = (column: string) => {
    if (filters.value.sort !== column) return ''
    return filters.value.dir === 'asc' ? ' \u2191' : ' \u2193'
}

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
                <span v-if="activeCount > 0" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-500/20 text-red-400 border border-red-500/30">
                    {{ activeCount }} active
                </span>
            </div>
            <div class="flex gap-2">
                <a :href="exportUrl('csv')" class="px-3 py-2 text-sm rounded-lg bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 transition-colors">
                    Export CSV
                </a>
                <a :href="exportUrl('json')" class="px-3 py-2 text-sm rounded-lg bg-white/5 border border-white/10 text-slate-300 hover:bg-white/10 transition-colors">
                    Export JSON
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap gap-3">
            <select v-model="filters.status" class="form-input text-sm w-36">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="resolved">Resolved</option>
            </select>

            <select v-model="filters.cause" class="form-input text-sm w-44">
                <option value="">All Causes</option>
                <option v-for="c in causes" :key="c.value" :value="c.value">{{ c.label }}</option>
            </select>

            <select v-model="filters.monitor_id" class="form-input text-sm w-48">
                <option value="">All Monitors</option>
                <option v-for="m in monitors" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
            </select>

            <input v-model="filters.from" type="date" class="form-input text-sm w-40" placeholder="From" />
            <input v-model="filters.to" type="date" class="form-input text-sm w-40" placeholder="To" />
        </div>

        <!-- Table -->
        <div class="glass overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-white/10">
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase cursor-pointer hover:text-white" @click="sortBy('monitor_name')">
                            Monitor{{ sortIcon('monitor_name') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Cause</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase cursor-pointer hover:text-white" @click="sortBy('started_at')">
                            Started{{ sortIcon('started_at') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase cursor-pointer hover:text-white" @click="sortBy('resolved_at')">
                            Resolved{{ sortIcon('resolved_at') }}
                        </th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-400 uppercase">Duration</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="incident in incidents.data" :key="incident.id" class="border-b border-white/5 hover:bg-white/5 transition-colors">
                        <td class="px-4 py-3">
                            <span :class="['inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xs font-medium',
                                incident.resolved_at ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400']">
                                <span :class="['w-1.5 h-1.5 rounded-full', incident.resolved_at ? 'bg-emerald-400' : 'bg-red-400 animate-pulse']"></span>
                                {{ incident.resolved_at ? 'Resolved' : 'Active' }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <Link :href="route('monitors.show', incident.monitor_id)" class="text-white hover:text-cyan-400 transition-colors font-medium">
                                {{ incident.monitor_name }}
                            </Link>
                            <span class="ml-2 text-xs text-slate-500 uppercase">{{ typeLabels[incident.monitor_type] || incident.monitor_type }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-0.5 rounded bg-white/10 text-slate-300">{{ incident.cause?.replace('_', ' ') }}</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-slate-300">{{ formatDate(incident.started_at) }}</td>
                        <td class="px-4 py-3 text-sm text-slate-300">{{ incident.resolved_at ? formatDate(incident.resolved_at) : '—' }}</td>
                        <td class="px-4 py-3 text-sm text-slate-300">{{ formatDuration(incident.started_at, incident.resolved_at) }}</td>
                    </tr>
                    <tr v-if="incidents.data.length === 0">
                        <td colspan="6" class="px-4 py-12 text-center text-slate-500">No incidents found</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div v-if="incidents.last_page > 1" class="flex justify-center gap-1">
            <template v-for="link in incidents.links" :key="link.label">
                <Link v-if="link.url" :href="link.url"
                    :class="['px-3 py-1.5 text-sm rounded-lg transition-colors',
                        link.active ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' : 'text-slate-400 hover:text-white hover:bg-white/10']"
                    v-html="link.label" />
                <span v-else class="px-3 py-1.5 text-sm text-slate-600" v-html="link.label" />
            </template>
        </div>
    </div>
</template>
