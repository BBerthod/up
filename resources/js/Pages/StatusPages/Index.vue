<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import DataView from 'primevue/dataview'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Message from 'primevue/message'

interface StatusPage {
    id: number
    name: string
    slug: string
    is_active: boolean
    monitors_count: number
}

const props = defineProps<{
    statusPages: StatusPage[]
}>()

const flash = computed(() => usePage().props.flash as { success?: string } | undefined)

const deleteStatusPage = (sp: StatusPage) => {
    if (confirm(`Delete "${sp.name}"?`)) {
        router.delete(route('status-pages.destroy', sp.id))
    }
}
</script>

<template>
    <Head title="Status Pages" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-white">Status Pages</h1>
            <Link :href="route('status-pages.create')">
                <Button label="Create Status Page" icon="pi pi-plus" severity="primary" class="font-semibold" />
            </Link>
        </div>

        <Message v-if="flash?.success" severity="success" class="mb-6">{{ flash.success }}</Message>

        <DataView :value="statusPages" :layout="'list'" :paginator="statusPages.length > 10" :rows="10" class="glass overflow-hidden rounded-xl">
            <template #list="slotProps">
                <div v-for="(sp, index) in slotProps.items" :key="sp.id" class="p-4 border-b border-white/5 last:border-b-0 hover:bg-white/5 transition-colors">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3">
                                <p class="text-white font-medium text-lg">{{ sp.name }}</p>
                                <Tag :severity="sp.is_active ? 'success' : 'secondary'" :value="sp.is_active ? 'Active' : 'Inactive'" rounded class="text-xs py-0.5 h-auto"></Tag>
                            </div>
                            <div class="flex items-center gap-3 mt-1.5">
                                <code class="text-xs text-cyan-400 bg-white/5 px-2 py-0.5 rounded font-mono">/status/{{ sp.slug }}</code>
                                <span class="text-xs text-text-muted">{{ sp.monitors_count }} monitor{{ sp.monitors_count !== 1 ? 's' : '' }}</span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <a :href="route('status.show', sp.slug)" target="_blank">
                                <Button label="View" icon="pi pi-external-link" text size="small" class="text-slate-300 hover:text-white" />
                            </a>
                            <Link :href="route('status-pages.edit', sp.id)">
                                <Button label="Edit" icon="pi pi-pencil" text size="small" class="text-slate-300 hover:text-white" />
                            </Link>
                            <Button label="Delete" icon="pi pi-trash" text severity="danger" size="small" @click="deleteStatusPage(sp)" />
                        </div>
                    </div>
                </div>
            </template>
            <template #empty>
                 <div class="glass p-12 text-center">
                     <i class="pi pi-globe text-4xl text-text-muted mb-4 block" />
                    <h3 class="text-lg font-medium text-white mb-2">No status pages yet</h3>
                    <p class="text-text-secondary mb-6">Create a public status page to share uptime with your users.</p>
                    <Link :href="route('status-pages.create')">
                        <Button label="Create Your First Status Page" icon="pi pi-plus" severity="primary" class="font-semibold" />
                    </Link>
                </div>
            </template>
        </DataView>
    </div>
</template>
