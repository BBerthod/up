<script setup lang="ts">
import { Head, useForm, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'

defineOptions({ layout: GuestLayout })

const status = computed(() => (usePage().props as any).status)

const form = useForm({
    email: '',
})

const submit = () => {
    form.post(route('password.email'))
}
</script>

<template>
    <Head title="Forgot Password" />

    <div class="text-center mb-6">
        <h2 class="text-xl font-bold text-white mb-2">Forgot Password</h2>
        <p class="text-slate-400 text-sm">Enter your email and we'll send you a reset link.</p>
    </div>

    <div v-if="status" class="mb-6 p-4 rounded-lg bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 text-sm">
        {{ status }}
    </div>

    <form @submit.prevent="submit" class="space-y-6">
        <div>
            <label for="email" class="block text-sm font-medium text-slate-300 mb-1">Email</label>
            <input id="email" type="email" v-model="form.email" required autofocus autocomplete="email" class="form-input" placeholder="you@example.com" />
            <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">{{ form.errors.email }}</p>
        </div>

        <button type="submit" :disabled="form.processing" class="btn-primary w-full py-3 px-4 disabled:opacity-50">
            {{ form.processing ? 'Sending...' : 'Send Reset Link' }}
        </button>
    </form>

    <div class="mt-6 text-center">
        <Link :href="route('login')" class="text-sm text-cyan-400 hover:text-cyan-300 transition-colors">Back to login</Link>
    </div>
</template>
