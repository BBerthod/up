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

    <div class="mt-6">
        <div class="relative">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-white/10" />
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="px-4 bg-transparent text-slate-500">Or continue with</span>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
            <a :href="route('oauth.redirect', 'google')" class="flex items-center justify-center gap-2 py-3 px-4 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 transition-colors text-slate-300 hover:text-white">
                <svg class="w-5 h-5" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                Google
            </a>
            <a :href="route('oauth.redirect', 'github')" class="flex items-center justify-center gap-2 py-3 px-4 rounded-lg border border-white/10 bg-white/5 hover:bg-white/10 transition-colors text-slate-300 hover:text-white">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/></svg>
                GitHub
            </a>
        </div>
    </div>
</template>
