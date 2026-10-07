<!--
THESIS: Command center interviewer — satu bar filter + tab antrean + daftar flat berurutan,
  menggantikan tumpukan kartu lama yang tanpa feedback. Back-button dan scroll terjaga.
OWN-WORLD: Sistem admin yang sudah ada (Card rounded-2xl, Badge, Button, Input);
  kartu slip janji: zona identitas (nama + satu badge prioritas + meta mono) di atas hairline,
  zona logistik (jadwal + antrean) dan aksi di bawahnya.
STORY: Interviewer menyerbu yang mendesak lewat filter, membaca tiap kartu sebagai satu janji,
  menilai tanpa tersesat.
FIRST VIEWPORT: Satu panel filter (search + selects + count),
  lalu daftar flat. Aksi primer selalu "Nilai / Ubah / Detail" di kanan kartu.
FORM: Approach A Filter Bar Command Center (spec 2026-09-18-my-interviews-redesign-design).
-->
<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import EmptyState from '@/components/modules/dashboard/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import {
    Pagination,
    PaginationContent,
    PaginationEllipsis,
    PaginationItem,
    PaginationNext,
    PaginationPrevious,
} from '@/components/ui/pagination'
import { routes } from '@/lib/routes'
import type { SimpleSelectOption } from '@/components/ui/simple-select'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import {
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    RotateCcw,
    Search,
} from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

interface InterviewRow {
    interview_id: string
    scheduled_at: string | null
    status_label: string
    location: string
    room: string
    needs_evaluation: boolean
    has_evaluation: boolean
    evaluation_locked: boolean
    has_attendance?: boolean
    application: {
        id: string
        full_name: string
        nim?: string | null
        registration_number: string
        primary_division: string | null
    } | null
    session: {
        id: string
        session_date: string
        division: string | null
    } | null
}

interface TodaySession {
    id: string
    session_date: string
    starts_at: string
    ends_at: string
    location: string
    room: string
    my_interviews_count: number
    division: { name: string } | null
}

interface NextAction {
    title: string
    description: string
    application_id: string
    full_name: string
    registration_number: string
    session_id: string | null
}

interface SecondaryOpportunity {
    application: {
        id: string
        full_name: string
        registration_number: string
        nim: string
        secondary_division: string | null
    }
    sessions: { value: string; label: string }[]
}

interface MyInterviewsQuery {
    tab?: string
    q?: string
    division_id?: string
    session_id?: string
    eval?: string
    sort?: string
}

interface FilterOption {
    value: string
    label: string
}

interface StatusBadge {
    label: string
    variant: 'default' | 'secondary' | 'outline'
}

const TABS: { key: string; label: string }[] = [
    { key: 'in_progress', label: 'Sedang interview' },
    { key: 'done', label: 'Selesai' },
]

const EVAL_OPTIONS: FilterOption[] = [
    { value: '', label: 'Semua status' },
    { value: 'pending', label: 'Perlu dinilai' },
    { value: 'done', label: 'Sudah dinilai' },
    { value: 'locked', label: 'Terkunci' },
]

const SORT_OPTIONS: FilterOption[] = [
    { value: '', label: 'Jadwal terdekat' },
    { value: 'pending_first', label: 'Belum dinilai dulu' },
    { value: 'name', label: 'Nama A–Z' },
]

const SEARCH_DEBOUNCE_MS = 300
const TASK_POLL_MS = 15000
const FALLBACK_PER_PAGE = 20

const props = withDefaults(
    defineProps<{
        interviews: {
            data: InterviewRow[]
            current_page: number
            last_page: number
            total: number
            per_page?: number
            from?: number | null
            to?: number | null
        }
        query: MyInterviewsQuery
        tab_counts: Record<string, number>
        today_sessions: TodaySession[]
        next_action: NextAction | null
        pending_start_count?: number
        division_options?: FilterOption[]
        session_options?: FilterOption[]
        secondary_opportunities?: SecondaryOpportunity[]
    }>(),
    {
        pending_start_count: 0,
        division_options: (): FilterOption[] => [],
        session_options: (): FilterOption[] => [],
        secondary_opportunities: (): SecondaryOpportunity[] => [],
    },
)

