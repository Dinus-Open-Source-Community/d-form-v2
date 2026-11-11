/** Nama field submission → label tampil; fallback buang prefix `field_` dan ubah `_` jadi spasi. */
export function humanizeSubmissionKey(fieldLabelMap: Record<string, string>, value: string): string {
    return fieldLabelMap[value] || value.replace(/^field_/, '').replace(/_/g, ' ');
}

import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import {
    FORM_ANSWER_REVIEW_STATUS_BADGES,
    MEMBER_CONFIRMATION_STATUS_BADGES,
    BUNDLE_GROUP_REVIEW_STATUS_BADGES,
    registrationRoleLabel as registrationRoleLabelFromMap,
} from '@/lib/registrationStatusUi';
import type {
    TBundleGroupReviewStatus,
    TFormAnswerReviewStatus,
    TMemberConfirmationStatus,
    TRegistrationRole,
} from '@/types/registrationStatus';

/** Ringkas jawaban form jadi satu baris teks; array digabung koma, objek → placeholder. */
export function answerPreview(value: unknown): string {
    if (Array.isArray(value)) return value.map(String).join(', ');
    if (typeof value === 'string') return value;
    if (value === null || value === undefined) return '—';
    if (typeof value === 'number' || typeof value === 'boolean') return String(value);
    return 'Jawaban terstruktur';
}

/** URL publik file jawaban dari path storage; `null` bila bukan string atau kosong. */
export function submissionFileUrl(value: unknown): string | null {
    if (typeof value !== 'string') {
        return null;
    }
    const t = value.trim();
    if (!t) {
        return null;
    }
    return normalizePublicStorageUrl(t);
}

/** True when admin must not accept until the member confirms (team / bundle). */
export function submissionAdminAcceptBlocked(sub: {
    registration_role?: string | null;
    member_confirmation_status?: string | null;
}): boolean {
    return sub.registration_role === 'member' && sub.member_confirmation_status !== 'accepted';
}

/** Matches {@see App\Enums\FormAnswerReviewStatus} — pending answers can be reviewed. */
export function formSubmissionReviewIsPending(submission: {
    review_status?: 'pending' | 'accepted' | 'rejected' | null;
}): boolean {
    return submission.review_status == null || submission.review_status === 'pending';
}

/** Label + kelas untuk badge status review (Bahasa Indonesia). */
export function submissionReviewBadge(status: TFormAnswerReviewStatus | null | undefined): {
    label: string;
    class: string;
} {
    return FORM_ANSWER_REVIEW_STATUS_BADGES[status ?? 'pending'];
}

/** Label untuk status konfirmasi anggota (Bahasa Indonesia). */
export function memberConfirmationStatusLabel(status: TMemberConfirmationStatus | null | undefined): string {
    return status === null || status === undefined ? '—' : MEMBER_CONFIRMATION_STATUS_BADGES[status].label;
}

/** Badge classes untuk status konfirmasi anggota. */
export function memberConfirmationStatusBadge(status: TMemberConfirmationStatus | null | undefined): {
    label: string;
    class: string;
} {
    return MEMBER_CONFIRMATION_STATUS_BADGES[status ?? 'pending'];
}

/** Label untuk status review group. */
export function groupReviewStatusLabel(status: TBundleGroupReviewStatus): string {
    return BUNDLE_GROUP_REVIEW_STATUS_BADGES[status].label;
}

/** Badge classes untuk status review group. */
export function groupReviewStatusBadge(status: TBundleGroupReviewStatus): {
    label: string;
    class: string;
} {
    return BUNDLE_GROUP_REVIEW_STATUS_BADGES[status];
}

/** Label untuk role registrasi. */
export function registrationRoleLabel(role: TRegistrationRole | null | undefined): string {
    return registrationRoleLabelFromMap(role);
}
