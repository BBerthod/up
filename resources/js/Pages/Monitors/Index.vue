<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import PageHeader from '@/Components/PageHeader.vue'
import { computed, ref } from 'vue'
import { useRealtimeUpdates } from '@/Composables/useRealtimeUpdates'
import DataView from 'primevue/dataview'

useRealtimeUpdates({
    onMonitorChecked: ['monitors'],
})
import SelectButton from 'primevue/selectbutton'
import Button from 'primevue/button'

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
    if (!dateStr) return '-'
    const diffMs = Date.now() - new Date(dateStr).getTime()
    const mins = Math.floor(diffMs / 60000)
    if (mins < 1) return 'now'
    if (mins < 60) return `${mins}m`
    const hours = Math.floor(mins / 60)
    if (hours < 24) return `${hours}h`
    return `${Math.floor(hours / 24)}d`
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

    <div class="space-y-8">
        <!-- Header -->
        <PageHeader title="Monitors" description="Manage your uptime checks and settings.">
            <template #actions>
                <Link :href="route('monitors.create')">
                    <Button label="New Monitor" icon="pi pi-plus" class="!bg-white !text-black !border-white hover:!bg-zinc-200 font-medium" />
                </Link>
            </template>
        </PageHeader>

        <!-- Filters & Stats Row -->
        <div class="flex flex-col md:flex-row gap-6 items-end md:items-center justify-between border-b border-white/5 pb-6">
            <div class="flex items-center gap-2">
                 <SelectButton v-model="activeFilter" :options="filterOptions" optionLabel="label" optionValue="key" @change="filterMonitors(activeFilter)" :allowEmpty="false" class="w-full md:w-auto" />
            </div>
            <div class="flex items-center gap-6 text-sm">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    <span class="text-white font-medium">{{ stats.up }}</span>
                    <span class="text-zinc-500">Up</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-red-500"></span>
                    <span class="text-white font-medium">{{ stats.down }}</span>
                    <span class="text-zinc-500">Down</span>
                </div>
                 <div class="flex items-center gap-2">
                    <span class="text-white font-medium">{{ stats.avgMs }}ms</span>
                    <span class="text-zinc-500">Avg</span>
                </div>
            </div>
        </div>

        <!-- Linear List -->
        <DataView :value="monitors" :paginator="monitors.length > 20" :rows="20" class="bg-transparent">
            <template #list="slotProps">
                <div class="flex flex-col">
                    <div v-for="(monitor, index) in slotProps.items" :key="index"
                        class="group flex items-center justify-between py-4 border-b border-white/5 hover:bg-white/[0.02] transition-colors -mx-4 px-4 sm:mx-0 sm:px-2 rounded-lg cursor-pointer"
                         @click="router.visit(route('monitors.show', monitor.id))">

                        <div class="flex items-center gap-4 min-w-0">
                             <!-- Status Dot -->
                            <div class="relative flex items-center justify-center w-4 h-4 shrink-0">
                                <template v-if="!monitor.is_active">
                                    <div class="w-2.5 h-2.5 rounded-full border-2 border-zinc-600 bg-transparent" />
                                </template>
                                <template v-else-if="monitor.latest_check?.status === 'up'">
                                     <div class="w-2.5 h-2.5 rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.4)]" />
                                </template>
                                <template v-else>
                                     <span class="absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-25 animate-ping"></span>
                                     <div class="relative w-2.5 h-2.5 rounded-full bg-red-500" />
                                </template>
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-white font-medium tracking-tight truncate">{{ monitor.name }}</span>
                                    <span v-if="!monitor.is_active" class="px-1.5 py-0.5 rounded text-[10px] font-bold uppercase bg-zinc-800 text-zinc-400">Paused</span>
                                </div>
                                <p class="text-xs text-zinc-500 font-mono truncate hover:text-emerald-400 transition-colors">{{ monitor.url }}</p>
                            </div>
                        </div>

                        <!-- Metrics Column -->
                        <div class="flex items-center gap-8 md:gap-12 shrink-0 ml-4">
                             <div class="hidden md:block text-right w-20">
                                 <span class="block text-sm font-medium" :class="monitor.uptime_24h > 98 ? 'text-emerald-500' : 'text-zinc-300'">{{ monitor.uptime_24h ?? '-' }}%</span>
                                 <span class="text-[10px] text-zinc-600 uppercase tracking-wider">Uptime</span>
                             </div>

                             <div class="hidden sm:block text-right w-16">
                                 <span class="block text-sm font-mono text-zinc-300">{{ monitor.latest_check?.response_time_ms ?? '-' }}ms</span>
                                 <span class="text-[10px] text-zinc-600 uppercase tracking-wider">Latency</span>
                             </div>

                             <div class="text-right w-16">
                                 <span class="block text-xs text-zinc-400">{{ relativeTime(monitor.latest_check?.checked_at) }}</span>
                                 <span class="text-[10px] text-zinc-600 uppercase tracking-wider">Checked</span>
                             </div>

                             <div class="text-zinc-600 group-hover:text-emerald-500 transition-colors">
                                 <i class="pi pi-chevron-right text-xs" />
                             </div>
                        </div>

                    </div>
                </div>
            </template>
            <template #empty>
                 <div class="flex flex-col items-center justify-center py-20 text-center border border-dashed border-white/5 rounded-2xl bg-white/[0.02]">
                    <div class="p-4 mb-4 rounded-full bg-emerald-500/10">
                        <i class="pi pi-server text-2xl text-emerald-500" />
                    </div>
                    <h3 class="text-lg font-medium text-white">No monitors found</h3>
                    <p class="text-zinc-500 mt-1 max-w-sm mb-6">Get started by creating your first uptime monitor.</p>
                     <Link :href="route('monitors.create')">
                         <Button label="Add Monitor" icon="pi pi-plus" class="bg-white text-black border-white hover:bg-zinc-200" />
                     </Link>
                </div>
            </template>
        </DataView>
    </div>
</template>
