<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputText from 'primevue/inputtext'
import Password from 'primevue/password'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'
import Message from 'primevue/message'

defineOptions({ layout: GuestLayout })

const flash = computed(() => (usePage().props as any).flash)

const rateLimited = ref(false)
const cooldown = ref(0) // ... (rest of logic remains)
const form = useForm({
    email: '',
    password: '',
    remember: false,
})

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
        onError: () => {
            // Rate limiting logic
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

    <Message v-if="flash?.error" severity="error" class="mb-6">{{ flash.error }}</Message>

    <form @submit.prevent="submit" class="space-y-6">
        <div class="flex flex-col gap-2">
            <label for="email" class="text-sm font-medium text-slate-300">Email</label>
            <InputText id="email" type="email" v-model="form.email" required autofocus autocomplete="email" placeholder="you@example.com" class="w-full" :invalid="!!form.errors.email" />
            <small v-if="form.errors.email" class="text-red-400">{{ form.errors.email }}</small>
        </div>

        <div class="flex flex-col gap-2">
            <label for="password" class="text-sm font-medium text-slate-300">Password</label>
            <Password id="password" v-model="form.password" required autocomplete="current-password" :feedback="false" toggleMask placeholder="••••••••" inputClass="w-full" class="w-full" :invalid="!!form.errors.password" />
            <small v-if="form.errors.password" class="text-red-400">{{ form.errors.password }}</small>
            <Link :href="route('password.request')" class="inline-block mt-1 text-sm text-cyan-400 hover:text-cyan-300 transition-colors">Forgot password?</Link>
        </div>

        <div class="flex items-center gap-2">
             <Checkbox v-model="form.remember" binary inputId="remember" />
             <label for="remember" class="text-sm text-slate-400 cursor-pointer">Remember me</label>
        </div>

        <Button type="submit" :loading="form.processing || rateLimited" label="Sign in" severity="primary" class="w-full py-3 font-semibold" />
    </form>
</template>