const searchInput = ref<string>(props.query.q ?? '')
const divisionId = ref<string>(props.query.division_id ?? '')
const sessionId = ref<string>(props.query.session_id ?? '')
const evalFilter = ref<string>(props.query.eval ?? '')
const sortKey = ref<string>(props.query.sort ?? '')
const activeTab = ref<string>(props.query.tab ?? 'in_progress')
const isNavigating = ref<boolean>(false)

let searchTimer: ReturnType<typeof setTimeout> | null = null
let taskPollTimer: ReturnType<typeof setInterval> | null = null
let skipFilterRun = false

function clearSearchTimer(): void {
    if (searchTimer !== null) {
        clearTimeout(searchTimer)
        searchTimer = null
    }
}

function resetSkipFilterRun(): void {
    skipFilterRun = false
}

function refsMatchQuery(): boolean {
    const current: MyInterviewsQuery = props.query
    return (
        searchInput.value === (current.q ?? '') &&
        divisionId.value === (current.division_id ?? '') &&
        sessionId.value === (current.session_id ?? '') &&
        evalFilter.value === (current.eval ?? '') &&
        sortKey.value === (current.sort ?? '') &&
        activeTab.value === (current.tab ?? 'in_progress')
    )
}

function baseParams(pageNumber: number): Record<string, string | number> {
    const params: Record<string, string | number> = {}
    const q: string = searchInput.value.trim()
    if (q !== '') params.q = q
    if (divisionId.value !== '') params.division_id = divisionId.value
    if (sessionId.value !== '') params.session_id = sessionId.value
    if (evalFilter.value !== '') params.eval = evalFilter.value
    if (sortKey.value !== '') params.sort = sortKey.value
    if (activeTab.value !== '') params.tab = activeTab.value
    if (pageNumber > 1) params.page = pageNumber
    return params
}

function handleFilterStart(): void {
    isNavigating.value = true
}

function handleFilterFinish(): void {
    isNavigating.value = false
}

function applyFilters(pageNumber: number = 1): void {
    router.get(routes.admin.recruitment.myInterviews.index, baseParams(pageNumber), {
        preserveState: true,
        preserveScroll: true,
        replace: false,
        onStart: handleFilterStart,
        onFinish: handleFilterFinish,
    })
}

type FilterTuple = [string, string, string, string, string, string]

function handleFilterChange(next: FilterTuple, prev: FilterTuple): void {
    if (skipFilterRun && refsMatchQuery()) {
        skipFilterRun = false
        return
    }
    skipFilterRun = false
    clearSearchTimer()
    const searchChanged: boolean = next[0] !== prev[0]
    if (searchChanged) {
        searchTimer = setTimeout((): void => {
            applyFilters(1)
        }, SEARCH_DEBOUNCE_MS)
        return
    }
    applyFilters(1)
}

watch([searchInput, divisionId, sessionId, evalFilter, sortKey, activeTab], handleFilterChange)

function syncRefsFromQuery(next: MyInterviewsQuery): void {
    clearSearchTimer()
    searchInput.value = next.q ?? ''
    divisionId.value = next.division_id ?? ''
    sessionId.value = next.session_id ?? ''
    evalFilter.value = next.eval ?? ''
    sortKey.value = next.sort ?? ''
    activeTab.value = next.tab ?? 'in_progress'
    skipFilterRun = true
    void nextTick(resetSkipFilterRun)
}

watch((): MyInterviewsQuery => props.query, syncRefsFromQuery)

function stopTaskPolling(): void {
    if (taskPollTimer !== null) {
        clearInterval(taskPollTimer)
        taskPollTimer = null
    }
}

