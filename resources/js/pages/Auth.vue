<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { routes } from '@/lib/routes'
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
                <a :href="routes.home" class="group mb-10 inline-block transition-transform hover:-translate-y-px">
                    <img
                        :src="`/${encodeURIComponent('DForm 1.png')}`"
                        alt="DForm"
                        class="h-10 w-auto select-none"
                        width="160"
                        height="40"
                    />
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
