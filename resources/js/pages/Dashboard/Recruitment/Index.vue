<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import DivisionListSheet, {
    type DashboardDivision,
} from '@/components/modules/dashboard/recruitment/DivisionListSheet.vue'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select'
import { routes } from '@/lib/routes'
import { cn } from '@/lib/utils'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import useAuth from '@/utils/composables/useAuth'
import { usePage } from '@inertiajs/vue3'
import {
    CalendarRange,
    Layers,
    User,
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

interface PeriodCreator {
    name: string
    avatar_url: string | null
}

interface PeriodRow {
    id: string
    name: string
    slug: string
    status: string
    status_label: string
    registration_opens_at: string | null
    registration_closes_at: string | null
    applications_count: number
    creator?: PeriodCreator | null
    can_edit?: boolean
}

interface PeriodPaginator {
    data: PeriodRow[]
    current_page: number
    last_page: number
    total: number
    per_page?: number
}

const props = withDefaults(
    defineProps<{
        summary: {
            active_period: PeriodSummary | null
            stats: Record<string, number>
            funnel?: { stage: string; label: string; count: number }[]
            accepted_count?: number
            is_interviewer_view?: boolean
            action_queues?: ActionQueue[]
            today_sessions?: TodaySession[]
        }
        periods?: PeriodPaginator | null
        query?: { search?: string; status?: string }
        statusOptions?: { value: string; label: string }[]
        divisions?: DashboardDivision[]
    }>(),
    { periods: null, query: () => ({}), statusOptions: () => [], divisions: () => [] },
)

const page = usePage()
const user = useAuth(page.props)
const canManagePeriods = computed(() => user.value?.can_manage_recruitment_periods === true)
const canScheduleInterviews = computed(() => user.value?.can_schedule_recruitment_interviews === true)
const canViewQueue = computed(() => user.value?.can_view_recruitment_queue === true)
const canScanAttendance = computed(() => user.value?.can_scan_recruitment_attendance === true)

const actionQueues = computed(() => props.summary.action_queues ?? [])
const todaySessions = computed(() => props.summary.today_sessions ?? [])

const periodRows = computed<PeriodRow[]>(() => props.periods?.data ?? [])
const periodCurrentPage = computed<number>(() => props.periods?.current_page ?? 1)
const periodLastPage = computed<number>(() => props.periods?.last_page ?? 1)
const periodTotal = computed<number>(() => props.periods?.total ?? 0)

const divisionRows = computed<DashboardDivision[]>(() => props.divisions ?? [])
const divisionDrawerOpen = ref<boolean>(false)

function openDivisionDrawer(): void {
    divisionDrawerOpen.value = true
}

function closeDivisionDrawer(): void {
    divisionDrawerOpen.value = false
}

/** Selaras dengan pemetaan di Periods/Show.vue — token design system, bukan warna arbitrary. */
const periodStatusClasses: Record<string, string> = {
    draft: 'border-border bg-secondary text-secondary-foreground',
    open: 'border-success/20 bg-success/10 text-success',
    closed: 'border-warning/25 bg-warning/10 text-warning-foreground',
    archived: 'border-border bg-muted text-muted-foreground',
}

function periodStatusClass(status: string): string {
    return periodStatusClasses[status] ?? 'border-border bg-secondary text-secondary-foreground'
}

const periodSearch = ref<string>(props.query?.search ?? '')
const periodStatus = ref<string>(props.query?.status ?? '')

/** Opsi dropdown status — nilai dari backend, UI SimpleSelect seperti admin/events. */
const periodStatusOptions = computed<SimpleSelectOption[]>(() => [
    { value: '', label: 'Semua status' },
    ...props.statusOptions,
])

function applyPeriodFilters(page: number = 1): void {
    router.get(
        routes.admin.recruitment.index,
        {
            search: periodSearch.value || undefined,
            status: periodStatus.value || undefined,
            page: page > 1 ? page : undefined,
        },
        { preserveState: true, replace: true },
    )
}

watch([periodSearch, periodStatus], () => applyPeriodFilters())

const quickApplicantHref = computed(() =>
    props.summary.active_period
        ? routes.admin.recruitment.periods.show(props.summary.active_period.id)
        : routes.admin.recruitment.index,
)

const quickInterviewHref = computed(() =>
    props.summary.active_period
        ? `${routes.admin.recruitment.periods.show(props.summary.active_period.id)}?tab=interview`
        : routes.admin.recruitment.index,
)

function applicationsQueueUrl(queue: string): string {
    const periodId = props.summary.active_period?.id
    if (!periodId) return routes.admin.recruitment.index
    const params = new URLSearchParams({ queue })
    return `${routes.admin.recruitment.periods.show(periodId)}?${params.toString()}`
}

onMounted(() => {
    setTopbar({ title: 'Rekrutmen', subtitle: 'OpenRecruitment DOSCOM' })
})
</script>

<template>
    <Head title="Rekrutmen" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <!-- Perlu tindakan (di atas: prioritas utama halaman) -->
        <section v-if="canManagePeriods && summary.active_period" aria-label="Perlu tindakan">
            <h2 class="mb-3 text-sm font-semibold tracking-wide uppercase text-muted-foreground">
                Perlu tindakan
            </h2>
            <div v-if="actionQueues.length > 0" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
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
                            {{ queue.count ?? 0 }}
                        </Badge>
                    </div>
                </Link>
            </div>
        </section>

        <!-- Daftar periode (kartu per-periode) -->
        <section v-if="canManagePeriods || periodRows.length > 0" aria-label="Daftar periode">
            <h2 class="mb-3 text-sm font-semibold tracking-wide uppercase text-muted-foreground">
                Daftar periode
            </h2>

            <div class="mb-4 flex flex-wrap items-center gap-3">
                <Input
                    v-model="periodSearch"
                    placeholder="Cari nama periode..."
                    class="min-w-0 flex-1 sm:max-w-xs sm:flex-none"
                />
                <SimpleSelect
                    v-model="periodStatus"
                    :options="periodStatusOptions"
                    id="filter-status"
                    class="border-border/80 bg-background/80 h-10 w-full text-xs sm:w-44 sm:text-sm"
                    aria-label="Filter status periode"
                />
                <Button variant="outline" size="sm" class="sm:ml-auto" @click="openDivisionDrawer">
                    <Layers class="mr-2 size-4" />
                    Divisi
                </Button>
                <Button v-if="canManagePeriods" as-child size="sm" class="shadow-sm">
                    <Link :href="routes.admin.recruitment.periods.create">
                        <CalendarRange class="mr-2 size-4" />
                        Periode baru
                    </Link>
                </Button>
            </div>

            <div v-if="periodRows.length > 0" class="grid items-stretch gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Card
                    v-for="period in periodRows"
                    :key="period.id"
                    class="flex flex-col rounded-2xl border-border/70 shadow-xs transition-colors hover:border-primary/40"
                >
                    <CardContent class="flex flex-1 flex-col gap-4 px-5 pt-4 pb-3">
                        <div class="flex items-start justify-between gap-x-3 gap-y-2">
                            <p class="min-w-0 flex-1 text-base leading-snug font-semibold tracking-tight break-words text-pretty line-clamp-2">
                                {{ period.name }}
                            </p>
                            <Badge :class="cn('shrink-0 border', periodStatusClass(period.status))">
                                {{ period.status_label }}
                            </Badge>
                        </div>
                        <div class="text-muted-foreground space-y-2 text-sm leading-relaxed">
                            <p class="flex items-center gap-2.5">
                                <CalendarRange class="size-4 shrink-0 opacity-70" aria-hidden="true" />
                                <span v-if="period.registration_opens_at" class="tabular-nums">
                                    {{ period.registration_opens_at?.slice(0, 10) }}
                                    —
                                    {{ period.registration_closes_at?.slice(0, 10) ?? '…' }}
                                </span>
                                <span v-else>Jadwal pendaftaran belum ditentukan</span>
                            </p>
                            <p class="flex items-center gap-2.5">
                                <Users class="size-4 shrink-0 opacity-70" aria-hidden="true" />
                                <span class="tabular-nums">
                                    {{ period.applications_count }} applicant
                                </span>
                            </p>
                        </div>
                        <div class="mt-auto flex flex-wrap items-center gap-2 border-t border-border/60 pt-2.5">
                            <div
                                v-if="period.creator"
                                class="mr-auto flex min-w-0 items-center gap-2"
                            >
                                <img
                                    v-if="period.creator.avatar_url"
                                    :src="period.creator.avatar_url"
                                    :alt="period.creator.name"
                                    loading="lazy"
                                    class="size-6 shrink-0 rounded-full border border-border/60 object-cover"
                                />
                                <span
                                    v-else
                                    class="bg-muted text-muted-foreground flex size-6 shrink-0 items-center justify-center rounded-full"
                                    aria-hidden="true"
                                >
                                    <User class="size-3.5" />
                                </span>
                                <span class="text-muted-foreground min-w-0 max-w-36 truncate text-xs">
                                    {{ period.creator.name }}
                                </span>
                            </div>
                            <div class="ml-auto flex flex-wrap items-center gap-2">
                                <Button as-child variant="outline" size="sm">
                                    <Link :href="routes.admin.recruitment.periods.show(period.id)">
                                        Detail
                                    </Link>
                                </Button>
                                <Button v-if="canManagePeriods || period.can_edit" as-child variant="ghost" size="sm">
                                    <Link :href="routes.admin.recruitment.periods.edit(period.id)">
                                        Edit
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
            <Card v-else class="rounded-2xl border-dashed border-border/70">
                <CardContent class="px-4 py-10 text-center">
                    <p class="text-muted-foreground text-sm">Belum ada periode recruitment.</p>
                </CardContent>
            </Card>

            <div v-if="periodLastPage > 1" class="mt-4 flex justify-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="periodCurrentPage <= 1"
                    @click="applyPeriodFilters(periodCurrentPage - 1)"
                >
                    Sebelumnya
                </Button>
                <span class="text-muted-foreground self-center text-xs tabular-nums">
                    {{ periodCurrentPage }} / {{ periodLastPage }} · {{ periodTotal }} periode
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="periodCurrentPage >= periodLastPage"
                    @click="applyPeriodFilters(periodCurrentPage + 1)"
                >
                    Berikutnya
                </Button>
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
            v-if="actionQueues.length === 0 && (canManagePeriods || canScheduleInterviews)"
            class="grid gap-3 sm:grid-cols-2"
        >
            <Card v-if="canManagePeriods" class="rounded-2xl border-dashed border-border/70">
                <CardContent class="flex items-center justify-between gap-4 p-5">
                    <div class="flex items-center gap-3">
                        <Users class="size-5 text-muted-foreground" />
                        <div>
                            <p class="font-medium">Semua applicant</p>
                            <p class="text-muted-foreground text-sm">Tidak ada antrean tindakan saat ini.</p>
                        </div>
                    </div>
                    <Button as-child variant="outline" size="sm">
                        <Link :href="quickApplicantHref">Buka</Link>
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
                        <Link :href="quickInterviewHref">Buka</Link>
                    </Button>
                </CardContent>
            </Card>
        </div>
        <DivisionListSheet
            :open="divisionDrawerOpen"
            :divisions="divisionRows"
            @close="closeDivisionDrawer"
        />
    </div>
</template>
