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
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, router } from '@inertiajs/vue3'
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
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import {
    ChevronLeft,
    ChevronRight,
    RotateCcw,
    Search,
    Ticket,
} from 'lucide-vue-next'
import InterviewRowCard, { type InterviewRow } from './InterviewRowCard.vue'
import InterviewApplicantCard, {
    type ApplicantCardMetaItem,
} from '@/components/modules/dashboard/recruitment/InterviewApplicantCard.vue'

defineOptions({ layout: DashboardLayout })

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
    sessions: { value: string; label: string; period: string | null }[]
}

interface PrimaryOpportunity {
    interview: {
        id: string
    }
    application: {
        id: string
        full_name: string
        registration_number: string
        nim: string
        primary_division: string | null
    }
    session: {
        id: string
        label: string
        period: string | null
    }
    interviewer_options: { value: string; label: string }[]
}

interface MyInterviewsQuery {
    tab?: string
    q?: string
    division_id?: string
    session_id?: string
    period_id?: string
    sort?: string
}

interface FilterOption {
    value: string
    label: string
}

const TABS: { key: string; label: string }[] = [
    { key: 'waiting', label: 'Waiting Rooms' },
    { key: 'in_progress', label: 'Sedang interview' },
    { key: 'done', label: 'Selesai' },
    { key: 'all', label: 'Semua Peserta' },
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
        period_options?: FilterOption[]
        session_options?: FilterOption[]
        secondary_opportunities?: SecondaryOpportunity[]
        primary_opportunities?: PrimaryOpportunity[]
        claimed_secondary?: InterviewRow[]
        can_assign_interviewer?: boolean
    }>(),
    {
        pending_start_count: 0,
        division_options: (): FilterOption[] => [],
        period_options: (): FilterOption[] => [],
        session_options: (): FilterOption[] => [],
        secondary_opportunities: (): SecondaryOpportunity[] => [],
        primary_opportunities: (): PrimaryOpportunity[] => [],
        claimed_secondary: (): InterviewRow[] => [],
        can_assign_interviewer: false,
    },
)

const searchInput = ref<string>(props.query.q ?? '')
const divisionId = ref<string>(props.query.division_id ?? '')
const sessionId = ref<string>(props.query.session_id ?? '')
const periodId = ref<string>(props.query.period_id ?? '')
const sortKey = ref<string>(props.query.sort ?? '')
const activeTab = ref<string>(props.query.tab ?? 'waiting')
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
        periodId.value === (current.period_id ?? '') &&
        sortKey.value === (current.sort ?? '') &&
        activeTab.value === (current.tab ?? 'waiting')
    )
}

function baseParams(pageNumber: number): Record<string, string | number> {
    const params: Record<string, string | number> = {}
    const q: string = searchInput.value.trim()
    if (q !== '') params.q = q
    if (divisionId.value !== '') params.division_id = divisionId.value
    if (sessionId.value !== '') params.session_id = sessionId.value
    if (periodId.value !== '') params.period_id = periodId.value
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

watch([searchInput, divisionId, sessionId, periodId, sortKey, activeTab], handleFilterChange)

function syncRefsFromQuery(next: MyInterviewsQuery): void {
    clearSearchTimer()
    searchInput.value = next.q ?? ''
    divisionId.value = next.division_id ?? ''
    sessionId.value = next.session_id ?? ''
    periodId.value = next.period_id ?? ''
    sortKey.value = next.sort ?? ''
    activeTab.value = next.tab ?? 'waiting'
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
    if (activeTab.value !== 'in_progress' && activeTab.value !== 'waiting') return
    if (searchTimer !== null) return
    if (isNavigating.value) return
    router.reload({
        only: ['interviews', 'tab_counts', 'pending_start_count', 'secondary_opportunities', 'primary_opportunities'],
        replace: true,
    })
}

function startTaskPolling(): void {
    stopTaskPolling()
    if (activeTab.value !== 'in_progress' && activeTab.value !== 'waiting') return
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
    if (activeTab.value === 'in_progress' || activeTab.value === 'waiting') startTaskPolling()
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
        periodId.value !== '' ||
        sortKey.value !== '' ||
        (activeTab.value !== '' && activeTab.value !== 'waiting')
    )
})

