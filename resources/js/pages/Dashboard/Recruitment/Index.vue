<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import useAuth from '@/utils/composables/useAuth'
import { usePage } from '@inertiajs/vue3'
import {
    CalendarRange,
    Layers,
    Users,
    ClipboardList,
    ScanLine,
    ListOrdered,
} from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

interface PeriodSummary {
    id: string
    name: string
    status: string
    status_label: string
}

interface ActionQueue {
    key: string
    label: string
    description: string
    count: number
}

interface TodaySession {
    id: string
    session_date: string
    starts_at: string
    ends_at: string
    location: string
    room: string
    interviews_count: number
    division: { name: string } | null
}

const props = defineProps<{
    summary: {
        active_period: PeriodSummary | null
        stats: Record<string, number>
        funnel?: { stage: string; label: string; count: number }[]
        accepted_count?: number
        is_interviewer_view?: boolean
        action_queues?: ActionQueue[]
        today_sessions?: TodaySession[]
    }
}>()

const page = usePage()
const user = useAuth(page.props)
const canManagePeriods = computed(() => user.value?.can_manage_recruitment_periods === true)
const canListApplications = computed(() => user.value?.can_list_recruitment_applications === true)
const canScheduleInterviews = computed(() => user.value?.can_schedule_recruitment_interviews === true)
const canViewQueue = computed(() => user.value?.can_view_recruitment_queue === true)
const canScanAttendance = computed(() => user.value?.can_scan_recruitment_attendance === true)
const canViewReports = computed(() => user.value?.can_view_recruitment_reports === true)

const actionQueues = computed(() => props.summary.action_queues ?? [])
const todaySessions = computed(() => props.summary.today_sessions ?? [])

const compactStats = computed(() => [
    { label: 'Total applicant', value: props.summary.stats.total_applicants ?? 0 },
    { label: 'Interview', value: props.summary.stats.in_interview ?? 0 },
    { label: 'Final review', value: props.summary.stats.final_review ?? 0 },
    { label: 'Diterima', value: props.summary.accepted_count ?? 0 },
])

function applicationsQueueUrl(queue: string): string {
    const params = new URLSearchParams({ queue })
    if (props.summary.active_period?.id) {
        params.set('period_id', props.summary.active_period.id)
    }
    return `${routes.admin.recruitment.applications.index}?${params.toString()}`
}

onMounted(() => {
    setTopbar({ title: 'Rekrutmen', subtitle: 'OpenRecruitment DOSCOM' })
})
</script>

