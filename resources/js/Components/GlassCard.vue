<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    title?: string
    padding?: number
}>(), {
    padding: 6,
})

const paddingClass = computed(() => {
    const paddings: Record<number, string> = {
        0: 'p-0',
        2: 'p-2',
        3: 'p-3',
        4: 'p-4',
        5: 'p-5',
        6: 'p-6',
        8: 'p-8',
        12: 'p-12',
    }
    return paddings[props.padding] ?? 'p-6'
})
</script>

<template>
    <div class="glass" :class="paddingClass">
        <h3 v-if="title" class="text-white font-medium mb-4">{{ title }}</h3>
        <slot />
    </div>
</template>
