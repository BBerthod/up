<script setup lang="ts">
import Skeleton from 'primevue/skeleton'
</script>

<template>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-8 border-b border-white/5 pb-12">
        <div v-for="i in 4" :key="i" class="space-y-3">
            <Skeleton width="4rem" height="0.75rem" class="!bg-white/5" />
            <Skeleton width="6rem" height="2.5rem" class="!bg-white/5" />
            <Skeleton width="3rem" height="0.75rem" class="!bg-white/5" />
        </div>
    </div>
</template>
