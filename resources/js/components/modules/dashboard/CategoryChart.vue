<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Bar } from 'vue-chartjs';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Tooltip,
    type ChartOptions,
} from 'chart.js';
import { categoryLabelMap, categoryColorMap } from '@/lib/dummyData';
import { LayoutGrid } from 'lucide-vue-next';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip);

const props = withDefaults(
    defineProps<{
        breakdown?: { token: string; count: number }[];
    }>(),
    { breakdown: () => [] },
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

const labels = computed(() => props.breakdown.map((d) => categoryLabelMap[d.token] ?? d.token));

const barColors = computed(() =>
    props.breakdown.map((d) => categoryColorMap[d.token] ?? (isDark.value ? 'oklch(0.55 0.12 255)' : 'oklch(0.52 0.16 255)')),
);

const totalInChart = computed(() => props.breakdown.reduce((s, d) => s + d.count, 0));

const chartData = computed(() => ({
    labels: labels.value,
    datasets: [
        {
            label: 'Jumlah acara',
            data: props.breakdown.map((d) => d.count),
            backgroundColor: barColors.value,
            borderColor: isDark.value ? 'oklch(0.16 0.012 255)' : 'oklch(1 0 0)',
            borderWidth: 1.5,
            borderRadius: 10,
            borderSkipped: false,
            maxBarThickness: 32,
        },
    ],
}));

const chartOptions = computed<ChartOptions<'bar'>>(() => {
    const tick = isDark.value ? 'oklch(0.72 0.018 255)' : 'oklch(0.46 0.025 255)';
    const grid = isDark.value ? 'oklch(0.32 0.02 255)' : 'oklch(0.92 0.008 255)';
    const tooltipBg = isDark.value ? 'oklch(0.22 0.012 255)' : 'oklch(0.18 0.018 255)';
    const tooltipFg = isDark.value ? 'oklch(0.96 0.005 255)' : 'oklch(0.99 0 0)';

    return {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
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
                displayColors: true,
                boxPadding: 4,
                callbacks: {
                    label(ctx) {
                        const n = ctx.parsed.x ?? 0;
                        return ` ${n.toLocaleString('id-ID')} acara`;
                    },
                },
            },
        },
        scales: {
            x: {
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
            y: {
                grid: { display: false },
                border: { display: false },
                ticks: {
                    font: { size: 12, weight: '500', family: 'Poppins, sans-serif' },
                    color: tick,
                    autoSkip: false,
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
                    <LayoutGrid class="size-5" />
                </div>
                <div class="min-w-0">
                    <CardTitle class="font-display text-lg font-bold tracking-[-0.02em] md:text-xl">
                        Acara per kategori
                    </CardTitle>
                    <p class="text-muted-foreground font-display mt-0.5 text-2xl font-bold tabular-nums tracking-tight">
                        {{ totalInChart.toLocaleString('id-ID') }}
                    </p>
                </div>
            </div>
        </CardHeader>
        <CardContent class="space-y-4 p-4 sm:p-5">
            <div
                v-if="breakdown.length === 0"
                class="text-muted-foreground/90 flex min-h-[15rem] items-center justify-center rounded-xl bg-muted/20 text-sm font-medium"
            >
                Tidak ada data
            </div>
            <template v-else>
                <div class="rounded-xl bg-gradient-to-b from-muted/25 to-transparent p-2 sm:min-h-[18rem]">
                    <Bar :key="String(isDark)" :data="chartData" :options="chartOptions" />
                </div>
                <ul class="flex flex-wrap gap-2">
                    <li
                        v-for="row in breakdown"
                        :key="row.token"
                        class="border-border/60 inline-flex items-center gap-2 rounded-full border bg-card/90 px-3 py-1 text-xs font-medium shadow-xs"
                    >
                        <span
                            class="size-2.5 shrink-0 rounded-full shadow-sm"
                            :style="{ backgroundColor: categoryColorMap[row.token] ?? 'var(--muted-foreground)' }"
                        />
                        <span>{{ categoryLabelMap[row.token] ?? row.token }}</span>
                        <span class="text-muted-foreground tabular-nums">{{ row.count.toLocaleString('id-ID') }}</span>
                    </li>
                </ul>
            </template>
        </CardContent>
    </Card>
</template>
