<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

interface Channel {
    id: number
    name: string
    type: string
    is_active: boolean
    settings: Record<string, unknown>
}

const props = defineProps<{
    channels: Channel[]
}>()

const flash = computed(() => usePage().props.flash as { success?: string; error?: string } | undefined)

const typeColors: Record<string, string> = {
    email: 'bg-blue-500/20 text-blue-400',
    webhook: 'bg-purple-500/20 text-purple-400',
    slack: 'bg-orange-500/20 text-orange-400',
    discord: 'bg-indigo-500/20 text-indigo-400',
    telegram: 'bg-sky-500/20 text-sky-400',
    push: 'bg-green-500/20 text-green-400',
}

const deleteChannel = (channel: Channel) => {
    if (confirm(`Delete "${channel.name}"?`)) {
        router.delete(route('channels.destroy', channel.id))
    }
}

const testChannel = (channel: Channel) => {
    router.post(route('channels.test', channel.id))
}
</script>

<template>
    <Head title="Notification Channels" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-white">Notification Channels</h1>
            <Link :href="route('channels.create')" class="py-3 px-4 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20">
                Add Channel
            </Link>
        </div>

        <div v-if="flash?.success" class="p-4 rounded-lg bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 text-sm">
            {{ flash.success }}
        </div>
        <div v-if="flash?.error" class="p-4 rounded-lg bg-red-500/20 border border-red-500/30 text-red-400 text-sm">
            {{ flash.error }}
        </div>

        <div v-if="channels.length > 0" class="space-y-4">
            <div v-for="channel in channels" :key="channel.id" class="glass p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div :class="['status-dot', channel.is_active ? 'online' : '']" :style="!channel.is_active ? 'background-color: var(--color-muted)' : ''" />
                        <div>
                            <p class="text-white font-medium">{{ channel.name }}</p>
                            <span :class="['px-2 py-0.5 rounded-full text-xs font-medium uppercase', typeColors[channel.type] || 'bg-slate-500/20 text-slate-400']">{{ channel.type }}</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="testChannel(channel)" class="px-3 py-1.5 rounded-lg text-sm text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">Test</button>
                        <Link :href="route('channels.edit', channel.id)" class="px-3 py-1.5 rounded-lg text-sm text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">Edit</Link>
                        <button @click="deleteChannel(channel)" class="px-3 py-1.5 rounded-lg text-sm text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 transition-colors">Delete</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="glass p-12 text-center">
            <svg class="w-16 h-16 mx-auto mb-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" /></svg>
            <h3 class="text-lg font-medium text-white mb-2">No notification channels yet</h3>
            <p class="text-slate-400 mb-6">Set up channels to receive alerts when monitors go down.</p>
            <Link :href="route('channels.create')" class="py-3 px-4 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20 inline-block">Create Your First Channel</Link>
        </div>
    </div>
</template>
