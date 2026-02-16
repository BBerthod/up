<script setup lang="ts">
import { Link } from '@inertiajs/vue3'

defineProps<{ title?: string }>()
</script>

<template>
    <div class="min-h-screen bg-navy flex flex-col items-center justify-center p-4 relative overflow-hidden">
        <!-- Background -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute inset-0" style="background: radial-gradient(ellipse at center, rgba(6,182,212,0.04) 0%, transparent 70%)" />
            <div class="absolute inset-0 bg-grid opacity-30" />
        </div>

        <div class="relative z-10 w-full max-w-md">
            <!-- Logo -->
            <Link href="/" class="flex items-center justify-center gap-3 mb-8">
                <div class="flex items-center justify-center w-14 h-14 rounded-xl bg-gradient-to-br from-cyan-500 to-cyan-600">
                    <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M12 19V5M5 12l7-7 7 7" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <span class="text-3xl font-bold text-white">Up</span>
            </Link>

            <h1 v-if="title" class="text-center text-xl font-semibold text-gray-300 mb-6">{{ title }}</h1>

            <div class="glass-intense p-8">
                <slot />
            </div>

            <div class="mt-6 text-center text-sm text-gray-500">
                <slot name="footer" />
            </div>
        </div>
    </div>
</template>
