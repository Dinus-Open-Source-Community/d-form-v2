<script setup lang="ts">
import { computed } from 'vue'
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'
import { Bar } from 'vue-chartjs'
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    BarElement,
    Tooltip,
} from 'chart.js'
import { categoryLabelMap } from '@/lib/dummyData'

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip)

const props = withDefaults(
    defineProps<{
        breakdown?: { token: string; count: number }[]
    }>(),
    { breakdown: () => [] },
)

const colors = ['#0A84DC', '#FF6BB5', '#41F0B4', '#FFD84D', '#9B8CFF']

const labels = computed(() =>
    props.breakdown.map(d => categoryLabelMap[d.token] ?? d.token),
)

const totalEventsInChart = computed(() => props.breakdown.reduce((s, d) => s + d.count, 0))

const barColors = computed(() => props.breakdown.map((_, i) => colors[i % colors.length]))

const chartData = computed(() => ({
    labels: labels.value,
    datasets: [
        {
            label: 'Events',
            data: props.breakdown.map(d => d.count),
            backgroundColor: barColors.value,
            borderColor: '#101014',
            borderWidth: 2,
            borderRadius: 10,
            barThickness: 36,
        },
    ],
}))

const chartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: 'oklch(0.145 0 0)',
            titleFont: { size: 12, weight: 500 as const },
            bodyFont: { size: 11 },
            padding: 10,
            cornerRadius: 12,
            displayColors: true,
        },
    },
    scales: {
        x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11, family: 'Plus Jakarta Sans' }, color: 'oklch(0.50 0 0)' } },
        y: { grid: { color: 'oklch(0.93 0 0)' }, border: { display: false }, ticks: { font: { size: 11, family: 'Plus Jakarta Sans' }, color: 'oklch(0.50 0 0)', stepSize: 1 }, beginAtZero: true },
    },
}
</script>

<template>
    <Card>
        <CardHeader class="pb-2">
            <CardTitle class="font-display text-xl font-extrabold">Events by category token</CardTitle>
            <CardDescription class="text-xs font-bold">{{ totalEventsInChart }} event rows counted</CardDescription>
        </CardHeader>
        <CardContent class="pt-0">
            <div v-if="breakdown.length === 0" class="flex h-52 items-center justify-center px-4 text-center text-sm text-muted-foreground">
                No category tags found on active events yet.
            </div>
            <div v-else class="h-52">
                <Bar :data="chartData" :options="chartOptions" />
            </div>
        </CardContent>
    </Card>
</template>
