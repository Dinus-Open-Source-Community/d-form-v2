<script setup lang="ts">
import FormBannerSettings from './FormBannerSettings.vue'
import type { FormBannerState } from './formBanner'

const banner = defineModel<FormBannerState>('banner', { required: true })

defineProps<{
    /** `card`: padded rounded card + h3; `inline`: inspector-style label */
    variant?: 'card' | 'inline'
}>()

function onBannerPatch(v: FormBannerState): void {
    Object.assign(banner.value, v)
}
</script>

<template>
    <section v-if="variant === 'card'" class="border-border bg-card rounded-2xl border p-5 shadow-xs">
        <h3 class="font-display text-foreground mb-3 text-sm font-semibold tracking-[-0.01em]">Banner</h3>
        <FormBannerSettings :model-value="banner" @update:model-value="onBannerPatch" />
    </section>
    <div v-else class="space-y-2 pt-2">
        <p class="text-foreground text-xs font-semibold">Banner</p>
        <FormBannerSettings :model-value="banner" @update:model-value="onBannerPatch" />
    </div>
</template>
