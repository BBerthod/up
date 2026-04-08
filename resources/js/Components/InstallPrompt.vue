<script setup lang="ts">
import { usePwaInstall } from '@/Composables/usePwaInstall'

const { showPrompt, install, dismiss } = usePwaInstall()
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div
            v-if="showPrompt"
            class="fixed bottom-0 inset-x-0 z-50 p-4 flex justify-center pointer-events-none"
            role="banner"
            aria-label="Install app prompt"
        >
            <div class="pointer-events-auto flex items-center gap-3 px-4 py-3 rounded-xl bg-[#0f1923]/90 border border-white/8 backdrop-blur-xl shadow-2xl shadow-black/40 max-w-sm w-full">
                <!-- Icon -->
                <div class="shrink-0 flex items-center justify-center w-9 h-9 rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                    <svg class="w-4 h-4 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 19V5M5 12l7-7 7 7"/>
                    </svg>
                </div>

                <!-- Text -->
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-white leading-tight">Install Up for quick access</p>
                    <p class="text-xs text-zinc-500 mt-0.5 leading-tight">Works offline, no browser chrome</p>
                </div>

                <!-- Install button -->
                <button
                    @click="install"
                    class="shrink-0 px-3 py-1.5 text-xs font-semibold text-white bg-emerald-500 hover:bg-emerald-400 rounded-lg transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2 focus-visible:ring-offset-[#0f1923]"
                    aria-label="Install the app"
                >
                    Install
                </button>

                <!-- Dismiss button -->
                <button
                    @click="dismiss"
                    class="shrink-0 p-1 text-zinc-500 hover:text-zinc-300 rounded-md hover:bg-white/5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white/20"
                    aria-label="Dismiss install prompt"
                >
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>
    </Transition>
</template>
