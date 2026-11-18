import { nextTick, ref, toValue, watch } from 'vue';
import type { MaybeRefOrGetter, Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { FormDataConvertible } from '@inertiajs/core';

/** Bentuk query filter acara yang dikirim server ke halaman daftar acara. */
export interface IEventListQueryFilterInput {
    search?: string;
    filter?: { categories?: string[]; sessions?: string[] };
}

export interface IEventListQueryFiltersOptions {
    /** Query aktif dari server; dibaca ulang setiap props Inertia berubah. */
    query: MaybeRefOrGetter<IEventListQueryFilterInput>;
    /** Jumlah baris per halaman yang harus dipertahankan saat filter berubah. */
    perPage: MaybeRefOrGetter<number>;
    /** URL halaman daftar acara yang dituju setiap perubahan filter. */
    targetUrl: string;
}

export interface IEventListQueryFilters {
    searchQuery: Ref<string>;
    filterCategory: Ref<string>;
    filterSession: Ref<string>;
    /** Payload siap kirim untuk `router.get`; `per_page` selalu ada. */
    buildQueryParams: () => Record<string, FormDataConvertible>;
    applyFilters: () => void;
}

/**
 * Kelola state filter daftar acara (pencarian, kategori, sesi) dan terjemahkannya
 * menjadi satu request Inertia. Dipakai bersama oleh halaman acara aktif dan arsip
 * agar parameter query, opsi visit, dan guard sinkronisasi props tetap identik.
 */
export function useEventListQueryFilters(options: IEventListQueryFiltersOptions): IEventListQueryFilters {
    const searchQuery = ref<string>('');
    const filterCategory = ref<string>('all');
    const filterSession = ref<string>('all');

    /** Mencegah permintaan ganda saat state disamakan ulang dari URL/Inertia. */
    let suppressFilterApply = false;

    function readQueryFromProps(): void {
        suppressFilterApply = true;
        const query = toValue(options.query);
        searchQuery.value = query.search ?? '';
        filterCategory.value = query.filter?.categories?.[0] ?? 'all';
        filterSession.value = query.filter?.sessions?.[0] ?? 'all';
        void nextTick(() => {
            suppressFilterApply = false;
        });
    }

    readQueryFromProps();
    watch(() => toValue(options.query), readQueryFromProps, { deep: true });

    /**
     * Parameter query untuk request berikutnya. `per_page` selalu ikut supaya perubahan
     * filter tidak mereset pilihan jumlah baris, sedangkan `search`/`filter` hanya dikirim
     * saat benar-benar aktif.
     */
    function buildQueryParams(): Record<string, FormDataConvertible> {
        const params: Record<string, FormDataConvertible> = {};
        if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
        params.per_page = toValue(options.perPage);

        const filter: Record<string, FormDataConvertible> = {};
        if (filterCategory.value !== 'all') filter.categories = [filterCategory.value];
        if (filterSession.value !== 'all') filter.sessions = [filterSession.value];

        if (Object.keys(filter).length > 0) params.filter = filter;

        return params;
    }

    function applyFilters(): void {
        if (suppressFilterApply) return;

        router.get(options.targetUrl, buildQueryParams(), {
            preserveState: true,
            preserveScroll: true,
            only: ['events', 'query'],
        });
    }

    watch([filterCategory, filterSession], applyFilters);

    return { searchQuery, filterCategory, filterSession, buildQueryParams, applyFilters };
}
