import { ref, computed, onMounted } from 'vue'

const DISMISSED_KEY = 'pwa-install-dismissed'
const DISMISS_DURATION_MS = 7 * 24 * 60 * 60 * 1000 // 7 days

export function usePwaInstall() {
    const canInstall = ref(false)
    const isDismissed = ref(false)
    let deferredPrompt: any = null

    onMounted(() => {
        // Check if dismissed recently
        const dismissed = localStorage.getItem(DISMISSED_KEY)
        if (dismissed && Date.now() - parseInt(dismissed) < DISMISS_DURATION_MS) {
            isDismissed.value = true
            return
        }

        window.addEventListener('beforeinstallprompt', (e: Event) => {
            e.preventDefault()
            deferredPrompt = e
            canInstall.value = true
        })

        window.addEventListener('appinstalled', () => {
            canInstall.value = false
            deferredPrompt = null
        })
    })

    const install = async (): Promise<boolean> => {
        if (!deferredPrompt) return false
        deferredPrompt.prompt()
        const result = await deferredPrompt.userChoice
        deferredPrompt = null
        canInstall.value = false
        return result.outcome === 'accepted'
    }

    const dismiss = () => {
        isDismissed.value = true
        localStorage.setItem(DISMISSED_KEY, Date.now().toString())
    }

    const showPrompt = computed(() => canInstall.value && !isDismissed.value)

    return { canInstall, showPrompt, install, dismiss }
}
