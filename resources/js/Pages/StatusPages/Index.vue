<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

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
            <Link :href="route('status-pages.create')" title="Create a new public status page" class="py-3 px-4 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20">
                Create Status Page
            </Link>
        </div>

        <div v-if="flash?.success" class="p-4 rounded-lg bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ flash.success }}
        </div>

        <div v-if="statusPages.length > 0" class="space-y-4">
            <div v-for="sp in statusPages" :key="sp.id" class="glass p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="flex items-center gap-3">
                            <p class="text-white font-medium">{{ sp.name }}</p>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium', sp.is_active ? 'bg-emerald-500/20 text-emerald-400' : 'bg-slate-500/20 text-slate-400']">
                                {{ sp.is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </div>
                        <div class="flex items-center gap-3 mt-1">
                            <code class="text-xs text-cyan-400 bg-white/5 px-2 py-0.5 rounded">/status/{{ sp.slug }}</code>
                            <span class="text-xs text-slate-500">{{ sp.monitors_count }} monitor{{ sp.monitors_count !== 1 ? 's' : '' }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a :href="route('status.show', sp.slug)" target="_blank" title="View public status page" class="px-3 py-1.5 rounded-lg text-sm text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">View</a>
                        <Link :href="route('status-pages.edit', sp.id)" title="Edit status page settings" class="px-3 py-1.5 rounded-lg text-sm text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">Edit</Link>
                        <button @click="deleteStatusPage(sp)" title="Delete this status page" class="px-3 py-1.5 rounded-lg text-sm text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 transition-colors">Delete</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="glass p-12 text-center">
            <svg class="w-16 h-16 mx-auto mb-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10" stroke-width="1.5" /><line x1="2" y1="12" x2="22" y2="12" stroke-width="1.5" /><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" stroke-width="1.5" /></svg>
            <h3 class="text-lg font-medium text-white mb-2">No status pages yet</h3>
            <p class="text-slate-400 mb-6">Create a public status page to share uptime with your users.</p>
            <Link :href="route('status-pages.create')" class="py-3 px-4 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20 inline-block">Create Your First Status Page</Link>
        </div>
    </div>
</template>
