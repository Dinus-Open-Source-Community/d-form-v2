<script setup lang="ts">
import FormPreviewBody from '@/components/modules/builder/FormPreviewBody.vue'
import type { FormPreviewField } from '@/types/form-builder'

defineProps<{
    title?: string
    description?: string
    bannerUrl?: string
    bannerCaption?: string
    fields?: FormPreviewField[]
}>()
</script>

<template>
    <!-- Kolom pratinjau live: header tetap di tempat, badan pratinjau menggulir sendiri. -->
    <aside
        data-testid="form-builder-live-preview"
        class="border-border bg-muted/20 flex min-h-0 w-[360px] shrink-0 flex-col overflow-hidden border-l"
        aria-label="Pratinjau live formulir"
    >
        <header
            class="border-border bg-card flex shrink-0 items-center justify-between gap-2 border-b px-4 py-3"
        >
            <p class="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Pratinjau</p>
            <span
                class="border-emerald-500/30 bg-emerald-500/10 inline-flex items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium text-emerald-700 dark:text-emerald-300"
            >
                <span class="size-1.5 rounded-full bg-emerald-500" aria-hidden="true" />
                Live
            </span>
        </header>

        <div class="min-h-0 flex-1 overflow-y-auto p-3">
            <div class="border-border bg-card overflow-hidden rounded-2xl border shadow-sm">
                <FormPreviewBody
                    variant="inline"
                    :title="title"
                    :description="description"
                    :form-banner-url="bannerUrl"
                    :form-banner-caption="bannerCaption"
                    :fields="fields"
                />
            </div>
        </div>
    </aside>
</template>
