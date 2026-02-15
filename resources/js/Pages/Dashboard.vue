<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
</script>

<template>
    <Head title="Dashboard" />

    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-white mb-6">Welcome to Up</h1>

        <div class="glass p-8 text-center">
            <svg class="mx-auto h-12 w-12 text-slate-500 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <h3 class="text-lg font-medium text-slate-300">No Monitors</h3>
            <p class="mt-1 text-sm text-slate-500">Your monitors will appear here. Get started by creating a new monitor.</p>
        </div>
    </div>
</template>
