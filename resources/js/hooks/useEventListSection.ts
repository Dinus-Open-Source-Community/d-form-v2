import { computed } from 'vue';
import { unref } from 'vue';
import type { ComputedRef, MaybeRef } from 'vue';
import { findCursorUrls } from '@/lib/pagination';
import { useEventSearch, type IEventSearchState } from '@/hooks/useEventSearch';
import { usePaginationNavigation } from '@/hooks/usePaginationNavigation';
import { useViewportReveal } from '@/hooks/useViewportReveal';
import type { IUseViewportRevealResult } from '@/hooks/useViewportReveal';

export interface IEventListPaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface IEventListPagination {
    links: IEventListPaginationLink[];
    currentPage?: number | null;
    hasMore: boolean;
}

export interface IEventListFilters {
    q: string | null;
}

export interface IEventListSectionOptions {
    filters?: MaybeRef<IEventListFilters | undefined>;
    pagination?: MaybeRef<IEventListPagination | undefined>;
}

export interface IEventListSectionState extends IEventSearchState {
    visible: IUseViewportRevealResult['visible'];
    activeQuery: ComputedRef<string>;
    cursorUrls: ComputedRef<{ previous: string | null; next: string | null }>;
    goToCursorUrl: (url: string) => void;
}

/** Mengelola state pencarian, pagination, dan reveal untuk section daftar event. */
export function useEventListSection(options: IEventListSectionOptions = {}): IEventListSectionState {
    const { visible } = useViewportReveal({ targetId: 'event-list', threshold: 0.05 });
    const initialFilters = unref(options.filters);
    const { query, cooldownSeconds, isCoolingDown, requestDebouncedSearch, resetSearch } =
        useEventSearch(initialFilters);
    const { goToCursorUrl } = usePaginationNavigation();

    const activeQuery = computed<string>(() => unref(options.filters)?.q?.trim() ?? '');
    const cursorUrls = computed<{ previous: string | null; next: string | null }>(() =>
        findCursorUrls(unref(options.pagination)?.links ?? [])
    );

    return {
        query,
        cooldownSeconds,
        isCoolingDown,
        requestDebouncedSearch,
        resetSearch,
        visible,
        activeQuery,
        cursorUrls,
        goToCursorUrl,
    };
}