function refreshTaskList(): void {
    if (document.hidden) return
    if (activeTab.value !== 'in_progress') return
    if (searchTimer !== null) return
    if (isNavigating.value) return
    router.reload({
        only: ['interviews', 'tab_counts', 'pending_start_count', 'secondary_opportunities'],
        replace: true,
    })
}

function startTaskPolling(): void {
    stopTaskPolling()
    if (activeTab.value !== 'in_progress') return
    taskPollTimer = setInterval((): void => {
        refreshTaskList()
    }, TASK_POLL_MS)
}

function handleVisibilityChange(): void {
    if (document.hidden) {
        stopTaskPolling()
        return
    }
    refreshTaskList()
    startTaskPolling()
}

watch(activeTab, (): void => {
    if (activeTab.value === 'in_progress') startTaskPolling()
    else stopTaskPolling()
})

onBeforeUnmount((): void => {
    clearSearchTimer()
    stopTaskPolling()
    document.removeEventListener('visibilitychange', handleVisibilityChange)
})

onMounted((): void => {
    setTopbar({ title: 'Interview Saya', subtitle: 'Penugasan & penilaian Open Recruitment' })
    document.addEventListener('visibilitychange', handleVisibilityChange)
    startTaskPolling()
})

const hasActiveFilters = computed<boolean>((): boolean => {
    return (
        searchInput.value.trim() !== '' ||
        divisionId.value !== '' ||
        sessionId.value !== '' ||
        evalFilter.value !== '' ||
        sortKey.value !== '' ||
        activeTab.value !== ''
    )
})

function resetFilters(): void {
    clearSearchTimer()
    searchInput.value = ''
    divisionId.value = ''
    sessionId.value = ''
    evalFilter.value = ''
    sortKey.value = ''
    activeTab.value = ''
}

function selectQueue(key: string): void {
    activeTab.value = key
}

function showUrl(interviewId: string): string {
    return routes.admin.recruitment.myInterviews.show(interviewId)
}

function sessionUrl(sessionIdValue: string): string {
    return routes.admin.recruitment.interviewSessions.show(sessionIdValue)
}

const claimSession: Record<string, string> = reactive({})
const claimingId = ref<string | null>(null)

function claimOptions(opp: SecondaryOpportunity): FilterOption[] {
    return [
        { value: '', label: 'Pilih sesi…' },
        ...opp.sessions.map(
            (session): FilterOption => ({ value: session.value, label: session.label }),
        ),
    ]
}

function canClaim(opp: SecondaryOpportunity): boolean {
    return (
        opp.sessions.length > 0 &&
        (claimSession[opp.application.id] ?? '') !== '' &&
        claimingId.value === null
    )
}

function isClaiming(opp: SecondaryOpportunity): boolean {
    return claimingId.value === opp.application.id
}

function claimSecondary(opp: SecondaryOpportunity): void {
    if (!canClaim(opp)) return
    claimingId.value = opp.application.id
    router.post(
        routes.admin.recruitment.myInterviews.secondaryClaim,
        {
            application_id: opp.application.id,
            session_id: claimSession[opp.application.id],
        },
        {
            preserveScroll: true,
            onFinish: (): void => {
                claimingId.value = null
            },
        },
    )
}

function formatInt(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value)
}

function formatSchedule(iso: string | null): string {
    if (!iso) return 'Jadwal belum ditetapkan'
    const parsed: Date = new Date(iso)
    if (Number.isNaN(parsed.getTime())) return 'Jadwal belum ditetapkan'
    return parsed.toLocaleString('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    })
}

function formatSessionDay(value: string): string {
    const parsed: Date = new Date(`${value}T00:00:00`)
    if (Number.isNaN(parsed.getTime())) return value
    return parsed.toLocaleDateString('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
    })
}

function sessionFallbackLabel(session: TodaySession): string {
    const division: string = session.division?.name ?? 'Interview'
    return `${division} · ${formatSessionDay(session.session_date)} · ${session.starts_at}–${session.ends_at}`
}

