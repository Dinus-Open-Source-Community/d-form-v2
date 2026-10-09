<script setup lang="ts">
import { computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Link } from '@inertiajs/vue3'
import { routes } from '@/lib/routes'
import { ChevronRight, ClipboardCheck, ThumbsUp, ThumbsDown } from 'lucide-vue-next'
import InterviewApplicantCard, {
    type ApplicantCardMetaItem,
    type ApplicantCardStatusVariant,
} from '@/components/modules/dashboard/recruitment/InterviewApplicantCard.vue'

export interface InterviewRow {
    interview_id: string
    scheduled_at: string | null
    status_label: string
    interview_kind: string
    location: string
    room: string
    needs_evaluation: boolean
    has_evaluation: boolean
    evaluation_locked: boolean
    evaluation_recommendation: 'recommended' | 'not_recommended' | null
    has_attendance?: boolean
    application: {
        id: string
        full_name: string
        nim?: string | null
        registration_number: string
        primary_division: string | null
        secondary_division?: string | null
    } | null
    session: {
        id: string
        session_date: string
        division: string | null
        period: string | null
    } | null
}

const props = withDefaults(
    defineProps<{
        row: InterviewRow
        /** Sembunyikan seluruh aksi (tab Semua Peserta = display-only). */
        hideAction?: boolean
    }>(),
    { hideAction: false },
)

function showUrl(interviewId: string): string {
    return routes.admin.recruitment.myInterviews.show(interviewId)
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

function statusLabel(row: InterviewRow): string {
    if (row.needs_evaluation) return 'Perlu dinilai'
    if (row.evaluation_locked) return 'Terkunci'
    if (row.has_evaluation) return 'Sudah dinilai'
    return row.status_label
}

function pillVariant(row: InterviewRow): ApplicantCardStatusVariant {
    if (row.needs_evaluation) return 'info'
    if (row.evaluation_locked) return 'locked'
    if (row.has_evaluation) return 'success'
    return 'muted'
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

const box1 = computed<[ApplicantCardMetaItem, ApplicantCardMetaItem]>(() => [
    { icon: 'id', label: 'No. Registrasi', value: props.row.application?.registration_number ?? '—' },
    { icon: 'cap', label: 'NIM', value: props.row.application?.nim ?? '—' },
])

const box2 = computed<[ApplicantCardMetaItem, ApplicantCardMetaItem]>(() => {
    const place: string = [props.row.location, props.row.room]
        .filter((part) => part !== '')
        .join(' · ')
    return [
        { icon: 'none', label: 'Jadwal', value: formatSchedule(props.row.scheduled_at) },
        { icon: 'pin', label: 'Lokasi', value: place === '' ? '—' : place },
    ]
})

const box3 = computed<[ApplicantCardMetaItem, ApplicantCardMetaItem]>(() => {
    const isSecondary: boolean = props.row.interview_kind === 'secondary'
    const divisionName: string = isSecondary
        ? (props.row.application?.secondary_division ?? '—')
        : (props.row.application?.primary_division ?? '—')
    return [
        { icon: 'none', label: 'Periode', value: props.row.session?.period ?? '—' },
        { icon: 'none', label: 'Divisi', value: divisionName },
    ]
})
</script>

<template>
    <InterviewApplicantCard
        :name="row.application?.full_name ?? 'Applicant'"
        :division="null"
        :status-label="statusLabel(row)"
        :status-variant="pillVariant(row)"
        :flag-label="isLockedByAttendance(row) ? 'Belum regis ulang' : null"
        :box1="box1"
        :box2="box2"
        :box3="box3"
        :dimmed="isLockedByAttendance(row)"
    >
        <template #name>
            <div class="flex flex-wrap items-center gap-2">
                <Link
                    v-if="row.application && !isLockedByAttendance(row) && !hideAction"
                    :href="showUrl(row.interview_id)"
                    class="rounded focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                >
                    {{ row.application.full_name }}
                </Link>
                <span
                    v-else-if="row.application"
                    class="text-muted-foreground"
                    aria-label="Nama applicant terkunci — belum regis ulang"
                >
                    {{ row.application.full_name }}
                </span>
                <span v-else>—</span>

                <span
                    v-if="row.evaluation_recommendation === 'recommended'"
                    class="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-2 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                >
                    <ThumbsUp class="size-3 shrink-0" aria-hidden="true" />
                    Direkomendasikan
                </span>
                <span
                    v-else-if="row.evaluation_recommendation === 'not_recommended'"
                    class="inline-flex items-center gap-1 rounded-full bg-rose-500/10 px-2 py-0.5 text-xs font-medium text-rose-600 dark:text-rose-400"
                >
                    <ThumbsDown class="size-3 shrink-0" aria-hidden="true" />
                    Tidak direkomendasikan
                </span>
            </div>
        </template>
        <template v-if="!hideAction" #action>
            <Button
                v-if="row.application && isLockedByAttendance(row)"
                class="h-[52px] w-full rounded-lg px-4 text-sm font-semibold transition-transform active:scale-[0.96]"
                disabled
                aria-disabled="true"
                :aria-label="attendanceLockedLabel(row)"
            >
                <ClipboardCheck class="size-5 shrink-0" aria-hidden="true" />
                <span class="min-w-0 flex-1 truncate text-left">{{ actionLabel(row) }}</span>
                <ChevronRight class="size-5 shrink-0" aria-hidden="true" />
            </Button>
            <Button
                v-else-if="row.application"
                as-child
                class="h-[52px] w-full rounded-lg px-4 text-sm font-semibold transition-transform active:scale-[0.96]"
            >
                <Link :href="showUrl(row.interview_id)">
                    <ClipboardCheck class="size-5 shrink-0" aria-hidden="true" />
                    <span class="min-w-0 flex-1 truncate text-left">{{ actionLabel(row) }}</span>
                    <ChevronRight class="size-5 shrink-0" aria-hidden="true" />
                </Link>
            </Button>
        </template>
    </InterviewApplicantCard>
</template>
