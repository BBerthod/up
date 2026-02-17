import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

const isNavigating = ref(false)
const navigationId = ref(0)

let startHandler: (() => void) | null = null
let finishHandler: (() => void) | null = null
let subscriberCount = 0

function setupGlobalHandlers() {
    if (startHandler) return

    startHandler = () => {
        isNavigating.value = true
        navigationId.value++
    }

    finishHandler = () => {
        isNavigating.value = false
    }

    router.on('start', startHandler)
    router.on('finish', finishHandler)
}

function teardownGlobalHandlers() {
    if (subscriberCount === 0 && startHandler) {
        router.off('start', startHandler)
        router.off('finish', finishHandler)
        startHandler = null
        finishHandler = null
    }
}

export function usePageLoading() {
    const isLoading = ref(true)
    const currentNavId = ref(navigationId.value)

    onMounted(() => {
        subscriberCount++
        setupGlobalHandlers()

        if (!isNavigating.value && navigationId.value === currentNavId.value) {
            isLoading.value = false
        }

        const checkLoading = () => {
            if (!isNavigating.value) {
                isLoading.value = false
                currentNavId.value = navigationId.value
            }
        }

        const stopStart = router.on('start', () => {
            isLoading.value = true
            currentNavId.value = navigationId.value
        })

        const stopFinish = router.on('finish', checkLoading)

        const interval = setInterval(checkLoading, 50)

        onUnmounted(() => {
            subscriberCount--
            stopStart()
            stopFinish()
            clearInterval(interval)
            teardownGlobalHandlers()
        })
    })

    return {
        isLoading,
        isNavigating,
    }
}

export { isNavigating, navigationId }