const divisionOptions = computed<FilterOption[]>((): FilterOption[] => {
    if (props.division_options.length > 0) {
        return [{ value: '', label: 'Semua divisi' }, ...props.division_options]
    }
    const names: string[] = []
    for (const session of props.today_sessions) {
        const divisionName: string | undefined = session.division?.name ?? undefined
        if (divisionName && !names.includes(divisionName)) names.push(divisionName)
    }
    for (const row of props.interviews.data) {
        const primary: string | undefined = row.application?.primary_division ?? undefined
        if (primary && !names.includes(primary)) names.push(primary)
        const sessionDivision: string | undefined = row.session?.division ?? undefined
        if (sessionDivision && !names.includes(sessionDivision)) names.push(sessionDivision)
    }
    const fallback: FilterOption[] = names.map(
        (name: string): FilterOption => ({ value: name, label: name }),
    )
    return [{ value: '', label: 'Semua divisi' }, ...fallback]
})

const sessionOptions = computed<SimpleSelectOption[]>((): SimpleSelectOption[] => {
    if (props.session_options.length > 0) {
        return [{ value: '', label: 'Semua sesi' }, ...props.session_options]
    }
    const fallback: SimpleSelectOption[] = props.today_sessions.map(
        (session: TodaySession): SimpleSelectOption => ({
            value: session.id,
            label: sessionFallbackLabel(session),
        }),
    )
    return [{ value: '', label: 'Semua sesi' }, ...fallback]
})

const evalOptions = computed<SimpleSelectOption[]>((): SimpleSelectOption[] => EVAL_OPTIONS)
const sortOptions = computed<SimpleSelectOption[]>((): SimpleSelectOption[] => SORT_OPTIONS)

function queueBadgeCount(key: string): number | null {
    const count: number | undefined = props.tab_counts[key]
    return count !== undefined ? count : null
}

const perPage = computed<number>((): number => props.interviews.per_page ?? FALLBACK_PER_PAGE)

const rangeStart = computed<number>((): number => {
    if (props.interviews.from !== undefined && props.interviews.from !== null) return props.interviews.from
    if (props.interviews.total === 0) return 0
    return (props.interviews.current_page - 1) * perPage.value + 1
})

const rangeEnd = computed<number>((): number => {
    if (props.interviews.to !== undefined && props.interviews.to !== null) return props.interviews.to
    return Math.min(props.interviews.total, props.interviews.current_page * perPage.value)
})

const totalLabel = computed<string>((): string => `${formatInt(props.interviews.total)} hasil`)

const rangeLabel = computed<string>(
    (): string =>
        `Menampilkan ${formatInt(rangeStart.value)}–${formatInt(rangeEnd.value)} dari ${formatInt(props.interviews.total)}`,
)

function statusBadge(row: InterviewRow): StatusBadge {
    if (row.needs_evaluation) return { label: 'Perlu dinilai', variant: 'default' }
    if (row.evaluation_locked) return { label: 'Terkunci', variant: 'secondary' }
    if (row.has_evaluation) return { label: 'Sudah dinilai', variant: 'outline' }
    return { label: row.status_label, variant: 'outline' }
}

function actionLabel(row: InterviewRow): string {
    if (row.needs_evaluation) return 'Nilai'
    if (row.has_evaluation && !row.evaluation_locked) return 'Ubah'
    return 'Detail'
}

function isLockedByAttendance(row: InterviewRow): boolean {
    return row.has_attendance !== true
}

function attendanceLockedLabel(row: InterviewRow): string {
    const name: string = row.application?.full_name ?? 'Applicant'
    return `${actionLabel(row)} ${name} terkunci — belum regis ulang (scan QR)`
}

const pendingStartCount = computed<number>((): number => {
    const raw: number | undefined = props.pending_start_count
    if (typeof raw !== 'number' || !Number.isFinite(raw) || raw <= 0) return 0
    return Math.floor(raw)
})

