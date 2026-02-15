<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
    scores: {
        performance: number
        accessibility: number
        best_practices: number
        seo: number
        scored_at: string
    } | null
}>()

const categories = computed(() => {
    if (!props.scores) return []
    return [
        { label: 'Performance', value: props.scores.performance },
        { label: 'Accessibility', value: props.scores.accessibility },
        { label: 'Best Practices', value: props.scores.best_practices },
        { label: 'SEO', value: props.scores.seo },
    ]
})

const scoreColor = (v: number) => v >= 90 ? '#0cce6b' : v >= 50 ? '#ffa400' : '#ff4e42'

const circumference = 2 * Math.PI * 40
const dashOffset = (v: number) => circumference - (v / 100) * circumference

const formatDate = (d: string) => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
</script>

<template>
    <div v-if="scores" class="space-y-4">
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
            <div v-for="cat in categories" :key="cat.label" class="flex flex-col items-center">
                <div class="relative w-24 h-24">
                    <svg viewBox="0 0 100 100" class="w-full h-full -rotate-90">
                        <circle cx="50" cy="50" r="40" fill="none" stroke="currentColor" stroke-width="6" class="text-white/10" />
                        <circle cx="50" cy="50" r="40" fill="none" :stroke="scoreColor(cat.value)" stroke-width="6"
                            stroke-linecap="round"
                            :stroke-dasharray="circumference"
                            :stroke-dashoffset="dashOffset(cat.value)"
                            class="transition-all duration-700" />
                    </svg>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="text-xl font-bold font-mono" :style="{ color: scoreColor(cat.value) }">{{ cat.value }}</span>
                    </div>
                </div>
                <span class="text-sm text-slate-400 mt-2 text-center">{{ cat.label }}</span>
            </div>
        </div>
        <p class="text-xs text-slate-500 text-center">Last audit: {{ formatDate(scores.scored_at) }}</p>
    </div>
    <div v-else class="text-center py-8">
        <p class="text-slate-500">No Lighthouse data yet</p>
    </div>
</template>
