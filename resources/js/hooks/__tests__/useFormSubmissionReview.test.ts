import { beforeEach, describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import { reviewFormSubmission, type IFormReviewResult } from '@/adapters/http/forms';
import { useFormSubmissionReview } from '@/hooks/useFormSubmissionReview';

/**
 * Kontrak alur review submission: guard pending/in-flight, pemetaan status
 * 403/404/409/422 ke prompt masing-masing, reload prop parsial, dan pembersihan
 * `reviewingIds` di semua jalur keluar. Router, adapter HTTP, dan seam toast
 * di-mock; tidak ada DOM sehingga hook diuji sebagai unit murni.
 */
const toastMock = vi.hoisted(() => ({
    showHttpErrorToast: vi.fn(),
    showErrorToast: vi.fn(),
    showSuccessToast: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        // `onSuccess` dipanggil langsung supaya jalur "reload selesai" ikut teruji.
        reload: vi.fn((options?: { onSuccess?: () => void }): void => options?.onSuccess?.()),
    },
}));

vi.mock('@/adapters/http/forms', () => ({
    reviewFormSubmission: vi.fn(),
}));

vi.mock('@/hooks/useToastNotifications', () => ({
    useToastNotifications: () => ({
        showHttpErrorToast: toastMock.showHttpErrorToast,
        showErrorToast: toastMock.showErrorToast,
        showSuccessToast: toastMock.showSuccessToast,
    }),
}));

vi.mock('@/actions/App/Http/Controllers/Dashboard/Events/Forms/FormAnswerReviewController', () => ({
    default: {
        patch: (): { url: string } => ({ url: '/admin/events/event-1/forms/form-1/submissions/submission-1/review' }),
    },
}));

const reloadMock = vi.mocked(router.reload);
const reviewMock = vi.mocked(reviewFormSubmission);

const OPTIONS = {
    eventId: 'event-1',
    formId: 'form-1',
    reloadOnly: ['submissions'],
};

const PENDING_SUBMISSION: IFormSubmission = {
    id: 'submission-1',
    user: null,
    answers: { full_name: 'Ada' },
    review_status: 'pending',
    submitted_at: '2026-11-11T10:00:00+07:00',
};

describe('useFormSubmissionReview', () => {
    beforeEach(() => {
        reloadMock.mockReset();
        reviewMock.mockReset();
        toastMock.showHttpErrorToast.mockReset();
        toastMock.showErrorToast.mockReset();
        toastMock.showSuccessToast.mockReset();
    });

    it.each([
        [403, 'Anda tidak punya izin untuk mereview jawaban ini.'],
        [404, 'Jawaban tidak ditemukan.'],
        [409, 'Jawaban ini sudah pernah direview.'],
        [422, 'Status review tidak valid.'],
    ])('maps status %i to its prompt', async (status: number, prompt: string) => {
        reviewMock.mockResolvedValue({ status, message: null, data: null, currentStatus: null });

        const review = useFormSubmissionReview(OPTIONS);
        review.reviewSubmission('accept', PENDING_SUBMISSION);

        await vi.waitFor(() => expect(toastMock.showHttpErrorToast).toHaveBeenCalledTimes(1));

        expect(toastMock.showHttpErrorToast).toHaveBeenCalledWith(
            status,
            {},
            {
                409: 'Jawaban ini sudah pernah direview.',
                422: 'Status review tidak valid.',
                403: 'Anda tidak punya izin untuk mereview jawaban ini.',
                404: 'Jawaban tidak ditemukan.',
            }
        );
        expect(toastMock.showHttpErrorToast.mock.calls[0]?.[2]?.[status]).toBe(prompt);
        expect(toastMock.showSuccessToast).not.toHaveBeenCalled();
        expect(review.isReviewing('submission-1')).toBe(false);
    });

    it.each([
        [409, true],
        [422, true],
        [403, false],
        [404, false],
    ])('reloads the submissions prop for status %i: %s', async (status: number, shouldReload: boolean) => {
        reviewMock.mockResolvedValue({ status, message: null, data: null, currentStatus: null });

        const review = useFormSubmissionReview(OPTIONS);
        review.reviewSubmission('accept', PENDING_SUBMISSION);

        await vi.waitFor(() => expect(toastMock.showHttpErrorToast).toHaveBeenCalledTimes(1));

        if (shouldReload) {
            expect(reloadMock).toHaveBeenCalledWith({ only: ['submissions'] });
        } else {
            expect(reloadMock).not.toHaveBeenCalled();
        }
    });

    it('dedupes a second request while the first one is in flight', async () => {
        reviewMock.mockImplementation(
            (): Promise<IFormReviewResult> =>
                new Promise(() => {
                    // sengaja tidak pernah selesai: status in-flight harus menahan request kedua.
                })
        );

        const review = useFormSubmissionReview(OPTIONS);
        review.reviewSubmission('accept', PENDING_SUBMISSION);

        await vi.waitFor(() => expect(reviewMock).toHaveBeenCalledTimes(1));
        expect(review.isReviewing('submission-1')).toBe(true);

        review.reviewSubmission('reject', PENDING_SUBMISSION);

        expect(reviewMock).toHaveBeenCalledTimes(1);
        expect(reviewMock.mock.calls[0]?.[0]).toMatchObject({ reviewStatus: 'accepted' });
    });

    it('ignores a submission that is no longer pending', () => {
        const review = useFormSubmissionReview(OPTIONS);

        review.reviewSubmission('accept', { ...PENDING_SUBMISSION, review_status: 'accepted' });

        expect(reviewMock).not.toHaveBeenCalled();
        expect(review.isReviewing('submission-1')).toBe(false);
    });

    it('clears the in-flight marker after a success and notifies the caller', async () => {
        reviewMock.mockResolvedValue({
            status: 200,
            message: null,
            data: {
                id: 'submission-1',
                review_status: 'accepted',
                reviewed_at: '2026-11-11T10:05:00+07:00',
                reviewed_by: 'admin-1',
                cascaded_rejections: 0,
                registration_code: 'REG-1',
            },
            currentStatus: null,
        });
        const onReviewed = vi.fn();

        const review = useFormSubmissionReview({ ...OPTIONS, onReviewed });
        review.reviewSubmission('accept', PENDING_SUBMISSION);

        await vi.waitFor(() => expect(onReviewed).toHaveBeenCalledWith('submission-1'));

        expect(toastMock.showSuccessToast).toHaveBeenCalledWith('Jawaban diterima.');
        expect(reloadMock).toHaveBeenCalledWith({
            only: ['submissions'],
            onSuccess: expect.any(Function),
        });
        expect(review.isReviewing('submission-1')).toBe(false);
    });

    it('reports a transport failure and still clears the in-flight marker', async () => {
        reviewMock.mockRejectedValue(new Error('network down'));

        const review = useFormSubmissionReview(OPTIONS);
        review.reviewSubmission('reject', PENDING_SUBMISSION);

        await vi.waitFor(() =>
            expect(toastMock.showErrorToast).toHaveBeenCalledWith('Tidak dapat menghubungi server. Coba lagi.')
        );

        expect(toastMock.showSuccessToast).not.toHaveBeenCalled();
        expect(reloadMock).not.toHaveBeenCalled();
        expect(review.isReviewing('submission-1')).toBe(false);
    });
});
