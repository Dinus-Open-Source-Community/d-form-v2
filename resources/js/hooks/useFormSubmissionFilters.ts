import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { currentPathname } from '@/adapters/browser/navigation';
import { buildFormSubmissionsUrl } from '@/lib/buildFormSubmissionsUrl';
import type { IFormSubmissionQuery, TSubmissionReferralFilter } from '@/types/formSubmissions';
import type { TFormAnswerReviewStatus } from '@/types/registrationStatus';

/** Sama dengan pencarian acara dan direktori pengguna: satu request per jeda ketik. */
const SEARCH_DEBOUNCE_MS = 350;

/** Prop yang perlu dimuat ulang saat filter berubah; state builder tidak disentuh. */
const FILTER_RELOAD_PROPS = [
    'submissions',
    'submissionsPagination',
    'query',
    'submissionsCount',
    'selectableSubmissionIds',
    'bulkSelectableTotal',
];

export interface IFormSubmissionFiltersOptions {
    /** Filter aktif dari server saat halaman dimuat. */
    query: IFormSubmissionQuery;
    /** Jumlah baris per halaman yang dipertahankan saat filter berubah. */
    perPage: number;
}

export interface IFormSubmissionFilters {
    search: Ref<string>;
    reviewStatus: Ref<TFormAnswerReviewStatus | null>;
    referral: Ref<TSubmissionReferralFilter | null>;
    isLoading: Ref<boolean>;
    hasActiveFilters: ComputedRef<boolean>;
    /** Kosongkan semua filter dan muat ulang daftar satu kali. */
    resetFilters: () => void;
}

/**
 * Kelola filter daftar jawaban: pencarian ber-debounce, chip status review, dan
 * chip referral. Setiap perubahan mengirim satu request Inertia yang membuang
 * `page` tetapi mempertahankan `per_page`.
 */
export function useFormSubmissionFilters(options: IFormSubmissionFiltersOptions): IFormSubmissionFilters {
    const search = ref<string>(options.query.search ?? '');
    const reviewStatus = ref<TFormAnswerReviewStatus | null>(options.query.review_status);
    const referral = ref<TSubmissionReferralFilter | null>(options.query.referral);
    const isLoading = ref<boolean>(false);

    // Reset mengubah tiga ref sekaligus; watcher ditahan agar hanya satu request.
    let suppressWatchers = false;

    const hasActiveFilters = computed<boolean>(
        () => search.value.trim() !== '' || reviewStatus.value !== null || referral.value !== null
    );

    function applyFilters(): void {
        router.get(
            buildFormSubmissionsUrl({
                pathname: currentPathname(),
                filters: {
                    search: search.value,
                    review_status: reviewStatus.value,
                    referral: referral.value,
                },
                perPage: options.perPage,
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: FILTER_RELOAD_PROPS,
                onStart: (): void => {
                    isLoading.value = true;
                },
                onFinish: (): void => {
                    isLoading.value = false;
                },
            }
        );
    }

    const applySearchDebounced = useDebounceFn(applyFilters, SEARCH_DEBOUNCE_MS);

    watch(
        search,
        (): void => {
            if (suppressWatchers) return;

            applySearchDebounced();
        },
        { flush: 'sync' }
    );

    watch(
        [reviewStatus, referral],
        (): void => {
            if (suppressWatchers) return;

            // Chip membawa kata kunci terbaru, jadi debounce pencarian yang tertunda dibuang.
            applySearchDebounced.cancel();
            applyFilters();
        },
        { flush: 'sync' }
    );

    function resetFilters(): void {
        applySearchDebounced.cancel();
        suppressWatchers = true;
        search.value = '';
        reviewStatus.value = null;
        referral.value = null;
        suppressWatchers = false;
        applyFilters();
    }

    return { search, reviewStatus, referral, isLoading, hasActiveFilters, resetFilters };
}
