<script setup lang="ts">
import { CheckCircle2, XCircle, X } from 'lucide-vue-next'
import type { AuthToastPayload } from '@/types/auth'

defineProps<{
    toast: AuthToastPayload | null
}>()

const emit = defineEmits<{
    (e: 'dismiss'): void
}>()
</script>

<template>
    <Transition name="toast">
        <div
            v-if="toast"
            role="status"
            aria-live="polite"
            class="fixed right-4 top-4 z-50 flex items-center gap-3 rounded-2xl border bg-card px-4 py-3 shadow-sm"
            :class="
                toast.type === 'success'
                    ? 'border-success/25 text-success'
                    : 'border-destructive/25 text-destructive'
            "
        >
            <span
                class="grid size-8 place-items-center rounded-xl"
                :class="
                    toast.type === 'success'
                        ? 'bg-success/10'
                        : 'bg-destructive/10'
                "
            >
                <CheckCircle2 v-if="toast.type === 'success'" class="size-4" :stroke-width="2.5" />
                <XCircle v-else class="size-4" :stroke-width="2.5" />
            </span>
            <span class="text-sm font-semibold text-foreground">{{ toast.message }}</span>
            <button
                @click="emit('dismiss')"
                aria-label="Dismiss"
                class="ml-1 -mr-1 grid size-7 place-items-center rounded-lg text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
            >
                <X class="size-3.5" :stroke-width="2.5" />
            </button>
        </div>
    </Transition>
</template>

<style scoped>
.toast-enter-active {
    animation: toastIn 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}
.toast-leave-active {
    animation: toastOut 0.22s cubic-bezier(0.4, 0, 1, 1);
}
@keyframes toastIn {
    from {
        opacity: 0;
        transform: translateX(40px) scale(0.97);
    }
    to {
        opacity: 1;
        transform: translateX(0) scale(1);
    }
}
@keyframes toastOut {
    from {
        opacity: 1;
        transform: translateX(0);
    }
    to {
        opacity: 0;
        transform: translateX(40px);
    }
}
</style>
