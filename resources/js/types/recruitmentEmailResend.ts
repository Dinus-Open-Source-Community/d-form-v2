/**
 * Kontrak resend email recruitment yang dipakai lintas hook, adapter, dan UI.
 * Runtime value (opsi, batas harian) tetap berada di `lib/recruitmentEmailResend.ts`.
 */

/** Jenis resend email applicant (verbatim kontrak 5a). */
export type TRecruitmentEmailResendType =
    'qr' | 'final' | 'tracking' | 'confirmation' | 'correction' | 'interviewer' | 'notification';

/** Status kirim-ulang per jenis (attempt, bukan delivery). */
export interface IEmailResendTypeStatus {
    last_attempt_at: string | null;
    count_24h: number;
}

/** Prasyarat resend (cermin aturan backend 5a). */
export interface IEmailResendPrereq {
    has_correction: boolean;
    has_interview: boolean;
    has_replayable_notification: boolean;
    has_final_decision?: boolean;
}

/** Meta tampilan satu opsi resend. */
export interface IEmailResendOption {
    type: TRecruitmentEmailResendType;
    label: string;
    description: string;
}

/** Masukan gerbang tombol resend (izin + status + prasyarat). */
export interface IEmailResendGate {
    type: TRecruitmentEmailResendType;
    status: IEmailResendTypeStatus | null;
    prereq: IEmailResendPrereq | null;
    canResend: boolean;
}
