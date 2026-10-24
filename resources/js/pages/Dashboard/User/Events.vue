<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import EmptyState from '@/components/modules/dashboard/EmptyState.vue';
import EventCard from '@/components/modules/dashboard/events/EventCard.vue';
import EventFilterBar from '@/components/modules/dashboard/events/EventFilterBar.vue';
import AppPagination from '@/components/core/AppPagination.vue';
import { Button } from '@/components/ui/button';
import { FilterX } from 'lucide-vue-next';
import { categoryLabelMap, sessionLabelMap } from '@/lib/dummyData';
import { toCategoryList } from '@/lib/eventCategories';
import { routes } from '@/lib/routes';
import { setTopbar } from '@/utils/composables/useDashboardTopbar';

defineOptions({ layout: DashboardLayout });

interface IEventPaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface IEventPagination {
    links: IEventPaginationLink[];
    currentPage?: number | null;
    hasMore: boolean;
}

interface IEventFilters {
    category: string | null;
    session: string | null;
}

const ALL_FILTER_VALUE = 'all';

const props = withDefaults(
    defineProps<{
        events: IEvent[];
        listMode?: 'mine' | 'browse';
        pagination?: IEventPagination;
        filters?: IEventFilters;
    }>(),
    { listMode: 'browse' }
);

const filterCategory = ref(props.filters?.category ?? ALL_FILTER_VALUE);
const filterSession = ref(props.filters?.session ?? ALL_FILTER_VALUE);

const isBrowse = computed((): boolean => props.listMode === 'browse');

const categoryOptions = computed((): { value: string; label: string }[] =>
    Object.entries(categoryLabelMap).map(([value, label]) => ({ value, label }))
);

const categoryFilterOptions = computed((): { value: string; label: string }[] => [
    { value: ALL_FILTER_VALUE, label: 'Semua kategori' },
    ...categoryOptions.value,
]);

/** Label sesi dari peta statis; fallback ke token bila tidak dikenal. */
function sessionLabelOf(token: string): string {
    return sessionLabelMap[token] ?? token;
}

/** Kumpulan token sesi unik dari acara yang dikirim server (mode "diikuti"). */
function collectSessionTokens(events: IEvent[]): string[] {
    const tokens = new Set<string>();
    for (const event of events) {
        for (const session of eventTokenList(event.session)) tokens.add(session);
    }
    return [...tokens];
}

const sessionOptions = computed((): { value: string; label: string }[] => {
    // Browse difilter di server, jadi opsi diambil dari peta statis agar tidak menyusut
    // mengikuti 5 acara di halaman aktif.
    const tokens = isBrowse.value ? Object.keys(sessionLabelMap) : collectSessionTokens(props.events);
    return tokens
        .sort((a, b) => sessionLabelOf(a).localeCompare(sessionLabelOf(b)))
        .map((value) => ({ value, label: sessionLabelOf(value) }));
});

const sessionFilterOptions = computed((): { value: string; label: string }[] => [
    { value: ALL_FILTER_VALUE, label: 'Semua sesi' },
    ...sessionOptions.value,
]);

function eventTokenList(value: string[] | string): string[] {
    if (Array.isArray(value)) return value.map((s) => s.trim()).filter(Boolean);
    return value
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
}

const hasActiveFilters = computed(
    (): boolean => filterCategory.value !== ALL_FILTER_VALUE || filterSession.value !== ALL_FILTER_VALUE
);

let requestedFilterKey = '';

/** Kirim filter aktif ke server sekali per kombinasi (browse); query kosong = semua acara. */
function requestBrowseFilter(query: Record<string, string>): void {
    const key = JSON.stringify(query);
    if (key === requestedFilterKey) return;
    requestedFilterKey = key;
    router.get(routes.member.browse, query, { preserveState: true, preserveScroll: true, replace: true });
}

