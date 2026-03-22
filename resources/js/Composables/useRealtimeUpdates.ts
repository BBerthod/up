import { onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

export function useRealtimeUpdates(options: {
    onMonitorChecked?: string[]
    onLighthouseCompleted?: string[]
    onWarmRunProgress?: string[]
    onRefresh?: () => void
}): void {
    const { onMonitorChecked = [], onLighthouseCompleted = [], onWarmRunProgress, onRefresh } = options

    const teamId = (usePage().props as any).auth?.team?.id
    const Echo = (window as any).Echo

    if (!Echo || !teamId) return

    const channelName = `team.${teamId}`

    let monitorTimer: ReturnType<typeof setTimeout> | null = null
    let lighthouseTimer: ReturnType<typeof setTimeout> | null = null
    let warmRunTimer: ReturnType<typeof setTimeout> | null = null

    const executeRefresh = (props: string[]) => {
        onRefresh?.()
        router.reload({ only: props })
    }

    onMounted(() => {
        const channel = Echo.private(channelName)
            .listen('.monitor.checked', () => {
                if (onMonitorChecked.length === 0) return
                if (monitorTimer) clearTimeout(monitorTimer)
                monitorTimer = setTimeout(() => executeRefresh(onMonitorChecked), 500)
            })
            .listen('.lighthouse.completed', () => {
                if (onLighthouseCompleted.length === 0) return
                if (lighthouseTimer) clearTimeout(lighthouseTimer)
                lighthouseTimer = setTimeout(() => executeRefresh(onLighthouseCompleted), 500)
            })

        if (onWarmRunProgress) {
            channel.listen('.warm.run.progress', (event: any) => {
                if (event.completed) {
                    if (warmRunTimer) clearTimeout(warmRunTimer)
                    warmRunTimer = setTimeout(() => {
                        onRefresh?.()
                        router.reload({ only: onWarmRunProgress })
                    }, 500)
                }
            })
        }
    })

    onUnmounted(() => {
        if (monitorTimer) clearTimeout(monitorTimer)
        if (lighthouseTimer) clearTimeout(lighthouseTimer)
        if (warmRunTimer) clearTimeout(warmRunTimer)
        if (Echo) Echo.leave(channelName)
    })
}
