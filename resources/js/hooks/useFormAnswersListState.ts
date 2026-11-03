import { computed, ref, watch, type ComputedRef, type Ref } from 'vue';
import type { TFormAnswersReviewFilter } from '@/types/formAnswersView';

const DEFAULT_PAGE_SIZE = 25;

/** Owns client-side search, status filtering, and pagination for form answers. */
export function useFormAnswersListState(rows: Ref<IFormSubmission[]>): {
    query: Ref<string>;
    status: Ref<TFormAnswersReviewFilter>;
    page: Ref<number>;
    pageSize: Ref<number>;
    filteredRows: ComputedRef<IFormSubmission[]>;
    pagedRows: ComputedRef<IFormSubmission[]>;
    pageCount: ComputedRef<number>;
    rangeLabel: ComputedRef<string>;
    resetFilters: () => void;
    setPage: (next: number) => void;
    setPageSize: (next: number) => void;
} {
    const query = ref('');
    const status = ref<TFormAnswersReviewFilter>('all');
    const page = ref(1);
    const pageSize = ref(DEFAULT_PAGE_SIZE);

    const filteredRows = computed<IFormSubmission[]>(() => {
        const normalizedQuery = query.value.trim().toLocaleLowerCase();
        return rows.value.filter((submission) => {
            const matchesQuery =
                normalizedQuery === '' ||
                submission.user?.name.toLocaleLowerCase().includes(normalizedQuery) === true ||
                submission.user?.email.toLocaleLowerCase().includes(normalizedQuery) === true;
            const matchesStatus = status.value === 'all' || (submission.review_status ?? 'pending') === status.value;
            return matchesQuery && matchesStatus;
        });
    });

    const pageCount = computed(() => Math.max(1, Math.ceil(filteredRows.value.length / pageSize.value)));

    const setPage = (next: number): void => {
        page.value = Math.min(Math.max(1, next), pageCount.value);
    };

    const pagedRows = computed<IFormSubmission[]>(() => {
        const start = (page.value - 1) * pageSize.value;
        return filteredRows.value.slice(start, start + pageSize.value);
    });

    const rangeLabel = computed(() => {
        const total = filteredRows.value.length;
        if (total === 0) return '';
        const start = (page.value - 1) * pageSize.value + 1;
        const end = Math.min(page.value * pageSize.value, total);
        return `${start}–${end} dari ${total}`;
    });

    watch([query, status], () => setPage(1));
    watch(filteredRows, () => setPage(page.value));

    const setPageSize = (next: number): void => {
        if (![25, 50, 100].includes(next)) return;
        pageSize.value = next;
        setPage(1);
    };

    const resetFilters = (): void => {
        query.value = '';
        status.value = 'all';
        setPage(1);
    };

    return {
        query,
        status,
        page,
        pageSize,
        filteredRows,
        pagedRows,
        pageCount,
        rangeLabel,
        resetFilters,
        setPage,
        setPageSize,
    };
}
