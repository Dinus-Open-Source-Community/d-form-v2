<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'

defineOptions({ layout: DashboardLayout })

interface FunnelRow {
    stage: string
    label: string
    count: number
}

const props = defineProps<{
    report: {
        period: { id: string; name: string } | null
        funnel: FunnelRow[]
        by_division: { division: string; count: number }[]
        by_semester: { semester: number; count: number }[]
        interview_stats: Record<string, number>
        feedback: { count: number; averages: Record<string, number | null> }
    }
    periodOptions: { id: string; name: string }[]
    query: { period_id: string | null }
    exportUrls: { funnel: string; applicants: string }
}>()

onMounted(() => {
    setTopbar({ title: 'Laporan OpRec', subtitle: props.report.period?.name ?? '' })
})

const maxFunnel = computed(() =>
    Math.max(1, ...props.report.funnel.map((row) => row.count)),
)

function onPeriodChange(event: Event) {
    const value = (event.target as HTMLSelectElement).value
    router.get(
        routes.admin.recruitment.reports.index,
        value ? { period_id: value } : {},
        { preserveState: true },
    )
}
</script>

<template>
    <Head title="Laporan OpRec" />

    <div class="mx-auto flex max-w-6xl flex-col gap-6">
        <PageHeader
            title="Laporan OpRec"
            subtitle="Funnel, statistik interview, dan feedback periode."
            :back-href="routes.admin.recruitment.index"
        >
            <template #actions>
                <select
                    class="border-input bg-background h-9 rounded-md border px-3 text-sm"
                    :value="query.period_id ?? report.period?.id ?? ''"
                    @change="onPeriodChange"
                >
                    <option v-for="period in periodOptions" :key="period.id" :value="period.id">
                        {{ period.name }}
                    </option>
                </select>
                <Button as-child variant="outline" size="sm">
                    <a :href="exportUrls.funnel">Export funnel CSV</a>
                </Button>
                <Button as-child size="sm">
                    <a :href="exportUrls.applicants">Export applicant CSV</a>
                </Button>
            </template>
        </PageHeader>

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

        <div class="grid gap-4 lg:grid-cols-2">
            <Card class="rounded-2xl border-border/70">
                <CardHeader><CardTitle class="text-base">Statistik interview</CardTitle></CardHeader>
                <CardContent class="grid grid-cols-2 gap-3 text-sm">
                    <div v-for="(value, key) in report.interview_stats" :key="key">
                        <p class="text-muted-foreground text-xs uppercase">{{ key }}</p>
                        <p class="text-xl font-semibold tabular-nums">{{ value }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card class="rounded-2xl border-border/70">
                <CardHeader><CardTitle class="text-base">Feedback applicant</CardTitle></CardHeader>
                <CardContent class="space-y-2 text-sm">
                    <p>Total respons: <strong>{{ report.feedback.count }}</strong></p>
                    <template v-if="report.feedback.count > 0">
                        <div
                            v-for="(avg, key) in report.feedback.averages"
                            :key="key"
                            class="flex justify-between"
                        >
                            <span class="text-muted-foreground">{{ key }}</span>
                            <span class="font-medium">{{ avg }}/5</span>
                        </div>
                    </template>
                    <p v-else class="text-muted-foreground">Belum ada feedback.</p>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
