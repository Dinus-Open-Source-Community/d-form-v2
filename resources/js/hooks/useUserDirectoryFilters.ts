import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { useDebounceFn } from '@vueuse/core';
import { buildUserIndexUrl } from '@/lib/buildUserIndexUrl';
import type { IUserIndexQuery } from '@/types/user-management';

/** Sama dengan pencarian acara: satu request per jeda ketik, bukan per huruf. */
const SEARCH_DEBOUNCE_MS = 350;

export interface IUserDirectoryFilters {
    search: Ref<string>;
    role: Ref<string>;
    isLoading: Ref<boolean>;
    hasActiveFilters: ComputedRef<boolean>;
    resetFilters: () => void;
}

/**
 * Kelola filter direktori pengguna: pencarian ber-debounce, chip role yang langsung
 * berlaku, indikator loading, dan reset. Setiap request membuang `page` karena hasil
 * filter berubah, tetapi mempertahankan `per_page` pilihan pengguna.
 */
export function useUserDirectoryFilters(query: IUserIndexQuery): IUserDirectoryFilters {
    const search = ref<string>(query.search);
    const role = ref<string>(query.role ?? '');
    const isLoading = ref<boolean>(false);

    // Reset mengubah dua ref sekaligus; watcher sinkron ditahan agar hanya satu request.
    let suppressWatchers = false;

    const hasActiveFilters = computed<boolean>(() => search.value.trim() !== '' || role.value !== '');

    function applyFilters(): void {
        router.get(
            buildUserIndexUrl({ search: search.value, role: role.value, perPage: query.per_page }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
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
        role,
        (): void => {
            if (suppressWatchers) return;

            // Chip role membawa kata kunci terbaru, jadi debounce pencarian yang tertunda dibuang.
            applySearchDebounced.cancel();
            applyFilters();
        },
        { flush: 'sync' }
    );

    /** Kosongkan pencarian dan role, lalu muat ulang direktori tepat satu kali. */
    function resetFilters(): void {
        applySearchDebounced.cancel();
        suppressWatchers = true;
        search.value = '';
        role.value = '';
        suppressWatchers = false;
        applyFilters();
    }

    return { search, role, isLoading, hasActiveFilters, resetFilters };
}
