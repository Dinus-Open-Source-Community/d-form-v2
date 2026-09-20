<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import EmptyState from '@/components/modules/dashboard/EmptyState.vue';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import EventCard from '@/components/modules/dashboard/events/EventCard.vue';
import EventFilterBar from '@/components/modules/dashboard/events/EventFilterBar.vue';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Plus, ChevronsLeft, ChevronsRight } from 'lucide-vue-next';
import {
    index as eventsIndex,
    destroy as destroyEvent,
} from '@/actions/App/Http/Controllers/Dashboard/Events/EventController';
import { routes } from '@/lib/routes';
import { setTopbar } from '@/utils/composables/useDashboardTopbar';
import useAuth from '@/utils/composables/useAuth';

defineOptions({ layout: DashboardLayout });

const page = usePage<IProps>();
const user = useAuth(page.props);
const canManageEvents = computed(() => user.value?.can_manage_events === true);

onMounted(() => {
    setTopbar({ title: 'Acara', subtitle: 'Kelola acara & pendaftaran' });
});

interface Paginator {
    data: IEvent[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    events: Paginator;
    filterOptions: {
        categories: { value: string; label: string }[];
        sessions: { value: string; label: string }[];
        statuses: { value: string; label: string }[];
    };
    query: {
        search?: string;
        filter?: {
            categories?: string[];
            sessions?: string[];
            statuses?: string[];
            showTrashed?: boolean;
            timeline?: string;
        };
        sort?: { by: string; order: string };
        per_page?: number;
    };
}>();

const searchQuery = ref('');
const filterCategory = ref('all');
const filterSession = ref('all');

/** Mencegah permintaan ganda saat state disamakan ulang dari URL/Inertia. */
let suppressFilterApply = false;

const categoryOptions = computed(() => props.filterOptions.categories);
const sessionOptions = computed(() => props.filterOptions.sessions);

const categoryFilterOptions = computed(() => [{ value: 'all', label: 'Semua kategori' }, ...categoryOptions.value]);

const sessionFilterOptions = computed(() => [{ value: 'all', label: 'Semua sesi' }, ...sessionOptions.value]);

function readQueryFromProps() {
    suppressFilterApply = true;
    const q = props.query;
    searchQuery.value = (q?.search as string) ?? '';
    filterCategory.value = q?.filter?.categories?.[0] ?? 'all';
    filterSession.value = q?.filter?.sessions?.[0] ?? 'all';
    void nextTick(() => {
        suppressFilterApply = false;
    });
}

readQueryFromProps();
watch(
    () => props.query,
    () => readQueryFromProps(),
    { deep: true }
);

function buildQueryParams(page?: number) {
    const params: Record<string, unknown> = {};
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    if (page && page > 1) params.page = page;

    const filter: Record<string, unknown> = {};
    if (filterCategory.value !== 'all') filter.categories = [filterCategory.value];
    if (filterSession.value !== 'all') filter.sessions = [filterSession.value];

    if (Object.keys(filter).length > 0) params.filter = filter;

    return params;
}

function applyFilters() {
    if (suppressFilterApply) return;
    router.get(eventsIndex().url, buildQueryParams() as never, {
        preserveState: true,
        preserveScroll: true,
        only: ['events', 'query'],
    });
}

watch([filterCategory, filterSession], applyFilters);

function goToPage(page: number) {
    router.get(eventsIndex().url, buildQueryParams(page) as never, {
        preserveState: true,
        preserveScroll: true,
        only: ['events', 'query'],
    });
}

const eventsList = computed(() => props.events.data);

const currentPage = computed(() => props.events.current_page);
const lastPage = computed(() => props.events.last_page);
const totalEvents = computed(() => props.events.total);

function confirmDelete(event: IEvent): void {
    deleteTarget.value = event;
    deleteDialogOpen.value = true;
}

/** Modal konfirmasi hapus acara. */
const deleteTarget = ref<IEvent | null>(null);
const deleteDialogOpen = ref(false);

function cancelDelete(): void {
    deleteDialogOpen.value = false;
}

function handleDeleteConfirm(): void {
    if (!deleteTarget.value) return;
    router.delete(destroyEvent({ event: deleteTarget.value.id }).url);
    deleteTarget.value = null;
    deleteDialogOpen.value = false;
}
</script>

<template>
    <Head title="Daftar acara" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <EventFilterBar
                v-model:category="filterCategory"
                v-model:session="filterSession"
                :category-options="categoryFilterOptions"
                :session-options="sessionFilterOptions"
            />
            <Button as-child class="w-full shadow-sm sm:w-auto">
                <Link :href="routes.admin.events.create" class="inline-flex items-center gap-2">
                    <Plus class="size-4" />
                    Buat acara
                </Link>
            </Button>
        </div>

        <div
            v-if="eventsList.length > 0"
            class="grid min-w-0 gap-4 sm:grid-cols-2 sm:gap-6 xl:grid-cols-3 2xl:grid-cols-4"
        >
            <EventCard
                v-for="event in eventsList"
                :key="event.id"
                :event="event"
                :href="routes.admin.events.show(event.id)"
                :can-manage="canManageEvents"
                @delete="confirmDelete"
            />
        </div>

        <ConfirmationModal
            :open="deleteDialogOpen"
            :title="`Hapus acara ${deleteTarget?.title ?? ''}?`"
            description="Tindakan ini tidak dapat dibatalkan. Data acara dan pendaftaran terkait akan terhapus."
            confirm-text="Hapus"
            cancel-text="Batal"
            variant="destructive"
            @confirm="handleDeleteConfirm"
            @cancel="cancelDelete"
            @update:open="(v) => { deleteDialogOpen = v }"
        />

        <EmptyState
            v-if="eventsList.length === 0"
            title="Tidak ada acara"
            description="Sesuaikan pencarian, kategori, atau sesi — atau buat acara baru."
            animation-name="errorState"
        />

        <Card
            v-if="lastPage > 1"
            class="border-border/70 flex flex-col gap-3 rounded-2xl border px-4 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-5"
        >
            <p class="text-muted-foreground text-center text-sm sm:text-left">
                Halaman
                <span class="text-foreground font-medium tabular-nums">{{ currentPage }}</span>
                /
                <span class="tabular-nums">{{ lastPage }}</span>
                — total
                <span class="text-foreground font-medium tabular-nums">{{ totalEvents }}</span>
                acara
            </p>
            <div class="flex flex-wrap items-center justify-center gap-2">
                <Button
                    radius="icon"
                    variant="outline"
                    size="icon"
                    class="size-9"
                    :disabled="currentPage <= 1"
                    @click="goToPage(1)"
                >
                    <ChevronsLeft class="size-4" />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 px-4"
                    :disabled="currentPage <= 1"
                    @click="goToPage(currentPage - 1)"
                >
                    Sebelumnya
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 px-4"
                    :disabled="currentPage >= lastPage"
                    @click="goToPage(currentPage + 1)"
                >
                    Berikutnya
                </Button>
                <Button
                    radius="icon"
                    variant="outline"
                    size="icon"
                    class="size-9"
                    :disabled="currentPage >= lastPage"
                    @click="goToPage(lastPage)"
                >
                    <ChevronsRight class="size-4" />
                </Button>
            </div>
        </Card>
    </div>
</template>
