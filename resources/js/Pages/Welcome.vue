<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { onMounted, ref } from 'vue'

defineOptions({ layout: false })

const visible = ref(false)

onMounted(() => {
    requestAnimationFrame(() => {
        visible.value = true
    })
})
</script>

<template>
    <Head title="Open-source uptime monitoring" />

    <div class="min-h-screen bg-navy flex flex-col relative overflow-hidden">
        <!-- Background -->
        <div class="absolute inset-0 overflow-hidden pointer-events-none">
            <div class="absolute -top-1/4 -left-1/4 w-1/2 h-1/2 bg-cyan-500/10 rounded-full blur-[100px] animate-pulse" />
            <div class="absolute -bottom-1/4 -right-1/4 w-1/2 h-1/2 bg-cyan-600/10 rounded-full blur-[100px] animate-pulse" style="animation-delay: 1s" />
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-1/3 h-1/3 bg-teal-500/5 rounded-full blur-[120px] animate-pulse" style="animation-delay: 2s" />
            <div class="absolute inset-0 bg-grid opacity-30" />
        </div>

        <!-- Content -->
        <div class="relative z-10 flex-1 flex flex-col items-center justify-center px-4 py-16">
            <!-- Hero -->
            <div
                class="text-center max-w-2xl mx-auto transition-all duration-700 ease-out"
                :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
            >
                <!-- Logo -->
                <div class="flex items-center justify-center gap-3 mb-8">
                    <div class="flex items-center justify-center w-14 h-14 rounded-xl bg-gradient-to-br from-cyan-500 to-cyan-600 shadow-lg shadow-cyan-500/30">
                        <svg class="w-7 h-7 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M12 19V5M5 12l7-7 7 7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span class="text-4xl font-bold text-white">Up</span>
                </div>

                <h1 class="text-3xl sm:text-4xl lg:text-5xl font-bold text-white mb-4">
                    Open-source <span class="text-gradient">uptime monitoring</span>
                </h1>
                <p class="text-lg text-gray-400 mb-10 max-w-lg mx-auto">
                    Monitor your websites, APIs and services. Get instant alerts when things go down. Self-hosted and free.
                </p>

                <!-- CTA -->
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <Link
                        :href="route('login')"
                        class="btn-primary w-full sm:w-auto px-8 py-3 text-center"
                    >
                        Sign in
                    </Link>
                    <a
                        href="https://github.com/BBerthod/up"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="w-full sm:w-auto px-8 py-3 rounded-lg font-semibold text-gray-300 border border-white/10 hover:border-white/20 hover:bg-white/5 transition-all text-center"
                    >
                        View on GitHub
                    </a>
                </div>
            </div>

            <!-- Features -->
            <div
                class="mt-20 w-full max-w-3xl mx-auto grid grid-cols-1 sm:grid-cols-2 gap-4 transition-all duration-700 ease-out delay-200"
                :class="visible ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-4'"
            >
                <!-- HTTP Monitoring -->
                <div class="glass p-6 transition-transform hover:scale-[1.02]">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-cyan-500/10">
                            <svg class="w-5 h-5 text-cyan-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M3.6 9h16.8M3.6 15h16.8M12 3a15 15 0 0 1 4 9 15 15 0 0 1-4 9 15 15 0 0 1-4-9 15 15 0 0 1 4-9Z" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-white">HTTP Monitoring</h3>
                    </div>
                    <p class="text-sm text-gray-400">Monitor websites and APIs with status code, keyword and SSL certificate checks.</p>
                </div>

                <!-- Ping & Port -->
                <div class="glass p-6 transition-transform hover:scale-[1.02]">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-cyan-500/10">
                            <svg class="w-5 h-5 text-cyan-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M5 12h14M12 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-white">Ping, Port & DNS</h3>
                    </div>
                    <p class="text-sm text-gray-400">ICMP ping, TCP port and DNS record checks for complete infrastructure coverage.</p>
                </div>

                <!-- Alerts -->
                <div class="glass p-6 transition-transform hover:scale-[1.02]">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-cyan-500/10">
                            <svg class="w-5 h-5 text-cyan-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9ZM13.73 21a2 2 0 0 1-3.46 0" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-white">Instant Alerts</h3>
                    </div>
                    <p class="text-sm text-gray-400">Get notified via email, Slack, Discord, Telegram or webhook when something goes wrong.</p>
                </div>

                <!-- Status Pages -->
                <div class="glass p-6 transition-transform hover:scale-[1.02]">
                    <div class="flex items-center gap-3 mb-2">
                        <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-cyan-500/10">
                            <svg class="w-5 h-5 text-cyan-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                <path d="M4 5a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5Z" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M4 9h16M8 4v5" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-white">Status Pages</h3>
                    </div>
                    <p class="text-sm text-gray-400">Public status pages to keep your users informed about service availability.</p>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="relative z-10 py-6 flex flex-col items-center gap-3 text-sm text-gray-500">
            <a
                href="https://radiank.com"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex items-center gap-2 text-gray-400 hover:text-emerald-400 transition-colors"
            >
                <span>by</span>
                <svg class="w-4 h-4" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
                    <rect width="32" height="32" rx="6" fill="#10b981"/>
                    <path fill="#fff" d="M8 6h8c4.4 0 7 2.6 7 6.5S20.4 19 16 19l6 7h-4.5L12 19v-3h4c2.8 0 4.5-1.5 4.5-3.5S18.8 9 16 9h-4.5v17H8V6z"/>
                </svg>
                <span class="font-semibold">Radiank</span>
            </a>
            <a
                href="https://github.com/BBerthod/up"
                target="_blank"
                rel="noopener noreferrer"
                class="text-gray-500 hover:text-cyan-400 transition-colors"
            >
                GitHub
            </a>
        </div>
    </div>
</template>
