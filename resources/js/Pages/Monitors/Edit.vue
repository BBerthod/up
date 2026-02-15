<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps<{
    monitor: any
    notificationChannels: Array<{ id: number; name: string; type: string }>
}>()

const form = useForm({
    name: props.monitor.name,
    url: props.monitor.url,
    method: props.monitor.method,
    expected_status_code: props.monitor.expected_status_code,
    keyword: props.monitor.keyword || '',
    interval: props.monitor.interval,
    warning_threshold_ms: props.monitor.warning_threshold_ms,
    critical_threshold_ms: props.monitor.critical_threshold_ms,
    notification_channels: [...(props.monitor.notification_channel_ids || [])],
})

const submit = () => form.put(route('monitors.update', props.monitor.id))

const toggleChannel = (id: number) => {
    const i = form.notification_channels.indexOf(id)
    i === -1 ? form.notification_channels.push(id) : form.notification_channels.splice(i, 1)
}
</script>

<template>
    <Head title="Edit Monitor" />

    <div class="max-w-2xl mx-auto space-y-6">
        <Link :href="route('monitors.index')" class="inline-flex items-center gap-2 text-slate-400 hover:text-white transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" /></svg>
            Back to Monitors
        </Link>

        <div>
            <h1 class="text-2xl font-bold text-white">Edit Monitor</h1>
            <p class="text-slate-400 mt-1">Update settings for {{ monitor.name }}.</p>
        </div>

        <form @submit.prevent="submit" class="glass p-6 space-y-6">
            <div>
                <label class="block text-sm font-medium text-white mb-2">Name</label>
                <input v-model="form.name" type="text" class="form-input w-full" required />
                <p v-if="form.errors.name" class="text-sm text-red-400 mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-white mb-2">URL</label>
                <input v-model="form.url" type="url" class="form-input w-full" required />
                <p v-if="form.errors.url" class="text-sm text-red-400 mt-1">{{ form.errors.url }}</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white mb-2">Method</label>
                    <select v-model="form.method" class="form-input w-full">
                        <option value="GET">GET</option><option value="POST">POST</option><option value="HEAD">HEAD</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-white mb-2">Expected Status</label>
                    <input v-model.number="form.expected_status_code" type="number" class="form-input w-full" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-white mb-2">Check Interval</label>
                <select v-model.number="form.interval" class="form-input w-full">
                    <option v-for="v in [1,2,3,5,10,15,30,60]" :key="v" :value="v">Every {{ v }} minute{{ v > 1 ? 's' : '' }}</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-white mb-2">Keyword <span class="text-slate-500 font-normal">(optional)</span></label>
                <input v-model="form.keyword" type="text" class="form-input w-full" placeholder="e.g. Welcome" />
                <p class="text-xs text-slate-500 mt-1">Check response body for this string</p>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-white mb-2">Warning Threshold <span class="text-slate-500 font-normal">(ms)</span></label>
                    <input v-model.number="form.warning_threshold_ms" type="number" class="form-input w-full" placeholder="e.g. 1000" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-white mb-2">Critical Threshold <span class="text-slate-500 font-normal">(ms)</span></label>
                    <input v-model.number="form.critical_threshold_ms" type="number" class="form-input w-full" placeholder="e.g. 3000" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-white mb-3">Notification Channels</label>
                <div v-if="notificationChannels.length > 0" class="space-y-2">
                    <div v-for="ch in notificationChannels" :key="ch.id" @click="toggleChannel(ch.id)"
                        :class="['flex items-center gap-3 p-3 rounded-lg cursor-pointer transition-colors border',
                            form.notification_channels.includes(ch.id) ? 'bg-cyan-500/20 border-cyan-500/30' : 'bg-white/5 border-white/10 hover:bg-white/10']">
                        <div class="flex-1"><p class="text-white text-sm font-medium">{{ ch.name }}</p><p class="text-slate-500 text-xs uppercase">{{ ch.type }}</p></div>
                        <div :class="['w-5 h-5 rounded border-2 flex items-center justify-center', form.notification_channels.includes(ch.id) ? 'bg-cyan-500 border-cyan-500' : 'border-slate-500']">
                            <svg v-if="form.notification_channels.includes(ch.id)" class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                        </div>
                    </div>
                </div>
                <p v-else class="text-slate-500 text-sm py-4 text-center bg-white/5 rounded-lg">No notification channels available</p>
            </div>

            <button type="submit" :disabled="form.processing" class="w-full py-3 px-4 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20 disabled:opacity-50">
                {{ form.processing ? 'Updating...' : 'Update Monitor' }}
            </button>
        </form>
    </div>
</template>
