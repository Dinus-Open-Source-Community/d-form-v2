<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import TiptapRichHtml from '@/components/modules/dashboard/events/TiptapRichHtml.vue'
import { formatDateTime } from '@/lib/dateTime'

defineProps<{
    event: IEvent
    cardShadow: string
}>()
</script>

<template>
    <Card :class="['rounded-2xl border-border/60', cardShadow]">
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between gap-3">
                <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">
                    Tentang acara
                </CardTitle>
                <span class="text-[11px] text-muted-foreground"
                    >Diperbarui {{ formatDateTime({ value: event.updated_at, variant: 'date-medium' }) }}</span
                >
            </div>
        </CardHeader>
        <CardContent class="pt-0">
            <TiptapRichHtml :html="event.description" />
        </CardContent>
    </Card>
</template>
