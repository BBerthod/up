<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(route('admin.users.store'), {
        onSuccess: () => form.reset(),
    })
}
</script>

<template>
    <Head title="Create User" />

    <div class="max-w-2xl mx-auto">
        <div class="mb-8">
            <Link :href="route('admin.users.index')" class="inline-flex items-center text-slate-400 hover:text-cyan-400 transition-colors mb-4">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" /></svg>
                Back to Users
            </Link>
            <h1 class="text-2xl font-bold text-white">Create User</h1>
            <p class="text-slate-400 mt-1">Add a new user to the platform</p>
        </div>

        <form @submit.prevent="submit" class="glass-intense p-8 space-y-6">
            <div>
                <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Full Name</label>
                <input id="name" v-model="form.name" type="text" class="form-input" placeholder="John Doe" required autofocus />
                <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">{{ form.errors.name }}</p>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1">Email Address</label>
                <input id="email" v-model="form.email" type="email" class="form-input" placeholder="john@example.com" required />
                <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">{{ form.errors.email }}</p>
            </div>

            <div>
                <label for="password" class="block text-sm font-medium text-slate-300 mb-1">Password</label>
                <input id="password" v-model="form.password" type="password" class="form-input" placeholder="••••••••" required />
                <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">{{ form.errors.password }}</p>
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-1">Confirm Password</label>
                <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="form-input" placeholder="••••••••" required />
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-white/10">
                <Link :href="route('admin.users.index')" class="px-4 py-2 rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors">Cancel</Link>
                <button type="submit" :disabled="form.processing" class="py-3 px-6 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20 disabled:opacity-50">
                    {{ form.processing ? 'Creating...' : 'Create User' }}
                </button>
            </div>
        </form>
    </div>
</template>
