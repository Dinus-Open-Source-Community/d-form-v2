<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PeriodApplicantSection from '@/components/modules/dashboard/recruitment/PeriodApplicantSection.vue'
import PeriodInterviewSection from '@/components/modules/dashboard/recruitment/PeriodInterviewSection.vue'
import PeriodReportSection from '@/components/modules/dashboard/recruitment/PeriodReportSection.vue'
import ApplicantDetailPanel from '@/components/modules/dashboard/recruitment/ApplicantDetailPanel.vue'
import { type ApplicationDetail } from '@/components/modules/dashboard/recruitment/ApplicantDetailContent.vue'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import InterviewerCreateSheet from '@/components/modules/dashboard/recruitment/InterviewerCreateSheet.vue'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { SearchableSelect, type SearchableSelectOption } from '@/components/ui/searchable-select'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { BarChart3, CalendarClock, Plus, Trash2, UserCheck, Users } from 'lucide-vue-next'
import { showErrorToast } from '@/lib/error-message'
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

interface ApplicationRow {
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
        applications?: ApplicationRow[] | null
        queue_counts: Record<string, number>
        divisionOptions: { id: string; name: string; code: string }[]
        stageOptions: { value: string; label: string }[]
        semesterOptions?: { value: string; label: string }[]
        screening_reason_options?: { value: string; label: string }[]
        division_options?: { id: string; name: string; code: string }[]
        membership_type_options?: { value: string; label: string }[]
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
        interview_division_options?: { id: string; name: string; code: string }[]
        report?: ReportPayload | null
        applicant_detail?: ApplicationDetail | null
        divisions?: InterviewerDivision[]
        assignments?: InterviewerAssignment[]
        interviewerCandidates?: InterviewerCandidate[]
    }>(),
    {
        semesterOptions: () => [],
        screening_reason_options: () => [],
        division_options: () => [],
        membership_type_options: () => [],
        interview_division_options: () => [],
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
    if (isDuplicateAssign.value || assignForm.processing) return
    assignForm.post(routes.admin.recruitment.interviewers.assign, {
        preserveScroll: true,
        onSuccess: () => {
            assignForm.reset('user_id')
        },
    })
}

const unassignDialogOpen = ref(false)
const pendingUnassign = ref<InterviewerAssignment | null>(null)
const unassigningId = ref<string | null>(null)

function requestUnassign(row: InterviewerAssignment): void {
    pendingUnassign.value = row
    unassignDialogOpen.value = true
}

function cancelUnassign(): void {
    unassignDialogOpen.value = false
}

function confirmUnassign(): void {
    const row: InterviewerAssignment | null = pendingUnassign.value
    if (!row || unassigningId.value !== null) return
    unassigningId.value = row.id
    router.delete(routes.admin.recruitment.interviewers.unassign(row.id), {
        preserveScroll: true,
        onError: () => {
            showErrorToast('Gagal menghapus penugasan interviewer.')
        },
        onFinish: () => {
            unassigningId.value = null
            pendingUnassign.value = null
            unassignDialogOpen.value = false
        },
    })
}

function initialsOf(name: string): string {
    const words: string[] = name.trim().split(/\s+/).filter((w) => w.length > 0)
    if (words.length === 0) return '—'
    const first: string = words[0]?.charAt(0) ?? ''
    const second: string = words.length > 1 ? (words[1]?.charAt(0) ?? '') : ''
    const letters: string = `${first}${second}`.toUpperCase()
    return letters.length > 0 ? letters : '—'
}

const assignedPairKeys = computed<Set<string>>(
    () => new Set(props.assignments.map((a) => `${a.user_id}|${a.division_id}`)),
)

const assignInterviewerOptions = computed<SearchableSelectOption[]>(() =>
    props.interviewerCandidates.map((u) => {
        const currentDivision: string = assignForm.recruitment_division_id
        const isTaken: boolean =
            currentDivision.length > 0 && assignedPairKeys.value.has(`${u.id}|${currentDivision}`)
        return {
            value: u.id,
            label: u.name,
            sublabel: u.email,
            initials: initialsOf(u.name),
            disabled: isTaken,
        }
    }),
)

