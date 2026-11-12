import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { reviewFormSubmissionsBulk } from '@/adapters/http/forms';
import { isHttpRequestError } from '@/adapters/http/httpError';
import { FORM_ANSWER_BULK_REVIEW_MAX_SELECTION } from '@/constants/formAnswerReview';
import {
    getVisibleSubmissionSelectionState,
    isSubmissionBulkReviewEligible,
    setVisibleSubmissionSelection,
    type TVisibleSubmissionSelectionState,
} from '@/lib/formSubmissionSelection';
import { describeRequestError } from '@/lib/requestErrorText';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import BulkFormAnswerReviewController from '@/actions/App/Http/Controllers/Dashboard/Events/Forms/BulkFormAnswerReviewController';
import type { TFormReviewDecision, TFormSubmissionBulkReviewStatus } from '@/types/forms';

export interface IUseFormSubmissionBulkReviewOptions {
    eventId: string;
    formId: string;
    /** Baris yang sedang tampil; seleksi mengikuti daftar ini setiap reload. */
    submissions: ComputedRef<readonly IFormSubmission[]>;
    /** Prop Inertia yang direload setelah batch berhasil maupun saat konflik. */
    reloadOnly: readonly string[];
    /** Dipanggil setelah reload sukses dengan jumlah baris yang diproses. */
    onReviewed?: (processedCount: number) => void;
}

export interface IFormSubmissionBulkReview {
    selectedSubmissionIds: Readonly<Ref<ReadonlySet<string>>>;
    selectedCount: ComputedRef<number>;
    visibleSelectionState: ComputedRef<TVisibleSubmissionSelectionState>;
    /** Baris terpilih yang tidak lolos aturan bulk server (tim/bundle atau sudah direview). */
    ineligibleSelectedCount: ComputedRef<number>;
    isSubmitting: Readonly<Ref<boolean>>;
    actionStatus: Readonly<Ref<TFormSubmissionBulkReviewStatus>>;
    actionError: Readonly<Ref<string | null>>;
    /** Dialog konfirmasi terbuka; dikendalikan hook agar `update:open` tidak membatalkan keputusan. */
    isConfirmOpen: Readonly<Ref<boolean>>;
    /** Keputusan yang menunggu konfirmasi pengguna; `null` berarti tidak ada keputusan tertunda. */
    pendingDecision: Readonly<Ref<TFormReviewDecision | null>>;
    isSelected: (submissionId: string) => boolean;
    setVisibleSelection: (checked: TVisibleSubmissionSelectionState) => void;
    setSubmissionSelection: (submissionId: string, checked: TVisibleSubmissionSelectionState) => void;
    clearSelection: () => void;
    requestBulkReview: (decision: TFormReviewDecision) => void;
    /** Sinkronkan visibilitas dialog saat ditutup dari luar (ESC/overlay); keputusan tetap utuh. */
    setDialogOpen: (open: boolean) => void;
    confirmBulkReview: () => void;
    cancelBulkReview: () => void;
}

const BULK_REVIEW_ERROR_OVERRIDES: Partial<Record<number, string>> = {
    403: 'Anda tidak punya izin untuk mereview jawaban ini.',
    404: 'Form atau jawaban tidak ditemukan.',
    409: 'Sebagian jawaban sudah direview. Daftar dimuat ulang.',
    422: 'Pilihan berisi jawaban yang belum bisa direview massal.',
    429: 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.',
};

/**
 * Satu alur bulk review untuk tab Jawaban: seleksi per halaman, hitungan pilihan,
 * guard kelayakan, konfirmasi, lalu satu request atomik dan reload prop Inertia.
 */
