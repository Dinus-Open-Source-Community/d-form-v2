<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Line } from 'vue-chartjs';
import {
    CategoryScale,
    Chart as ChartJS,
    LineElement,
    LinearScale,
    PointElement,
    type ChartOptions,
} from 'chart.js';
import { observeThemeAttribute, readThemeAttribute } from '@/adapters/browser/documentState';
import type { IWeeklyKpiPoint, TWeeklyKpiColor } from '@/types/kpi';

ChartJS.register(CategoryScale, LinearScale, LineElement, PointElement);

const props = defineProps<{
    points: IWeeklyKpiPoint[];
    metricLabel: string;
    color: TWeeklyKpiColor;
}>();

interface IChartColorPair {
    base: string;
    muted: string;
    hover: string;
}

interface IWeeklyKpiZeroMarker {
    index: number;
    left: string;
}

interface IWeeklyKpiTooltipPosition {
    left: string;
    top: string;
    transform: string;
}

const isDark = ref(false);
const activeIndex = ref<number | null>(null);
const isPointerInsideChart = ref(false);
const activeTooltipPosition = ref<IWeeklyKpiTooltipPosition | null>(null);
let releaseTheme: (() => void) | null = null;

const colorPairs = computed<Record<TWeeklyKpiColor, IChartColorPair>>(() => {
    if (isDark.value) {
        return {
            primary: {
                base: 'oklch(0.72 0.14 250)',
                muted: 'oklch(0.72 0.14 250 / 0.38)',
                hover: 'oklch(0.86 0.11 250)',
            },
            success: {
                base: 'oklch(0.72 0.14 155)',
                muted: 'oklch(0.72 0.14 155 / 0.38)',
                hover: 'oklch(0.86 0.11 155)',
            },
            warning: {
                base: 'oklch(0.78 0.14 85)',
                muted: 'oklch(0.78 0.14 85 / 0.38)',
                hover: 'oklch(0.9 0.1 85)',
            },
            destructive: {
                base: 'oklch(0.72 0.16 25)',
                muted: 'oklch(0.72 0.16 25 / 0.38)',
                hover: 'oklch(0.86 0.12 25)',
            },
        };
    }

    return {
        primary: {
            base: 'oklch(0.52 0.16 255)',
            muted: 'oklch(0.52 0.16 255 / 0.28)',
            hover: 'oklch(0.44 0.18 255)',
        },
        success: {
            base: 'oklch(0.54 0.14 155)',
            muted: 'oklch(0.54 0.14 155 / 0.28)',
            hover: 'oklch(0.46 0.16 155)',
        },
        warning: {
            base: 'oklch(0.62 0.14 85)',
            muted: 'oklch(0.62 0.14 85 / 0.28)',
            hover: 'oklch(0.54 0.16 85)',
        },
        destructive: {
            base: 'oklch(0.56 0.16 25)',
            muted: 'oklch(0.56 0.16 25 / 0.28)',
            hover: 'oklch(0.48 0.18 25)',
        },
    };
});

const chartData = computed(() => {
    const colors = colorPairs.value[props.color];

    return {
        labels: props.points.map((point: IWeeklyKpiPoint): string => point.label),
        datasets: [
            {
                data: props.points.map((point: IWeeklyKpiPoint): number => point.value),
                borderColor: colors.base,
                backgroundColor: 'transparent',
                borderWidth: 2,
                tension: 0.25,
                fill: false,
                pointRadius: 2,
                pointHoverRadius: 4,
                pointBackgroundColor: props.points.map((_point: IWeeklyKpiPoint, index: number): string =>
                    index === activeIndex.value ? colors.hover : colors.base
                ),
                pointBorderColor: props.points.map((_point: IWeeklyKpiPoint, index: number): string =>
                    index === activeIndex.value ? colors.hover : colors.base
                ),
            },
        ],
    };
});

