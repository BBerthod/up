<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import BackLink from '@/Components/BackLink.vue'
import PageHeader from '@/Components/PageHeader.vue'

const props = defineProps<{
    frequencies: Array<{ value: number; label: string }>
    modes: Array<{ value: string; label: string }>
}>()

const form = useForm({
    name: '',
    domain: '',
    mode: 'urls',
    sitemap_url: '',
    urls: [''] as string[],
    frequency_minutes: 60,
    max_urls: 50,
})

// Textarea binding for the URLs field (one per line)
const urlText = ref('')

// Auto-fill sitemap URL when domain changes and mode is sitemap
watch(() => form.domain, (domain) => {
    if (form.mode === 'sitemap' && domain) {
        const cleaned = domain.replace(/^https?:\/\//, '').replace(/\/$/, '')
        form.sitemap_url = `https://${cleaned}/sitemap.xml`
    }
})

watch(() => form.mode, (mode) => {
    if (mode === 'sitemap' && form.domain) {
        const cleaned = form.domain.replace(/^https?:\/\//, '').replace(/\/$/, '')
        form.sitemap_url = `https://${cleaned}/sitemap.xml`
    }
})

const submit = () => {
    if (form.mode === 'urls') {
        form.urls = urlText.value.split('\n').map(u => u.trim()).filter(Boolean)
    }
    form.post(route('warming.store'))
}

const modeInfo: Record<string, { label: string; description: string }> = {
    sitemap: { label: 'Sitemap', description: 'Crawl and warm URLs from your XML sitemap' },
    urls: { label: 'URL List', description: 'Warm a custom list of specific URLs' },
}
</script>

<template>
    <Head title="Create Warm Site" />

    <div class="max-w-2xl mx-auto space-y-6">
        <BackLink :href="route('warming.index')" label="Back to Cache Warming" />
        <PageHeader title="Create Warm Site" description="Configure a new cache warming target." />

        <form @submit.prevent="submit" class="glass p-6 space-y-6">
            <!-- Mode selector -->
            <div>
                <label class="block text-sm font-medium text-white mb-3">Warming Mode</label>
                <div class="grid grid-cols-2 gap-3">
                    <div
                        v-for="m in modes"
                        :key="m.value"
                        @click="form.mode = m.value"
                        :class="['p-3 rounded-lg cursor-pointer transition-colors border text-center',
                            form.mode === m.value
                                ? 'bg-emerald-500/20 border-emerald-500/30'
                                : 'bg-white/5 border-white/10 hover:bg-white/10']"
                    >
                        <p class="text-white text-sm font-medium">{{ modeInfo[m.value]?.label ?? m.label }}</p>
                        <p class="text-slate-500 text-xs mt-0.5">{{ modeInfo[m.value]?.description ?? '' }}</p>
                    </div>
                </div>
                <p v-if="form.errors.mode" class="text-sm text-red-400 mt-1">{{ form.errors.mode }}</p>
            </div>

            <!-- Name -->
            <div>
                <label class="block text-sm font-medium text-white mb-2">Name</label>
                <input v-model="form.name" type="text" class="form-input w-full" placeholder="My Website" required />
                <p v-if="form.errors.name" class="text-sm text-red-400 mt-1">{{ form.errors.name }}</p>
            </div>

            <!-- Domain -->
            <div>
                <label class="block text-sm font-medium text-white mb-2">Domain</label>
                <input v-model="form.domain" type="text" class="form-input w-full" placeholder="example.com" required />
                <p class="text-xs text-slate-500 mt-1">Without protocol prefix, e.g. example.com</p>
                <p v-if="form.errors.domain" class="text-sm text-red-400 mt-1">{{ form.errors.domain }}</p>
            </div>

            <!-- Sitemap URL (mode=sitemap) -->
            <div v-if="form.mode === 'sitemap'">
                <label class="block text-sm font-medium text-white mb-2">Sitemap URL</label>
                <input v-model="form.sitemap_url" type="url" class="form-input w-full" placeholder="https://example.com/sitemap.xml" required />
                <p class="text-xs text-slate-500 mt-1">Auto-filled from domain — change if your sitemap is at a custom path</p>
                <p v-if="form.errors.sitemap_url" class="text-sm text-red-400 mt-1">{{ form.errors.sitemap_url }}</p>
            </div>

            <!-- URLs textarea (mode=urls) -->
            <div v-if="form.mode === 'urls'">
                <label class="block text-sm font-medium text-white mb-2">URLs <span class="text-slate-500 font-normal">(one per line)</span></label>
                <textarea
                    v-model="urlText"
                    rows="6"
                    class="form-input w-full font-mono text-sm resize-y"
                    placeholder="https://example.com/&#10;https://example.com/about&#10;https://example.com/products"
                />
                <p class="text-xs text-slate-500 mt-1">Enter one full URL per line</p>
                <p v-if="form.errors.urls" class="text-sm text-red-400 mt-1">{{ form.errors.urls }}</p>
            </div>

            <!-- Frequency -->
            <div>
                <label class="block text-sm font-medium text-white mb-2">Frequency</label>
                <select v-model.number="form.frequency_minutes" class="form-input w-full">
                    <option v-for="f in frequencies" :key="f.value" :value="f.value">{{ f.label }}</option>
                </select>
                <p v-if="form.errors.frequency_minutes" class="text-sm text-red-400 mt-1">{{ form.errors.frequency_minutes }}</p>
            </div>

            <!-- Max URLs -->
            <div>
                <label class="block text-sm font-medium text-white mb-2">Max URLs per run</label>
                <input v-model.number="form.max_urls" type="number" min="1" max="500" class="form-input w-full" placeholder="50" />
                <p class="text-xs text-slate-500 mt-1">Limit how many URLs are warmed per scheduled run</p>
                <p v-if="form.errors.max_urls" class="text-sm text-red-400 mt-1">{{ form.errors.max_urls }}</p>
            </div>

            <button type="submit" :disabled="form.processing" class="btn-primary w-full py-3 px-4 disabled:opacity-50">
                {{ form.processing ? 'Creating...' : 'Create Warm Site' }}
            </button>
        </form>
    </div>
</template>
