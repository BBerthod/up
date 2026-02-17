<script setup lang="ts">
defineProps<{
    theme?: 'dark' | 'light'
}>()
</script>

<template>
    <div :class="theme === 'light' ? 'bg-gray-50 min-h-screen' : 'bg-navy min-h-screen'">
        <div class="min-h-screen py-12 px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl">
                <main>
                    <slot />
                </main>

                <footer class="mt-12 text-center">
                    <p :class="theme === 'light' ? 'text-gray-400 text-sm' : 'text-slate-500 text-sm'">
                        Powered by <span :class="theme === 'light' ? 'text-emerald-600 font-medium' : 'text-emerald-400 font-medium'">Up</span>
                    </p>
                </footer>
            </div>
        </div>
    </div>
</template>
