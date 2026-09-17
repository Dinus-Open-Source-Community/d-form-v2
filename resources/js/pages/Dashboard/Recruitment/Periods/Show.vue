<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PeriodApplicantSection from '@/components/modules/dashboard/recruitment/PeriodApplicantSection.vue'
import PeriodInterviewSection from '@/components/modules/dashboard/recruitment/PeriodInterviewSection.vue'
import PeriodReportSection from '@/components/modules/dashboard/recruitment/PeriodReportSection.vue'
import ApplicantDetailPanel from '@/components/modules/dashboard/recruitment/ApplicantDetailPanel.vue'
import { type ApplicationDetail } from '@/components/modules/dashboard/recruitment/ApplicantDetailContent.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { BarChart3, CalendarClock, Trash2, UserCheck, Users } from 'lucide-vue-next'
import { routes } from '@/lib/routes'
import type { PeriodStatusValue } from '@/lib/recruitmentPeriodPhase'
import {
    daysRemaining,
    parsePeriodDate,
    phaseCountdownLabel,
    phaseDeadline,
    resolvePeriodPhase,
    statusLabel,
} from '@/lib/recruitmentPeriodPhase'
import { formatIdDateLabel, formatIdDateTimeLabel } from '@/lib/shadcnDateFormat'
import { cn } from '@/lib/utils'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import useAuth from '@/utils/composables/useAuth'

defineOptions({ layout: DashboardLayout })

interface Period {
    id: string
    name: string
    slug: string
    status: PeriodStatusValue
    status_label: string
    description: string | null
    registration_opens_at: string | null
    registration_closes_at: string | null
    interview_starts_at: string | null
    interview_ends_at: string | null
    finalization_deadline_at: string | null
    applications_count: number
}

interface ApplicationPaginator {
    data: {
        id: string
        registration_number: string
        full_name: string
        nim: string
        semester: number
        stage: string
        stage_label: string
        result: string
        result_label: string
        revision_required: boolean
        submitted_at: string | null
        primary_division: { id: string; name: string } | null
        period: { id: string; name: string } | null
    }[]
    current_page: number
    last_page: number
    total: number
}

interface SessionRow {
    id: string
    session_date: string
    starts_at: string
    ends_at: string
    location: string
    room: string
    is_active: boolean
    interviews_count: number
    period: { id: string; name: string } | null
    division: { id: string; name: string; code: string } | null
}

interface SessionPaginator {
    data: SessionRow[]
    current_page: number
    last_page: number
    total: number
}

interface ReportPayload {
    period: { id: string; name: string } | null
    funnel: { stage: string; label: string; count: number }[]
    by_division: { division: string; count: number }[]
    by_semester: { semester: number; count: number }[]
    interview_stats: Record<string, number>
    feedback: { count: number; averages: Record<string, number | null> }
}

interface InterviewerDivision {
    id: string
    code: string
    name: string
    description: string | null
    is_active: boolean
    sort_order: number
    interviewer_assignments_count: number
}

interface InterviewerAssignment {
    id: string
    user_id: string
    user_name: string
    user_email: string
    division_id: string
    division_name: string
    division_code: string
}

interface InterviewerCandidate {
    id: string
    name: string
    email: string
}

const props = withDefaults(
    defineProps<{
        period: Period
        applications?: ApplicationPaginator | null
        queue_counts: Record<string, number>
        divisionOptions: { id: string; name: string; code: string }[]
        stageOptions: { value: string; label: string }[]
        semesterOptions?: { value: string; label: string }[]
        query: {
            search?: string
            division_id?: string
            stage?: string
            queue?: string
            semester?: string
            application?: string
            page?: number | string
            per_page?: number | string
        }
        tab: string
        sessions?: SessionPaginator | null
        today_sessions?: SessionRow[]
        report?: ReportPayload | null
        applicant_detail?: ApplicationDetail | null
        divisions?: InterviewerDivision[]
        assignments?: InterviewerAssignment[]
        interviewerCandidates?: InterviewerCandidate[]
    }>(),
    {
        semesterOptions: () => [],
        today_sessions: () => [],
        divisions: () => [],
        assignments: () => [],
        interviewerCandidates: () => [],
    },
)

