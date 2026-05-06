<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card'

defineProps<{
    event: IEvent
    fillPercent: number
    remainingSeats: number
    progressTone: { ring: string; label: string; pill: string }
    cardShadow: string
    formatDateTime: (v: string) => string
}>()
</script>

<template>
    <Card :class="['overflow-hidden rounded-2xl border-border/60', cardShadow]">
        <CardContent class="grid gap-6 p-6 sm:grid-cols-[auto_1fr] sm:items-center sm:gap-8">
            <div class="relative mx-auto size-36 shrink-0 sm:mx-0">
                <svg viewBox="0 0 120 120" class="size-full -rotate-90">
                    <circle cx="60" cy="60" r="52" stroke="currentColor" stroke-width="10" fill="none" class="text-muted/50" />
                    <circle
                        cx="60" cy="60" r="52"
                        stroke="currentColor" stroke-width="10" fill="none"
                        stroke-linecap="round"
                        :stroke-dasharray="2 * Math.PI * 52"
                        :stroke-dashoffset="2 * Math.PI * 52 * (1 - fillPercent / 100)"
                        :class="[progressTone.ring, 'transition-[stroke-dashoffset] duration-700 ease-out']"
                    />
                </svg>
                <div class="absolute inset-0 flex flex-col items-center justify-center">
                    <span class="text-3xl font-semibold tabular-nums tracking-tight text-foreground">{{ fillPercent }}%</span>
                    <span class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">filled</span>
                </div>
            </div>

            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-sm font-semibold tracking-tight text-foreground">Registration pulse</h3>
                    <span :class="['inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[11px] font-medium', progressTone.pill]">
                        <span class="size-1.5 rounded-full bg-current" />
                        {{ progressTone.label }}
                    </span>
                </div>
                <p class="mt-2 text-[15px] leading-relaxed text-foreground/85">
                    <span class="font-semibold tabular-nums">{{ event.registered_count.toLocaleString() }}</span>
                    of
                    <span class="font-semibold tabular-nums">{{ event.quota.toLocaleString() }}</span>
                    seats are taken —
                    <span class="text-muted-foreground">{{ remainingSeats.toLocaleString() }} still open.</span>
                </p>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-xl border border-border/50 bg-muted/30 px-3.5 py-2.5">
                        <p class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Opens</p>
                        <p class="mt-0.5 text-[13px] font-medium text-foreground">{{ formatDateTime(event.registration_start) }}</p>
                    </div>
                    <div class="rounded-xl border border-border/50 bg-muted/30 px-3.5 py-2.5">
                        <p class="text-[10px] font-medium uppercase tracking-wider text-muted-foreground">Closes</p>
                        <p class="mt-0.5 text-[13px] font-medium text-foreground">{{ formatDateTime(event.registration_end) }}</p>
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
