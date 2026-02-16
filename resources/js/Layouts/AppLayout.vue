<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'

import Toast from 'primevue/toast'
import Menu from 'primevue/menu'
import Drawer from 'primevue/drawer'
import Button from 'primevue/button'
import { useFlashToast } from '@/Composables/useFlashToast'

const page = usePage()
useFlashToast()
const sidebarOpen = ref(true)
const mobileMenuOpen = ref(false)
const userMenu = ref()
const userDropdownOpen = ref(false)

const user = computed(() => (page.props as any).auth?.user)
const team = computed(() => (page.props as any).auth?.team)

const isAdmin = computed(() => (page.props as any).auth?.user?.is_admin ?? false)

const navigation = computed(() => {
    const items = [
        { name: 'Dashboard', href: '/dashboard', icon: 'grid', current: false },
        { name: 'Monitors', href: '/monitors', icon: 'activity', current: false },
        { name: 'Incidents', href: '/incidents', icon: 'alert-triangle', current: false },
        { name: 'Status Pages', href: '/status-pages', icon: 'globe', current: false },
    ]

    if (isAdmin.value) {
        items.push({ name: 'Users', href: '/users', icon: 'users', current: false })
    }

    return items
})

const isActive = (href: string) => page.url.startsWith(href)

const toggleUserMenu = (event: Event) => {
    userMenu.value.toggle(event)
}

const userMenuItems = [
    {
        label: 'My Profile',
        icon: 'pi pi-user',
        command: () => router.get(route('profile.edit'))
    },
    {
        label: 'Log Out',
        icon: 'pi pi-sign-out',
        command: () => router.post('/logout')
    }
]

onMounted(() => {
    // Check screen size for initial sidebar state
    if (window.innerWidth < 1024) {
        sidebarOpen.value = false
    }
})
</script>

<template>
    <Toast position="top-right" />
    <div class="min-h-screen bg-[#09090b] text-[#ececef] font-sans selection:bg-emerald-500/30 selection:text-emerald-400">
        <!-- Mobile overlay -->
        <Drawer v-model:visible="mobileMenuOpen" header="Menu" class="lg:hidden border-r border-white/5 bg-[#09090b]">
            <template #header>
                <div class="flex items-center gap-3">
                     <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20">
                        <svg class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M12 19V5M5 12l7-7 7 7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span class="text-lg font-bold text-white tracking-tight">Up <span class="text-xs font-normal text-zinc-500">by Radiank</span></span>
                </div>
            </template>
            <nav class="space-y-1 mt-4">
                 <Link v-for="item in navigation" :key="item.name" :href="item.href" :class="['flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-all duration-200', isActive(item.href) ? 'text-white bg-white/5' : 'text-zinc-500 hover:text-zinc-300 hover:bg-white/5']" @click="mobileMenuOpen = false">
                    <svg v-if="item.icon === 'grid'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    <svg v-else-if="item.icon === 'activity'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    <svg v-else-if="item.icon === 'alert-triangle'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <svg v-else-if="item.icon === 'bell'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <svg v-else-if="item.icon === 'globe'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <svg v-else-if="item.icon === 'users'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>{{ item.name }}</span>
                </Link>
            </nav>
        </Drawer>

        <!-- Sidebar -->
        <aside :class="[
            'fixed top-0 left-0 z-50 h-full bg-[#09090b] border-r border-white/5 transition-all duration-300',
            sidebarOpen ? 'w-64' : 'w-20',
            mobileMenuOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
        ]">
            <!-- Logo -->
            <div class="flex items-center h-16 px-6">
                <Link href="/dashboard" class="flex items-center gap-3 group">
                    <div class="flex items-center justify-center w-8 h-8 rounded-lg bg-emerald-500/10 border border-emerald-500/20 group-hover:border-emerald-500/40 transition-colors">
                        <svg class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M12 19V5M5 12l7-7 7 7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span v-if="sidebarOpen" class="text-lg font-bold text-white tracking-tight">Up <span class="text-xs font-normal text-zinc-500">by Radiank</span></span>
                </Link>
            </div>

            <!-- Nav -->
            <nav class="flex-1 px-4 py-6 space-y-1">
                <div v-if="sidebarOpen" class="px-2 mb-2 text-xs font-semibold text-zinc-500 uppercase tracking-wider">Platform</div>
                <Link v-for="item in navigation" :key="item.name" :href="item.href" :class="['flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-all duration-200 group', isActive(item.href) ? 'text-white bg-white/5' : 'text-zinc-500 hover:text-zinc-300 hover:bg-white/5']">
                    <svg v-if="item.icon === 'grid'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                    <svg v-else-if="item.icon === 'activity'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    <svg v-else-if="item.icon === 'alert-triangle'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <svg v-else-if="item.icon === 'bell'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <svg v-else-if="item.icon === 'globe'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    <svg v-else-if="item.icon === 'users'" class="w-4 h-4 shrink-0 transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span v-if="sidebarOpen">{{ item.name }}</span>
                </Link>
            </nav>

            <!-- Collapse button -->
            <div class="p-4 border-t border-white/5">
                <button @click="sidebarOpen = !sidebarOpen" :aria-label="sidebarOpen ? 'Collapse sidebar' : 'Expand sidebar'" class="hidden lg:flex items-center justify-center w-full p-2 text-zinc-500 hover:text-white rounded-lg hover:bg-white/5 transition-colors">
                    <svg :class="['w-4 h-4 transition-transform', !sidebarOpen && 'rotate-180']" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                </button>
            </div>
        </aside>

        <!-- Main -->
        <div :class="['transition-all duration-300', sidebarOpen ? 'lg:ml-64' : 'lg:ml-20']">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 h-16 bg-[#09090b]/50 backdrop-blur-md">
                <div class="flex items-center justify-between h-full px-6 lg:px-8">
                    <!-- Breadcrumbs or Page Title could go here -->
                    <div class="flex items-center gap-4">
                        <button @click="mobileMenuOpen = true" class="lg:hidden p-2 text-zinc-500 hover:text-white rounded-lg hover:bg-white/5" aria-label="Open navigation menu" :aria-expanded="mobileMenuOpen">
                            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                        </button>
                    </div>

                    <div class="flex items-center gap-6">
                        <div v-if="team" class="hidden md:flex items-center gap-2 px-3 py-1 rounded-full bg-white/5 border border-white/5">
                            <div class="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                            <span class="text-xs font-medium text-zinc-400">{{ team.name }}</span>
                        </div>

                        <div class="relative">
                            <Menu ref="userMenu" :model="userMenuItems" :popup="true" />
                            <button @click="toggleUserMenu" class="flex items-center gap-3 p-1.5 rounded-full hover:bg-white/5 transition-colors group">
                                <div class="w-8 h-8 rounded-full bg-linear-to-br from-emerald-500 to-emerald-600 flex items-center justify-center text-white font-medium text-sm shadow-lg shadow-emerald-500/20 group-hover:scale-105 transition-transform">
                                    {{ user?.name?.charAt(0)?.toUpperCase() || 'U' }}
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-6 lg:p-8 max-w-7xl mx-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
