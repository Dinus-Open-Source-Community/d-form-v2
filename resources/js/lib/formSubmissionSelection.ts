import type { TFormAnswerReviewStatus } from '@/types/registrationStatus';

export type TVisibleSubmissionSelectionState = boolean | 'indeterminate';

/** Bagian submission yang menentukan kelayakan batch review di server. */
export interface IBulkReviewableSubmissionState {
    review_status?: TFormAnswerReviewStatus | null;
    registration_role?: 'leader' | 'member' | null;
    member_confirmation_status?: string | null;
    group_token?: string | null;
}

/** Returns checked, unchecked, or mixed state for the currently visible submissions. */
export function getVisibleSubmissionSelectionState(
    visibleSubmissionIds: readonly string[],
    selectedSubmissionIds: ReadonlySet<string>
): TVisibleSubmissionSelectionState {
    const selectedCount = visibleSubmissionIds.filter((submissionId) => selectedSubmissionIds.has(submissionId)).length;

    if (selectedCount === 0) return false;
    return selectedCount === visibleSubmissionIds.length ? true : 'indeterminate';
}

/**
 * True bila server mengizinkan baris ini masuk batch bulk review: masih menunggu
 * review dan bukan bagian pendaftaran tim/bundle (yang butuh alur per jawaban).
 */
export function isSubmissionBulkReviewEligible(submission: IBulkReviewableSubmissionState): boolean {
    const isPending = submission.review_status == null || submission.review_status === 'pending';
    const isSingleRegistration =
        submission.registration_role == null &&
        (submission.group_token == null || submission.group_token === '') &&
        submission.member_confirmation_status == null;

    return isPending && isSingleRegistration;
}

/** Updates only visible submissions, preserving any selections from other pages. */
export function setVisibleSubmissionSelection(
    visibleSubmissionIds: readonly string[],
    selectedSubmissionIds: ReadonlySet<string>,
    checked: boolean
): Set<string> {
    const nextSelection = new Set(selectedSubmissionIds);

    for (const submissionId of visibleSubmissionIds) {
        if (checked) {
            nextSelection.add(submissionId);
        } else {
            nextSelection.delete(submissionId);
        }
    }

    return nextSelection;
}
