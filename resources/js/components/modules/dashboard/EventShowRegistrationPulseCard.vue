<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { formatDateTime } from '@/lib/dateTime';

defineProps<{
    event: IEvent;
    fillPercent: number;
    remainingSeats: number;
    progressTone: { ring: string; label: string; pill: string };
    cardShadow: string;
}>();
</script>

<template>
    <Card :class="['border-border/60 overflow-hidden rounded-2xl p-4 sm:p-5', cardShadow]">
        <CardContent class="flex flex-col gap-3 p-0">
            <div class="flex items-center gap-3">
                <div class="relative size-16 shrink-0">
                    <svg viewBox="0 0 120 120" class="size-full -rotate-90">
                        <circle
                            cx="60"
                            cy="60"
                            r="52"
                            stroke="currentColor"
                            stroke-width="7"
                            fill="none"
                            class="text-muted/60"
                        />
                        <circle
                            cx="60"
                            cy="60"
                            r="52"
                            stroke="currentColor"
                            stroke-width="7"
                            fill="none"
                            stroke-linecap="round"
                            :stroke-dasharray="2 * Math.PI * 52"
                            :stroke-dashoffset="2 * Math.PI * 52 * (1 - fillPercent / 100)"
                            :class="[progressTone.ring, 'transition-[stroke-dashoffset] duration-700 ease-out']"
                        />
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center leading-none">
                        <span class="text-foreground text-lg font-semibold tracking-tight tabular-nums"
                            >{{ fillPercent }}%</span
                        >
                        <span class="text-muted-foreground mt-0.5 text-[9px] font-medium tracking-[0.08em] uppercase"
                            >filled</span
                        >
                    </div>
                </div>

                <p class="text-foreground/85 min-w-0 text-[0.8125rem] leading-snug text-pretty">
                    <span class="font-semibold tabular-nums">{{ event.registered_count.toLocaleString() }}</span>
                    of
                    <span class="font-semibold tabular-nums">{{ event.quota.toLocaleString() }}</span>
                    seats are taken —
                    <span class="text-muted-foreground">{{ remainingSeats.toLocaleString() }} still open.</span>
                </p>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div class="border-border/60 rounded-lg border px-2 py-1.5">
                    <p class="text-muted-foreground text-[9px] font-medium tracking-[0.08em] uppercase">Opens</p>
                    <p class="text-foreground mt-0.5 text-xs font-medium tabular-nums">
                        {{ formatDateTime({ value: event.registration_start, variant: 'date-time-medium' }) }}
                    </p>
                </div>
                <div class="border-border/60 rounded-lg border px-2 py-1.5">
                    <p class="text-muted-foreground text-[9px] font-medium tracking-[0.08em] uppercase">Closes</p>
                    <p class="text-foreground mt-0.5 text-xs font-medium tabular-nums">
                        {{ formatDateTime({ value: event.registration_end, variant: 'date-time-medium' }) }}
                    </p>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