export function useFormSubmissionBulkReview(options: IUseFormSubmissionBulkReviewOptions): IFormSubmissionBulkReview {
    const { showErrorToast, showHttpErrorToast, showSuccessToast } = useToastNotifications();
    const selectedSubmissionIds = ref<Set<string>>(new Set());
    const pendingDecision = ref<TFormReviewDecision | null>(null);
    const isConfirmOpen = ref(false);
    const submitting = ref(false);
    const actionStatus = ref<TFormSubmissionBulkReviewStatus>('idle');
    const actionError = ref<string | null>(null);

    const visibleSubmissionIds = computed<string[]>(() => options.submissions.value.map((submission) => submission.id));
    const submissionsById = computed<Map<string, IFormSubmission>>(
        () => new Map(options.submissions.value.map((submission) => [submission.id, submission]))
    );

    const selectedCount = computed<number>(() => selectedSubmissionIds.value.size);
    const visibleSelectionState = computed<TVisibleSubmissionSelectionState>(() =>
        getVisibleSubmissionSelectionState(visibleSubmissionIds.value, selectedSubmissionIds.value)
    );
    const ineligibleSelectedCount = computed<number>(() => {
        let count = 0;

        for (const submissionId of selectedSubmissionIds.value) {
            const submission = submissionsById.value.get(submissionId);
            if (submission === undefined || !isSubmissionBulkReviewEligible(submission)) count += 1;
        }

        return count;
    });

    function isSelected(submissionId: string): boolean {
        return selectedSubmissionIds.value.has(submissionId);
    }

    function setVisibleSelection(checked: TVisibleSubmissionSelectionState): void {
        if (checked === 'indeterminate') return;

        selectedSubmissionIds.value = setVisibleSubmissionSelection(
            visibleSubmissionIds.value,
            selectedSubmissionIds.value,
            checked
        );
    }

    function setSubmissionSelection(submissionId: string, checked: TVisibleSubmissionSelectionState): void {
        if (checked === 'indeterminate') return;

        selectedSubmissionIds.value = setVisibleSubmissionSelection(
            [submissionId],
            selectedSubmissionIds.value,
            checked
        );
    }

    function clearSelection(): void {
        selectedSubmissionIds.value = new Set();
    }

    /** Validasi pilihan sebelum dialog konfirmasi; pilihan tidak valid tidak pernah dikirim. */
    function requestBulkReview(decision: TFormReviewDecision): void {
        if (submitting.value || selectedCount.value === 0) return;

        if (selectedCount.value > FORM_ANSWER_BULK_REVIEW_MAX_SELECTION) {
            showErrorToast(
                `Maksimal ${FORM_ANSWER_BULK_REVIEW_MAX_SELECTION} jawaban sekali proses. Kurangi pilihan Anda.`
            );
            return;
        }

        if (ineligibleSelectedCount.value > 0) {
            showErrorToast(
                'Pilihan berisi jawaban yang belum bisa direview massal. Hanya pendaftaran tunggal berstatus menunggu review yang bisa diproses sekaligus.'
            );
            return;
        }

        pendingDecision.value = decision;
        actionStatus.value = 'idle';
        actionError.value = null;
        isConfirmOpen.value = true;
    }

    /** Send the confirmed explicit ID snapshot once; repeated submits are blocked while it is in flight. */
    function confirmBulkReview(): void {
        const decision = pendingDecision.value;
        if (decision === null || submitting.value) return;

        const submissionIds = [...selectedSubmissionIds.value].sort();
        if (submissionIds.length === 0) return;

        const { url } = BulkFormAnswerReviewController.patch({
            event: options.eventId,
            form: options.formId,
        });

        submitting.value = true;
        actionStatus.value = 'submitting';
        actionError.value = null;

        void (async (): Promise<void> => {
            try {
                const result = await reviewFormSubmissionsBulk({ url, submissionIds, reviewStatus: decision });

                if (result.status < 200 || result.status >= 300) {
                    showHttpErrorToast(result.status, {}, BULK_REVIEW_ERROR_OVERRIDES);
                    actionStatus.value = 'error';
                    actionError.value =
                        BULK_REVIEW_ERROR_OVERRIDES[result.status] ??
                        result.message ??
                        'Gagal memproses jawaban terpilih.';

                    if (result.status === 409) {
                        clearSelection();
                        pendingDecision.value = null;
                        isConfirmOpen.value = false;
                        router.reload({ only: [...options.reloadOnly] });
                    }

                    return;
                }

                const processedCount = result.data?.processed ?? submissionIds.length;
                showSuccessToast(`${processedCount} jawaban ${decision === 'accepted' ? 'diterima' : 'ditolak'}.`);
                actionStatus.value = 'success';
                pendingDecision.value = null;
                isConfirmOpen.value = false;
                clearSelection();
                router.reload({
                    only: [...options.reloadOnly],
                    onSuccess: () => options.onReviewed?.(processedCount),
                });
            } catch (error: unknown) {
                if (isHttpRequestError(error)) {
                    const message = describeRequestError(error);
                    actionError.value = message ?? 'Gagal memproses jawaban terpilih. Coba lagi.';
                    showErrorToast(actionError.value);
                } else {
                    actionError.value = 'Tidak dapat menghubungi server. Coba lagi.';
                    showErrorToast(actionError.value);
                }
                actionStatus.value = 'error';
            } finally {
                submitting.value = false;
            }
        })();
    }

    function cancelBulkReview(): void {
        if (submitting.value) return;
        isConfirmOpen.value = false;
        pendingDecision.value = null;
        actionStatus.value = 'idle';
        actionError.value = null;
    }

    /** Escape and close controls cancel the confirmation without clearing the selection. */
    function setDialogOpen(open: boolean): void {
        if (!open) {
            cancelBulkReview();
            return;
        }
        isConfirmOpen.value = open;
    }

    return {
        selectedSubmissionIds,
        selectedCount,
        visibleSelectionState,
        ineligibleSelectedCount,
        isSubmitting: submitting,
        actionStatus,
        actionError,
        isConfirmOpen,
        pendingDecision,
        isSelected,
        setVisibleSelection,
        setSubmissionSelection,
        clearSelection,
        requestBulkReview,
        setDialogOpen,
        confirmBulkReview,
        cancelBulkReview,
    };
}
