<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

defineOptions({ layout: GuestLayout })

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Log in" />

    <form @submit.prevent="submit" class="space-y-6">
        <div>
            <label for="email" class="block text-sm font-medium text-slate-300 mb-1">Email</label>
            <input id="email" type="email" v-model="form.email" required autofocus autocomplete="email" class="form-input" placeholder="you@example.com" />
            <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">{{ form.errors.email }}</p>
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-300 mb-1">Password</label>
            <input id="password" type="password" v-model="form.password" required autocomplete="current-password" class="form-input" placeholder="••••••••" />
            <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">{{ form.errors.password }}</p>
        </div>

        <div class="flex items-center">
            <label class="flex items-center">
                <input type="checkbox" v-model="form.remember" class="rounded border-slate-600 bg-slate-700 text-cyan-500 focus:ring-cyan-500" />
                <span class="ms-2 text-sm text-slate-400">Remember me</span>
            </label>
        </div>

        <button type="submit" :disabled="form.processing" class="w-full py-3 px-4 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20 disabled:opacity-50">
            Sign in
        </button>
    </form>

    <p class="text-center text-sm text-slate-400 mt-6">
        New to Up?
        <Link :href="route('register')" class="text-cyan-400 hover:text-cyan-300 font-medium ml-1">Create an account</Link>
    </p>
</template>
