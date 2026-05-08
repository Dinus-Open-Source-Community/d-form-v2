<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Line } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
    type ChartOptions,
} from 'chart.js';
import { TrendingUp } from 'lucide-vue-next';

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip);

const props = withDefaults(
    defineProps<{
        points?: { label: string; count: number }[];
    }>(),
    { points: () => [] },
);

const isDark = ref(false);

onMounted(() => {
    const sync = () => {
        isDark.value = document.documentElement.getAttribute('data-theme') === 'dark';
    };
    sync();
    const mo = new MutationObserver(sync);
    mo.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    onUnmounted(() => mo.disconnect());
});

const total = computed(() => props.points.reduce((s, d) => s + d.count, 0));

const chartData = computed(() => ({
    labels: props.points.map((d) => d.label),
    datasets: [
        {
            label: 'Pengajuan',
            data: props.points.map((d) => d.count),
            borderColor: isDark.value ? 'oklch(0.72 0.14 250)' : 'oklch(0.52 0.16 255)',
            backgroundColor: isDark.value ? 'oklch(0.72 0.14 250 / 0.12)' : 'oklch(0.52 0.16 255 / 0.14)',
            fill: true,
            tension: 0.35,
            pointRadius: 3,
            pointHoverRadius: 6,
            pointBackgroundColor: isDark.value ? 'oklch(0.85 0.08 250)' : 'oklch(0.52 0.16 255)',
            pointBorderColor: isDark.value ? 'oklch(0.2 0.02 255)' : 'oklch(1 0 0)',
            pointHoverBackgroundColor: isDark.value ? 'oklch(0.82 0.12 250)' : 'oklch(0.45 0.15 255)',
            pointHoverBorderColor: isDark.value ? 'oklch(0.96 0.005 255)' : 'oklch(0.18 0.018 255)',
            pointBorderWidth: 2,
            pointHoverBorderWidth: 2,
            borderWidth: 2.5,
        },
    ],
}));

const chartOptions = computed<ChartOptions<'line'>>(() => {
    const tick = isDark.value ? 'oklch(0.72 0.018 255)' : 'oklch(0.46 0.025 255)';
    const grid = isDark.value ? 'oklch(0.32 0.02 255)' : 'oklch(0.92 0.008 255)';
    const tooltipBg = isDark.value ? 'oklch(0.22 0.012 255)' : 'oklch(0.18 0.018 255)';
    const tooltipFg = isDark.value ? 'oklch(0.96 0.005 255)' : 'oklch(0.99 0 0)';

    return {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: tooltipBg,
                titleColor: tooltipFg,
                bodyColor: tooltipFg,
                titleFont: { size: 12, weight: '600', family: 'Poppins, sans-serif' },
                bodyFont: { size: 12, family: 'Poppins, sans-serif' },
                padding: 12,
                cornerRadius: 10,
                displayColors: false,
                callbacks: {
                    title(items) {
                        return items[0]?.label ?? '';
                    },
                    label(ctx) {
                        const n = ctx.parsed.y ?? 0;
                        return `${n.toLocaleString('id-ID')} pengajuan`;
                    },
                },
            },
        },
        scales: {
            x: {
                grid: { display: false },
                border: { display: false },
                ticks: {
                    font: { size: 11, family: 'Poppins, sans-serif' },
                    color: tick,
                    maxRotation: 45,
                    minRotation: 0,
                },
            },
            y: {
                grid: { color: grid, drawTicks: false },
                border: { display: false },
                beginAtZero: true,
                ticks: {
                    font: { size: 11, family: 'Poppins, sans-serif' },
                    color: tick,
                    precision: 0,
                    callback: (v) => (typeof v === 'number' ? v.toLocaleString('id-ID') : v),
                },
            },
        },
    };
});
</script>

<template>
    <Card
        class="overflow-hidden rounded-2xl border-border/70 shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.06]"
    >
        <CardHeader class="flex flex-row flex-wrap items-center justify-between gap-4 border-b border-border/50 bg-muted/10 px-5 py-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <div
                    class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/12 text-primary shadow-inner"
                >
                    <TrendingUp class="size-5" />
                </div>
                <div class="min-w-0">
                    <CardTitle class="font-display text-lg font-bold tracking-[-0.02em] md:text-xl">
                        Tren pengajuan
                    </CardTitle>
                    <p class="text-muted-foreground font-display mt-0.5 text-2xl font-bold tabular-nums tracking-tight">
                        {{ total.toLocaleString('id-ID') }}
                    </p>
                </div>
            </div>
        </CardHeader>
        <CardContent class="p-4 sm:p-5">
            <div
                v-if="points.length === 0 || total === 0"
                class="text-muted-foreground/90 flex min-h-[15rem] items-center justify-center rounded-xl bg-muted/20 text-sm font-medium"
            >
                Tidak ada data
            </div>
            <div v-else class="rounded-xl bg-gradient-to-b from-muted/25 to-transparent p-2 sm:min-h-[16rem]">
                <Line :key="String(isDark)" :data="chartData" :options="chartOptions" />
            </div>
        </CardContent>
    </Card>
</template>
