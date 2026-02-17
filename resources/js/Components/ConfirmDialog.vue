<script setup lang="ts">
import { ref, watch, computed, toRef } from 'vue'
import { useFocusTrap } from '@/Composables/useFocusTrap'

const props = withDefaults(defineProps<{
    show: boolean
    title: string
    message: string
    confirmLabel?: string
    variant?: 'danger' | 'warning'
}>(), {
    confirmLabel: 'Delete',
    variant: 'danger',
})

const emit = defineEmits<{
    confirm: []
    cancel: []
    'update:show': [value: boolean]
}>()

const dialogRef = ref<HTMLElement | null>(null)
const showRef = toRef(props, 'show')

useFocusTrap(dialogRef, showRef)

const handleConfirm = () => {
    emit('confirm')
    emit('update:show', false)
}

const handleCancel = () => {
    emit('cancel')
    emit('update:show', false)
}

const handleKeydown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.show) {
        handleCancel()
    }
}

const variantConfig = computed(() => {
    const configs = {
        danger: {
            iconBg: 'bg-red-500/10',
            iconText: 'text-red-400',
            buttonClass: 'bg-red-500 hover:bg-red-600 text-white',
        },
        warning: {
            iconBg: 'bg-amber-500/10',
            iconText: 'text-amber-400',
            buttonClass: 'bg-amber-500 hover:bg-amber-600 text-white',
        },
    }
    return configs[props.variant]
})

watch(() => props.show, (val) => {
    if (val) {
        document.addEventListener('keydown', handleKeydown)
    } else {
        document.removeEventListener('keydown', handleKeydown)
    }
})
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition ease-out duration-200"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition ease-in duration-150"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="handleCancel" />

                <Transition
                    enter-active-class="transition ease-out duration-200"
                    enter-from-class="opacity-0 scale-95"
                    enter-to-class="opacity-100 scale-100"
                    leave-active-class="transition ease-in duration-150"
                    leave-from-class="opacity-100 scale-100"
                    leave-to-class="opacity-0 scale-95"
                >
                    <div
                        v-if="show"
                        ref="dialogRef"
                        class="relative bg-[var(--color-surface-0)] border border-white/10 rounded-xl p-6 w-full max-w-sm shadow-2xl"
                        role="alertdialog"
                        aria-modal="true"
                        :aria-labelledby="`dialog-title-${Date.now()}`"
                        :aria-describedby="`dialog-desc-${Date.now()}`"
                    >
                        <div class="flex items-center justify-center w-12 h-12 mx-auto mb-4 rounded-full" :class="variantConfig.iconBg">
                            <svg v-if="variant === 'danger'" class="w-6 h-6" :class="variantConfig.iconText" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <svg v-else class="w-6 h-6" :class="variantConfig.iconText" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>

                        <h3 class="text-lg font-semibold text-white text-center mb-2">{{ title }}</h3>
                        <p class="text-sm text-zinc-400 text-center mb-6">{{ message }}</p>

                        <div class="flex gap-3">
                            <button @click="handleCancel" class="flex-1 px-4 py-2.5 text-sm font-medium text-zinc-300 bg-white/5 hover:bg-white/10 border border-white/10 rounded-lg transition-colors">
                                Cancel
                            </button>
                            <button ref="confirmButton" @click="handleConfirm" class="flex-1 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors" :class="variantConfig.buttonClass">
                                {{ confirmLabel }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>
