<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { routes } from '@/lib/routes'

interface ReportPayload {
    period: { id: string; name: string } | null
    funnel: { stage: string; label: string; count: number }[]
    by_division: { division: string; count: number }[]
    by_semester: { semester: number; count: number }[]
    interview_stats: Record<string, number>
    feedback: { count: number; averages: Record<string, number | null> }
}

const props = defineProps<{
    periodId: string
    report: ReportPayload | null
}>()

const maxFunnel = computed(() =>
    Math.max(1, ...(props.report?.funnel.map((row) => row.count) ?? [1])),
)

function detailUrl(periodId: string): string {
    return `${routes.admin.recruitment.reports.index}?period_id=${periodId}`
}
</script>

<template>
    <div v-if="report" class="flex flex-col gap-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold tracking-wide uppercase text-muted-foreground">
                Laporan periode ini
            </h2>
            <div class="flex flex-wrap gap-2">
                <Button as-child size="sm" variant="outline">
                    <Link :href="detailUrl(periodId)">Lihat detail</Link>
                </Button>
                <Button as-child size="sm" variant="outline">
                    <a :href="routes.admin.recruitment.reports.exportFunnel(periodId)">Export funnel CSV</a>
                </Button>
                <Button as-child size="sm">
                    <a :href="routes.admin.recruitment.reports.exportApplicants(periodId)">Export applicant CSV</a>
                </Button>
            </div>
        </div>

        <Card class="rounded-2xl border-border/70">
            <CardHeader><CardTitle class="text-base">Funnel recruitment</CardTitle></CardHeader>
            <CardContent class="space-y-3">
                <div v-for="row in report.funnel" :key="row.stage" class="space-y-1">
                    <div class="flex justify-between text-sm">
                        <span>{{ row.label }}</span>
                        <span class="font-medium tabular-nums">{{ row.count }}</span>
                    </div>
                    <div class="bg-muted h-2 rounded-full">
                        <div
                            class="bg-primary h-2 rounded-full transition-all"
                            :style="{ width: `${(row.count / maxFunnel) * 100}%` }"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 lg:grid-cols-2">
            <Card class="rounded-2xl border-border/70">
                <CardHeader><CardTitle class="text-base">Per divisi utama</CardTitle></CardHeader>
                <CardContent class="space-y-2 text-sm">
                    <div
                        v-for="row in report.by_division"
                        :key="row.division"
                        class="flex justify-between border-b border-border/40 py-2 last:border-0"
                    >
                        <span>{{ row.division }}</span>
                        <span class="font-medium tabular-nums">{{ row.count }}</span>
                    </div>
                </CardContent>
            </Card>

            <Card class="rounded-2xl border-border/70">
                <CardHeader><CardTitle class="text-base">Per semester</CardTitle></CardHeader>
                <CardContent class="space-y-2 text-sm">
                    <div
                        v-for="row in report.by_semester"
                        :key="row.semester"
                        class="flex justify-between border-b border-border/40 py-2 last:border-0"
                    >
                        <span>Semester {{ row.semester }}</span>
                        <span class="font-medium tabular-nums">{{ row.count }}</span>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
    <p v-else class="text-muted-foreground text-sm">Data laporan tidak tersedia untuk tab ini.</p>
</template>
