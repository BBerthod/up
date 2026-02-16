<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import DataView from 'primevue/dataview'
import Button from 'primevue/button'
import Tag from 'primevue/tag'
import Message from 'primevue/message'
import PageHeader from '@/Components/PageHeader.vue'
import EmptyState from '@/Components/EmptyState.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'
import CopyButton from '@/Components/CopyButton.vue'

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

const showDeleteDialog = ref(false)
const pageToDelete = ref<StatusPage | null>(null)

const deleteStatusPage = (sp: StatusPage) => {
    pageToDelete.value = sp
    showDeleteDialog.value = true
}

const confirmDelete = () => {
    if (pageToDelete.value) {
        router.delete(route('status-pages.destroy', pageToDelete.value.id))
    }
}

const statusPageUrl = (slug: string) => `/status/${slug}`
</script>

<template>
    <Head title="Status Pages" />

    <div class="space-y-6">
        <PageHeader title="Status Pages" description="Create public pages to share uptime status with your users.">
            <template #actions>
                <Link :href="route('status-pages.create')">
                    <Button label="Create Status Page" icon="pi pi-plus" severity="primary" class="font-semibold" />
                </Link>
            </template>
        </PageHeader>

        <Message v-if="flash?.success" severity="success" class="mb-6">{{ flash.success }}</Message>

        <DataView :value="statusPages" :layout="'list'" :paginator="statusPages.length > 10" :rows="10" class="glass overflow-hidden rounded-xl">
            <template #list="slotProps">
                <div v-for="sp in slotProps.items" :key="sp.id" class="p-4 border-b border-white/5 last:border-b-0 hover:bg-white/5 transition-colors">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-3">
                                <p class="text-white font-medium text-lg">{{ sp.name }}</p>
                                <Tag :severity="sp.is_active ? 'success' : 'secondary'" :value="sp.is_active ? 'Active' : 'Inactive'" rounded class="text-xs py-0.5 h-auto" />
                            </div>
                            <div class="flex items-center gap-3 mt-1.5">
                                <div class="flex items-center gap-1.5">
                                    <code class="text-xs text-cyan-400 bg-white/5 px-2 py-0.5 rounded font-mono">{{ statusPageUrl(sp.slug) }}</code>
                                    <CopyButton :text="statusPageUrl(sp.slug)" />
                                </div>
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
                <EmptyState
                    title="No status pages yet"
                    description="Create a public status page to share uptime with your users."
                    icon="globe"
                    icon-color="cyan"
                >
                    <template #action>
                        <Link :href="route('status-pages.create')">
                            <Button label="Create Your First Status Page" icon="pi pi-plus" severity="primary" class="font-semibold" />
                        </Link>
                    </template>
                </EmptyState>
            </template>
        </DataView>
    </div>

    <ConfirmDialog
        v-model:show="showDeleteDialog"
        title="Delete Status Page"
        :message="`Are you sure you want to delete '${pageToDelete?.name}'? This action cannot be undone.`"
        confirm-label="Delete"
        variant="danger"
        @confirm="confirmDelete"
    />
</template>
