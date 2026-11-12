import { computed } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import { reviewFormSubmissionsBulk } from '@/adapters/http/forms';
import { useFormSubmissionBulkReview, type IFormSubmissionBulkReview } from '@/hooks/useFormSubmissionBulkReview';
import type { IBulkFormReviewResult } from '@/types/forms';

/**
 * Kontrak bulk review tab Jawaban: seleksi per halaman, hitungan pilihan, guard
 * kelayakan/batas batch, konfirmasi, satu request atomik, reload prop parsial,
 * serta pembersihan pilihan. Router, adapter HTTP, dan seam toast di-mock.
 */
const toastMock = vi.hoisted(() => ({
    showErrorToast: vi.fn(),
    showHttpErrorToast: vi.fn(),
    showSuccessToast: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        reload: vi.fn((options?: { onSuccess?: () => void }): void => {
            options?.onSuccess?.();
        }),
    },
}));

vi.mock('@/adapters/http/forms', () => ({
    reviewFormSubmissionsBulk: vi.fn(),
}));

vi.mock('@/hooks/useToastNotifications', () => ({
    useToastNotifications: () => ({
        showErrorToast: toastMock.showErrorToast,
        showHttpErrorToast: toastMock.showHttpErrorToast,
        showSuccessToast: toastMock.showSuccessToast,
    }),
}));

vi.mock('@/actions/App/Http/Controllers/Dashboard/Events/Forms/BulkFormAnswerReviewController', () => ({
    default: {
        patch: (): { url: string; method: 'patch' } => ({
            url: '/admin/events/event-1/forms/form-1/submissions/bulk-review',
            method: 'patch',
        }),
    },
}));

const reloadMock = vi.mocked(router.reload);
const reviewMock = vi.mocked(reviewFormSubmissionsBulk);

function submission(id: string, overrides: Partial<IFormSubmission> = {}): IFormSubmission {
    return {
        id,
        user: null,
        answers: {},
        submitted_at: '2026-11-12T09:00:00+07:00',
        review_status: 'pending',
        ...overrides,
    };
}

function setup(rows: IFormSubmission[]): IFormSubmissionBulkReview {
    const submissions = computed<readonly IFormSubmission[]>(() => rows);

    return useFormSubmissionBulkReview({
        eventId: 'event-1',
        formId: 'form-1',
        submissions,
        reloadOnly: ['submissions'],
    });
}

function successResult(processed: number): IBulkFormReviewResult {
    return {
        status: 200,
        message: null,
        data: {
            requested: processed,
            processed,
            skipped: 0,
            conflicts: 0,
            queued_intents: processed,
        },
    };
}

beforeEach(() => {
    reloadMock.mockReset();
    reviewMock.mockReset();
    toastMock.showErrorToast.mockReset();
    toastMock.showHttpErrorToast.mockReset();
    toastMock.showSuccessToast.mockReset();
});

