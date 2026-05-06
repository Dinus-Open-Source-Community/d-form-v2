<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import AuthToast from '@/components/modules/auth/AuthToast.vue'
import AuthIllustration from '@/components/modules/auth/AuthIllustration.vue'
import AuthLoginForm from '@/components/modules/auth/AuthLoginForm.vue'
import AuthRegisterForm from '@/components/modules/auth/AuthRegisterForm.vue'
import AuthOAuthButtons from '@/components/modules/auth/AuthOAuthButtons.vue'
import type { AuthToastPayload, AuthToastType } from '@/types/auth'

const mode = ref<'login' | 'register'>('login')

const toast = ref<AuthToastPayload | null>(null)
let toastTimer: ReturnType<typeof setTimeout> | null = null

function showToast(type: AuthToastType, message: string): void {
    if (toastTimer) clearTimeout(toastTimer)
    toast.value = { type, message }
    toastTimer = setTimeout(() => {
        toast.value = null
    }, 4000)
}

function dismissToast(): void {
    toast.value = null
}

const page = usePage()
const errors = computed<Record<string, string>>(() => {
    const p = page.props as { errors?: Record<string, string> }
    return p.errors ?? {}
})
watch(
    errors,
    (newErrors) => {
        const keys = Object.keys(newErrors)
        if (keys.length > 0) showToast('error', newErrors[keys[0]])
    },
    { deep: true },
)
</script>

<template>
    <AuthToast :toast="toast" @dismiss="dismissToast" />

    <div class="grid min-h-dvh w-full grid-cols-1 bg-background lg:grid-cols-2">
        <AuthIllustration />

        <div class="flex min-h-dvh flex-col items-center justify-center overflow-y-auto px-6 py-12 sm:px-12">
            <div class="w-full max-w-md rounded-3xl border border-border bg-card p-7 shadow-sm sm:p-8">
                <a href="/" class="group mb-10 inline-flex items-center gap-2.5">
                    <div
                        class="grid size-10 place-items-center rounded-xl bg-primary text-primary-foreground shadow-xs transition-transform group-hover:-translate-y-px"
                    >
                        <svg
                            width="20"
                            height="20"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2.2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z" />
                            <path d="M14 2v6h6" />
                            <path d="M16 13H8" />
                            <path d="M16 17H8" />
                            <path d="M10 9H8" />
                        </svg>
                    </div>
                    <span class="font-display text-2xl font-bold tracking-[-0.02em] text-foreground">
                        D<span class="text-primary">Form</span>
                    </span>
                </a>

                <AuthLoginForm v-if="mode === 'login'" @toast="showToast" @switch-mode="mode = 'register'" />
                <AuthRegisterForm v-else @toast="showToast" @switch-mode="mode = 'login'" />

                <AuthOAuthButtons />

                <p class="mt-10 text-center text-[10px] font-medium uppercase tracking-[0.16em] text-muted-foreground lg:hidden">
                    Created by Dinus Open Source Community
                </p>
            </div>
        </div>
    </div>
</template>
