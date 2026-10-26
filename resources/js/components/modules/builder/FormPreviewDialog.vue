<script setup lang="ts">
import { X } from 'lucide-vue-next'
import FormPreviewBody from '@/components/modules/builder/FormPreviewBody.vue'
import { Button } from '@/components/ui/button'
import type { FormPreviewField } from '@/types/form-builder'

const props = defineProps<{
    open: boolean
    title?: string
    description?: string
    formBannerUrl?: string
    formBannerCaption?: string
    fields?: FormPreviewField[]
}>()

const emit = defineEmits<{ close: [] }>()
</script>

<template>
    <Teleport to="body">
        <Transition name="preview-fade">
            <div
                v-if="open"
                class="fixed inset-0 z-[110] flex items-center justify-center p-4 sm:p-6"
                role="dialog"
                aria-modal="true"
                aria-label="Form respondent preview"
            >
                <div class="absolute inset-0 bg-foreground/40 backdrop-blur-[2px]" @click="emit('close')" />

                <div
                    class="relative z-10 flex max-h-[min(92vh,880px)] w-full max-w-3xl flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
                >
                    <!-- Chrome: matches dashboard “tool” panels -->
                    <div
                        class="flex shrink-0 items-start justify-between gap-3 border-b border-border bg-gradient-to-r from-muted/60 via-card to-primary/5 px-4 py-3 sm:px-5"
                    >
                        <div class="flex w-full h-full items-start">
                            <p class=" text-base font-extrabold uppercase tracking-wider text-muted-foreground">
                                Respondent preview
                            </p>
                        </div>
                        <Button
                            radius="icon"
                            variant="ghost"
                            size="icon"
                            class="size-9 shrink-0 border border-transparent hover:border-border hover:bg-muted/60"
                            type="button"
                            aria-label="Close preview"
                            @click="emit('close')"
                        >
                            <X class="size-4" />
                        </Button>
                    </div>

                    <div class="flex-1 overflow-y-auto bg-muted/25 px-4 py-6 sm:px-6">
                        <FormPreviewBody
                            :title="props.title"
                            :description="props.description"
                            :form-banner-url="props.formBannerUrl"
                            :form-banner-caption="props.formBannerCaption"
                            :fields="props.fields"
                        />
                    </div>

                    <div class="shrink-0 border-t border-border bg-muted/30 px-4 py-3 sm:px-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-[11px] leading-relaxed text-muted-foreground">
                                Preview mode — nothing is saved. Close when you’re happy with the layout.
                            </p>
                            <Button type="button" class="shrink-0 border border-border shadow-xs" @click="emit('close')">
                                Done
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.preview-fade-enter-active,
.preview-fade-leave-active {
    transition: opacity 0.2s ease;
}
.preview-fade-enter-from,
.preview-fade-leave-to {
    opacity: 0;
}
</style>
