<!--
THESIS: Command center interviewer — satu bar filter + tab antrean + daftar terkelompok urgensi,
  menggantikan tumpukan kartu lama yang tanpa feedback. Back-button dan scroll terjaga.
OWN-WORLD: Sistem admin yang sudah ada (Card rounded-2xl, Badge, Button, SimpleSelect, Input);
  kartu slip janji: zona identitas (nama + satu badge prioritas + meta mono) di atas hairline,
  zona logistik (jadwal + antrean) dan aksi di bawahnya. Daftar sesi hari ini tampil sebagai
  panel baris ringkas, bukan kartu geser.
STORY: Interviewer menyerbu yang mendesak lewat filter, membaca tiap kartu sebagai satu janji,
  menilai tanpa tersesat.
FIRST VIEWPORT: Panel sesi hari ini, satu panel filter (search + selects + count),
  lalu daftar terkelompok. Aksi primer selalu "Nilai / Ubah / Detail" di kanan kartu.
FORM: Approach A Filter Bar Command Center (spec 2026-09-18-my-interviews-redesign-design).
-->
<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
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
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import useAuth from '@/utils/composables/useAuth'
import {
    ChevronLeft,
    ChevronRight,
    ClipboardCheck,
    ListOrdered,
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
    queue_number: number | null
    needs_evaluation: boolean
    has_evaluation: boolean
    evaluation_locked: boolean
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

interface MyInterviewsQuery {
    queue?: string
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

interface InterviewFilterParams {
    q?: string
    division_id?: string
    session_id?: string
    eval?: string
    sort?: string
    queue?: string
    page?: number
}

type InterviewGroupKey = 'urgent' | 'today' | 'upcoming' | 'done'

interface InterviewGroup {
    key: InterviewGroupKey
    title: string
    hint: string
    rows: InterviewRow[]
}

interface StatusBadge {
    label: string
    variant: 'default' | 'secondary' | 'outline'
}

interface QueuePermission {
    can_view_recruitment_queue?: boolean
}

const QUEUE_TABS: { key: string; label: string }[] = [
    { key: '', label: 'Semua' },
    { key: 'pending', label: 'Perlu dinilai' },
    { key: 'today', label: 'Hari ini' },
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
    { value: 'queue', label: 'No. antrean' },
    { value: 'name', label: 'Nama A–Z' },
]

const SEARCH_DEBOUNCE_MS = 300
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
        queue_counts: Record<string, number>
        today_sessions: TodaySession[]
        next_action: NextAction | null
        division_options?: FilterOption[]
        session_options?: FilterOption[]
    }>(),
    {
        division_options: (): FilterOption[] => [],
        session_options: (): FilterOption[] => [],
    },
)

const page = usePage()
const authUser = useAuth(page.props)

const canViewQueue = computed<boolean>((): boolean => {
    const candidate: QueuePermission | null = authUser.value
    return candidate?.can_view_recruitment_queue === true
})

const searchInput = ref<string>(props.query.q ?? '')
const divisionId = ref<string>(props.query.division_id ?? '')
const sessionId = ref<string>(props.query.session_id ?? '')
const evalFilter = ref<string>(props.query.eval ?? '')
const sortKey = ref<string>(props.query.sort ?? '')
const queueTab = ref<string>(props.query.queue ?? '')
const isNavigating = ref<boolean>(false)

let searchTimer: ReturnType<typeof setTimeout> | null = null
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
        queueTab.value === (current.queue ?? '')
    )
}