const page = usePage()
const user = useAuth(page.props)
const canListApplications = computed(() => user.value?.can_list_recruitment_applications === true)
const canScheduleInterviews = computed(() => user.value?.can_schedule_recruitment_interviews === true)
const canScreenApplications = computed(() => user.value?.can_screen_recruitment_applications === true)
const canViewReports = computed(() => user.value?.can_view_recruitment_reports === true)
/** Sama seperti halaman Divisi semula: hanya pengelola periode yang mengatur interviewer. */
const canManagePeriods = computed(() => user.value?.can_manage_recruitment_periods === true)

const assignForm = useForm({
    user_id: '',
    recruitment_division_id: '',
})

function submitAssign(): void {
    assignForm.post(routes.admin.recruitment.interviewers.assign, {
        preserveScroll: true,
        onSuccess: () => {
            assignForm.reset('user_id')
        },
    })
}

function unassignInterviewer(id: string): void {
    router.delete(routes.admin.recruitment.interviewers.unassign(id), { preserveScroll: true })
}

const applicantTotal = computed<number>(() => {
    return props.applications?.total ?? props.period.applications_count ?? 0
})

const participantCountLabel = computed<string>(() => {
    return applicantTotal.value.toLocaleString('id-ID')
})

const validTabs = ['peserta', 'interview', 'laporan', 'interviewer'] as const
type TabValue = (typeof validTabs)[number]

function normalizeTab(value: string): TabValue {
    return (validTabs as readonly string[]).includes(value) ? (value as TabValue) : 'peserta'
}

const activeTab = ref<TabValue>(normalizeTab(props.tab))
watch(
    () => props.tab,
    (value) => {
        activeTab.value = normalizeTab(value)
    },
)

function onTabChange(value: string | number): void {
    const next = String(value)
    if (!(validTabs as readonly string[]).includes(next)) return
    if (next === activeTab.value) return
    router.get(
        routes.admin.recruitment.periods.show(props.period.id),
        { tab: next === 'peserta' ? undefined : next },
        { preserveState: true, preserveScroll: true },
    )
}

const selectedId = computed<string | null>(() => props.query.application ?? null)
const isPanelLoading = ref<boolean>(false)

/** Penjaga agar Esc ganda (handler sheet + listener window) tidak memicu dua kunjungan. */
let closeGuard = false

function baseParams(): Record<string, unknown> {
    const q = props.query
    return {
        search: q.search,
        division_id: q.division_id,
        stage: q.stage,
        queue: q.queue,
        semester: q.semester,
        page: q.page,
        per_page: q.per_page,
        tab: props.tab === 'peserta' ? undefined : props.tab,
    }
}

function selectApplicant(id: string): void {
    if (selectedId.value === id) return
    router.get(
        routes.admin.recruitment.periods.show(props.period.id),
        { ...baseParams(), application: id },
        {
            only: ['applicant_detail', 'query'],
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                isPanelLoading.value = true
            },
            onFinish: () => {
                isPanelLoading.value = false
            },
        },
    )
}

function closePanel(): void {
    if (selectedId.value === null || closeGuard) return
    closeGuard = true
    router.get(routes.admin.recruitment.periods.show(props.period.id), baseParams(), {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => {
            closeGuard = false
        },
    })
}

function onGlobalKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Escape') return
    closePanel()
}

onMounted(() => {
    window.addEventListener('keydown', onGlobalKeydown)
})

onUnmounted(() => {
    window.removeEventListener('keydown', onGlobalKeydown)
})

const phaseInput = computed(() => ({
    status: props.period.status,
    registrationOpensAt: props.period.registration_opens_at,
    registrationClosesAt: props.period.registration_closes_at,
    interviewStartsAt: props.period.interview_starts_at,
    interviewEndsAt: props.period.interview_ends_at,
    finalizationDeadlineAt: props.period.finalization_deadline_at,
}))

