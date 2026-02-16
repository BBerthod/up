<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    status: 'up' | 'down' | 'paused'
    size?: 'sm' | 'md'
    showLabel?: boolean
}>(), {
    size: 'sm',
    showLabel: true,
})

const config = computed(() => {
    const configs = {
        up: {
            label: 'UP',
            dotClass: 'bg-emerald-400',
            textClass: 'text-emerald-400',
            pillClass: 'bg-emerald-500/20 text-emerald-400',
            pulse: false,
        },
        down: {
            label: 'DOWN',
            dotClass: 'bg-red-500',
            textClass: 'text-red-400',
            pillClass: 'bg-red-500/20 text-red-400',
            pulse: true,
        },
        paused: {
            label: 'PAUSED',
            dotClass: 'bg-slate-500',
            textClass: 'text-slate-400',
            pillClass: 'bg-slate-500/20 text-slate-400',
            pulse: false,
        },
    }
    return configs[props.status]
})

const ariaLabel = computed(() => {
    const labels = { up: 'Status: Online', down: 'Status: Offline', paused: 'Status: Paused' }
    return labels[props.status]
})
</script>

<template>
    <!-- Pill style (md) -->
    <span
        v-if="size === 'md'"
        :class="['px-3 py-1 rounded-full text-xs font-medium', config.pillClass]"
        :aria-label="ariaLabel"
        role="status"
    >
        {{ config.label }}
    </span>

    <!-- Dot style (sm) -->
    <span v-else class="inline-flex items-center gap-1.5" :aria-label="ariaLabel" role="status">
        <span class="relative flex items-center justify-center w-2.5 h-2.5">
            <span v-if="config.pulse" class="absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75 animate-ping" />
            <span :class="['relative w-2 h-2 rounded-full', config.dotClass]" />
        </span>
        <span v-if="showLabel" :class="['text-xs font-medium uppercase', config.textClass]">
            {{ config.label }}
        </span>
    </span>
</template>
