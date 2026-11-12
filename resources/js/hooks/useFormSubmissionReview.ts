import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { reviewFormSubmission } from '@/adapters/http/forms';
import { isHttpRequestError } from '@/adapters/http/httpError';
import { formSubmissionReviewIsPending } from '@/lib/formSubmissionsUi';
import { humanizeErrorMessage } from '@/lib/error-message';
import { describeRequestError } from '@/lib/requestErrorText';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import FormAnswerReviewController from '@/actions/App/Http/Controllers/Dashboard/Events/Forms/FormAnswerReviewController';

export type TFormSubmissionReviewAction = 'accept' | 'reject';

export interface IUseFormSubmissionReviewOptions {
    eventId: string;
    formId: string;
    /** Prop Inertia yang direload setelah review berhasil maupun saat konflik status. */
    reloadOnly: readonly string[];
    /** Dipanggil setelah reload sukses; dipakai pemanggil untuk menyegarkan baris terpilih. */
    onReviewed?: (submissionId: string) => void;
}

export interface IFormSubmissionReview {
    /** True selama request review untuk submission ini berjalan. */
    isReviewing: (submissionId: string) => boolean;
    /** Kirim keputusan review; guard pending + in-flight mencegah request ganda. */
    reviewSubmission: (action: TFormSubmissionReviewAction, submission: IFormSubmission) => void;
}

const REVIEW_ERROR_OVERRIDES: Partial<Record<number, string>> = {
    409: 'Jawaban ini sudah pernah direview.',
    422: 'Status review tidak valid.',
    403: 'Anda tidak punya izin untuk mereview jawaban ini.',
    404: 'Jawaban tidak ditemukan.',
};

/**
 * Satu-satunya implementasi alur review submission untuk jalur hidup
 * (`pages/Dashboard/Events/Forms/Show.vue`): guard pending/in-flight, prompt
 * status 409/422/403/404, `reviewingIds`, dan reload prop Inertia parsial.
 */
export function useFormSubmissionReview(options: IUseFormSubmissionReviewOptions): IFormSubmissionReview {
    const { showErrorToast, showHttpErrorToast, showSuccessToast } = useToastNotifications();
    const reviewingIds = ref<Set<string>>(new Set());

    function isReviewing(submissionId: string): boolean {
        return reviewingIds.value.has(submissionId);
    }

    function markReviewing(submissionId: string): void {
        reviewingIds.value = new Set(reviewingIds.value).add(submissionId);
    }

    function clearReviewing(submissionId: string): void {
        const next = new Set(reviewingIds.value);
        next.delete(submissionId);
        reviewingIds.value = next;
    }

    function reviewSubmission(action: TFormSubmissionReviewAction, submission: IFormSubmission): void {
        if (!formSubmissionReviewIsPending(submission) || isReviewing(submission.id)) return;

        const submissionId = submission.id;
        const reviewStatus = action === 'accept' ? 'accepted' : 'rejected';
        const { url } = FormAnswerReviewController.patch({
            event: options.eventId,
            form: options.formId,
            formAnswer: submissionId,
        });

        markReviewing(submissionId);

        void (async () => {
            try {
                const result = await reviewFormSubmission({ url, method: 'PATCH', reviewStatus });
                const isSuccess = result.status >= 200 && result.status < 300;

                if (!isSuccess) {
                    showHttpErrorToast(
                        result.status,
                        result.message ? { message: humanizeErrorMessage(result.message) } : {},
                        {
                            ...REVIEW_ERROR_OVERRIDES,
                        }
                    );

                    if (result.status === 409 || result.status === 422) {
                        router.reload({ only: [...options.reloadOnly] });
                    }
                    return;
                }

                showSuccessToast(action === 'accept' ? 'Jawaban diterima.' : 'Jawaban ditolak.');
                router.reload({
                    only: [...options.reloadOnly],
                    onSuccess: () => {
                        options.onReviewed?.(submissionId);
                    },
                });
            } catch (error: unknown) {
                if (isHttpRequestError(error)) {
                    const message = describeRequestError(error);
                    if (message !== null) showErrorToast(message);
                } else {
                    showErrorToast('Tidak dapat menghubungi server. Coba lagi.');
                }
            } finally {
                clearReviewing(submissionId);
            }
        })();
    }

    return { isReviewing, reviewSubmission };
}