const phase = computed(() => resolvePeriodPhase(phaseInput.value))
const remainingDays = computed<number | null>(() => daysRemaining(phaseDeadline(phase.value, phaseInput.value)))
const countdown = computed<string | null>(() => phaseCountdownLabel(phase.value, remainingDays.value))

const statusClasses: Record<PeriodStatusValue, string> = {
    draft: 'border-border bg-secondary text-secondary-foreground',
    open: 'border-success/20 bg-success/10 text-success',
    closed: 'border-warning/25 bg-warning/10 text-warning-foreground',
    archived: 'border-border bg-muted text-muted-foreground',
}

const canOpen = computed<boolean>(() => props.period.status === 'draft' || props.period.status === 'closed')
const canClose = computed<boolean>(() => props.period.status === 'open')

const descriptionExpanded = ref<boolean>(false)
const showDescriptionToggle = computed<boolean>(() => (props.period.description?.length ?? 0) > 140)

interface ScheduleItem {
    key: string
    label: string
    display: string
    dotClass: string
    isUnscheduled: boolean
}

function scheduleDisplay(value: string | null, withTime: boolean): string {
    if (!value || !parsePeriodDate(value)) return 'Belum ditentukan'
    return withTime ? formatIdDateTimeLabel(value) : formatIdDateLabel(value)
}

const scheduleNodes = computed<ScheduleItem[]>(() => {
    const now = new Date()
    const raw: { key: string; label: string; value: string | null; withTime: boolean }[] = [
        { key: 'registration-open', label: 'Buka pendaftaran', value: props.period.registration_opens_at, withTime: true },
        { key: 'registration-close', label: 'Tutup pendaftaran', value: props.period.registration_closes_at, withTime: true },
        { key: 'interview-start', label: 'Mulai interview', value: props.period.interview_starts_at, withTime: false },
        { key: 'interview-end', label: 'Selesai interview', value: props.period.interview_ends_at, withTime: false },
        { key: 'finalization', label: 'Target finalisasi', value: props.period.finalization_deadline_at, withTime: false },
    ]

    const isOpen = props.period.status === 'open'
    let currentAssigned = false

    return raw.map((node) => {
        const parsed = node.value ? parsePeriodDate(node.value) : null
        let dotClass = 'border border-dashed border-muted-foreground/40 bg-transparent'
        let isUnscheduled = false

        if (!parsed) {
            isUnscheduled = true
        } else if (parsed.getTime() <= now.getTime()) {
            dotClass = isOpen ? 'bg-success' : 'bg-muted-foreground/40'
        } else if (!isOpen) {
            dotClass = 'bg-muted-foreground/30'
        } else if (!currentAssigned) {
            currentAssigned = true
            dotClass = 'bg-primary'
        } else {
            dotClass = 'bg-muted-foreground/30'
        }

        return {
            key: node.key,
            label: node.label,
            display: scheduleDisplay(node.value, node.withTime),
            dotClass,
            isUnscheduled,
        }
    })
})

onMounted(() => {
    setTopbar({ title: props.period.name, subtitle: 'Detail periode Open Recruitment' })
})

function openPeriod(): void {
    router.post(routes.admin.recruitment.periods.open(props.period.id))
}

function closePeriod(): void {
    router.post(routes.admin.recruitment.periods.close(props.period.id))
}
</script>

