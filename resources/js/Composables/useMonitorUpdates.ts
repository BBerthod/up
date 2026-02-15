import { type Ref, onMounted, onUnmounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

interface LatestCheck {
    status: string
    response_time_ms: number
    checked_at: string
}

interface Monitor {
    id: number
    latest_check: LatestCheck | null
    [key: string]: unknown
}

interface MonitorCheckedPayload {
    monitor_id: number
    check: {
        status: string
        response_time_ms: number
        status_code: number
        checked_at: string
    }
}

export function useMonitorUpdates(monitors: Ref<Monitor[]>) {
    const updatedMonitorId = ref<number | null>(null)
    const page = usePage()

    let channelName: string | null = null
    let clearTimer: ReturnType<typeof setTimeout> | null = null

    onMounted(() => {
        const teamId = (page.props as any).auth?.team?.id as number | undefined

        if (!teamId || !window.Echo) return

        channelName = `team.${teamId}`

        window.Echo.private(channelName)
            .listen('.MonitorChecked', (payload: MonitorCheckedPayload) => {
                const monitor = monitors.value.find(m => m.id === payload.monitor_id)
                if (!monitor) return

                monitor.latest_check = {
                    status: payload.check.status,
                    response_time_ms: payload.check.response_time_ms,
                    checked_at: payload.check.checked_at,
                }

                updatedMonitorId.value = payload.monitor_id

                if (clearTimer) clearTimeout(clearTimer)
                clearTimer = setTimeout(() => {
                    updatedMonitorId.value = null
                }, 1500)
            })
    })

    onUnmounted(() => {
        if (clearTimer) clearTimeout(clearTimer)
        if (channelName && window.Echo) {
            window.Echo.leave(channelName)
        }
    })

    return { updatedMonitorId }
}
