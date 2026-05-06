<script setup lang="ts">
import LocalLottie from '@/components/core/LocalLottie.vue'
import type { LottieName } from '@/lib/lotties'

defineProps<{
    title: string
    description?: string
    animationUrl?: string
    animationName?: LottieName
    size?: number
}>()
</script>

<template>
    <div class="app-surface flex flex-col items-center justify-center px-6 py-14 text-center">
        <LocalLottie
            v-if="animationUrl || animationName"
            :name="animationUrl ? undefined : (animationName ?? 'emptyData')"
            :animation-link="animationUrl"
            :height="size ?? 180"
            :width="size ?? 180"
            class="mb-2"
        />
        <p class="font-display text-base font-bold tracking-[-0.015em] text-foreground">{{ title }}</p>
        <p v-if="description" class="mt-2 max-w-sm text-sm leading-relaxed text-muted-foreground">{{ description }}</p>
        <div class="mt-5">
            <slot />
        </div>
    </div>
</template>
