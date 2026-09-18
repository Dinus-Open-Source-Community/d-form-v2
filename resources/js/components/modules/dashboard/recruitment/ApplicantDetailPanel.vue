<script setup lang="ts">
import { computed } from 'vue'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Badge } from '@/components/ui/badge'
import ApplicantDetailContent, { type ApplicationDetail } from './ApplicantDetailContent.vue'

const props = defineProps<{
    application: ApplicationDetail | null
    loading: boolean
}>()

const emit = defineEmits<{ close: [] }>()

const sheetOpen = computed<boolean>({
    get: () => props.application !== null,
    set: (value: boolean) => {
        if (!value) emit('close')
    },
})
</script>

<template>
    <Sheet v-model:open="sheetOpen">
        <SheetContent
            side="right"
            overlay-class="bg-black/60 backdrop-blur-sm"
            class="inset-y-0 right-0 h-full w-full gap-0 p-0 sm:inset-y-3 sm:right-3 sm:h-[calc(100%-1.5rem)] sm:w-[52rem] sm:max-w-[calc(100vw-1.5rem)] sm:rounded-2xl sm:border sm:shadow-xl"
        >
            <SheetHeader class="border-border/70 shrink-0 space-y-1 border-b py-4 pl-4 pr-12 text-left">
                <SheetTitle class="truncate text-base">
                    {{ application?.full_name ?? 'Detail peserta' }}
                </SheetTitle>
                <SheetDescription class="text-muted-foreground truncate text-xs">
                    {{ application?.registration_number }} · {{ application?.nim }}
                </SheetDescription>
                <div v-if="application" class="flex flex-wrap items-center gap-1.5 pt-1.5">
                    <Badge variant="secondary">{{ application.stage_label }}</Badge>
                    <Badge variant="outline">{{ application.result_label }}</Badge>
                </div>
            </SheetHeader>

            <div
                v-if="application"
                :aria-busy="loading"
                class="min-h-0 flex-1 overflow-y-auto p-4 transition-opacity"
                :class="loading ? 'opacity-60' : ''"
            >
                <ApplicantDetailContent :application="application" readonly />
            </div>
        </SheetContent>
    </Sheet>
</template>
