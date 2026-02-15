<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'

interface User {
    id: number
    name: string
    email: string
    is_admin: boolean
    team: { id: number; name: string } | null
}

const props = defineProps<{ user: User }>()

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.put(route('admin.users.update', props.user.id), {
        onSuccess: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Edit User" />

    <div class="max-w-2xl mx-auto">
        <div class="mb-8">
            <Link :href="route('admin.users.index')" class="inline-flex items-center text-slate-400 hover:text-cyan-400 transition-colors mb-4">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to Users
            </Link>
            <h1 class="text-2xl font-bold text-white">Edit User</h1>
            <p class="text-slate-400 mt-1">Update user information</p>
        </div>

        <div class="glass p-4 mb-6 flex items-center gap-4">
            <div class="w-12 h-12 rounded-full bg-gradient-to-br from-cyan-500/20 to-teal-500/20 flex items-center justify-center border border-white/10">
                <span class="text-cyan-400 font-bold text-lg">{{ user.name.charAt(0).toUpperCase() }}</span>
            </div>
            <div>
                <h3 class="text-white font-semibold">{{ user.name }}</h3>
                <p class="text-slate-400 text-sm">{{ user.email }}</p>
            </div>
            <div class="ml-auto">
                <span v-if="user.is_admin" class="px-2 py-1 rounded-full bg-cyan-500/20 text-cyan-400 text-xs font-semibold border border-cyan-500/30">Admin</span>
                <span v-else class="px-2 py-1 rounded-lg bg-slate-700/30 text-slate-400 text-xs border border-white/5">Member</span>
            </div>
        </div>

        <form @submit.prevent="submit" class="glass-intense p-8 space-y-6">
            <div>
                <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Full Name</label>
                <input id="name" v-model="form.name" type="text" class="form-input" required autofocus />
                <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1">Email Address</label>
                <input id="email" v-model="form.email" type="email" class="form-input" required />
                <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-1">New Password</label>
                <input id="password" v-model="form.password" type="password" class="form-input" placeholder="Leave blank to keep current" />
                <p class="mt-1 text-xs text-slate-500">Leave blank to keep the current password</p>
                <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-1">Confirm New Password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="form-input" placeholder="Confirm new password" />
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-white/10">
                <Link :href="route('admin.users.index')" class="px-4 py-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors">Cancel</Link>
                <button type="submit" :disabled="form.processing" class="py-3 px-6 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20 disabled:opacity-50">
                    {{ form.processing ? 'Saving...' : 'Save Changes' }}
                </button>
            </div>
        </form>
    </div>
</template>
