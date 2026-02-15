<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'

interface User {
    id: number
    name: string
    email: string
    is_admin: boolean
    created_at: string
    team: { id: number; name: string } | null
}

interface PaginatedUsers {
    data: User[]
    links: { url: string | null; label: string; active: boolean }[]
    current_page: number
    last_page: number
}

defineProps<{
    users: PaginatedUsers
}>()

const deleteUser = (userId: number, userName: string) => {
    if (confirm(`Are you sure you want to delete "${userName}"?`)) {
        router.delete(route('admin.users.destroy', userId), { preserveScroll: true })
    }
}
</script>

<template>
    <Head title="Users" />

    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-white">Users</h1>
                <p class="text-slate-400 mt-1">Manage platform users and permissions</p>
            </div>
            <Link :href="route('admin.users.create')" class="py-3 px-4 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20">
                Add User
            </Link>
        </div>

        <div class="glass overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-white/10">
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Email</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Team</th>
                            <th class="px-6 py-4 text-left text-xs font-semibold text-slate-300 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-4 text-right text-xs font-semibold text-slate-300 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-white/5 transition-colors">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-full bg-gradient-to-br from-cyan-500/20 to-teal-500/20 flex items-center justify-center border border-white/10">
                                        <span class="text-cyan-400 font-semibold text-sm">{{ user.name.charAt(0).toUpperCase() }}</span>
                                    </div>
                                    <span class="text-white font-medium">{{ user.name }}</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-slate-300">{{ user.email }}</td>
                            <td class="px-6 py-4">
                                <span v-if="user.team" class="px-2 py-1 rounded-lg bg-slate-700/50 text-slate-300 text-sm border border-white/5">{{ user.team.name }}</span>
                                <span v-else class="text-slate-500 text-sm">No team</span>
                            </td>
                            <td class="px-6 py-4">
                                <span v-if="user.is_admin" class="px-2 py-1 rounded-full bg-cyan-500/20 text-cyan-400 text-xs font-semibold border border-cyan-500/30">Admin</span>
                                <span v-else class="px-2 py-1 rounded-lg bg-slate-700/30 text-slate-400 text-xs border border-white/5">Member</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <Link :href="route('admin.users.edit', user.id)" class="px-3 py-1.5 rounded-lg text-sm text-slate-300 hover:text-white bg-white/5 hover:bg-white/10 border border-white/10 transition-colors">Edit</Link>
                                    <button @click="deleteUser(user.id, user.name)" class="px-3 py-1.5 rounded-lg text-sm text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 border border-red-500/20 transition-colors">Delete</button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="5" class="px-6 py-12 text-center">
                                <svg class="w-16 h-16 mx-auto mb-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <h3 class="text-lg font-medium text-white mb-2">No users yet</h3>
                                <p class="text-slate-400 mb-6">Get started by creating your first user.</p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div v-if="users.last_page > 1" class="px-6 py-4 border-t border-white/10 flex items-center justify-between">
                <p class="text-sm text-slate-400">Page {{ users.current_page }} of {{ users.last_page }}</p>
                <div class="flex items-center gap-1">
                    <template v-for="(link, index) in users.links" :key="index">
                        <Link v-if="link.url" :href="link.url" class="px-3 py-1.5 rounded-lg text-sm transition-colors" :class="link.active ? 'bg-cyan-500/20 text-cyan-400 border border-cyan-500/30' : 'text-slate-400 hover:text-white hover:bg-white/10'" v-html="link.label" />
                        <span v-else class="px-3 py-1.5 text-sm text-slate-600" v-html="link.label" />
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>
