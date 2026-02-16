import { computed, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { useToast } from 'primevue/usetoast'

export function useFlashToast() {
    const toast = useToast()
    const page = usePage()

    const flash = computed(() => (page.props as any).flash)

    watch(
        flash,
        (newFlash) => {
            if (!newFlash) return

            if (newFlash.success) {
                toast.add({
                    severity: 'success',
                    summary: 'Success',
                    detail: newFlash.success,
                    life: 4000,
                })
            }

            if (newFlash.error) {
                toast.add({
                    severity: 'error',
                    summary: 'Error',
                    detail: newFlash.error,
                    life: 4000,
                })
            }

            if (newFlash.warning) {
                toast.add({
                    severity: 'warn',
                    summary: 'Warning',
                    detail: newFlash.warning,
                    life: 4000,
                })
            }

            if (newFlash.message) {
                toast.add({
                    severity: 'info',
                    summary: 'Info',
                    detail: newFlash.message,
                    life: 4000,
                })
            }
        },
        { deep: true, immediate: true }
    )
}