const emptyTitle = computed<string>((): string => {
    return hasActiveFilters.value ? 'Tidak ada hasil yang cocok' : 'Belum ada peserta regis ulang'
})

const emptyDescription = computed<string>((): string => {
    return hasActiveFilters.value
        ? 'Coba ubah kata kunci atau atur ulang filter untuk melihat penugasan lain.'
        : 'Daftar ini memuat semua assignment kamu. Yang belum scan QR terkunci.'
})
</script>

<template>
    <Head title="Interview Saya" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="rounded-2xl border border-border/70 bg-background p-3">
            <div class="flex flex-col gap-2.5">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <Input
                        v-model="searchInput"
                        type="search"
                        placeholder="Cari nama, NIM, atau no. registrasi…"
                        aria-label="Cari applicant"
                        class="pl-9"
                    />
                </div>
                <div class="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                    <SimpleSelect
                        id="filter-divisi"
                        v-model="divisionId"
                        :options="divisionOptions"
                        aria-label="Filter divisi"
                    />
                    <SimpleSelect
                        id="filter-sesi"
                        v-model="sessionId"
                        :options="sessionOptions"
                        aria-label="Filter sesi"
                    />
                    <SimpleSelect
                        id="filter-status"
                        v-model="evalFilter"
                        :options="evalOptions"
                        aria-label="Filter status penilaian"
                    />
                    <SimpleSelect
                        id="filter-urut"
                        v-model="sortKey"
                        :options="sortOptions"
                        aria-label="Urutkan daftar"
                    />
                </div>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-xs text-muted-foreground" aria-live="polite">{{ totalLabel }}</p>
                    <Button v-if="hasActiveFilters" variant="ghost" size="sm" @click="resetFilters">
                        <RotateCcw class="mr-2 size-4" aria-hidden="true" />
                        Atur ulang
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    Peserta yang belum regis ulang (scan QR) terkunci — tidak bisa dinilai sebelum scan.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2" aria-label="Pintasan antrean">
            <button
                v-for="tab in TABS"
                :key="tab.key || 'all'"
                type="button"
                :aria-pressed="activeTab === tab.key"
                class="inline-flex min-h-9 items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-colors"
                :class="
                    activeTab === tab.key
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'border-border bg-background hover:bg-muted/50'
                "
                @click="selectQueue(tab.key)"
            >
                {{ tab.label }}
                <Badge
                    v-if="queueBadgeCount(tab.key) !== null && queueBadgeCount(tab.key)! > 0"
                    variant="secondary"
                    class="tabular-nums"
                    :class="activeTab === tab.key ? 'bg-primary-foreground/20 text-primary-foreground' : ''"
                >
                    {{ formatInt(queueBadgeCount(tab.key)!) }}
                </Badge>
            </button>
        </div>

        <div v-if="isNavigating" class="grid gap-3" aria-hidden="true">
            <Card v-for="n in 3" :key="n" class="rounded-2xl border-border/70">
                <CardContent class="flex animate-pulse items-start justify-between gap-4 p-5">
                    <div class="min-w-0 flex-1 space-y-2">
                        <div class="h-4 w-2/5 rounded bg-muted" />
                        <div class="h-3 w-3/5 rounded bg-muted" />
                        <div class="h-3 w-1/3 rounded bg-muted" />
                    </div>
                    <div class="h-8 w-20 shrink-0 rounded-lg bg-muted" />
                </CardContent>
            </Card>
        </div>

        <template v-else-if="pendingStartCount > 0 && interviews.data.length === 0">
            <div
                role="status"
                class="rounded-2xl border border-border/70 bg-background px-6 py-10 text-center"
            >
                <p class="text-sm font-semibold">Interview belum dimulai.</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    Kartu penilaian muncul setelah jadwal dimulai.
                </p>
            </div>
        </template>

        <template v-else-if="interviews.data.length > 0">
            <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                <section aria-label="Daftar primary division">
                    <div class="mb-3 flex items-baseline justify-between gap-3">
                        <h2 class="text-sm font-semibold">Primary Division</h2>
                        <p class="text-xs text-muted-foreground">Pilihan divisi pertama applicant</p>
                    </div>
                    <div class="grid gap-3">
                        <Card
                            v-for="row in interviews.data"
                            :key="row.interview_id"
                            class="relative rounded-2xl transition-colors hover:border-primary/40 hover:bg-muted/30"
                            :class="
                                isLockedByAttendance(row)
                                    ? 'border-dashed border-border/70 opacity-70'
                                    : 'border-border/70'
                            "
                        >
                                    <CardContent class="p-4 sm:p-5">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                                            <Link
                                                v-if="row.application && !isLockedByAttendance(row)"
                                                :href="showUrl(row.interview_id)"
                                                class="rounded text-sm font-semibold before:absolute before:inset-0 focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                                            >
                                                {{ row.application.full_name }}
                                            </Link>
                                            <span
                                                v-else-if="row.application"
                                                class="text-sm font-semibold text-muted-foreground"
                                                aria-label="Nama applicant terkunci — belum regis ulang"
                                            >
                                                {{ row.application.full_name }}
                                            </span>
                                            <p v-else class="text-sm font-semibold">—</p>
                                            <Badge
                                                v-if="!row.needs_evaluation"
                                                :variant="statusBadge(row).variant"
                                            >
                                                {{ statusBadge(row).label }}
                                            </Badge>
                                            <Badge
                                                v-if="isLockedByAttendance(row)"
                                                variant="secondary"
                                            >
                                                Belum regis ulang
                                            </Badge>
                                        </div>
                                        <p class="mt-1 font-mono text-xs text-muted-foreground">
                                            {{ row.application?.registration_number ?? '—' }}
                                            <span v-if="row.application?.nim">
                                                · {{ row.application.nim }}</span
                                            >
                                            · {{ row.application?.primary_division ?? '—' }}
                                        </p>
                                        <div
                                            class="mt-3 flex flex-wrap items-end justify-between gap-x-4 gap-y-3 border-t border-border/60 pt-3"
                                        >
                                            <div class="min-w-0">
                                                <p class="text-sm">
                                                    {{ formatSchedule(row.scheduled_at) }}
                                                    · {{ row.location }} · {{ row.room }}
                                                </p>
                                            </div>
                                            <div class="relative flex shrink-0 flex-wrap gap-2">
                                                <Button
                                                    v-if="row.application && isLockedByAttendance(row)"
                                                    size="sm"
                                                    disabled
                                                    aria-disabled="true"
                                                    :aria-label="attendanceLockedLabel(row)"
                                                >
                                                    <ClipboardCheck
                                                        class="mr-2 size-4"
                                                        aria-hidden="true"
                                                    />
                                                    {{ actionLabel(row) }}
                                                </Button>
                                                <Button
                                                    v-else-if="row.application"
                                                    as-child
                                                    size="sm"
                                                >
                                                    <Link :href="showUrl(row.interview_id)">
                                                        <ClipboardCheck
                                                            class="mr-2 size-4"
                                                            aria-hidden="true"
                                                        />
                                                        {{ actionLabel(row) }}
                                                    </Link>
                                                </Button>
                                                <Button
                                                    v-if="row.session && isLockedByAttendance(row)"
                                                    size="sm"
                                                    variant="outline"
                                                    disabled
                                                    aria-disabled="true"
                                                    aria-label="Lihat antrean terkunci — applicant belum regis ulang"
                                                >
                                                    Antrean
                                                </Button>
                                                <Button
                                                    v-else-if="row.session"
                                                    as-child
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link :href="sessionUrl(row.session.id)">Sesi</Link>
                                                </Button>
                                            </div>
                                        </div>
                                    </CardContent>
                        </Card>
                    </div>
                </section>
                <section aria-label="Daftar secondary division">
                    <div class="mb-3 flex items-baseline justify-between gap-3">
                        <h2 class="text-sm font-semibold">Secondary Division</h2>
                        <p class="text-xs text-muted-foreground">Pilihan divisi kedua · opsional</p>
                    </div>
                    <div v-if="props.secondary_opportunities.length > 0" class="grid gap-3">
                        <Card
                            v-for="opp in props.secondary_opportunities"
                            :key="opp.application.id"
                            class="rounded-2xl border-border/70"
                        >
                            <CardContent class="space-y-3 p-4 sm:p-5">
                                <div>
                                    <p class="text-sm font-semibold">{{ opp.application.full_name }}</p>
                                    <p class="mt-1 font-mono text-xs text-muted-foreground">
                                        {{ opp.application.registration_number }} · {{ opp.application.nim }} ·
                                        {{ opp.application.secondary_division ?? '—' }}
                                    </p>
                                </div>
                                <div v-if="opp.sessions.length > 0" class="flex flex-col gap-2">
                                    <SimpleSelect
                                        :id="`claim-sesi-${opp.application.id}`"
                                        v-model="claimSession[opp.application.id]"
                                        :options="claimOptions(opp)"
                                        aria-label="Pilih sesi secondary"
                                    />
                                    <Button
                                        size="sm"
                                        :disabled="!canClaim(opp)"
                                        @click="claimSecondary(opp)"
                                    >
                                        {{ isClaiming(opp) ? 'Mengambil…' : 'Ambil' }}
                                    </Button>
                                </div>
                                <p v-else class="text-xs text-muted-foreground">
                                    Belum ada sesi aktif untuk divisi ini.
                                </p>
                            </CardContent>
                        </Card>
                    </div>
                    <Card v-else class="rounded-2xl border-dashed border-border/70">
                        <CardContent
                            class="flex flex-col items-center gap-2 p-8 text-center sm:p-10"
                        >
                            <h3 class="text-sm font-semibold">Belum ada peluang secondary</h3>
                            <p class="max-w-xs text-sm text-muted-foreground">
                                Applicant yang primary-nya sudah dinilai akan tampil di sini.
                            </p>
                        </CardContent>
                    </Card>
                </section>
            </div>
        </template>

        <EmptyState
            v-else
            :title="emptyTitle"
            :description="emptyDescription"
            animation-name="emptyData"
        >
            <Button v-if="hasActiveFilters" variant="outline" size="sm" @click="resetFilters">
                <RotateCcw class="mr-2 size-4" aria-hidden="true" />
                Atur ulang filter
            </Button>
        </EmptyState>

        <div v-if="interviews.last_page > 1" class="flex flex-col items-center gap-3">
            <Pagination
                :page="interviews.current_page"
                :total="interviews.total"
                :items-per-page="perPage"
                :sibling-count="1"
                @update:page="applyFilters"
            >
                <PaginationContent v-slot="{ items }">
                    <PaginationPrevious>
                        <ChevronLeft class="size-4" aria-hidden="true" />
                        <span class="hidden sm:block">Sebelumnya</span>
                    </PaginationPrevious>
                    <template v-for="(item, index) in items" :key="index">
                        <PaginationItem
                            v-if="item.type === 'page'"
                            :value="item.value"
                            :is-active="item.value === interviews.current_page"
                            :aria-label="`Ke halaman ${item.value}`"
                        >
                            {{ item.value }}
                        </PaginationItem>
                        <PaginationEllipsis v-else :index="index" />
                    </template>
                    <PaginationNext>
                        <span class="hidden sm:block">Berikutnya</span>
                        <ChevronRight class="size-4" aria-hidden="true" />
                    </PaginationNext>
                </PaginationContent>
            </Pagination>
            <p class="text-sm text-muted-foreground">{{ rangeLabel }}</p>
        </div>
        <p
            v-else-if="interviews.data.length > 0"
            class="text-center text-sm text-muted-foreground"
        >
            {{ rangeLabel }}
        </p>
    </div>
</template>