function baseParams(pageNumber: number): InterviewFilterParams {
    return {
        q: searchInput.value.trim() || undefined,
        division_id: divisionId.value || undefined,
        session_id: sessionId.value || undefined,
        eval: evalFilter.value || undefined,
        sort: sortKey.value || undefined,
        queue: queueTab.value || undefined,
        page: pageNumber > 1 ? pageNumber : undefined,
    }
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

watch([searchInput, divisionId, sessionId, evalFilter, sortKey, queueTab], handleFilterChange)

function syncRefsFromQuery(next: MyInterviewsQuery): void {
    clearSearchTimer()
    searchInput.value = next.q ?? ''
    divisionId.value = next.division_id ?? ''
    sessionId.value = next.session_id ?? ''
    evalFilter.value = next.eval ?? ''
    sortKey.value = next.sort ?? ''
    queueTab.value = next.queue ?? ''
    skipFilterRun = true
    void nextTick(resetSkipFilterRun)
}

watch((): MyInterviewsQuery => props.query, syncRefsFromQuery)

onBeforeUnmount((): void => {
    clearSearchTimer()
})

onMounted((): void => {
    setTopbar({ title: 'Interview Saya', subtitle: 'Penugasan & penilaian Open Recruitment' })
})

const hasActiveFilters = computed<boolean>((): boolean => {
    return (
        searchInput.value.trim() !== '' ||
        divisionId.value !== '' ||
        sessionId.value !== '' ||
        evalFilter.value !== '' ||
        sortKey.value !== '' ||
        queueTab.value !== ''
    )
})

function resetFilters(): void {
    clearSearchTimer()
    searchInput.value = ''
    divisionId.value = ''
    sessionId.value = ''
    evalFilter.value = ''
    sortKey.value = ''
    queueTab.value = ''
}

function selectQueue(key: string): void {
    queueTab.value = key
}

function showUrl(applicationId: string): string {
    return routes.admin.recruitment.myInterviews.show(applicationId)
}

function queueUrl(sessionIdValue: string): string {
    return routes.recruitment.queue.show(sessionIdValue)
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
    if (key === '') return props.queue_counts.all ?? null
    const count: number | undefined = props.queue_counts[key]
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

function parseSchedule(value: string | null): Date | null {
    if (!value) return null
    const parsed: Date = new Date(value)
    return Number.isNaN(parsed.getTime()) ? null : parsed
}

function isSameCalendarDay(a: Date, b: Date): boolean {
    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate()
}

function startOfCalendarDay(value: Date): Date {
    const day: Date = new Date(value)
    day.setHours(0, 0, 0, 0)
    return day
}

function groupKeyForRow(row: InterviewRow, now: Date): InterviewGroupKey {
    if (row.evaluation_locked || row.has_evaluation) return 'done'
    const scheduled: Date | null = parseSchedule(row.scheduled_at)
    if (scheduled === null) return row.needs_evaluation ? 'urgent' : 'upcoming'
    if (isSameCalendarDay(scheduled, now)) return row.needs_evaluation ? 'urgent' : 'today'
    if (scheduled < startOfCalendarDay(now)) return 'urgent'
    return 'upcoming'
}

const interviewGroups = computed<InterviewGroup[]>((): InterviewGroup[] => {
    const now: Date = new Date()
    const buckets: Record<InterviewGroupKey, InterviewRow[]> = {
        urgent: [],
        today: [],
        upcoming: [],
        done: [],
    }
    for (const row of props.interviews.data) {
        buckets[groupKeyForRow(row, now)].push(row)
    }
    const groups: InterviewGroup[] = []
    if (buckets.urgent.length > 0) {
        groups.push({
            key: 'urgent',
            title: 'Mendesak',
            hint: 'Perlu dinilai, jadwalnya hari ini atau belum ada jadwal',
            rows: buckets.urgent,
        })
    }
    if (buckets.today.length > 0) {
        groups.push({
            key: 'today',
            title: 'Hari ini',
            hint: 'Terjadwal hari ini',
            rows: buckets.today,
        })
    }
    if (buckets.upcoming.length > 0) {
        groups.push({
            key: 'upcoming',
            title: 'Mendatang',
            hint: 'Jadwal berikutnya',
            rows: buckets.upcoming,
        })
    }
    if (buckets.done.length > 0) {
        groups.push({
            key: 'done',
            title: 'Selesai',
            hint: 'Sudah dinilai atau terkunci',
            rows: buckets.done,
        })
    }
    return groups
})

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

function queueNumberLabel(value: number | null): string {
    if (value === null) return ''
    return `Antrean #${String(value).padStart(2, '0')}`
}

const emptyTitle = computed<string>((): string =>
    hasActiveFilters.value ? 'Tidak ada hasil yang cocok' : 'Belum ada peserta regis ulang',
)

const emptyDescription = computed<string>((): string =>
    hasActiveFilters.value
        ? 'Coba ubah kata kunci atau atur ulang filter untuk melihat penugasan lain.'
        : 'Daftar ini hanya memuat peserta yang sudah regis ulang (scan QR) di sesi interviewmu.',
)
</script>

<template>
    <Head title="Interview Saya" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <section v-if="today_sessions.length > 0" aria-label="Sesi hari ini">
            <div class="mb-3 flex items-baseline justify-between gap-3">
                <h2 class="text-sm font-semibold">Sesi hari ini</h2>
                <p class="text-xs text-muted-foreground">{{ formatInt(today_sessions.length) }} sesi</p>
            </div>
            <Card class="rounded-2xl border-border/70">
                <CardContent class="divide-y divide-border/60 p-0">
                    <div
                        v-for="session in today_sessions"
                        :key="session.id"
                        class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3 px-4 py-3 sm:px-5"
                    >
                        <div class="min-w-0">
                            <p class="text-sm">
                                <span class="font-semibold tabular-nums"
                                    >{{ session.starts_at }}–{{ session.ends_at }}</span
                                >
                                <span class="text-muted-foreground">
                                    · {{ session.division?.name ?? 'Interview' }}</span
                                >
                            </p>
                            <p class="mt-0.5 text-xs text-muted-foreground">
                                {{ formatSessionDay(session.session_date) }} · {{ session.location }} ·
                                {{ session.room }} · {{ formatInt(session.my_interviews_count) }} assignment kamu
                            </p>
                        </div>
                        <Button v-if="canViewQueue" as-child variant="outline" size="sm" class="shrink-0">
                            <Link :href="queueUrl(session.id)">
                                <ListOrdered class="mr-2 size-4" aria-hidden="true" />
                                Lihat antrean
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </section>

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
                    Hanya peserta yang sudah regis ulang (scan QR) yang tampil di sini.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2" aria-label="Pintasan antrean">
            <button
                v-for="tab in QUEUE_TABS"
                :key="tab.key || 'all'"
                type="button"
                :aria-pressed="queueTab === tab.key"
                class="inline-flex min-h-9 items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-colors"
                :class="
                    queueTab === tab.key
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
                    :class="queueTab === tab.key ? 'bg-primary-foreground/20 text-primary-foreground' : ''"
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

        <template v-else-if="interviews.data.length > 0">
            <section v-for="group in interviewGroups" :key="group.key" :aria-label="group.title">
                <div class="mt-1 mb-2 flex items-center gap-2">
                    <h2 class="text-sm font-semibold">{{ group.title }}</h2>
                    <Badge variant="secondary" class="tabular-nums">{{ formatInt(group.rows.length) }}</Badge>
                    <p class="hidden text-xs text-muted-foreground sm:block">{{ group.hint }}</p>
                </div>
                <div class="grid gap-3">
                    <Card
                        v-for="row in group.rows"
                        :key="row.interview_id"
                        class="relative rounded-2xl border-border/70 transition-colors hover:border-primary/40 hover:bg-muted/30"
                    >
                        <CardContent class="p-4 sm:p-5">
                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1.5">
                                <Link
                                    v-if="row.application"
                                    :href="showUrl(row.application.id)"
                                    class="rounded text-sm font-semibold before:absolute before:inset-0 focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                                >
                                    {{ row.application.full_name }}
                                </Link>
                                <p v-else class="text-sm font-semibold">—</p>
                                <Badge :variant="statusBadge(row).variant">
                                    {{ statusBadge(row).label }}
                                </Badge>
                            </div>
                            <p class="mt-1 font-mono text-xs text-muted-foreground">
                                {{ row.application?.registration_number ?? '—' }}
                                <span v-if="row.application?.nim"> · {{ row.application.nim }}</span>
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
                                    <p
                                        v-if="row.queue_number !== null"
                                        class="mt-0.5 text-xs text-muted-foreground"
                                    >
                                        {{ queueNumberLabel(row.queue_number) }}
                                        <span v-if="row.session">
                                            · {{ row.session.division ?? 'Interview' }}</span
                                        >
                                    </p>
                                </div>
                                <div class="relative flex shrink-0 flex-wrap gap-2">
                                    <Button v-if="row.application" as-child size="sm">
                                        <Link :href="showUrl(row.application.id)">
                                            <ClipboardCheck class="mr-2 size-4" aria-hidden="true" />
                                            {{ actionLabel(row) }}
                                        </Link>
                                    </Button>
                                    <Button
                                        v-if="row.session && canViewQueue"
                                        as-child
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Link :href="queueUrl(row.session.id)">Antrean</Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>
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
        <p v-else-if="interviews.data.length > 0" class="text-center text-sm text-muted-foreground">
            {{ rangeLabel }}
        </p>
    </div>
</template>
