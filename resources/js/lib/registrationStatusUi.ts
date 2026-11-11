import type {
    IStatusPresentation,
    TBundleGroupReviewStatus,
    TFormAnswerReviewStatus,
    TMemberConfirmationStatus,
    TRegistrantStatus,
    TRegistrationRole,
} from '@/types/registrationStatus';

/**
 * Single source for registration/review status copy and badge classes.
 * Every map is a `Record` over the full enum union, so a new backend value fails typecheck
 * instead of silently falling through a default branch.
 */

const NEUTRAL_BADGE_CLASS = 'border-muted-foreground/25 bg-muted/20 text-muted-foreground';

export const FORM_ANSWER_REVIEW_STATUS_BADGES: Record<TFormAnswerReviewStatus, IStatusPresentation> = {
    pending: { label: 'Menunggu review', class: 'border-warning/30 bg-warning/10 text-warning' },
    accepted: { label: 'Diterima', class: 'border-success/30 bg-success/10 text-success' },
    rejected: { label: 'Ditolak', class: 'border-destructive/25 bg-destructive/10 text-destructive' },
};

export const MEMBER_CONFIRMATION_STATUS_BADGES: Record<TMemberConfirmationStatus, IStatusPresentation> = {
    pending: { label: 'Menunggu', class: 'border-warning/30 bg-warning/10 text-warning' },
    accepted: { label: 'Diterima', class: 'border-success/30 bg-success/10 text-success' },
    rejected: { label: 'Ditolak', class: 'border-destructive/25 bg-destructive/10 text-destructive' },
    expired: { label: 'Kedaluwarsa', class: NEUTRAL_BADGE_CLASS },
};

export const BUNDLE_GROUP_REVIEW_STATUS_BADGES: Record<TBundleGroupReviewStatus, IStatusPresentation> = {
    pending: { label: 'Menunggu review', class: 'border-warning/30 bg-warning/10 text-warning' },
    partial: {
        label: 'Sebagian direview',
        class: 'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-400',
    },
    accepted: { label: 'Semua diterima', class: 'border-success/30 bg-success/10 text-success' },
    rejected: { label: 'Ditolak', class: 'border-destructive/25 bg-destructive/10 text-destructive' },
};

export const REGISTRANT_STATUS_BADGES: Record<TRegistrantStatus, IStatusPresentation> = {
    pending: { label: 'Menunggu', class: 'bg-warning/15 text-warning-foreground ring-warning/20' },
    accepted: { label: 'Disetujui', class: 'bg-success/10 text-success ring-success/15' },
    rejected: { label: 'Ditolak', class: 'bg-destructive/10 text-destructive ring-destructive/15' },
};

const REGISTRATION_ROLE_LABELS: Record<TRegistrationRole, string> = {
    leader: 'Ketua',
    member: 'Anggota',
};

/** Review badge; a missing status is treated as pending. */
export function formAnswerReviewStatusBadge(status: TFormAnswerReviewStatus | null | undefined): IStatusPresentation {
    return FORM_ANSWER_REVIEW_STATUS_BADGES[status ?? 'pending'];
}

/** Member confirmation badge; a missing status is treated as pending. */
export function memberConfirmationStatusBadge(
    status: TMemberConfirmationStatus | null | undefined
): IStatusPresentation {
    return MEMBER_CONFIRMATION_STATUS_BADGES[status ?? 'pending'];
}

/** Bundle group review badge. */
export function bundleGroupReviewStatusBadge(status: TBundleGroupReviewStatus): IStatusPresentation {
    return BUNDLE_GROUP_REVIEW_STATUS_BADGES[status];
}

/** Registrant list badge. */
export function registrantStatusBadge(status: TRegistrantStatus): IStatusPresentation {
    return REGISTRANT_STATUS_BADGES[status];
}

/** Registration role label; `—` when the role is absent. */
export function registrationRoleLabel(role: TRegistrationRole | null | undefined): string {
    return role === null || role === undefined ? '—' : REGISTRATION_ROLE_LABELS[role];
}
