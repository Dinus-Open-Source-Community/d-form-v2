<script lang="ts" setup>
import type { ToasterProps } from 'vue-sonner'
import { CircleCheck, Info, Loader2, OctagonX, TriangleAlert, X } from 'lucide-vue-next'
import { Toaster as Sonner } from 'vue-sonner'
import { cn } from '@/lib/utils'

const props = defineProps<ToasterProps>()
</script>

<template>
    <Sonner
        :class="cn('toaster group app-toaster', props.class)"
        :style="{
            '--width': 'min(100vw - 2rem, 22rem)',
            '--normal-bg': 'var(--card)',
            '--normal-text': 'var(--foreground)',
            '--normal-border': 'var(--app-border)',
            '--border-radius': '0.875rem',
        }"
        v-bind="props"
    >
        <template #success-icon>
            <CircleCheck class="size-4 text-success" :stroke-width="2.4" />
        </template>
        <template #info-icon>
            <Info class="size-4 text-primary" :stroke-width="2.4" />
        </template>
        <template #warning-icon>
            <TriangleAlert class="size-4 text-warning" :stroke-width="2.4" />
        </template>
        <template #error-icon>
            <OctagonX class="size-4 text-destructive" :stroke-width="2.4" />
        </template>
        <template #loading-icon>
            <Loader2 class="size-4 animate-spin text-muted-foreground" :stroke-width="2.4" />
        </template>
        <template #close-icon>
            <X class="size-3.5" :stroke-width="2.4" />
        </template>
    </Sonner>
</template>

<style>
.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'] {
    border-width: 1px !important;
    border-color: var(--app-border) !important;
    box-shadow: var(--shadow-sm) !important;
    font-weight: 500;
}

.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'] [data-title] {
    color: var(--foreground) !important;
    font-weight: 600;
    letter-spacing: -0.005em;
}

.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'] [data-description] {
    color: var(--muted-foreground) !important;
    font-weight: 400;
}

.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'] [data-close-button] {
    border: 1px solid var(--app-border) !important;
    background: var(--card) !important;
    color: var(--muted-foreground) !important;
}

.app-toaster [data-rich-colors='true'][data-sonner-toast][data-type='success'][data-styled='true'] {
    background: color-mix(in srgb, var(--success) 8%, var(--card)) !important;
    border-color: color-mix(in srgb, var(--success) 25%, var(--app-border)) !important;
}

.app-toaster [data-rich-colors='true'][data-sonner-toast][data-type='info'][data-styled='true'] {
    background: color-mix(in srgb, var(--primary) 8%, var(--card)) !important;
    border-color: color-mix(in srgb, var(--primary) 25%, var(--app-border)) !important;
}

.app-toaster [data-rich-colors='true'][data-sonner-toast][data-type='warning'][data-styled='true'] {
    background: color-mix(in srgb, var(--warning) 14%, var(--card)) !important;
    border-color: color-mix(in srgb, var(--warning) 30%, var(--app-border)) !important;
}

.app-toaster [data-rich-colors='true'][data-sonner-toast][data-type='error'][data-styled='true'] {
    background: color-mix(in srgb, var(--destructive) 8%, var(--card)) !important;
    border-color: color-mix(in srgb, var(--destructive) 25%, var(--app-border)) !important;
}
</style>
