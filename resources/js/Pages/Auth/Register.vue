<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'

defineOptions({ layout: GuestLayout })

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Register" />

    <form @submit.prevent="submit" class="space-y-6">
        <div>
            <label for="name" class="block text-sm font-medium text-slate-300 mb-1">Name</label>
            <input id="name" type="text" v-model="form.name" required autofocus autocomplete="name" class="form-input" placeholder="John Doe" />
            <p v-if="form.errors.name" class="mt-2 text-sm text-red-400">{{ form.errors.name }}</p>
        </div>

        <div>
            <label for="email" class="block text-sm font-medium text-slate-300 mb-1">Email</label>
            <input id="email" type="email" v-model="form.email" required autocomplete="email" class="form-input" placeholder="you@example.com" />
            <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">{{ form.errors.email }}</p>
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-300 mb-1">Password</label>
            <input id="password" type="password" v-model="form.password" required autocomplete="new-password" class="form-input" placeholder="••••••••" />
            <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">{{ form.errors.password }}</p>
        </div>

        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-1">Confirm Password</label>
            <input id="password_confirmation" type="password" v-model="form.password_confirmation" required autocomplete="new-password" class="form-input" placeholder="••••••••" />
        </div>

        <button type="submit" :disabled="form.processing" class="w-full py-3 px-4 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20 disabled:opacity-50">
            Create account
        </button>
    </form>

    <p class="text-center text-sm text-slate-400 mt-6">
        Already have an account?
        <Link :href="route('login')" class="text-cyan-400 hover:text-cyan-300 font-medium ml-1">Sign in</Link>
    </p>
</template>
