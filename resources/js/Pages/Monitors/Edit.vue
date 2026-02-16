<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'

const props = defineProps<{
    monitor: any
    notificationChannels: Array<{ id: number; name: string; type: string }>
}>()

const form = useForm({
    name: props.monitor.name,
    type: props.monitor.type || 'http',
    url: props.monitor.url,
    method: props.monitor.method,
    expected_status_code: props.monitor.expected_status_code,
    keyword: props.monitor.keyword || '',
    port: props.monitor.port,
    dns_record_type: props.monitor.dns_record_type || 'A',
    dns_expected_value: props.monitor.dns_expected_value || '',
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

const portPresets = [
    { label: 'HTTP (80)', value: 80 },
    { label: 'HTTPS (443)', value: 443 },
    { label: 'SSH (22)', value: 22 },
    { label: 'FTP (21)', value: 21 },
    { label: 'SMTP (25)', value: 25 },
    { label: 'SMTP (587)', value: 587 },
    { label: 'MySQL (3306)', value: 3306 },
    { label: 'PostgreSQL (5432)', value: 5432 },
    { label: 'Redis (6379)', value: 6379 },
]

const dnsRecordTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SOA', 'SRV']

const typeLabels: Record<string, string> = {
    http: 'HTTP(S)',
    ping: 'Ping (ICMP)',
    port: 'TCP Port',
    dns: 'DNS Record',
}

const urlLabel = () => form.type === 'http' ? 'URL' : 'Host / Domain'
</script>

<template>
    <Head :title="'Edit ' + monitor.name" />

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
            <!-- Monitor Type (read-only badge) -->
            <div>
                <label class="block text-sm font-medium text-white mb-2">Monitor Type</label>
                <span class="inline-flex items-center px-3 py-1.5 rounded-lg text-sm font-medium bg-cyan-500/20 text-cyan-400 border border-cyan-500/30">
                    {{ typeLabels[form.type] || form.type }}
                </span>
            </div>

            <div>
                <label class="block text-sm font-medium text-white mb-2">Name</label>
                <input v-model="form.name" type="text" class="form-input w-full" required />
                <p v-if="form.errors.name" class="text-sm text-red-400 mt-1">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-white mb-2">{{ urlLabel() }}</label>
                <input v-model="form.url" :type="form.type === 'http' ? 'url' : 'text'" class="form-input w-full" required />
                <p v-if="form.errors.url" class="text-sm text-red-400 mt-1">{{ form.errors.url }}</p>
            </div>

            <!-- HTTP-specific fields -->
            <template v-if="form.type === 'http'">
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
                    <label class="block text-sm font-medium text-white mb-2">Keyword <span class="text-slate-500 font-normal">(optional)</span></label>
                    <input v-model="form.keyword" type="text" class="form-input w-full" placeholder="e.g. Welcome" />
                    <p class="text-xs text-slate-500 mt-1">Check response body for this string</p>
                </div>
            </template>

            <!-- Port-specific fields -->
            <template v-if="form.type === 'port'">
                <div>
                    <label class="block text-sm font-medium text-white mb-2">Port</label>
                    <div class="flex gap-3">
                        <input v-model.number="form.port" type="number" min="1" max="65535" class="form-input flex-1" required />
                        <select @change="form.port = Number(($event.target as HTMLSelectElement).value)" class="form-input w-48">
                            <option value="">Presets...</option>
                            <option v-for="p in portPresets" :key="p.value" :value="p.value">{{ p.label }}</option>
                        </select>
                    </div>
                    <p v-if="form.errors.port" class="text-sm text-red-400 mt-1">{{ form.errors.port }}</p>
                </div>
            </template>

            <!-- DNS-specific fields -->
            <template v-if="form.type === 'dns'">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-white mb-2">Record Type</label>
                        <select v-model="form.dns_record_type" class="form-input w-full">
                            <option v-for="t in dnsRecordTypes" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-white mb-2">Expected Value</label>
                        <input v-model="form.dns_expected_value" type="text" class="form-input w-full" required />
                    </div>
                </div>
            </template>

            <div>
                <label class="block text-sm font-medium text-white mb-2">Check Interval</label>
                <select v-model.number="form.interval" class="form-input w-full">
                    <option v-for="v in [1,2,3,5,10,15,30,60]" :key="v" :value="v">Every {{ v }} minute{{ v > 1 ? 's' : '' }}</option>
                </select>
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

            <button type="submit" :disabled="form.processing" class="btn-primary w-full py-3 px-4 disabled:opacity-50">
                {{ form.processing ? 'Updating...' : 'Update Monitor' }}
            </button>
        </form>
    </div>
</template>
