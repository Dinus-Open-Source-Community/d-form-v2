<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import useAuth from '@/utils/composables/useAuth'
import { usePage } from '@inertiajs/vue3'
import { CalendarRange, Layers, Users, BarChart3, ScrollText, Mail } from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

interface PeriodSummary {
    id: string
    name: string
    status: string
    status_label: string
}

interface FunnelRow {
    stage: string
    label: string
    count: number
}

const props = defineProps<{
    summary: {
        active_period: PeriodSummary | null
        stats: Record<string, number>
        funnel?: FunnelRow[]
        interview_stats?: Record<string, number>
        feedback?: { count: number }
        accepted_count?: number
        is_interviewer_view?: boolean
    }
}>()

const page = usePage()
const user = useAuth(page.props)
const canManagePeriods = computed(() => user.value?.can_manage_recruitment_periods === true)
const canListApplications = computed(() => user.value?.can_list_recruitment_applications === true)
const canViewReports = computed(() => user.value?.can_view_recruitment_reports === true)
const canViewActivity = computed(() => user.value?.can_view_recruitment_activity === true)
const canEditTemplates = computed(() => user.value?.can_edit_recruitment_email_templates === true)

const isInterviewerView = computed(() => props.summary.is_interviewer_view === true)

const maxFunnel = computed(() =>
    Math.max(1, ...(props.summary.funnel ?? []).map((row) => row.count)),
)

const statCards = computed(() => {
    if (isInterviewerView.value) {
        return [
            { label: 'Assignment total', value: props.summary.stats.assigned_total ?? 0 },
            { label: 'Terjadwal', value: props.summary.stats.scheduled ?? 0 },
            { label: 'Interview selesai', value: props.summary.stats.completed ?? 0 },
            { label: 'Belum dievaluasi', value: props.summary.stats.pending_evaluation ?? 0 },
        ]
    }

    return [
        { label: 'Total applicant', value: props.summary.stats.total_applicants ?? 0 },
        { label: 'Menunggu screening', value: props.summary.stats.pending_screening ?? 0 },
        { label: 'Dalam screening', value: props.summary.stats.in_screening ?? 0 },
        { label: 'Lolos screening', value: props.summary.stats.passed_screening ?? 0 },
        { label: 'Ditolak', value: props.summary.stats.rejected_applicants ?? 0 },
        { label: 'Tahap interview', value: props.summary.stats.in_interview ?? 0 },
        { label: 'Final review', value: props.summary.stats.final_review ?? 0 },
        { label: 'Selesai', value: props.summary.stats.completed ?? 0 },
    ]
})

onMounted(() => {
    setTopbar({ title: 'Rekrutmen', subtitle: 'OpenRecruitment DOSCOM' })
})
</script>

