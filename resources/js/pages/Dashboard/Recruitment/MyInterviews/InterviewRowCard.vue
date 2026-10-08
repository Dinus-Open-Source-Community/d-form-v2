<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Link } from '@inertiajs/vue3'
import { routes } from '@/lib/routes'
import { ClipboardCheck } from 'lucide-vue-next'

export interface InterviewRow {
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

interface StatusBadge {
    label: string
    variant: 'default' | 'secondary' | 'outline'
}

defineProps<{
    row: InterviewRow
}>()

function showUrl(interviewId: string): string {
    return routes.admin.recruitment.myInterviews.show(interviewId)
}

function sessionUrl(sessionIdValue: string): string {
    return routes.admin.recruitment.interviewSessions.show(sessionIdValue)
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
</script>

<template>
    <Card
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
                        v-if="row.session"
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
</template>
