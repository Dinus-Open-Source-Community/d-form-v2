import { describe, expect, it } from 'vitest';
import {
    getVisibleSubmissionSelectionState,
    isSubmissionBulkReviewEligible,
    setVisibleSubmissionSelection,
} from '@/lib/formSubmissionSelection';
import { formSubmissionReviewActionLabel } from '@/lib/formSubmissionsUi';

describe('form submission selection', () => {
    it('selects only the visible page and preserves selections outside it', () => {
        const selected = setVisibleSubmissionSelection(['answer-1', 'answer-2'], new Set(['answer-3']), true);

        expect(selected).toEqual(new Set(['answer-1', 'answer-2', 'answer-3']));
    });

    it('reports checked, unchecked, and indeterminate states for visible rows', () => {
        expect(getVisibleSubmissionSelectionState(['answer-1', 'answer-2'], new Set())).toBe(false);
        expect(getVisibleSubmissionSelectionState(['answer-1', 'answer-2'], new Set(['answer-1']))).toBe(
            'indeterminate'
        );
        expect(
            getVisibleSubmissionSelectionState(['answer-1', 'answer-2'], new Set(['answer-1', 'answer-2', 'answer-3']))
        ).toBe(true);
    });

    it('returns to indeterminate after one visible row is unchecked', () => {
        const allSelected = setVisibleSubmissionSelection(['answer-1', 'answer-2'], new Set(), true);
        const oneUnchecked = setVisibleSubmissionSelection(['answer-1'], allSelected, false);

        expect(getVisibleSubmissionSelectionState(['answer-1', 'answer-2'], oneUnchecked)).toBe('indeterminate');
    });

    it('uses the same page selection logic for mobile rows', () => {
        const selected = setVisibleSubmissionSelection(['mobile-answer'], new Set(), true);

        expect(getVisibleSubmissionSelectionState(['mobile-answer'], selected)).toBe(true);
    });
});

describe('bulk review eligibility', () => {
    it('accepts only pending single-registration rows', () => {
        expect(isSubmissionBulkReviewEligible({ review_status: 'pending' })).toBe(true);
        expect(isSubmissionBulkReviewEligible({ review_status: null })).toBe(true);
        expect(isSubmissionBulkReviewEligible({ review_status: 'accepted' })).toBe(false);
        expect(isSubmissionBulkReviewEligible({ review_status: 'rejected' })).toBe(false);
    });

    it('rejects team and bundle rows that need individual review', () => {
        expect(isSubmissionBulkReviewEligible({ review_status: 'pending', registration_role: 'leader' })).toBe(false);
        expect(isSubmissionBulkReviewEligible({ review_status: 'pending', registration_role: 'member' })).toBe(false);
        expect(
            isSubmissionBulkReviewEligible({ review_status: 'pending', member_confirmation_status: 'accepted' })
        ).toBe(false);
        expect(isSubmissionBulkReviewEligible({ review_status: 'pending', group_token: 'group-1' })).toBe(false);
    });

    it('treats an empty group token as a single registration', () => {
        expect(isSubmissionBulkReviewEligible({ review_status: 'pending', group_token: '' })).toBe(true);
    });
});

describe('form submission action labels', () => {
    it('names review and detail actions for assistive technology', () => {
        expect(formSubmissionReviewActionLabel('accept', false, 'Dina')).toBe('Terima jawaban dari Dina');
        expect(formSubmissionReviewActionLabel('reject', true, 'Dina')).toBe('Memproses jawaban dari Dina');
        expect(formSubmissionReviewActionLabel('detail', false, 'Dina')).toBe('Lihat detail jawaban dari Dina');
    });
});