function resetFilters(): void {
    clearSearchTimer()
    searchInput.value = ''
    divisionId.value = ''
    sessionId.value = ''
    periodId.value = ''
    sortKey.value = ''
    activeTab.value = ''
}

function selectQueue(key: string): void {
    activeTab.value = key
}

const claimingId = ref<string | null>(null)

/** Kotak meta identitas (No. Registrasi + NIM) untuk kartu applicant. */
function identityBox(
    registrationNumber: string,
    nim: string,
): [ApplicantCardMetaItem, ApplicantCardMetaItem] {
    return [
        { icon: 'id', label: 'No. Registrasi', value: registrationNumber },
        { icon: 'cap', label: 'NIM', value: nim },
    ]
}

/** Kotak meta sesi (Tanggal Interview + Ruangan + Periode) dari label sesi. */
function sessionBox(
    label: string,
    periodName: string | null,
): [ApplicantCardMetaItem, ApplicantCardMetaItem, ApplicantCardMetaItem] {
    const parsed = parseSessionLabel(label)
    return [
        { icon: 'none', label: 'Tanggal Interview', value: parsed.date },
        { icon: 'pin', label: 'Ruangan', value: parsed.place === '' ? '—' : parsed.place },
        { icon: 'none', label: 'Periode', value: periodName ?? '—' },
    ]
}

/**
 * Pecah label sesi "09 Oct · Divisi · Lokasi/Ruang" untuk tampilan kartu.
 * Divisi tidak dikembalikan (sudah tampil sekali sebagai chip divisi applicant).
 */
function parseSessionLabel(label: string): { date: string; place: string } {
    const parts: string[] = label
        .split('·')
        .map((part) => part.trim())
        .filter((part) => part !== '')
    if (parts.length === 0) return { date: '—', place: '' }
    if (parts.length === 1) return { date: parts[0] ?? '—', place: '' }
    return { date: parts[0] ?? '—', place: parts.slice(2).join(' · ') || (parts[1] ?? '') }
}

function isClaiming(opp: SecondaryOpportunity): boolean {
    return claimingId.value === opp.application.id
}

function claimSecondary(opp: SecondaryOpportunity): void {
    if (claimingId.value !== null) return
    claimingId.value = opp.application.id
    router.post(
        routes.admin.recruitment.myInterviews.secondaryClaim,
        {
            application_id: opp.application.id,
        },
        {
            preserveScroll: true,
            onFinish: (): void => {
                claimingId.value = null
            },
        },
    )
}

const claimingPrimaryId = ref<string | null>(null)

function isClaimingPrimary(opp: PrimaryOpportunity): boolean {
    return claimingPrimaryId.value === opp.interview.id
}

function claimPrimary(opp: PrimaryOpportunity): void {
    if (claimingPrimaryId.value !== null) return
    claimingPrimaryId.value = opp.interview.id
    router.post(
        routes.admin.recruitment.myInterviews.primaryClaim,
        {
            interview_id: opp.interview.id,
        },
        {
            preserveScroll: true,
            onFinish: (): void => {
                claimingPrimaryId.value = null
            },
        },
    )
}

const assigneeMap = ref<Record<string, string>>({})
const assigningId = ref<string | null>(null)

function assigneeFor(opp: PrimaryOpportunity): string {
    return assigneeMap.value[opp.interview.id] ?? ''
}

function setAssignee(opp: PrimaryOpportunity, value: string): void {
    assigneeMap.value[opp.interview.id] = value
}

function isAssigning(opp: PrimaryOpportunity): boolean {
    return assigningId.value === opp.interview.id
}

