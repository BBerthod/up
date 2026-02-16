import { onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

export function useRealtimeUpdates(options: {
    onMonitorChecked?: string[]
    onLighthouseCompleted?: string[]
}): void {
    const { onMonitorChecked = [], onLighthouseCompleted = [] } = options

    const teamId = (usePage().props as any).auth?.team?.id
    const Echo = (window as any).Echo

    if (!Echo || !teamId) return

    const channelName = `team.${teamId}`

    let monitorTimer: ReturnType<typeof setTimeout> | null = null
    let lighthouseTimer: ReturnType<typeof setTimeout> | null = null

    onMounted(() => {
        Echo.private(channelName)
            .listen('.monitor.checked', () => {
                if (onMonitorChecked.length === 0) return
                if (monitorTimer) clearTimeout(monitorTimer)
                monitorTimer = setTimeout(() => {
                    router.reload({ only: onMonitorChecked, preserveScroll: true })
                }, 500)
            })
            .listen('.lighthouse.completed', () => {
                if (onLighthouseCompleted.length === 0) return
                if (lighthouseTimer) clearTimeout(lighthouseTimer)
                lighthouseTimer = setTimeout(() => {
                    router.reload({ only: onLighthouseCompleted, preserveScroll: true })
                }, 500)
            })
    })

    onUnmounted(() => {
        if (monitorTimer) clearTimeout(monitorTimer)
        if (lighthouseTimer) clearTimeout(lighthouseTimer)
        if (Echo) Echo.leave(channelName)
    })
}
