<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'

defineOptions({ layout: GuestLayout })

const flash = computed(() => (usePage().props as any).flash)

const showPassword = ref(false)
const rateLimited = ref(false)
const cooldown = ref(0)

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
        onError: () => {
            rateLimited.value = true
            cooldown.value = 5
            const interval = setInterval(() => {
                cooldown.value--
                if (cooldown.value <= 0) {
                    rateLimited.value = false
                    clearInterval(interval)
                }
            }, 1000)
        },
    })
}
</script>

<template>
    <Head title="Log in" />

    <div v-if="flash?.error" class="mb-6 p-4 rounded-lg bg-red-500/20 border border-red-500/30 text-red-400 text-sm">
        {{ flash.error }}
    </div>

    <form @submit.prevent="submit" class="space-y-6">
        <div>
            <label for="email" class="block text-sm font-medium text-slate-300 mb-1">Email</label>
            <input id="email" type="email" v-model="form.email" required autofocus autocomplete="email" class="form-input" placeholder="you@example.com" />
            <p v-if="form.errors.email" class="mt-2 text-sm text-red-400">{{ form.errors.email }}</p>
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-slate-300 mb-1">Password</label>
            <div class="relative">
                <input id="password" :type="showPassword ? 'text' : 'password'" v-model="form.password" required autocomplete="current-password" class="form-input w-full pr-10" placeholder="••••••••" />
                <button type="button" @click="showPassword = !showPassword" class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-300 transition-colors">
                    <svg v-if="!showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12c1.292 4.338 5.31 7.5 10.066 7.5.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                </button>
            </div>
            <p v-if="form.errors.password" class="mt-2 text-sm text-red-400">{{ form.errors.password }}</p>
            <Link :href="route('password.request')" class="inline-block mt-2 text-sm text-cyan-400 hover:text-cyan-300 transition-colors">Forgot password?</Link>
        </div>

        <div class="flex items-center">
            <label class="flex items-center">
                <input type="checkbox" v-model="form.remember" class="rounded border-slate-600 bg-slate-700 text-cyan-500 focus:ring-cyan-500" />
                <span class="ms-2 text-sm text-slate-400">Remember me</span>
            </label>
        </div>

        <button type="submit" :disabled="form.processing || rateLimited" class="w-full py-3 px-4 rounded-lg text-white font-semibold bg-gradient-to-r from-cyan-500 to-teal-500 hover:from-cyan-600 hover:to-teal-600 transition-all shadow-lg shadow-cyan-500/20 disabled:opacity-50">
            <template v-if="rateLimited">Try again in {{ cooldown }}s</template>
            <template v-else>Sign in</template>
        </button>
    </form>
</template>