<template>
    <Head title="Rekrutmen" />

    <div class="flex flex-col gap-6 md:gap-8">
        <PageHeader
            title="Pusat kerja OpRec"
            subtitle="Semua yang perlu ditindak — tanpa bolak-balik menu."
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
            <CardContent class="flex flex-wrap items-center justify-between gap-4 p-5">
                <div>
                    <p class="text-lg font-medium">{{ summary.active_period.name }}</p>
                    <p class="text-muted-foreground text-sm">
                        Status: {{ summary.active_period.status_label }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Badge variant="secondary" class="tabular-nums">
                        {{ compactStats.map((s) => `${s.value} ${s.label.toLowerCase()}`).join(' · ') }}
                    </Badge>
                    <Button v-if="canManagePeriods" as-child variant="secondary" size="sm">
                        <Link :href="routes.admin.recruitment.periods.show(summary.active_period.id)">
                            Kelola periode
                        </Link>
                    </Button>
                </div>
            </CardContent>
        </Card>

        <!-- Action queues -->
        <section v-if="canListApplications && actionQueues.length > 0">
            <h2 class="mb-3 text-sm font-semibold tracking-wide uppercase text-muted-foreground">
                Perlu tindakan
            </h2>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <Link
                    v-for="queue in actionQueues"
                    :key="`${queue.key}-${queue.label}`"
                    :href="applicationsQueueUrl(queue.key)"
                    class="group rounded-2xl border border-border/70 bg-card p-5 transition-colors hover:border-primary/40 hover:bg-muted/30"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3">
                            <div class="bg-amber-500/10 text-amber-700 flex size-9 shrink-0 items-center justify-center rounded-lg">
                                <ClipboardList class="size-4" />
                            </div>
                            <div>
                                <p class="font-medium group-hover:text-primary">{{ queue.label }}</p>
                                <p class="text-muted-foreground mt-0.5 text-sm">{{ queue.description }}</p>
                            </div>
                        </div>
                        <Badge variant="secondary" class="shrink-0 tabular-nums text-base font-semibold">
                            {{ queue.count }}
                        </Badge>
                    </div>
                </Link>
            </div>
        </section>

        <!-- Today's interview sessions -->
        <section v-if="todaySessions.length > 0">
            <h2 class="mb-3 text-sm font-semibold tracking-wide uppercase text-muted-foreground">
                Interview hari ini
            </h2>
            <div class="grid gap-3">
                <Card
                    v-for="session in todaySessions"
                    :key="session.id"
                    class="rounded-2xl border-border/70"
                >
                    <CardContent class="flex flex-wrap items-center justify-between gap-4 p-5">
                        <div>
                            <p class="font-medium">
                                {{ session.division?.name ?? 'Interview' }}
                                · {{ session.starts_at }}–{{ session.ends_at }}
                            </p>
                            <p class="text-muted-foreground text-sm">
                                {{ session.location }} · {{ session.room }}
                                · {{ session.interviews_count }} terjadwal
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button as-child variant="outline" size="sm">
                                <Link :href="routes.admin.recruitment.interviewSessions.show(session.id)">
                                    Kelola sesi
                                </Link>
                            </Button>
                            <Button v-if="canViewQueue" as-child size="sm">
                                <Link :href="routes.admin.recruitment.queue.show(session.id)">
                                    <ListOrdered class="mr-2 size-4" />
                                    Antrean
                                </Link>
                            </Button>
                            <Button v-if="canScanAttendance" as-child variant="secondary" size="sm">
                                <Link :href="routes.admin.scan.index">
                                    <ScanLine class="mr-2 size-4" />
                                    Scan
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </section>

        <!-- Quick access when no queues -->
        <div
            v-if="canListApplications && actionQueues.length === 0"
            class="grid gap-3 sm:grid-cols-2"
        >
            <Card class="rounded-2xl border-dashed border-border/70">
                <CardContent class="flex items-center justify-between gap-4 p-5">
                    <div class="flex items-center gap-3">
                        <Users class="size-5 text-muted-foreground" />
                        <div>
                            <p class="font-medium">Semua applicant</p>
                            <p class="text-muted-foreground text-sm">Tidak ada antrean tindakan saat ini.</p>
                        </div>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="routes.admin.recruitment.applications.index">Buka</Link>
                    </Button>
                </CardContent>
            </Card>
            <Card v-if="canScheduleInterviews" class="rounded-2xl border-dashed border-border/70">
                <CardContent class="flex items-center justify-between gap-4 p-5">
                    <div>
                        <p class="font-medium">Sesi interview</p>
                        <p class="text-muted-foreground text-sm">Jadwalkan applicant yang lolos screening.</p>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="routes.admin.recruitment.interviewSessions.index">Buka</Link>
                    </Button>
                </CardContent>
            </Card>
        </div>

        <Card v-if="canViewReports" class="rounded-2xl border-border/70">
            <CardHeader class="pb-2">
                <CardTitle class="text-base font-medium">Laporan & funnel</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-wrap items-center justify-between gap-4">
                <p class="text-muted-foreground text-sm">
                    Statistik lengkap, export CSV, dan breakdown per divisi ada di Laporan.
                </p>
                <Button as-child variant="outline" size="sm">
                    <Link :href="routes.admin.recruitment.reports.index">Buka laporan</Link>
                </Button>
            </CardContent>
        </Card>
    </div>
</template>