/** Reset filter ke semua; mode browse memuat ulang daftar tanpa query filter. */
function clearFilters(): void {
    filterCategory.value = ALL_FILTER_VALUE;
    filterSession.value = ALL_FILTER_VALUE;
    if (isBrowse.value) requestBrowseFilter({});
}

watch([filterCategory, filterSession], (): void => {
    if (!isBrowse.value) return;

    const query: Record<string, string> = {};
    if (filterCategory.value !== ALL_FILTER_VALUE) query.category = filterCategory.value;
    if (filterSession.value !== ALL_FILTER_VALUE) query.session = filterSession.value;
    requestBrowseFilter(query);
});

const pageTitle = computed(() => (isBrowse.value ? 'Jelajah acara' : 'Acara diikuti'));
const pageSubtitle = computed(() =>
    isBrowse.value
        ? 'Lihat semua acara terpublikasi dan daftar sebagai peserta.'
        : 'Acara yang Anda daftar atau ikuti (tim / undangan yang masih aktif).'
);

const headTitle = computed(() => (isBrowse.value ? 'Jelajah acara' : 'Acara diikuti'));

/** Acara yang dirender: browse memakai halaman aktif dari server, mine difilter lokal. */
const filteredEvents = computed((): IEvent[] => {
    if (isBrowse.value) return props.events;

    let list = props.events;

    if (filterCategory.value !== ALL_FILTER_VALUE)
        list = list.filter((e) => toCategoryList(e.category).includes(filterCategory.value));
    if (filterSession.value !== ALL_FILTER_VALUE)
        list = list.filter((e) => eventTokenList(e.session).includes(filterSession.value));
    return list;
});

/** Paginasi simple (prev/next) hanya dirender bila backend mengirim minimal satu link. */
const shownPagination = computed((): IEventPagination | null =>
    props.pagination !== undefined && props.pagination.links.length > 0 ? props.pagination : null
);

const emptyTitle = computed(() => (isBrowse.value ? 'Tidak ada acara ditemukan' : 'Belum ada acara yang diikuti'));
const emptyDescription = computed(() =>
    isBrowse.value
        ? 'Sesuaikan pencarian atau filter kategori.'
        : 'Telusuri acara terbuka dan daftar untuk melihatnya di sini.'
);

onMounted(() => {
    setTopbar({ title: pageTitle.value, subtitle: pageSubtitle.value });
});
</script>

<template>
    <Head :title="headTitle" />

    <div class="flex flex-col gap-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <EventFilterBar
                v-model:category="filterCategory"
                v-model:session="filterSession"
                :category-options="categoryFilterOptions"
                :session-options="sessionFilterOptions"
            />
            <Button
                v-if="hasActiveFilters"
                variant="outline"
                size="sm"
                class="h-10 shrink-0 gap-2 border-dashed"
                type="button"
                @click="clearFilters"
            >
                <FilterX class="size-3.5 stroke-[1.75]" />
                Reset
            </Button>
        </div>

        <div v-if="filteredEvents.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <EventCard
                v-for="event in filteredEvents"
                :key="event.id"
                :event="event"
                :href="routes.member.event.show(event.slug)"
                :can-manage="false"
                :show-price="false"
                :alert-badge="event.pending_team_invitation_url ? 'Diundang' : null"
            />
        </div>

        <EmptyState
            v-else
            :title="emptyTitle"
            :description="emptyDescription"
            animation-url="https://lottie.host/4e039bf3-670e-4a0f-8a6c-1bee793bfc23/JkaGBMIxOz.json"
        >
            <Link
                v-if="!isBrowse"
                :href="routes.member.browse"
                class="text-primary text-sm font-medium underline-offset-4 hover:underline"
            >
                Jelajah semua acara
            </Link>
        </EmptyState>

        <template v-if="shownPagination">
            <AppPagination
                :links="shownPagination.links"
                :current-page="shownPagination.currentPage"
                total-label="acara"
            />
        </template>
    </div>
</template>
