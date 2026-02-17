import { onMounted, onUnmounted, Ref } from 'vue'

export function useFocusTrap(containerRef: Ref<HTMLElement | null>, isActive: Ref<boolean>) {
    const focusableSelectors = [
        'button:not([disabled])',
        '[href]',
        'input:not([disabled])',
        'select:not([disabled])',
        'textarea:not([disabled])',
        '[tabindex]:not([tabindex="-1"])',
    ].join(', ')

    let previouslyFocused: HTMLElement | null = null

    const getFocusableElements = (): HTMLElement[] => {
        if (!containerRef.value) return []
        return Array.from(containerRef.value.querySelectorAll(focusableSelectors))
    }

    const trap = (e: KeyboardEvent) => {
        if (!isActive.value || !containerRef.value) return
        if (e.key !== 'Tab') return

        const focusable = getFocusableElements()
        if (focusable.length === 0) return

        const first = focusable[0]
        const last = focusable[focusable.length - 1]

        if (e.shiftKey) {
            if (document.activeElement === first) {
                e.preventDefault()
                last.focus()
            }
        } else {
            if (document.activeElement === last) {
                e.preventDefault()
                first.focus()
            }
        }
    }

    const activate = () => {
        previouslyFocused = document.activeElement as HTMLElement
        document.addEventListener('keydown', trap)
        const focusable = getFocusableElements()
        if (focusable.length > 0) {
            focusable[0].focus()
        }
    }

    const deactivate = () => {
        document.removeEventListener('keydown', trap)
        if (previouslyFocused && typeof previouslyFocused.focus === 'function') {
            previouslyFocused.focus()
        }
    }

    onMounted(() => {
        if (isActive.value) {
            activate()
        }
    })

    onUnmounted(() => {
        deactivate()
    })

    return { activate, deactivate }
}
