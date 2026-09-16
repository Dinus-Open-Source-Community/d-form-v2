export type PeriodStatusValue = 'draft' | 'open' | 'closed' | 'archived'

export type PeriodPhase =
    | 'draft'
    | 'not_open'
    | 'registration'
    | 'awaiting_interview'
    | 'interview'
    | 'finalization'
    | 'overdue'
    | 'closed'
    | 'archived'

export interface PeriodPhaseInput {
    status: PeriodStatusValue
    registrationOpensAt: string | null
    registrationClosesAt: string | null
    interviewStartsAt: string | null
    interviewEndsAt: string | null
    finalizationDeadlineAt: string | null
}

const DAY_MS = 86_400_000

const DATE_ONLY_PATTERN = /^\d{4}-\d{2}-\d{2}$/

/**
 * Satu-satunya sumber parsing tanggal periode.
 * Nilai date-only (`YYYY-MM-DD`) diperlakukan sebagai akhir hari waktu lokal
 * (23:59:59.999), bukan tengah malam UTC.
 */
export function parsePeriodDate(value: string | null): Date | null {
    if (!value) return null
    if (DATE_ONLY_PATTERN.test(value)) {
        const [year, month, day] = value.split('-').map(Number)
        const parsed = new Date(year, month - 1, day, 23, 59, 59, 999)
        const valid =
            parsed.getFullYear() === year &&
            parsed.getMonth() === month - 1 &&
            parsed.getDate() === day
        return valid ? parsed : null
    }
    const parsed = new Date(value)
    return Number.isNaN(parsed.getTime()) ? null : parsed
}

const PHASE_LABELS: Record<PeriodPhase, string> = {
    draft: 'Draf — belum dibuka',
    not_open: 'Pendaftaran segera dibuka',
    registration: 'Pendaftaran dibuka',
    awaiting_interview: 'Menunggu jadwal interview',
    interview: 'Interview berlangsung',
    finalization: 'Tahap finalisasi',
    overdue: 'Melewati target finalisasi',
    closed: 'Pendaftaran ditutup',
    archived: 'Periode diarsipkan',
}

const STATUS_LABELS: Record<PeriodStatusValue, string> = {
    draft: 'Draf',
    open: 'Dibuka',
    closed: 'Ditutup',
    archived: 'Diarsipkan',
}

export function phaseLabel(phase: PeriodPhase): string {
    return PHASE_LABELS[phase]
}

export function statusLabel(status: PeriodStatusValue): string {
    return STATUS_LABELS[status]
}

export function resolvePeriodPhase(input: PeriodPhaseInput, now: Date = new Date()): PeriodPhase {
    if (input.status === 'archived') return 'archived'
    if (input.status === 'draft') return 'draft'
    if (input.status === 'closed') return 'closed'

    const opens = parsePeriodDate(input.registrationOpensAt)
    const closes = parsePeriodDate(input.registrationClosesAt)
    const interviewStarts = parsePeriodDate(input.interviewStartsAt)
    const interviewEnds = parsePeriodDate(input.interviewEndsAt)
    const deadline = parsePeriodDate(input.finalizationDeadlineAt)

    if (opens && now < opens) return 'not_open'
    if (!closes || now <= closes) return 'registration'

    if (interviewStarts && now < interviewStarts) return 'awaiting_interview'
    if (interviewEnds && now <= interviewEnds) return 'interview'
    if (interviewStarts && !interviewEnds) {
        if (deadline && now > deadline) return 'overdue'
        return 'interview'
    }
    if (!interviewStarts && !interviewEnds && (!deadline || now <= deadline)) return 'awaiting_interview'
    if (deadline && now <= deadline) return 'finalization'

    return 'overdue'
}

export function phaseDeadline(phase: PeriodPhase, input: PeriodPhaseInput): Date | null {
    switch (phase) {
        case 'not_open':
            return parsePeriodDate(input.registrationOpensAt)
        case 'registration':
            return parsePeriodDate(input.registrationClosesAt)
        case 'awaiting_interview':
            return parsePeriodDate(input.interviewStartsAt)
        case 'interview':
            return parsePeriodDate(input.interviewEndsAt)
        case 'finalization':
            return parsePeriodDate(input.finalizationDeadlineAt)
        default:
            return null
    }
}

const COUNTDOWN_ACTIONS: Partial<Record<PeriodPhase, string>> = {
    not_open: 'dibuka',
    registration: 'ditutup',
    awaiting_interview: 'interview mulai',
    interview: 'interview berakhir',
    finalization: 'tenggat finalisasi',
}

export function phaseCountdownLabel(phase: PeriodPhase, days: number | null): string | null {
    if (days === null) return null
    const action = COUNTDOWN_ACTIONS[phase]
    if (!action) return null
    return days === 0 ? `${action} hari ini` : `${action} dalam ${days} hari`
}

export function daysRemaining(target: Date | null, now: Date = new Date()): number | null {
    if (!target) return null
    const diff = target.getTime() - now.getTime()
    if (diff < DAY_MS) return 0
    return Math.floor(diff / DAY_MS)
}