const activePoint = computed<IWeeklyKpiPoint | null>(() => {
    const index = activeIndex.value;
    return index === null ? null : (props.points[index] ?? null);
});

const zeroMarkers = computed<IWeeklyKpiZeroMarker[]>(() => {
    const pointCount = props.points.length;

    return props.points.flatMap((point: IWeeklyKpiPoint, index: number): IWeeklyKpiZeroMarker[] => {
        if (point.value !== 0 || pointCount === 0) return [];
        return [{ index, left: `${((index + 0.5) / pointCount) * 100}%` }];
    });
});

const accessibleChartLabel = computed<string>(() =>
    `Tren mingguan ${props.metricLabel}: ${props.points.map((point: IWeeklyKpiPoint): string => `${point.label}, ${point.value.toLocaleString('id-ID')}`).join('; ')}`
);

const chartOptions = computed<ChartOptions<'line'>>(() => ({
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    onHover: (event, elements, chart) => {
        const index = elements[0]?.index;
        const point = index === undefined ? undefined : chart.getDatasetMeta(0).data[index];

        if (
            !isPointerInsideChart.value ||
            event.type === 'mouseout' ||
            index === undefined ||
            !(point instanceof PointElement)
        ) {
            activeIndex.value = null;
            activeTooltipPosition.value = null;
            return;
        }

        const position = point.getCenterPoint();
        const horizontalInset = Math.min(56, chart.width / 2);
        const left = Math.min(Math.max(position.x, horizontalInset), chart.width - horizontalInset);
        const side = position.y < 20 ? 'below' : 'above';

        activeIndex.value = index;
        activeTooltipPosition.value = {
            left: `${left}px`,
            top: `${position.y + (side === 'below' ? 5 : -5)}px`,
            transform: `translate(-50%, ${side === 'below' ? '0' : '-100%'})`,
        };
    },
    plugins: {
        legend: { display: false },
        tooltip: { enabled: false },
    },
    scales: {
        x: {
            offset: true,
            grid: { display: false },
            border: { display: false },
            ticks: { display: false },
        },
        y: {
            beginAtZero: true,
            grid: { display: false },
            border: { display: false },
            ticks: { display: false },
        },
    },
}));

onMounted(() => {
    isDark.value = readThemeAttribute() === 'dark';
    releaseTheme = observeThemeAttribute((theme: string | null): void => {
        isDark.value = theme === 'dark';
    });
});

onUnmounted(() => {
    releaseTheme?.();
    releaseTheme = null;
});
</script>

<template>
    <div
        data-slot="weekly-kpi-chart"
        data-chart-type="line"
        class="relative h-10 w-full sm:h-11"
        role="img"
        :aria-label="accessibleChartLabel"
        @mouseenter="isPointerInsideChart = true"
        @mouseleave="isPointerInsideChart = false; activeIndex = null; activeTooltipPosition = null"
    >
        <Line :data="chartData" :options="chartOptions" />
        <div aria-hidden="true" class="border-border/70 pointer-events-none absolute inset-x-1 bottom-1 border-t" />
        <span
            v-for="marker in zeroMarkers"
            :key="marker.index"
            data-slot="weekly-kpi-zero-marker"
            aria-hidden="true"
            class="pointer-events-none absolute bottom-0.5 size-1 -translate-x-1/2 rounded-full"
            :style="{
                left: marker.left,
                backgroundColor:
                    marker.index === activeIndex ? colorPairs[color].hover : colorPairs[color].muted,
            }"
        />
        <div
            v-if="activePoint"
            data-slot="weekly-kpi-tooltip"
            class="bg-popover text-popover-foreground border-border pointer-events-none absolute z-10 max-w-[calc(100%-0.5rem)] whitespace-nowrap rounded-md border px-2 py-1 text-[10px] font-semibold shadow-sm"
            :style="activeTooltipPosition ?? undefined"
        >
            {{ activePoint.label }}: {{ activePoint.value.toLocaleString('id-ID') }}
        </div>
    </div>
</template>
