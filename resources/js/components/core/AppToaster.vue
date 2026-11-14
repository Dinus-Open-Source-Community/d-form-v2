<script lang="ts" setup>
import { CircleCheck, Info, Loader2, OctagonX, TriangleAlert, X } from 'lucide-vue-next';
import 'vue-sonner/style.css';
import { Toaster, type ToasterProps } from 'vue-sonner';
import { cn } from '@/lib/utils';

/**
 * Toaster aplikasi: satu-satunya titik mount `vue-sonner` untuk semua layout.
 *
 * Menyimpan token warna dan ikon di satu tempat, termasuk aturan tombol aksi
 * (`Coba lagi`, `Muat ulang`) supaya tampil sebagai tombol outline kecil yang
 * konsisten dengan `Button` aplikasi, bukan tombol bawaan sonner (24px, radius 4px,
 * solid gelap). Lihat blok `<style>` untuk detail ukuran dan state.
 */
const props = withDefaults(defineProps<ToasterProps>(), {
    position: 'top-right',
    richColors: true,
});
</script>

<template>
    <Toaster
        :class="cn('toaster group app-toaster', props.class)"
        :style="{
            '--width': 'min(100vw - 1.5rem, 22.5rem)',
            '--normal-bg': 'var(--card)',
            '--normal-text': 'var(--foreground)',
            '--normal-border': 'var(--app-border)',
            '--normal-bg-hover': 'var(--accent)',
            '--border-radius': '0.875rem',
        }"
        v-bind="props"
    >
        <template #success-icon>
            <CircleCheck class="text-success size-4" :stroke-width="2.4" />
        </template>
        <template #info-icon>
            <Info class="text-primary size-4" :stroke-width="2.4" />
        </template>
        <template #warning-icon>
            <TriangleAlert class="text-warning size-4" :stroke-width="2.4" />
        </template>
        <template #error-icon>
            <OctagonX class="text-destructive size-4" :stroke-width="2.4" />
        </template>
        <template #loading-icon>
            <Loader2 class="text-muted-foreground size-4 animate-spin" :stroke-width="2.4" />
        </template>
        <template #close-icon>
            <X class="size-3.5" :stroke-width="2.4" />
        </template>
    </Toaster>
</template>

<style>
.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'] {
    border-width: 1px !important;
    border-color: var(--app-border) !important;
    box-shadow: var(--shadow-sm) !important;
    font-weight: 500;
    gap: 0.625rem;
}

.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'] [data-content] {
    min-width: 0;
    gap: 0.125rem;
}

.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'] [data-title] {
    color: var(--foreground) !important;
    font-weight: 600;
    line-height: 1.35;
    letter-spacing: -0.005em;
}

.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'] [data-description] {
    color: var(--muted-foreground) !important;
    font-weight: 400;
    line-height: 1.45;
}

/* Tombol aksi: mengikuti `Button variant="outline" size="sm"` (h-9, rounded-lg, shadow-xs). */
.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'] [data-button] {
    height: 2.25rem;
    min-height: 2.25rem;
    padding: 0 0.75rem;
    margin-left: 0.75rem;
    margin-right: 0;
    border: 1px solid color-mix(in srgb, var(--foreground) 10%, var(--app-border));
    border-radius: 0.625rem;
    background: var(--card);
    color: var(--foreground);
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: -0.005em;
    line-height: 1;
    box-shadow: var(--shadow-xs);
    transition:
        background-color 150ms cubic-bezier(0.22, 1, 0.36, 1),
        border-color 150ms cubic-bezier(0.22, 1, 0.36, 1),
        transform 150ms cubic-bezier(0.22, 1, 0.36, 1);
}

.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'] [data-button]:hover {
    background: var(--accent);
    border-color: color-mix(in srgb, var(--primary) 25%, var(--app-border));
}

.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'] [data-button]:active {
    transform: scale(0.98);
}

.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'] [data-button]:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--ring) 32%, transparent);
}

/* Aksi pada toast error tetap netral, hanya border yang mengikuti warna destructive. */
.app-toaster[data-sonner-toaster] [data-sonner-toast][data-styled='true'][data-type='error'] [data-button] {
    border-color: color-mix(in srgb, var(--destructive) 30%, var(--app-border));
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