describe('useFormSubmissionBulkReview', () => {
    it('counts the selection and reports page-level checked/indeterminate state', () => {
        const bulk = setup([submission('answer-1'), submission('answer-2'), submission('answer-3')]);

        expect(bulk.selectedCount.value).toBe(0);
        expect(bulk.actionStatus.value).toBe('idle');
        expect(bulk.actionError.value).toBeNull();

        bulk.setVisibleSelection(true);
        expect(bulk.selectedCount.value).toBe(3);
        expect(bulk.visibleSelectionState.value).toBe(true);

        bulk.setSubmissionSelection('answer-2', false);
        expect(bulk.selectedCount.value).toBe(2);
        expect(bulk.visibleSelectionState.value).toBe('indeterminate');
        expect(bulk.isSelected('answer-1')).toBe(true);
        expect(bulk.isSelected('answer-2')).toBe(false);

        bulk.setVisibleSelection('indeterminate');
        expect(bulk.selectedCount.value).toBe(2);

        bulk.clearSelection();
        expect(bulk.selectedCount.value).toBe(0);
        expect(bulk.visibleSelectionState.value).toBe(false);
    });

    it('does not open or send a batch when no submission is explicitly selected', () => {
        const bulk = setup([submission('answer-1')]);

        bulk.requestBulkReview('accepted');
        bulk.confirmBulkReview();

        expect(bulk.isConfirmOpen.value).toBe(false);
        expect(bulk.pendingDecision.value).toBeNull();
        expect(reviewMock).not.toHaveBeenCalled();
    });

    it('counts reviewed and team rows as ineligible and refuses to send them', () => {
        const bulk = setup([
            submission('answer-1'),
            submission('answer-2', { review_status: 'accepted' }),
            submission('answer-3', { registration_role: 'member', member_confirmation_status: 'accepted' }),
        ]);

        bulk.setVisibleSelection(true);

        expect(bulk.ineligibleSelectedCount.value).toBe(2);

        bulk.requestBulkReview('accepted');

        expect(bulk.pendingDecision.value).toBeNull();
        expect(bulk.isConfirmOpen.value).toBe(false);
        expect(toastMock.showErrorToast).toHaveBeenCalledTimes(1);
        expect(reviewMock).not.toHaveBeenCalled();
    });

    it('refuses a batch above the server limit before any request', () => {
        const bulk = setup(
            Array.from({ length: 101 }, (_value, index): IFormSubmission => submission(`answer-${index}`))
        );

        bulk.setVisibleSelection(true);
        bulk.requestBulkReview('rejected');

        expect(bulk.pendingDecision.value).toBeNull();
        expect(bulk.isConfirmOpen.value).toBe(false);
        expect(toastMock.showErrorToast).toHaveBeenCalledWith(
            'Maksimal 100 jawaban sekali proses. Kurangi pilihan Anda.'
        );
        expect(reviewMock).not.toHaveBeenCalled();
    });

    it('sends sorted visible ids once confirmed, then clears the selection and reloads', async () => {
        reviewMock.mockResolvedValueOnce(successResult(2));
        const bulk = setup([submission('answer-2'), submission('answer-1'), submission('answer-3')]);

        bulk.setSubmissionSelection('answer-2', true);
        bulk.setSubmissionSelection('answer-1', true);
        expect(bulk.selectedCount.value).toBe(2);
        bulk.requestBulkReview('accepted');
        expect(bulk.pendingDecision.value).toBe('accepted');
        expect(bulk.isConfirmOpen.value).toBe(true);

        bulk.confirmBulkReview();

        expect(bulk.pendingDecision.value).toBe('accepted');
        expect(bulk.isConfirmOpen.value).toBe(true);
        expect(bulk.actionStatus.value).toBe('submitting');

        await vi.waitFor(() => expect(toastMock.showSuccessToast).toHaveBeenCalledWith('2 jawaban diterima.'));

        expect(reviewMock).toHaveBeenCalledWith({
            url: '/admin/events/event-1/forms/form-1/submissions/bulk-review',
            submissionIds: ['answer-1', 'answer-2'],
            reviewStatus: 'accepted',
        });
        expect(bulk.selectedCount.value).toBe(0);
        expect(bulk.isSubmitting.value).toBe(false);
        expect(bulk.actionStatus.value).toBe('success');
        expect(bulk.pendingDecision.value).toBeNull();
        expect(bulk.isConfirmOpen.value).toBe(false);
        expect(reloadMock).toHaveBeenCalledWith(expect.objectContaining({ only: ['submissions'] }));
    });

    it('cancels the pending decision when the dialog closes with Escape', () => {
        const bulk = setup([submission('answer-1')]);

        bulk.setVisibleSelection(true);
        bulk.requestBulkReview('rejected');
        expect(bulk.isConfirmOpen.value).toBe(true);

        // The controlled confirmation dialog preserves the decision while its request is pending.
        bulk.setDialogOpen(false);
        expect(bulk.isConfirmOpen.value).toBe(false);
        expect(bulk.pendingDecision.value).toBeNull();
        expect(reviewMock).not.toHaveBeenCalled();
    });

    it('cancels a pending confirmation without sending anything', () => {
        const bulk = setup([submission('answer-1')]);

        bulk.setVisibleSelection(true);
        bulk.requestBulkReview('accepted');
        bulk.setDialogOpen(false);
        bulk.cancelBulkReview();

        expect(bulk.isConfirmOpen.value).toBe(false);
        expect(bulk.pendingDecision.value).toBeNull();
        expect(bulk.selectedCount.value).toBe(1);
        expect(reviewMock).not.toHaveBeenCalled();
    });

    it('clears a stale batch and reloads after a 409 conflict', async () => {
        reviewMock.mockResolvedValueOnce({ status: 409, message: null, data: null });
        const bulk = setup([submission('answer-1')]);

        bulk.setVisibleSelection(true);
        bulk.requestBulkReview('rejected');
        bulk.confirmBulkReview();

        await vi.waitFor(() => expect(toastMock.showHttpErrorToast).toHaveBeenCalledTimes(1));

        expect(toastMock.showHttpErrorToast).toHaveBeenCalledWith(
            409,
            {},
            expect.objectContaining({ 409: 'Sebagian jawaban sudah direview. Daftar dimuat ulang.' })
        );
        expect(bulk.selectedCount.value).toBe(0);
        expect(reloadMock).toHaveBeenCalledWith(expect.objectContaining({ only: ['submissions'] }));
    });

    it('keeps the selection after a 422 so the user can fix it, without reloading', async () => {
        reviewMock.mockResolvedValueOnce({ status: 422, message: null, data: null });
        const bulk = setup([submission('answer-1')]);

        bulk.setVisibleSelection(true);
        bulk.requestBulkReview('accepted');
        bulk.confirmBulkReview();

        await vi.waitFor(() => expect(toastMock.showHttpErrorToast).toHaveBeenCalledTimes(1));

        expect(bulk.selectedCount.value).toBe(1);
        expect(bulk.actionStatus.value).toBe('error');
        expect(bulk.actionError.value).toBeTruthy();
        expect(bulk.isConfirmOpen.value).toBe(true);
        expect(reloadMock).not.toHaveBeenCalled();
    });

    it('ignores a second confirmation while the first request is in flight', async () => {
        let resolveReview: (result: IBulkFormReviewResult) => void = () => undefined;
        reviewMock.mockReturnValueOnce(
            new Promise<IBulkFormReviewResult>((resolve) => {
                resolveReview = resolve;
            })
        );
        const bulk = setup([submission('answer-1')]);

        bulk.setVisibleSelection(true);
        bulk.requestBulkReview('rejected');
        bulk.confirmBulkReview();
        bulk.confirmBulkReview();

        expect(reviewMock).toHaveBeenCalledTimes(1);

        resolveReview(successResult(1));

        await vi.waitFor(() => expect(toastMock.showSuccessToast).toHaveBeenCalledWith('1 jawaban ditolak.'));
    });
});