function assignInterviewer(opp: PrimaryOpportunity): void {
    const interviewerId: string = assigneeFor(opp)
    if (assigningId.value !== null || interviewerId === '') return
    assigningId.value = opp.interview.id
    router.post(
        routes.admin.recruitment.myInterviews.primaryAssign(opp.interview.id),
        {
            interviewer_id: interviewerId,
        },
        {
            preserveScroll: true,
            onFinish: (): void => {
                assigningId.value = null
            },
        },
    )
}

function formatInt(value: number): string {
    return new Intl.NumberFormat('id-ID').format(value)
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

const periodOptions = computed<SimpleSelectOption[]>((): SimpleSelectOption[] => {
    if (props.period_options.length > 0) {
        return [{ value: '', label: 'Semua periode' }, ...props.period_options]
    }
    return [{ value: '', label: 'Semua periode' }]
})
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

const pendingStartCount = computed<number>((): number => {
    const raw: number | undefined = props.pending_start_count
    if (typeof raw !== 'number' || !Number.isFinite(raw) || raw <= 0) return 0
    return Math.floor(raw)
})

const hasWaitingContent = computed<boolean>((): boolean => {
    return (
        props.primary_opportunities.length > 0 ||
        props.secondary_opportunities.length > 0 ||
        props.claimed_secondary.length > 0
    )
})

const emptyTitle = computed<string>((): string => {
    if (hasActiveFilters.value) return 'Tidak ada hasil yang cocok'
    if (activeTab.value === 'waiting') return 'Belum ada antrean'
    return 'Belum ada interview hari ini'
})

const emptyDescription = computed<string>((): string => {
    if (hasActiveFilters.value)
        return 'Coba ubah kata kunci atau atur ulang filter untuk melihat penugasan lain.'
    if (activeTab.value === 'waiting')
        return 'Applicant yang sudah regis ulang akan muncul di sini untuk diambil.'
    return 'Interviews yang terjadwal hari ini akan muncul di sini.'
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
                        id="filter-periode"
                        v-model="periodId"
                        :options="periodOptions"
                        aria-label="Filter periode"
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

        <div class="flex flex-wrap gap-2" aria-label="Pintasan waiting room">
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

        <div
            v-if="activeTab === 'waiting'"
            class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2"
        >
        <section
            v-if="props.primary_opportunities.length > 0 && activeTab === 'waiting'"
            aria-label="Waiting room interview"
            class="flex min-w-0 flex-col gap-3"
        >
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="text-sm font-semibold">Waiting Rooms</h2>
            </div>
            <div class="grid gap-3">
                <InterviewApplicantCard
                    v-for="opp in props.primary_opportunities"
                    :key="opp.interview.id"
                    :name="opp.application.full_name"
                    :division="opp.application.primary_division"
                    status-label="Menunggu"
                    status-variant="waiting"
                    :box1="identityBox(opp.application.registration_number, opp.application.nim)"
                    :box2="sessionBox(opp.session.label, opp.session.period)"
                    note-label="Catatan"
                    note-body="pengarahan ruangan manual oleh staff"
                >
                    <template #action>
                        <Button
                            class="h-[52px] w-full rounded-lg px-4 text-sm font-semibold transition-transform active:scale-[0.96]"
                            :disabled="claimingPrimaryId !== null"
                            @click="claimPrimary(opp)"
                        >
                            <Ticket class="size-5 shrink-0" aria-hidden="true" />
                            <span class="min-w-0 flex-1 truncate text-left">{{
                                isClaimingPrimary(opp) ? 'Mengambil…' : 'Ambil antrean'
                            }}</span>
                            <ChevronRight class="size-5 shrink-0" aria-hidden="true" />
                        </Button>
                    </template>
                    <template #extra>
                        <div
                            v-if="props.can_assign_interviewer"
                            class="flex w-full flex-wrap items-center gap-2 rounded-xl border border-border/60 bg-muted/30 px-3 py-2.5"
                        >
                            <SimpleSelect
                                :id="`assign-${opp.interview.id}`"
                                :model-value="assigneeFor(opp)"
                                :options="[{ value: '', label: 'Pilih interviewer' }, ...opp.interviewer_options]"
                                aria-label="Pilih interviewer"
                                class="min-w-0 flex-1"
                                @update:model-value="(value: string) => setAssignee(opp, value)"
                            />
                            <Button
                                size="sm"
                                variant="secondary"
                                class="transition-transform active:scale-[0.96]"
                                :disabled="assigningId !== null || assigneeFor(opp) === ''"
                                @click="assignInterviewer(opp)"
                            >
                                {{ isAssigning(opp) ? 'Menetapkan…' : 'Tetapkan' }}
                            </Button>
                        </div>
                    </template>
                </InterviewApplicantCard>
            </div>
        </section>

        <section
            v-if="
                activeTab === 'waiting' &&
                (props.claimed_secondary.length > 0 || props.secondary_opportunities.length > 0)
            "
            aria-label="Daftar secondary division"
            class="flex min-w-0 flex-col gap-3"
        >
            <div class="flex items-baseline justify-between gap-3">
                <h2 class="text-sm font-semibold">Secondary Division</h2>
                <p class="text-xs text-muted-foreground">Pilihan divisi kedua · opsional</p>
            </div>
            <div v-if="props.claimed_secondary.length > 0" class="grid gap-3">
                <InterviewRowCard
                    v-for="row in props.claimed_secondary"
                    :key="row.interview_id"
                    :row="row"
                />
            </div>
            <div v-if="props.secondary_opportunities.length > 0" class="grid gap-3">
                <InterviewApplicantCard
                    v-for="opp in props.secondary_opportunities"
                    :key="opp.application.id"
                    :name="opp.application.full_name"
                    :division="opp.application.secondary_division"
                    status-label="Menunggu"
                    status-variant="waiting"
                    :box1="identityBox(opp.application.registration_number, opp.application.nim)"
                    :box2="
                        opp.sessions.length > 0
                            ? sessionBox(opp.sessions[0]?.label ?? '', opp.sessions[0]?.period ?? null)
                            : null
                    "
                    :note-label="opp.sessions.length > 0 ? 'Catatan' : 'Sesi'"
                    :note-body="
                        opp.sessions.length > 0
                            ? 'pengarahan ruangan manual oleh staff'
                            : 'Belum ada sesi aktif untuk divisi ini.'
                    "
                >
                    <template #action>
                        <Button
                            v-if="opp.sessions.length > 0"
                            class="h-[52px] w-full rounded-lg px-4 text-sm font-semibold transition-transform active:scale-[0.96]"
                            :disabled="claimingId !== null"
                            @click="claimSecondary(opp)"
                        >
                            <Ticket class="size-5 shrink-0" aria-hidden="true" />
                            <span class="min-w-0 flex-1 truncate text-left">{{
                                isClaiming(opp) ? 'Mengambil…' : 'Ambil antrean'
                            }}</span>
                            <ChevronRight class="size-5 shrink-0" aria-hidden="true" />
                        </Button>
                    </template>
                </InterviewApplicantCard>
            </div>
        </section>
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

        <template v-else-if="activeTab !== 'waiting' && interviews.data.length > 0">
            <div
                v-if="activeTab === 'all'"
                class="grid grid-cols-1 items-start gap-4 sm:grid-cols-2 xl:grid-cols-3"
            >
                <InterviewRowCard
                    v-for="row in interviews.data"
                    :key="row.interview_id"
                    :row="row"
                    :hide-action="true"
                />
            </div>
            <div v-else class="grid grid-cols-1 items-start gap-6 lg:grid-cols-2">
                <section aria-label="Daftar primary division">
                    <div class="mb-3 flex items-baseline justify-between gap-3">
                        <h2 class="text-sm font-semibold">Primary Division</h2>
                        <p class="text-xs text-muted-foreground">Pilihan divisi pertama applicant</p>
                    </div>
                    <div class="grid gap-3">
                        <InterviewRowCard
                            v-for="row in interviews.data"
                            :key="row.interview_id"
                            :row="row"
                        />
                    </div>
                </section>
            </div>
        </template>

        <EmptyState
            v-else-if="activeTab !== 'waiting' || !hasWaitingContent"
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