const assignDivisionOptions = computed<SearchableSelectOption[]>(() =>
    props.divisions.map((d) => {
        const currentUser: string = assignForm.user_id
        const isTaken: boolean =
            currentUser.length > 0 && assignedPairKeys.value.has(`${currentUser}|${d.id}`)
        return {
            value: d.id,
            label: d.name,
            sublabel: d.code,
            initials: d.code.slice(0, 2).toUpperCase(),
            disabled: isTaken,
        }
    }),
)

const isDuplicateAssign = computed<boolean>(() => {
    const userId: string = assignForm.user_id
    const divisionId: string = assignForm.recruitment_division_id
    if (userId.length === 0 || divisionId.length === 0) return false
    return assignedPairKeys.value.has(`${userId}|${divisionId}`)
})

const canSubmitAssign = computed<boolean>(() => {
    return (
        assignForm.user_id.length > 0 &&
        assignForm.recruitment_division_id.length > 0 &&
        !isDuplicateAssign.value &&
        !assignForm.processing
    )
})

interface AssignmentGroup {
    key: string
    divisionName: string
    divisionCode: string
    count: number
    rows: InterviewerAssignment[]
}

const groupedAssignments = computed<AssignmentGroup[]>(() => {
    const byDivision = new Map<string, InterviewerAssignment[]>()
    for (const row of props.assignments) {
        const list: InterviewerAssignment[] = byDivision.get(row.division_id) ?? []
        list.push(row)
        byDivision.set(row.division_id, list)
    }
    const order = new Map<string, number>(props.divisions.map((d, i) => [d.id, i]))
    const groups: AssignmentGroup[] = Array.from(byDivision.entries()).map(([divisionId, rows]) => {
        const sortedRows: InterviewerAssignment[] = [...rows].sort((a, b) =>
            a.user_name.localeCompare(b.user_name, 'id'),
        )
        const first: InterviewerAssignment | undefined = sortedRows[0]
        return {
            key: divisionId,
            divisionName: first?.division_name ?? 'Divisi',
            divisionCode: first?.division_code ?? '',
            count: sortedRows.length,
            rows: sortedRows,
        }
    })
    groups.sort((a, b) => {
        const orderA: number | undefined = order.get(a.key)
        const orderB: number | undefined = order.get(b.key)
        if (orderA !== undefined && orderB !== undefined) return orderA - orderB
        if (orderA !== undefined) return -1
        if (orderB !== undefined) return 1
        return a.divisionName.localeCompare(b.divisionName, 'id')
    })
    return groups
})

const assignmentsCountLabel = computed<string>(() => props.assignments.length.toLocaleString('id-ID'))

const pendingUnassignDescription = computed<string>(() => {
    const row: InterviewerAssignment | null = pendingUnassign.value
    if (!row) return ''
    return `${row.user_name} tidak lagi menjadi interviewer untuk ${row.division_name}.`
})

const isCreateSheetOpen = ref<boolean>(false)
const pendingCreatedEmail = ref<string>('')

function openCreateSheet(): void {
    isCreateSheetOpen.value = true
}

function closeCreateSheet(): void {
    isCreateSheetOpen.value = false
}

function tryAdoptCreatedInterviewer(): void {
    const email: string = pendingCreatedEmail.value.trim().toLowerCase()
    if (email === '') return
    const match: InterviewerCandidate | undefined = props.interviewerCandidates.find(
        (c) => c.email.trim().toLowerCase() === email,
    )
    if (match) {
        assignForm.user_id = match.id
        pendingCreatedEmail.value = ''
    }
}

function onInterviewerCreated(email: string): void {
    pendingCreatedEmail.value = email
    tryAdoptCreatedInterviewer()
}

watch(
    () => props.interviewerCandidates,
    () => {
        tryAdoptCreatedInterviewer()
    },
)

