<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import { ArrowRight, ClipboardCheck, ListOrdered } from 'lucide-vue-next'

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

const QUEUE_TABS = [
    { key: '', label: 'Semua' },
    { key: 'pending', label: 'Perlu dinilai' },
    { key: 'today', label: 'Hari ini' },
    { key: 'done', label: 'Selesai' },
] as const

const props = defineProps<{
    interviews: {
        data: InterviewRow[]
        current_page: number
        last_page: number
        total: number
    }
    query: { queue?: string }
    queue_counts: Record<string, number>
    today_sessions: TodaySession[]
    next_action: NextAction | null
}>()

const queue = ref(props.query.queue ?? '')

const activeQueueLabel = computed(
    () => QUEUE_TABS.find((tab) => tab.key === queue.value)?.label ?? 'Semua',
)

onMounted(() => {
    setTopbar({ title: 'Interview Saya', subtitle: 'Penugasan & penilaian OpRec' })
})

function formatSchedule(iso: string | null) {
    if (!iso) return 'Jadwal belum ditetapkan'
    return new Date(iso).toLocaleString('id-ID', {
        weekday: 'short',
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    })
}

function queueBadgeCount(key: string): number | null {
    if (key === '') return props.queue_counts.all ?? null
    const count = props.queue_counts[key]
    return count !== undefined ? count : null
}

function applyFilters(page = 1) {
    router.get(
        routes.admin.recruitment.myInterviews.index,
        {
            queue: queue.value || undefined,
            page: page > 1 ? page : undefined,
        },
        { preserveState: true, replace: true },
    )
}

function selectQueue(key: string) {
    queue.value = key
    applyFilters()
}
</script>

<template>
    <Head title="Interview Saya" />

    <div class="mx-auto flex max-w-4xl flex-col gap-6">
        <PageHeader
            title="Pusat interview"
            :subtitle="`${queue_counts.pending ?? 0} perlu dinilai · ${activeQueueLabel}`"
        />

        <Card
            v-if="next_action && (query.queue === '' || query.queue === 'pending')"
            class="rounded-2xl border-primary/30 bg-primary/5"
        >
            <CardContent class="flex flex-wrap items-center justify-between gap-4 p-5">
                <div>
                    <p class="font-semibold">{{ next_action.title }}</p>
                    <p class="mt-1 text-lg font-medium">{{ next_action.full_name }}</p>
                    <p class="text-muted-foreground font-mono text-sm">
                        {{ next_action.registration_number }}
                    </p>
                    <p class="text-muted-foreground mt-1 text-sm">{{ next_action.description }}</p>
                </div>
                <Button as-child>
                    <Link :href="routes.admin.recruitment.myInterviews.show(next_action.application_id)">
                        Nilai sekarang
                        <ArrowRight class="ml-2 size-4" />
                    </Link>
                </Button>
            </CardContent>
        </Card>

        <section v-if="today_sessions.length > 0">
            <h2 class="text-muted-foreground mb-3 text-xs font-semibold tracking-wide uppercase">
                Sesi hari ini
            </h2>
            <div class="grid gap-3">
                <Card
                    v-for="session in today_sessions"
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
                                · {{ session.my_interviews_count }} assignment kamu
                            </p>
                        </div>
                        <Button as-child variant="outline" size="sm">
                            <Link :href="routes.admin.recruitment.myInterviews.queue(session.id)">
                                <ListOrdered class="mr-2 size-4" />
                                Lihat antrean
                            </Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </section>

        <div class="flex flex-wrap gap-2">
            <button
                v-for="tab in QUEUE_TABS"
                :key="tab.key || 'all'"
                type="button"
                class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm transition-colors"
                :class="
                    queue === tab.key
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
                    :class="queue === tab.key ? 'bg-primary-foreground/20 text-primary-foreground' : ''"
                >
                    {{ queueBadgeCount(tab.key) }}
                </Badge>
            </button>
        </div>

        <div class="grid gap-3">
            <Card
                v-for="row in interviews.data"
                :key="row.interview_id"
                class="rounded-2xl border-border/70"
            >
                <CardContent class="flex flex-wrap items-start justify-between gap-4 p-5">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-medium">{{ row.application?.full_name ?? '—' }}</p>
                            <Badge v-if="row.needs_evaluation" variant="default">Perlu dinilai</Badge>
                            <Badge v-else-if="row.has_evaluation && !row.evaluation_locked" variant="outline">
                                Sudah dinilai
                            </Badge>
                            <Badge v-else-if="row.evaluation_locked" variant="secondary">Terkunci</Badge>
                            <Badge v-else variant="outline">{{ row.status_label }}</Badge>
                        </div>
                        <p class="text-muted-foreground mt-1 font-mono text-xs">
                            {{ row.application?.registration_number }}
                            · {{ row.application?.primary_division ?? '—' }}
                        </p>
                        <p class="text-muted-foreground mt-2 text-sm">
                            {{ formatSchedule(row.scheduled_at) }}
                            · {{ row.location }} · {{ row.room }}
                        </p>
                        <p v-if="row.queue_number" class="text-muted-foreground mt-1 text-sm">
                            Antrean #{{ String(row.queue_number).padStart(2, '0') }}
                        </p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        <Button v-if="row.application" as-child size="sm">
                            <Link :href="routes.admin.recruitment.myInterviews.show(row.application.id)">
                                <ClipboardCheck class="mr-2 size-4" />
                                {{
                                    row.needs_evaluation
                                        ? 'Nilai'
                                        : row.has_evaluation && !row.evaluation_locked
                                          ? 'Ubah'
                                          : 'Detail'
                                }}
                            </Link>
                        </Button>
                        <Button v-if="row.session" as-child size="sm" variant="outline">
                            <Link :href="routes.admin.recruitment.myInterviews.queue(row.session.id)">
                                Antrean
                            </Link>
                        </Button>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="interviews.data.length === 0" class="rounded-2xl border-dashed border-border/70">
                <CardContent class="text-muted-foreground py-10 text-center text-sm">
                    Belum ada penugasan untuk filter ini.
                </CardContent>
            </Card>
        </div>

        <div v-if="interviews.last_page > 1" class="flex items-center justify-between text-sm">
            <p class="text-muted-foreground">{{ interviews.total }} assignment</p>
            <div class="flex gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="interviews.current_page <= 1"
                    @click="applyFilters(interviews.current_page - 1)"
                >
                    Sebelumnya
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    :disabled="interviews.current_page >= interviews.last_page"
                    @click="applyFilters(interviews.current_page + 1)"
                >
                    Berikutnya
                </Button>
            </div>
        </div>
    </div>
</template>
