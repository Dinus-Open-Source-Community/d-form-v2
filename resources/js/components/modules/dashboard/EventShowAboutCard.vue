<script setup lang="ts">
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

defineProps<{
    event: IEvent
    cardShadow: string
    formatDate: (v: string) => string
}>()
</script>

<template>
    <Card :class="['rounded-2xl border-border/60', cardShadow]">
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">About this event</CardTitle>
                <span class="text-[11px] text-muted-foreground">Last updated {{ formatDate(event.updated_at) }}</span>
            </div>
        </CardHeader>
        <CardContent class="pt-0">
            <div
                class="prose prose-sm max-w-none text-pretty leading-relaxed text-foreground/90 prose-headings:font-semibold prose-headings:tracking-tight prose-headings:text-foreground prose-p:my-3 prose-ul:my-3 prose-li:my-0.5 prose-strong:text-foreground"
                v-html="event.description"
            />
        </CardContent>
    </Card>
</template>
