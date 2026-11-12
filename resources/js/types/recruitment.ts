/**
 * Kontrak response HTTP yang dipakai lintas adapter dan UI.
 * Runtime value tidak boleh berada di folder ini.
 */

import type {
    IEmailResendPrereq,
    IEmailResendTypeStatus,
    TRecruitmentEmailResendType,
} from '@/types/recruitmentEmailResend';

/** Satu baris riwayat screening pada detail applicant. */
export interface IScreeningRow {
    id: string;
    decision: string;
    decision_label: string;
    reason: string | null;
    reason_label: string | null;
    notes: string | null;
    acted_at: string | null;
    actor: { id: string; name: string } | null;
}

/** Satu baris activity log pada detail applicant. */
export interface IApplicationActivityRow {
    id: string;
    action: string;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    created_at: string | null;
    actor: { id: string; name: string } | null;
}

/** Satu permintaan koreksi pada detail applicant. */
export interface ICorrectionRow {
    id: string;
    status: string;
    status_label: string;
    request_message: string;
    review_notes: string | null;
    reviewed_at: string | null;
    completed_at: string | null;
    reviewer: { id: string; name: string } | null;
}

/** Hasil evaluasi interview pada detail applicant. */
export interface IEvaluationDetail {
    speaking_score: number;
    technical_score: number;
    attitude_score: number;
    recommendation: string;
    recommendation_label: string;
    notes: string | null;
    is_locked: boolean;
    evaluated_at: string | null;
    evaluator: { id: string; name: string } | null;
    division?: string | null;
    division_id?: string | null;
    save_count?: number;
    saves_remaining?: number;
}

/** Keputusan akhir pada detail applicant. */
export interface IFinalDecisionDetail {
    membership_type: string | null;
    membership_type_label: string | null;
    final_division: { id: string; name: string; code: string } | null;
    internal_reason: string | null;
    public_message: string | null;
    decided_at: string | null;
    decider: { id: string; name: string } | null;
}

/** Payload detail applicant dari endpoint recruitment period. */
export interface IApplicationDetail {
    id: string;
    registration_number: string;
    full_name: string;
    nim: string;
    semester: number;
    phone: string;
    personal_email: string;
    student_email: string;
    instagram_username: string;
    stage: string;
    stage_label: string;
    result: string;
    result_label: string;
    is_verified: boolean;
    revision_required: boolean;
    submitted_at: string | null;
    period: { id: string; name: string } | null;
    primary_division: { id: string; name: string; code: string } | null;
    secondary_division: { id: string; name: string; code: string } | null;
    document: {
        cv_original_name: string;
        cv_mime: string;
        cv_size_bytes: number;
        portfolio_type: string;
        portfolio_url: string | null;
        portfolio_original_name: string | null;
        portfolio_mime: string | null;
        portfolio_size_bytes: number | null;
        instagram_follow_original_name: string | null;
        instagram_follow_mime: string | null;
        instagram_follow_size_bytes: number | null;
        twibbon_url: string | null;
        has_cv_file: boolean;
        has_portfolio_file: boolean;
        has_instagram_follow_file: boolean;
    } | null;
    screenings: IScreeningRow[];
    activity_logs: IApplicationActivityRow[];
    correction_requests: ICorrectionRow[];
    evaluation: IEvaluationDetail | null;
    evaluations: {
        primary: IEvaluationDetail | null;
        secondary: IEvaluationDetail | null;
    };
    final_decision: IFinalDecisionDetail | null;
    can_screen: boolean;
    can_verify: boolean;
    can_decide_final: boolean;
    can_resend_tracking: boolean;
    email_resend_status?: Partial<Record<TRecruitmentEmailResendType, IEmailResendTypeStatus>> | null;
    email_resend_prereq?: IEmailResendPrereq | null;
}
