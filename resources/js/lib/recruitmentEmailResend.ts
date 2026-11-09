import { formatIdDateTimeLabel } from '@/lib/shadcnDateFormat'
import type {
    IEmailResendGate,
    IEmailResendOption,
    TRecruitmentEmailResendType,
} from '@/types/recruitmentEmailResend'

export type {
    IEmailResendGate,
    IEmailResendOption,
    IEmailResendPrereq,
    IEmailResendTypeStatus,
    TRecruitmentEmailResendType,
} from '@/types/recruitmentEmailResend'

/** Batas kirim per 24 jam per applicant per jenis (verbatim throttle backend). */
export const RECRUITMENT_EMAIL_RESEND_DAILY_LIMIT = 3

/** Tujuh opsi resend (label + deskripsi ID). */
export const RECRUITMENT_EMAIL_RESEND_OPTIONS: IEmailResendOption[] = [
    { type: 'qr', label: 'QR Presensi', description: 'Kirim ulang email jadwal interview beserta lampiran QR presensi.' },
    { type: 'final', label: 'Pengumuman Hasil Akhir', description: 'Kirim ulang email pengumuman hasil akhir (diterima/ditolak).' },
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

/** Alasan tombol nonaktif (null bila boleh kirim). */
export function resolveEmailResendDisabled(gate: IEmailResendGate): string | null {
    if (!gate.canResend) return 'Anda tidak memiliki izin mengirim ulang email.'
    if (gate.type === 'correction' && gate.prereq?.has_correction !== true) {
        return 'Belum ada permintaan koreksi untuk applicant ini.'
    }
    if (gate.type === 'interviewer' && gate.prereq?.has_interview !== true) {
        return 'Belum ada jadwal interview untuk applicant ini.'
    }
    if (gate.type === 'qr' && gate.prereq?.has_interview !== true) {
        return 'Belum ada jadwal interview untuk applicant ini.'
    }
    if (gate.type === 'final' && gate.prereq?.has_final_decision !== true) {
        return 'Hasil akhir belum ditentukan (masih pending).'
    }
    if (gate.type === 'notification' && gate.prereq?.has_replayable_notification !== true) {
        return 'Belum ada notifikasi yang bisa dikirim ulang.'
    }
    if ((gate.status?.count_24h ?? 0) >= RECRUITMENT_EMAIL_RESEND_DAILY_LIMIT) {
        return `Batas ${RECRUITMENT_EMAIL_RESEND_DAILY_LIMIT}x kirim per 24 jam tercapai.`
    }
    return null
}
