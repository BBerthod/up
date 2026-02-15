<script setup lang="ts">
import { Head, useForm, usePage, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { usePushNotifications } from '@/Composables/usePushNotifications'

interface Member { id: number; name: string; email: string; created_at: string }
interface Token { id: number; name: string; created_at: string; last_used_at: string | null }

const props = defineProps<{
    team: { id: number; name: string }
    members: Member[]
    tokens: Token[]
}>()

const flash = computed(() => (usePage().props as any).flash)

const teamForm = useForm({ name: props.team.name })
const tokenForm = useForm({ name: '' })

const updateTeam = () => teamForm.put('/settings/team')
const createToken = () => tokenForm.post('/settings/tokens', { onSuccess: () => tokenForm.reset() })
const deleteToken = (id: number) => { if (confirm('Revoke this token?')) router.delete(`/settings/tokens/${id}`) }

const formatDate = (d: string) => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })

const { isSupported, isSubscribed, isLoading, error, subscribe, unsubscribe } = usePushNotifications()
</script>

<template>
    <Head title="Settings" />

    <div class="space-y-6">
        <h1 class="text-2xl font-bold text-white">Settings</h1>

        <div v-if="flash?.success" class="p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm">
            {{ flash.success }}
        </div>

        <div class="glass p-6">
            <h3 class="text-white font-medium mb-4">Team Settings</h3>
            <form @submit.prevent="updateTeam" class="flex items-end gap-4">
                <div class="flex-1">
                    <label class="block text-sm text-slate-400 mb-1">Team Name</label>
                    <input v-model="teamForm.name" type="text" class="form-input w-full" />
                    <p v-if="teamForm.errors.name" class="text-red-400 text-sm mt-1">{{ teamForm.errors.name }}</p>
                </div>
                <button type="submit" class="btn-primary" :disabled="teamForm.processing">Save</button>
            </form>
        </div>

        <div class="glass p-6">
            <h3 class="text-white font-medium mb-4">Team Members</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead><tr class="text-left text-slate-400 text-sm border-b border-white/10"><th class="pb-3 font-medium">Name</th><th class="pb-3 font-medium">Email</th><th class="pb-3 font-medium">Joined</th></tr></thead>
                    <tbody>
                        <tr v-for="member in members" :key="member.id" class="border-b border-white/5 last:border-0">
                            <td class="py-3 text-white text-sm">{{ member.name }}</td>
                            <td class="py-3 text-slate-300 text-sm font-mono">{{ member.email }}</td>
                            <td class="py-3 text-slate-400 text-sm">{{ formatDate(member.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="glass p-6">
            <h3 class="text-white font-medium mb-4">API Tokens</h3>
            <form @submit.prevent="createToken" class="flex items-end gap-4 mb-6">
                <div class="flex-1">
                    <label class="block text-sm text-slate-400 mb-1">Token Name</label>
                    <input v-model="tokenForm.name" type="text" class="form-input w-full" placeholder="e.g. CLI Access" />
                    <p v-if="tokenForm.errors.name" class="text-red-400 text-sm mt-1">{{ tokenForm.errors.name }}</p>
                </div>
                <button type="submit" title="Generate a new API token" class="btn-primary" :disabled="tokenForm.processing">Create Token</button>
            </form>

            <div v-if="tokens.length > 0" class="overflow-x-auto">
                <table class="w-full">
                    <thead><tr class="text-left text-slate-400 text-sm border-b border-white/10"><th class="pb-3 font-medium">Name</th><th class="pb-3 font-medium">Created</th><th class="pb-3 font-medium">Last Used</th><th class="pb-3 font-medium"></th></tr></thead>
                    <tbody>
                        <tr v-for="token in tokens" :key="token.id" class="border-b border-white/5 last:border-0">
                            <td class="py-3 text-white text-sm font-mono">{{ token.name }}</td>
                            <td class="py-3 text-slate-400 text-sm">{{ formatDate(token.created_at) }}</td>
                            <td class="py-3 text-slate-400 text-sm">{{ token.last_used_at ? formatDate(token.last_used_at) : 'Never' }}</td>
                            <td class="py-3 text-right">
                                <button @click="deleteToken(token.id)" title="Revoke this token permanently" class="text-red-400 hover:text-red-300 text-sm transition-colors">Revoke</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p v-else class="text-slate-500 text-center py-4">No API tokens created yet</p>
        </div>

        <div class="glass p-6">
            <h3 class="text-white font-medium mb-4">Push Notifications</h3>
            <p v-if="!isSupported" class="text-slate-500">Push notifications are not supported in this browser.</p>
            <template v-else>
                <div class="flex items-center justify-between p-4 rounded-lg border transition-colors" :class="isSubscribed ? 'bg-cyan-500/20 border-cyan-500/30' : 'bg-white/5 border-white/10'">
                    <div class="flex items-center gap-3">
                        <div class="w-2.5 h-2.5 rounded-full" :class="isSubscribed ? 'bg-cyan-400' : 'bg-slate-500'" />
                        <span class="text-white text-sm">{{ isSubscribed ? 'Enabled' : 'Disabled' }}</span>
                    </div>
                    <button @click="isSubscribed ? unsubscribe() : subscribe()" :disabled="isLoading" class="text-sm px-3 py-1.5 rounded transition-colors disabled:opacity-50" :class="isSubscribed ? 'bg-cyan-500/20 text-cyan-400 hover:bg-cyan-500/30' : 'bg-white/10 text-white hover:bg-white/20'">
                        <span v-if="isLoading" class="flex items-center gap-2">
                            <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                            {{ isSubscribed ? 'Disabling...' : 'Enabling...' }}
                        </span>
                        <span v-else>{{ isSubscribed ? 'Disable' : 'Enable' }}</span>
                    </button>
                </div>
                <p v-if="error" class="text-red-400 text-sm mt-2">{{ error }}</p>
                <p class="text-slate-500 text-xs mt-3">Push notifications are per-browser. Enable on each device where you want to receive alerts.</p>
            </template>
        </div>
    </div>
</template>
