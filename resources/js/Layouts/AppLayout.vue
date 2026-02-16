<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

import Menu from 'primevue/menu'
import Drawer from 'primevue/drawer'
import Button from 'primevue/button'

const page = usePage()
const sidebarOpen = ref(true)
const mobileMenuOpen = ref(false)
const userMenu = ref()
const userDropdownOpen = ref(false)

const user = computed(() => (page.props as any).auth?.user)
const team = computed(() => (page.props as any).auth?.team)

const isAdmin = computed(() => (page.props as any).auth?.user?.is_admin ?? false)

const navigation = computed(() => {
    const items = [
        { name: 'Dashboard', href: '/dashboard', icon: 'grid' },
        { name: 'Monitors', href: '/monitors', icon: 'activity' },
        { name: 'Incidents', href: '/incidents', icon: 'alert-triangle' },
        { name: 'Channels', href: '/channels', icon: 'bell' },
        { name: 'Status Pages', href: '/status-pages', icon: 'globe' },
        { name: 'Settings', href: '/settings', icon: 'settings' },
    ]
    if (isAdmin.value) {
        items.push({ name: 'Users', href: '/admin/users', icon: 'users' })
    }
    return items
})

onMounted(() => {
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js')
    }
})

const isActive = (href: string) => page.url.startsWith(href)

const toggleUserMenu = (event: Event) => {
    userMenu.value.toggle(event)
}

const userMenuItems = [
    {
        label: 'My Profile',
        icon: 'pi pi-user',
        command: () => router.visit('/profile')
    },
    {
        label: 'Settings',
        icon: 'pi pi-cog',
        command: () => router.visit('/settings')
    },
    {
        separator: true
    },
    {
        label: 'Logout',
        icon: 'pi pi-sign-out',
        class: 'text-red-400',
        command: () => router.post('/logout')
    }
]

</script>

<template>
    <div class="min-h-screen bg-navy">
        <!-- Mobile overlay -->
        <Drawer v-model:visible="mobileMenuOpen" header="Menu" class="lg:hidden">
            <template #header>
                <div class="flex items-center gap-3">
                     <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-gradient-to-br from-cyan-500 to-cyan-600">
                        <svg class="w-4 h-4 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M12 19V5M5 12l7-7 7 7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span class="text-lg font-bold text-white">Up</span>
                </div>
            </template>
            <nav class="space-y-1">
                 <Link v-for="item in navigation" :key="item.name" :href="item.href" :class="['nav-item', isActive(item.href) && 'active']" @click="mobileMenuOpen = false">
                    <svg v-if="item.icon === 'grid'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    <svg v-else-if="item.icon === 'activity'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    <svg v-else-if="item.icon === 'alert-triangle'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <svg v-else-if="item.icon === 'bell'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <svg v-else-if="item.icon === 'globe'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <svg v-else-if="item.icon === 'settings'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.26.46.76.8 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <svg v-else-if="item.icon === 'users'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>{{ item.name }}</span>
                </Link>
            </nav>
        </Drawer>

        <!-- Sidebar -->
        <aside :class="[
            'fixed top-0 left-0 z-50 h-full bg-surface-0 border-r border-[rgba(255,255,255,0.06)] transition-all duration-300',
            sidebarOpen ? 'w-64' : 'w-20',
            mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        ]">
            <!-- Logo -->
            <div class="flex items-center h-14 px-4 border-b border-[rgba(255,255,255,0.06)]">
                <Link href="/dashboard" class="flex items-center gap-3">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-gradient-to-br from-cyan-500 to-cyan-600">
                        <svg class="w-5 h-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M12 19V5M5 12l7-7 7 7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span v-if="sidebarOpen" class="text-xl font-bold text-white">Up</span>
                </Link>
            </div>

            <!-- Nav -->
            <nav class="flex-1 px-3 py-4 space-y-1">
                <Link v-for="item in navigation" :key="item.name" :href="item.href" :class="['nav-item', isActive(item.href) && 'active']">
                    <svg v-if="item.icon === 'grid'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    <svg v-else-if="item.icon === 'activity'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    <svg v-else-if="item.icon === 'alert-triangle'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <svg v-else-if="item.icon === 'bell'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <svg v-else-if="item.icon === 'globe'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <svg v-else-if="item.icon === 'settings'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.26.46.76.8 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <svg v-else-if="item.icon === 'users'" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span v-if="sidebarOpen">{{ item.name }}</span>
                </Link>
            </nav>

            <!-- Collapse button -->
            <div class="p-4 border-t border-[rgba(255,255,255,0.06)]">
                <button @click="sidebarOpen = !sidebarOpen" class="hidden lg:flex items-center justify-center w-full p-2 text-gray-400 hover:text-white rounded-lg hover:bg-[rgba(255,255,255,0.04)] transition-colors">
                    <svg :class="['w-5 h-5 transition-transform', !sidebarOpen && 'rotate-180']" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
            </div>
        </aside>

        <!-- Main -->
        <div :class="['transition-all duration-300', sidebarOpen ? 'lg:ml-64' : 'lg:ml-20']">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 h-14 bg-page/80 backdrop-blur-sm border-b border-[rgba(255,255,255,0.06)]">
                <div class="flex items-center justify-between h-full px-4 lg:px-6">
                    <div class="flex items-center gap-4">
                        <button @click="mobileMenuOpen = true" class="lg:hidden p-2 text-gray-400 hover:text-white rounded-lg hover:bg-[rgba(255,255,255,0.04)]">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-4">
                        <div v-if="team" class="hidden md:flex items-center gap-2 px-3 py-1.5 rounded-lg bg-surface-1 border border-[rgba(255,255,255,0.06)]">
                            <div class="w-2 h-2 rounded-full bg-success" />
                            <span class="text-sm text-gray-300">{{ team.name }}</span>
                        </div>

                        <div class="relative">
                            <Menu ref="userMenu" :model="userMenuItems" :popup="true" />
                            <button @click="toggleUserMenu" class="flex items-center gap-3 p-2 rounded-lg hover:bg-[rgba(255,255,255,0.04)] transition-colors">
                                <div class="hidden sm:block text-right">
                                    <p class="text-sm font-medium text-white">{{ user?.name }}</p>
                                </div>
                                <div class="w-9 h-9 rounded-full bg-gradient-to-br from-cyan-500 to-cyan-600 flex items-center justify-center text-white font-medium text-sm">
                                    {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-4 lg:p-6">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 200ms; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