<template>
    <Head :title="period.name" />

    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-5 pb-8 sm:pb-10">
        <Card class="overflow-hidden rounded-xl border-border/70 shadow-sm">
            <CardContent class="p-4 sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between lg:gap-6">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1.5">
                            <h1 class="min-w-0 break-words text-lg font-semibold tracking-tight sm:text-xl">
                                {{ period.name }}
                            </h1>
                            <Badge :class="cn('shrink-0 border text-[11px] font-medium', statusClasses[period.status])">
                                {{ statusLabel(period.status) }}
                            </Badge>
                        </div>
                        <p class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                            <span class="inline-flex min-w-0 max-w-full items-center">
                                <span class="truncate font-mono text-[11px]" :title="period.slug">{{ period.slug }}</span>
                            </span>
                            <span v-if="countdown" class="inline-flex flex-wrap items-center gap-x-2">
                                <span aria-hidden="true" class="select-none">·</span>
                                <span>{{ countdown }}</span>
                            </span>
                            <span v-if="applicantTotal > 0" class="inline-flex items-center gap-x-2">
                                <span aria-hidden="true" class="select-none">·</span>
                                <span class="tabular-nums">{{ participantCountLabel }} pendaftar</span>
                            </span>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 lg:shrink-0 lg:justify-end">
                        <Button
                            v-if="canOpen"
                            size="sm"
                            :aria-label="'Buka pendaftaran ' + period.name"
                            @click="openPeriod"
                        >
                            Buka pendaftaran
                        </Button>
                        <Button
                            v-if="canClose"
                            size="sm"
                            variant="destructive"
                            :aria-label="'Tutup pendaftaran ' + period.name"
                            @click="closePeriod"
                        >
                            Tutup pendaftaran
                        </Button>
                        <Button as-child size="sm" variant="outline">
                            <Link
                                :href="routes.admin.recruitment.periods.edit(period.id)"
                                :aria-label="'Edit periode ' + period.name"
                            >
                                Edit
                            </Link>
                        </Button>
                    </div>
                </div>

                <div v-if="period.description" class="mt-3 border-t border-border/60 pt-3">
                    <h2 class="sr-only">Deskripsi periode</h2>
                    <p
                        id="period-description"
                        class="text-sm leading-relaxed text-muted-foreground"
                        :class="!descriptionExpanded && 'line-clamp-2'"
                    >
                        {{ period.description }}
                    </p>
                    <button
                        v-if="showDescriptionToggle"
                        type="button"
                        :aria-expanded="descriptionExpanded"
                        aria-controls="period-description"
                        class="mt-1 text-xs font-medium text-foreground underline-offset-4 hover:underline"
                        @click="descriptionExpanded = !descriptionExpanded"
                    >
                        {{ descriptionExpanded ? 'Ringkas' : 'Selengkapnya' }}
                    </button>
                </div>

                <div class="mt-3 border-t border-border/60 pt-3">
                    <h2 class="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">Jadwal</h2>
                    <ol class="mt-2 flex gap-5 overflow-x-auto pb-0.5 sm:grid sm:grid-cols-5 sm:gap-4 sm:overflow-visible">
                        <li v-for="node in scheduleNodes" :key="node.key" class="min-w-[136px] flex-1 sm:min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span aria-hidden="true" :class="cn('size-1.5 shrink-0 rounded-full', node.dotClass)" />
                                <p class="truncate text-xs font-medium text-foreground">{{ node.label }}</p>
                            </div>
                            <p
                                class="mt-1 pl-3 text-[11px] tabular-nums"
                                :class="node.isUnscheduled ? 'italic text-muted-foreground/80' : 'text-muted-foreground'"
                            >
                                {{ node.display }}
                            </p>
                        </li>
                    </ol>
                </div>
            </CardContent>
        </Card>

        <Tabs :model-value="activeTab" @update:model-value="onTabChange" class="w-full">
            <TabsList
                class="flex h-auto w-full items-center justify-start gap-6 overflow-x-auto overflow-y-hidden whitespace-nowrap rounded-none border-0 border-b border-border bg-transparent p-0 text-muted-foreground [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                <TabsTrigger
                    v-if="canListApplications"
                    value="peserta"
                    class="group -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none hover:text-foreground data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                >
                    <Users class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100" aria-hidden="true" />
                    <span>Peserta</span>
                    <span
                        class="ml-1 inline-flex min-h-5 min-w-6 items-center justify-center rounded-full bg-muted px-1.5 text-[11px] font-medium tabular-nums leading-4 text-muted-foreground transition-colors group-hover:text-foreground group-data-[state=active]:bg-foreground/10 group-data-[state=active]:text-foreground"
                    >
                        {{ participantCountLabel }}
                    </span>
                </TabsTrigger>
                <TabsTrigger
                    v-if="canScheduleInterviews"
                    value="interview"
                    class="group -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none hover:text-foreground data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                >
                    <CalendarClock class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100" aria-hidden="true" />
                    <span>Interview</span>
                </TabsTrigger>
                <TabsTrigger
                    v-if="canViewReports"
                    value="laporan"
                    class="group -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none hover:text-foreground data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                >
                    <BarChart3 class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100" aria-hidden="true" />
                    <span>Laporan</span>
                </TabsTrigger>
                <TabsTrigger
                    v-if="canManagePeriods"
                    value="interviewer"
                    class="group -mb-px shrink-0 gap-2 rounded-none border-0 border-b-2 border-transparent bg-transparent px-1 py-2.5 text-sm font-medium shadow-none hover:text-foreground data-[state=active]:border-foreground data-[state=active]:bg-transparent data-[state=active]:text-foreground data-[state=active]:shadow-none"
                >
                    <UserCheck class="size-4 shrink-0 opacity-60 group-data-[state=active]:opacity-100" aria-hidden="true" />
                    <span>Interviewer</span>
                </TabsTrigger>
            </TabsList>

            <TabsContent value="peserta" class="mt-4">
                <div v-if="canListApplications">
                    <PeriodApplicantSection
                        :period-id="period.id"
                        :tab="activeTab"
                        :can-screen="canScreenApplications"
                        :selected-id="selectedId"
                        :applications="applications"
                        :queue-counts="queue_counts"
                        :division-options="divisionOptions"
                        :stage-options="stageOptions"
                        :semester-options="semesterOptions"
                        :query="query"
                        @select="selectApplicant"
                        @deselect="closePanel"
                    />

                    <ApplicantDetailPanel
                        :application="applicant_detail ?? null"
                        :loading="isPanelLoading"
                        @close="closePanel"
                    />
                </div>
            </TabsContent>

            <TabsContent value="interview" class="mt-4">
                <PeriodInterviewSection
                    v-if="canScheduleInterviews"
                    :sessions="sessions ?? null"
                    :today-sessions="today_sessions ?? []"
                />
            </TabsContent>

            <TabsContent value="laporan" class="mt-4">
                <PeriodReportSection v-if="canViewReports" :period-id="period.id" :report="report ?? null" />
            </TabsContent>

            <TabsContent value="interviewer" class="mt-4">
                <Card v-if="canManagePeriods" class="rounded-2xl border-border/70">
                    <CardHeader>
                        <CardTitle class="text-base">Tugaskan interviewer</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitAssign">
                            <div class="space-y-2">
                                <Label>Interviewer</Label>
                                <select
                                    v-model="assignForm.user_id"
                                    class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    required
                                >
                                    <option value="">Pilih user</option>
                                    <option v-for="u in interviewerCandidates" :key="u.id" :value="u.id">
                                        {{ u.name }} ({{ u.email }})
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label>Divisi</Label>
                                <select
                                    v-model="assignForm.recruitment_division_id"
                                    class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                    required
                                >
                                    <option value="">Pilih divisi</option>
                                    <option v-for="d in divisions" :key="d.id" :value="d.id">{{ d.name }}</option>
                                </select>
                            </div>
                            <div class="sm:col-span-2">
                                <Button type="submit" size="sm" :disabled="assignForm.processing">Tugaskan</Button>
                            </div>
                        </form>

                        <ul class="divide-border divide-y rounded-lg border">
                            <li
                                v-for="row in assignments"
                                :key="row.id"
                                class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
                            >
                                <div>
                                    <p class="font-medium">{{ row.user_name }}</p>
                                    <p class="text-muted-foreground text-xs">
                                        {{ row.division_name }} · {{ row.user_email }}
                                    </p>
                                </div>
                                <Button variant="ghost" size="icon" @click="unassignInterviewer(row.id)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </li>
                            <li v-if="assignments.length === 0" class="text-muted-foreground px-4 py-6 text-center text-sm">
                                Belum ada interviewer yang ditugaskan.
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </TabsContent>
        </Tabs>
    </div>
</template>
