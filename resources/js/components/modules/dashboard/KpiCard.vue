<script setup lang="ts">
import { computed, defineAsyncComponent } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Minus, TrendingDown, TrendingUp } from 'lucide-vue-next';
import type { TIconComponent } from '@/types/icons';
import type { IWeeklyKpiPoint, TWeeklyKpiColor } from '@/types/kpi';

const WeeklyKpiBarChart = defineAsyncComponent(() => import('./WeeklyKpiBarChart.vue'));

type TWeeklyKpiDirection = 'up' | 'down' | 'flat';
type TWeeklyKpiAbsoluteDeltaUnit = 'count' | 'percentagePoints';

const props = defineProps<{
    label: string;
    value: string | number;
    trend?: number;
    icon: TIconComponent;
    color?: TWeeklyKpiColor;
    weeklyTrend?: IWeeklyKpiPoint[];
    weeklyTrendAbsoluteDeltaUnit?: TWeeklyKpiAbsoluteDeltaUnit;
}>();

const isPositive = computed(() => (props.trend ?? 0) >= 0);

const iconColorClass = computed(() => {
    const map: Record<TWeeklyKpiColor, string> = {
        primary: 'text-primary',
        success: 'text-success',
        warning: 'text-warning-foreground',
        destructive: 'text-destructive',
    };
    return map[props.color ?? 'primary'];
});

const weeklyCurrentValue = computed<number>(() => {
    const points = props.weeklyTrend ?? [];
    return points[points.length - 1]?.value ?? 0;
});

const weeklyPreviousValue = computed<number>(() => {
    const points = props.weeklyTrend ?? [];
    return points[points.length - 2]?.value ?? 0;
});

const weeklyDirection = computed<TWeeklyKpiDirection>(() => {
    if (weeklyCurrentValue.value === weeklyPreviousValue.value) return 'flat';
    return weeklyCurrentValue.value > weeklyPreviousValue.value ? 'up' : 'down';
});

const weeklyChangePercent = computed<number | null>(() => {
    if (weeklyPreviousValue.value === 0) return null;
    return Math.round(((weeklyCurrentValue.value - weeklyPreviousValue.value) / weeklyPreviousValue.value) * 100);
});

const weeklyAbsoluteDelta = computed<string>(() => {
    const value = `+${weeklyCurrentValue.value.toLocaleString('id-ID')}`;
    return props.weeklyTrendAbsoluteDeltaUnit === 'percentagePoints' ? `${value} pp` : value;
});

const weeklyTrendIcon = computed<TIconComponent>(() => {
    if (weeklyDirection.value === 'up') return TrendingUp;
    if (weeklyDirection.value === 'down') return TrendingDown;
    return Minus;
});

const weeklyTrendClass = computed<string>(() => {
    if (weeklyDirection.value === 'up') return 'text-success';
    if (weeklyDirection.value === 'down') return 'text-destructive';
    return 'text-muted-foreground';
});

const weeklyTrendLabel = computed<string>(() => {
    if (weeklyPreviousValue.value === 0) {
        return weeklyCurrentValue.value === 0
            ? 'Belum ada aktivitas minggu ini'
            : `Baru minggu ini · ${weeklyAbsoluteDelta.value}`;
    }

    if (weeklyDirection.value === 'flat') return 'Tetap · 0% dari minggu lalu';

    const direction = weeklyDirection.value === 'up' ? 'Naik' : 'Turun';
    const changePercent = weeklyChangePercent.value;

    return changePercent === null
        ? direction
        : `${direction} ${Math.abs(changePercent)}% dari minggu lalu`;
});
</script>

<template>
    <Card
        data-slot="kpi-card"
        class="border-border/70 gap-0 rounded-xl py-0 shadow-sm"
    >
        <CardContent class="p-3.5 sm:p-4">
            <div class="flex items-center justify-between gap-3">
                <p class="text-muted-foreground min-w-0 text-sm leading-5 font-medium">{{ label }}</p>
                <div
                    data-slot="kpi-icon"
                    :class="cn('flex size-8 shrink-0 items-center justify-center', iconColorClass)"
                >
                    <component :is="icon" class="size-[18px]" aria-hidden="true" />
                </div>
            </div>
            <div class="mt-0.5 flex flex-wrap items-baseline justify-between gap-x-2 gap-y-1">
                <p class="font-display text-foreground text-3xl font-semibold tracking-[-0.04em] sm:text-4xl">{{ value }}</p>
                <div
                    v-if="weeklyTrend !== undefined && weeklyTrend.length > 0"
                    data-slot="weekly-kpi-trend"
                    class="flex items-center gap-1 text-[11px] leading-4 font-semibold"
                    :class="weeklyTrendClass"
                >
                    <component :is="weeklyTrendIcon" class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>{{ weeklyTrendLabel }}</span>
                </div>
                <div v-else-if="trend !== undefined" class="flex items-center gap-1">
                    <component
                        :is="isPositive ? TrendingUp : TrendingDown"
                        :class="cn('size-3.5', isPositive ? 'text-success' : 'text-destructive')"
                    />
                    <span :class="cn('text-xs font-semibold', isPositive ? 'text-success' : 'text-destructive')">
                        {{ isPositive ? '+' : '' }}{{ trend }}%
                    </span>
                    <span class="text-muted-foreground text-xs">vs last month</span>
                </div>
            </div>
            <div v-if="weeklyTrend !== undefined && weeklyTrend.length > 0" class="border-border/60 mt-2 border-t pt-2">
                <WeeklyKpiBarChart
                    :points="weeklyTrend"
                    :metric-label="label"
                    :color="color ?? 'primary'"
                />
            </div>
        </CardContent>
    </Card>
</template>