const applicantTotal = computed<number>(() => {
    return props.applications?.length ?? props.period.applications_count ?? 0
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

const selectedApplication = ref<ApplicationDetail | null>(null)
const detailLoading = ref<boolean>(false)
const detailCache = new Map<string, ApplicationDetail>()

function detailUrl(id: string): string {
    return `${routes.admin.recruitment.periods.show(props.period.id)}/applications/${id}`
}

async function selectApplicant(id: string): Promise<void> {
    if (selectedApplication.value?.id === id) return
    const cached: ApplicationDetail | undefined = detailCache.get(id)
    if (cached && 'can_resend_tracking' in cached) {
        selectedApplication.value = cached
        return
    }
    detailLoading.value = true
    try {
        const { data } = await axios.get<{ application: ApplicationDetail }>(detailUrl(id), {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        detailCache.set(id, data.application)
        selectedApplication.value = data.application
    } catch {
        selectedApplication.value = null
    } finally {
        detailLoading.value = false
    }
}

function closePanel(): void {
    selectedApplication.value = null
}

function refreshList(): void {
    detailCache.clear()
    router.reload({ only: ['applications', 'queue_counts', 'screening_reason_options', 'division_options', 'membership_type_options'] })
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
                    <span
                        class="ml-1 inline-flex min-h-5 min-w-6 items-center justify-center rounded-full bg-muted px-1.5 text-[11px] font-medium tabular-nums leading-4 text-muted-foreground transition-colors group-hover:text-foreground group-data-[state=active]:bg-foreground/10 group-data-[state=active]:text-foreground"
                    >
                        {{ assignmentsCountLabel }}
                    </span>
                </TabsTrigger>
            </TabsList>

            <TabsContent value="peserta" class="mt-4">
                <div v-if="canListApplications">
                    <PeriodApplicantSection
                        :period-id="period.id"
                        :tab="activeTab"
                        :can-screen="canScreenApplications"
                        :selected-id="selectedApplication?.id ?? null"
                        :applications="applications ?? null"
                        :queue-counts="queue_counts"
                        :division-options="divisionOptions"
                        :stage-options="stageOptions"
                        :semester-options="semesterOptions"
                        :query="query"
                        @select="selectApplicant"
                        @deselect="closePanel"
                    />

                    <ApplicantDetailPanel
                        :application="selectedApplication"
                        :loading="detailLoading"
                        :reason-options="screening_reason_options"
                        :division-options="division_options"
                        :membership-type-options="membership_type_options"
                        :editable="canScreenApplications"
                        @close="closePanel"
                        @submitted="refreshList"
                    />
                </div>
            </TabsContent>

            <TabsContent value="interview" class="mt-4">
                <PeriodInterviewSection
                    v-if="canScheduleInterviews"
                    :sessions="sessions ?? null"
                    :period-id="period.id"
                    :division-options="interview_division_options"
                />
            </TabsContent>

            <TabsContent value="laporan" class="mt-4">
                <PeriodReportSection v-if="canViewReports" :period-id="period.id" :report="report ?? null" />
            </TabsContent>

            <TabsContent value="interviewer" class="mt-4">
                <Card v-if="canManagePeriods" class="rounded-2xl border-border/70">
                    <CardHeader class="pb-1">
                        <CardTitle class="text-base">Tugaskan interviewer</CardTitle>
                        <p class="text-sm text-muted-foreground">
                            Pilih orang dan divisi, lalu tugaskan. Kombinasi yang sudah ada tidak bisa dipilih lagi.
                        </p>
                    </CardHeader>
                    <CardContent class="space-y-5">
                        <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="submitAssign">
                            <div class="space-y-2">
                                <Label for="assign-user">Interviewer</Label>
                                <SearchableSelect
                                    id="assign-user"
                                    v-model="assignForm.user_id"
                                    :options="assignInterviewerOptions"
                                    placeholder="Pilih interviewer"
                                    search-placeholder="Cari nama atau email…"
                                    :aria-invalid="assignForm.errors.user_id ? true : undefined"
                                    @create="openCreateSheet"
                                >
                                    <template #action>
                                        <Plus class="size-4" aria-hidden="true" />
                                        <span>Tambah interviewer baru</span>
                                    </template>
                                </SearchableSelect>
                                <p v-if="assignForm.errors.user_id" role="alert" class="text-xs text-destructive">
                                    {{ assignForm.errors.user_id }}
                                </p>
                            </div>
                            <div class="space-y-2">
                                <Label for="assign-division">Divisi</Label>
                                <SearchableSelect
                                    id="assign-division"
                                    v-model="assignForm.recruitment_division_id"
                                    :options="assignDivisionOptions"
                                    placeholder="Pilih divisi"
                                    search-placeholder="Cari divisi…"
                                    :aria-invalid="assignForm.errors.recruitment_division_id ? true : undefined"
                                />
                                <p
                                    v-if="assignForm.errors.recruitment_division_id"
                                    role="alert"
                                    class="text-xs text-destructive"
                                >
                                    {{ assignForm.errors.recruitment_division_id }}
                                </p>
                            </div>
                            <div class="sm:col-span-2">
                                <p v-if="isDuplicateAssign" role="status" class="mb-2 text-xs text-muted-foreground">
                                    Kombinasi interviewer dan divisi ini sudah ditugaskan.
                                </p>
                                <Button type="submit" size="sm" :disabled="!canSubmitAssign">
                                    {{ assignForm.processing ? 'Menugaskan…' : 'Tugaskan' }}
                                </Button>
                            </div>
                        </form>

                        <TooltipProvider v-if="groupedAssignments.length > 0">
                            <div class="space-y-4">
                            <section
                                v-for="group in groupedAssignments"
                                :key="group.key"
                                aria-label="Interviewer divisi"
                                class="overflow-hidden rounded-xl border border-border/70"
                            >
                                <header
                                    class="flex items-center justify-between gap-3 border-b border-border/60 bg-muted/40 px-4 py-2.5"
                                >
                                    <div class="flex min-w-0 items-center gap-2">
                                        <span
                                            aria-hidden="true"
                                            class="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold tracking-wide text-muted-foreground"
                                        >
                                            {{ group.divisionCode.slice(0, 2).toUpperCase() }}
                                        </span>
                                        <h3 class="truncate text-sm font-semibold">{{ group.divisionName }}</h3>
                                    </div>
                                    <Badge variant="secondary" class="shrink-0 tabular-nums">
                                        {{ group.count.toLocaleString('id-ID') }} orang
                                    </Badge>
                                </header>
                                <ul class="divide-y divide-border/70">
                                    <li
                                        v-for="row in group.rows"
                                        :key="row.id"
                                        class="flex items-center justify-between gap-3 px-4 py-3"
                                    >
                                        <div class="flex min-w-0 items-center gap-3">
                                            <Avatar class="size-8 shrink-0">
                                                <AvatarFallback class="text-[11px] font-semibold">
                                                    {{ initialsOf(row.user_name) }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-medium">{{ row.user_name }}</p>
                                                <p class="truncate text-xs text-muted-foreground">
                                                    {{ row.user_email }}
                                                </p>
                                            </div>
                                        </div>
                                        <Tooltip>
                                            <TooltipTrigger as-child>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    :aria-label="`Hapus ${row.user_name} dari ${row.division_name}`"
                                                    @click="requestUnassign(row)"
                                                >
                                                    <Trash2 class="size-4" aria-hidden="true" />
                                                </Button>
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <p>Hapus penugasan</p>
                                            </TooltipContent>
                                        </Tooltip>
                                    </li>
                                </ul>
                            </section>
                            </div>
                        </TooltipProvider>
                        <div
                            v-else
                            class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border/80 px-4 py-8 text-center"
                        >
                            <span
                                aria-hidden="true"
                                class="flex size-10 items-center justify-center rounded-full bg-muted"
                            >
                                <UserCheck class="size-5 text-muted-foreground" />
                            </span>
                            <p class="text-sm font-medium">Belum ada interviewer yang ditugaskan.</p>
                            <p class="max-w-sm text-xs leading-relaxed text-muted-foreground">
                                Pilih interviewer dan divisi di atas untuk menugaskan.
                            </p>
                        </div>
                    </CardContent>
                </Card>

                <ConfirmationModal
                    :open="unassignDialogOpen"
                    title="Hapus penugasan?"
                    :description="pendingUnassignDescription"
                    confirm-text="Hapus"
                    cancel-text="Batal"
                    variant="destructive"
                    :loading="unassigningId !== null"
                    @confirm="confirmUnassign"
                    @cancel="cancelUnassign"
                    @update:open="(v: boolean) => { unassignDialogOpen = v }"
                />

                <InterviewerCreateSheet
                    :open="isCreateSheetOpen"
                    :divisions="divisions"
                    :initial-division-id="assignForm.recruitment_division_id"
                    @close="closeCreateSheet"
                    @created="onInterviewerCreated"
                />
            </TabsContent>
        </Tabs>
    </div>
</template>
