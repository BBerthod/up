<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
    target: number
    current: number
}>()

// Percentage of current vs target to fill the bar (capped at 100%)
const fillPercent = computed(() => {
    if (props.target === 0) return 100
    return Math.min((props.current / props.target) * 100, 100)
})

const delta = computed(() => props.current - props.target)

const colorClass = computed(() => {
    if (props.current >= props.target) return 'bg-emerald-500'
    if (delta.value >= -0.5) return 'bg-yellow-400'
    return 'bg-red-500'
})

const trackColorClass = computed(() => {
    if (props.current >= props.target) return 'bg-emerald-500/20'
    if (delta.value >= -0.5) return 'bg-yellow-400/20'
    return 'bg-red-500/20'
})

const textColorClass = computed(() => {
    if (props.current >= props.target) return 'text-emerald-400'
    if (delta.value >= -0.5) return 'text-yellow-400'
    return 'text-red-400'
})
</script>

<template>
    <div class="rounded-xl bg-white/5 backdrop-blur-sm border border-white/10 p-4 space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-xs font-semibold uppercase tracking-wider text-zinc-500">SLA This Month</span>
            <span :class="['text-sm font-semibold font-mono', textColorClass]">
                {{ current.toFixed(2) }}% / {{ target.toFixed(2) }}%
            </span>
        </div>

        <div class="relative w-full h-2 rounded-full overflow-hidden" :class="trackColorClass">
            <div
                class="h-full rounded-full transition-all duration-500"
                :class="colorClass"
                :style="{ width: `${fillPercent}%` }"
                role="progressbar"
                :aria-valuenow="current"
                :aria-valuemin="0"
                :aria-valuemax="target"
                :aria-label="`SLA: ${current.toFixed(2)}% of ${target.toFixed(2)}% target`"
            />
        </div>

        <div class="flex items-center justify-between text-xs text-zinc-500">
            <span>0%</span>
            <span
                v-if="current >= target"
                class="flex items-center gap-1 text-emerald-400"
            >
                <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                Target met
            </span>
            <span v-else :class="['font-mono', textColorClass]">
                {{ delta.toFixed(2) }}%
            </span>
            <span>100%</span>
        </div>
    </div>
</template>
