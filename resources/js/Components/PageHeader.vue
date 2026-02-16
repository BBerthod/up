<script setup lang="ts">
defineProps<{
    title: string
    description?: string
}>()
</script>

<template>
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white tracking-tight">{{ title }}</h1>
            <p v-if="description" class="text-zinc-500 mt-1">{{ description }}</p>
        </div>
        <div v-if="$slots.actions" class="flex items-center gap-3">
            <slot name="actions" />
        </div>
    </div>
</template>