<template>
    <Head title="Rekrutmen" />

    <div class="flex flex-col gap-8 md:gap-10">
        <PageHeader
            title="Rekrutmen"
            subtitle="Dashboard OpenRecruitment — pantau progress periode aktif."
            :back-href="routes.dashboard.index"
        >
            <template v-if="canManagePeriods" #actions>
                <Button as-child variant="outline" size="sm">
                    <Link :href="routes.admin.recruitment.divisions.index">
                        <Layers class="mr-2 size-4" />
                        Divisi
                    </Link>
                </Button>
                <Button as-child size="sm">
                    <Link :href="routes.admin.recruitment.periods.create">
                        <CalendarRange class="mr-2 size-4" />
                        Periode baru
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <Card v-if="summary.active_period" class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base font-semibold">Periode aktif</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-lg font-medium">{{ summary.active_period.name }}</p>
                    <p class="text-muted-foreground text-sm">
                        Status: {{ summary.active_period.status_label }}
                    </p>
                </div>
                <Button v-if="canManagePeriods" as-child variant="secondary" size="sm">
                    <Link :href="routes.admin.recruitment.periods.show(summary.active_period.id)">
                        Kelola periode
                    </Link>
                </Button>
            </CardContent>
        </Card>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card v-for="card in statCards" :key="card.label" class="rounded-2xl border-border/70">
                <CardContent class="flex items-center gap-4 p-5">
                    <div class="bg-primary/10 text-primary flex size-10 items-center justify-center rounded-xl">
                        <Users class="size-5" />
                    </div>
                    <div>
                        <p class="text-muted-foreground text-xs font-medium uppercase tracking-wide">
                            {{ card.label }}
                        </p>
                        <p class="text-2xl font-semibold tabular-nums">{{ card.value }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card
            v-if="!isInterviewerView && summary.funnel && summary.funnel.length > 0"
            class="rounded-2xl border-border/70"
        >
            <CardHeader class="flex flex-row items-center justify-between pb-2">
                <CardTitle class="text-base">Funnel recruitment</CardTitle>
                <Button v-if="canViewReports" as-child variant="link" size="sm">
                    <Link :href="routes.admin.recruitment.reports.index">Lihat detail</Link>
                </Button>
            </CardHeader>
            <CardContent class="space-y-3">
                <div v-for="row in summary.funnel.slice(0, 6)" :key="row.stage" class="space-y-1">
                    <div class="flex justify-between text-sm">
                        <span>{{ row.label }}</span>
                        <span class="font-medium tabular-nums">{{ row.count }}</span>
                    </div>
                    <div class="bg-muted h-2 rounded-full">
                        <div
                            class="bg-primary h-2 rounded-full"
                            :style="{ width: `${(row.count / maxFunnel) * 100}%` }"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            <Card v-if="canListApplications" class="rounded-2xl border-dashed border-border/70">
                <CardContent class="flex flex-wrap items-center justify-between gap-4 p-6">
                    <div>
                        <p class="font-medium">Kelola applicant</p>
                        <p class="text-muted-foreground text-sm">Screening, dokumen, keputusan final.</p>
                    </div>
                    <Button as-child>
                        <Link :href="routes.admin.recruitment.applications.index">Daftar applicant</Link>
                    </Button>
                </CardContent>
            </Card>

            <Card v-if="isInterviewerView" class="rounded-2xl border-dashed border-border/70">
                <CardContent class="flex flex-wrap items-center justify-between gap-4 p-6">
                    <div>
                        <p class="font-medium">Interview Saya</p>
                        <p class="text-muted-foreground text-sm">Lihat assignment dan isi evaluasi.</p>
                    </div>
                    <Button as-child>
                        <Link :href="routes.admin.recruitment.myInterviews.index">Buka</Link>
                    </Button>
                </CardContent>
            </Card>

            <Card v-if="canViewReports" class="rounded-2xl border-dashed border-border/70">
                <CardContent class="flex flex-wrap items-center justify-between gap-4 p-6">
                    <div class="flex items-start gap-3">
                        <BarChart3 class="text-muted-foreground mt-0.5 size-5" />
                        <div>
                            <p class="font-medium">Laporan</p>
                            <p class="text-muted-foreground text-sm">Funnel, divisi, export CSV.</p>
                        </div>
                    </div>
                    <Button as-child variant="outline">
                        <Link :href="routes.admin.recruitment.reports.index">Buka</Link>
                    </Button>
                </CardContent>
            </Card>

            <Card v-if="canViewActivity" class="rounded-2xl border-dashed border-border/70">
                <CardContent class="flex flex-wrap items-center justify-between gap-4 p-6">
                    <div class="flex items-start gap-3">
                        <ScrollText class="text-muted-foreground mt-0.5 size-5" />
                        <div>
                            <p class="font-medium">Activity log</p>
                            <p class="text-muted-foreground text-sm">Audit trail keputusan staff.</p>
                        </div>
                    </div>
                    <Button as-child variant="outline">
                        <Link :href="routes.admin.recruitment.activityLogs.index">Buka</Link>
                    </Button>
                </CardContent>
            </Card>

            <Card v-if="canEditTemplates" class="rounded-2xl border-dashed border-border/70">
                <CardContent class="flex flex-wrap items-center justify-between gap-4 p-6">
                    <div class="flex items-start gap-3">
                        <Mail class="text-muted-foreground mt-0.5 size-5" />
                        <div>
                            <p class="font-medium">Template email</p>
                            <p class="text-muted-foreground text-sm">Edit notifikasi OpRec.</p>
                        </div>
                    </div>
                    <Button as-child variant="outline">
                        <Link :href="routes.admin.recruitment.emailTemplates.index">Buka</Link>
                    </Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
