import { formatIdDateTimeLabel } from '@/lib/shadcnDateFormat'

/** Jenis resend email applicant (verbatim kontrak 5a). */
export type TRecruitmentEmailResendType =
    | 'tracking'
    | 'confirmation'
    | 'correction'
    | 'interviewer'
    | 'notification'

/** Status kirim-ulang per jenis (attempt, bukan delivery). */
export interface IEmailResendTypeStatus {
    last_attempt_at: string | null
    count_24h: number
}

/** Prasyarat resend (cermin aturan backend 5a). */
export interface IEmailResendPrereq {
    has_correction: boolean
    has_interview: boolean
    has_replayable_notification: boolean
}

/** Meta tampilan satu opsi resend. */
export interface IEmailResendOption {
    type: TRecruitmentEmailResendType
    label: string
    description: string
}

/** Batas kirim per 24 jam per applicant per jenis (verbatim throttle backend). */
export const RECRUITMENT_EMAIL_RESEND_DAILY_LIMIT = 3

/** Lima opsi resend (label + deskripsi ID). */
export const RECRUITMENT_EMAIL_RESEND_OPTIONS: IEmailResendOption[] = [
    { type: 'tracking', label: 'Info tracking', description: 'Email token pelacakan pendaftaran (token lama dirotasi).' },
    { type: 'confirmation', label: 'Konfirmasi pendaftaran', description: 'Email konfirmasi pendaftaran awal.' },
    { type: 'correction', label: 'Permintaan koreksi', description: 'Teruskan email permintaan koreksi terakhir.' },
    { type: 'interviewer', label: 'Notifikasi interviewer', description: 'Teruskan penugasan interview terakhir ke interviewer.' },
    { type: 'notification', label: 'Notifikasi terakhir', description: 'Kirim ulang notifikasi terakhir yang pernah terkirim.' },
]

/** Label opsi resend dari typenya. */
export function emailResendLabelOf(type: TRecruitmentEmailResendType): string {
    return RECRUITMENT_EMAIL_RESEND_OPTIONS.find((option) => option.type === type)?.label ?? type
}

/** Label "Terakhir dicoba" dari ISO attempt (null berarti belum pernah). */
export function formatEmailResendAttempt(lastAttemptAt: string | null): string {
    if (!lastAttemptAt) return 'Belum pernah'
    return formatIdDateTimeLabel(lastAttemptAt)
}

/** Masukan gerbang tombol resend (izin + status + prasyarat). */
export interface IEmailResendGate {
    type: TRecruitmentEmailResendType
    status: IEmailResendTypeStatus | null
    prereq: IEmailResendPrereq | null
    canResend: boolean
}

/** Alasan tombol nonaktif (null bila boleh kirim). */
export function resolveEmailResendDisabled(gate: IEmailResendGate): string | null {
    if (!gate.canResend) return 'Anda tidak memiliki izin mengirim ulang email.'
    if (gate.type === 'correction' && gate.prereq?.has_correction !== true) {
        return 'Belum ada permintaan koreksi untuk applicant ini.'
    }
    if (gate.type === 'interviewer' && gate.prereq?.has_interview !== true) {
        return 'Belum ada jadwal interview untuk applicant ini.'
    }
    if (gate.type === 'notification' && gate.prereq?.has_replayable_notification !== true) {
        return 'Belum ada notifikasi yang bisa dikirim ulang.'
    }
    if ((gate.status?.count_24h ?? 0) >= RECRUITMENT_EMAIL_RESEND_DAILY_LIMIT) {
        return `Batas ${RECRUITMENT_EMAIL_RESEND_DAILY_LIMIT}x kirim per 24 jam tercapai.`
    }
    return null
}
