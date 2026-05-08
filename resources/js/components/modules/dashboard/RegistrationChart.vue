<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Line } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    PointElement,
    LineElement,
    Filler,
    Tooltip,
} from 'chart.js'

ChartJS.register(CategoryScale, LinearScale, PointElement, LineElement, Filler, Tooltip)

const props = withDefaults(
    defineProps<{
        points?: { label: string; count: number }[]
    }>(),
    { points: () => [] },
)

const total = computed(() => props.points.reduce((s, d) => s + d.count, 0))

const chartData = computed(() => ({
    labels: props.points.map(d => d.label),
    datasets: [
        {
            label: 'Registrations',
            data: props.points.map(d => d.count),
            borderColor: 'oklch(0.59 0.14 242)',
            backgroundColor: 'rgba(255, 216, 77, 0.28)',
            fill: true,
            tension: 0.4,
            pointRadius: 4,
            pointHoverRadius: 7,
            pointBackgroundColor: '#FFD84D',
            pointBorderColor: '#101014',
            pointHoverBackgroundColor: '#41F0B4',
            pointHoverBorderColor: '#101014',
            pointBorderWidth: 2,
            pointHoverBorderWidth: 2,
            borderWidth: 4,
        },
    ],
}))

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index' as const, intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'oklch(0.145 0 0)',
            titleFont: { size: 12, weight: 500 as const },
            bodyFont: { size: 11 },
            padding: 10,
            cornerRadius: 12,
            displayColors: false,
        },
    },
    scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11, family: 'Plus Jakarta Sans' }, color: 'oklch(0.50 0 0)' } },
        y: { grid: { color: 'oklch(0.93 0 0)' }, border: { display: false }, ticks: { font: { size: 11, family: 'Plus Jakarta Sans' }, color: 'oklch(0.50 0 0)' }, beginAtZero: true },
    },
}
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="font-display text-xl font-extrabold">Registration trends</CardTitle>
            <CardDescription class="text-xs font-bold">{{ total.toLocaleString() }} new submissions in range</CardDescription>
        </CardHeader>
        <CardContent class="pt-0">
            <div v-if="points.length === 0 || total === 0" class="flex h-52 items-center justify-center text-sm text-muted-foreground">
                No submissions in the last six months.
            </div>
            <div v-else class="h-52">
                <Line :data="chartData" :options="chartOptions" />
            </div>
        </CardContent>
    </Card>
</template>
