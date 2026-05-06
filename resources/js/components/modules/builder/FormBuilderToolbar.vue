<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Link } from '@inertiajs/vue3'
import { ArrowLeft, Eye, Save } from 'lucide-vue-next'

defineProps<{
    backHref: string
    toolbarSubtitle: string
    headingTitle: string
    isReadyToSave: boolean
    validationIssueCount: number
    isEmpty: boolean
    processing: boolean
    saveLabel: string
}>()

defineEmits<{
    preview: []
    save: []
}>()
</script>

<template>
    <header
        class="border-border bg-card/85 sticky top-0 z-30 flex shrink-0 items-center justify-between gap-3 border-b px-3 py-2.5 backdrop-blur-xl sm:px-5"
    >
        <div class="flex min-w-0 items-center gap-2">
            <Button variant="ghost" size="icon-sm" class="rounded-lg" as-child>
                <Link :href="backHref" aria-label="Back to forms list">
                    <ArrowLeft class="size-4" />
                </Link>
            </Button>
            <div class="min-w-0">
                <p class="text-muted-foreground truncate text-[10px] font-semibold tracking-[0.14em] uppercase">
                    {{ toolbarSubtitle }}
                </p>
                <h1 class="text-foreground truncate text-sm font-semibold tracking-[-0.01em]">
                    {{ headingTitle || 'Untitled form' }}
                </h1>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <slot name="toolbar-extra" />
            <span
                class="hidden items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-semibold tracking-[0.12em] uppercase sm:inline-flex"
                :class="
                    isReadyToSave
                        ? 'border-success/20 bg-success/10 text-success'
                        : 'border-warning/20 bg-warning/10 text-warning'
                "
            >
                <span class="size-1.5 rounded-full bg-current" />
                {{ isReadyToSave ? 'Ready' : `${validationIssueCount} missing` }}
            </span>
            <Button variant="outline" size="sm" :disabled="isEmpty" @click="$emit('preview')">
                <Eye class="size-4 sm:mr-2" />
                <span class="hidden sm:inline">Preview</span>
            </Button>
            <Button size="sm" :disabled="processing" @click="$emit('save')">
                <Save class="size-4 sm:mr-2" />
                <span class="hidden sm:inline">{{ saveLabel }}</span>
            </Button>
        </div>
    </header>
</template>
