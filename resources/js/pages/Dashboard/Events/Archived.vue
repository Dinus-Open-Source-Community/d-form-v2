<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import EmptyState from '@/components/core/EmptyState.vue';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import TypeToConfirmDialog from '@/components/core/TypeToConfirmDialog.vue';
import EventCard from '@/components/modules/dashboard/events/EventCard.vue';
import EventFilterBar from '@/components/modules/dashboard/events/EventFilterBar.vue';
import AppPagination from '@/components/core/AppPagination.vue';
import { index as archivedEventsIndex } from '@/actions/App/Http/Controllers/Dashboard/Events/ArchivedEventController';
import { routes } from '@/lib/routes';
import { setTopbar } from '@/hooks/useDashboardTopbar';
import { useAuth } from '@/hooks/useAuth';
import { usePaginationNavigation } from '@/hooks/usePaginationNavigation';
import { useEventListQueryFilters } from '@/hooks/useEventListQueryFilters';
import { useArchivedEventsPage } from '@/hooks/useArchivedEventsPage';

defineOptions({ layout: DashboardLayout });

interface IArchivedEventsPaginator {
    data: IEvent[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    events: IArchivedEventsPaginator;
    filterOptions: {
        categories: { value: string; label: string }[];
        sessions: { value: string; label: string }[];
    };
    query: {
        search?: string;
        filter?: {
            categories?: string[];
            sessions?: string[];
            timeline?: string;
        };
        sort?: { by: string; order: string };
        per_page?: number;
    };
}>();

const page = usePage();
const user = useAuth(page.props);
const canManageEvents = computed<boolean>(() => user.value?.can_manage_events === true);

onMounted(() => {
    setTopbar({ title: 'Arsip acara', subtitle: 'Acara yang diarsipkan & dapat dipulihkan' });
});

const { goToPage, changePerPage } = usePaginationNavigation();

const { filterCategory, filterSession } = useEventListQueryFilters({
    query: () => props.query,
    perPage: () => props.events.per_page,
    targetUrl: archivedEventsIndex().url,
});

const {
    restoreTarget,
    forceDeleteTarget,
    restoreDialogOpen,
    forceDeleteDialogOpen,
    confirmationInput,
    isRestoring,
    isForceDeleting,
    requestRestore,
    cancelRestore,
    dismissRestore,
    confirmRestore,
    requestForceDelete,
    cancelForceDelete,
    dismissForceDelete,
    confirmForceDelete,
} = useArchivedEventsPage();

const categoryFilterOptions = computed(() => [
    { value: 'all', label: 'Semua kategori' },
    ...props.filterOptions.categories,
]);

const sessionFilterOptions = computed(() => [{ value: 'all', label: 'Semua sesi' }, ...props.filterOptions.sessions]);

const eventsList = computed(() => props.events.data);
</script>

<template>
    <Head title="Arsip acara" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <EventFilterBar
            v-model:category="filterCategory"
            v-model:session="filterSession"
            :category-options="categoryFilterOptions"
            :session-options="sessionFilterOptions"
        />

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
                variant="archived"
                @restore="requestRestore"
                @force-delete="requestForceDelete"
            />
        </div>

        <ConfirmationModal
            :open="restoreDialogOpen"
            :title="`Pulihkan acara ${restoreTarget?.title ?? ''}?`"
            description="Acara akan tampil kembali di daftar acara dan dapat menerima pendaftaran sesuai jadwalnya."
            confirm-text="Pulihkan"
            cancel-text="Batal"
            :loading="isRestoring"
            @confirm="confirmRestore"
            @cancel="cancelRestore"
            @update:open="dismissRestore"
        />

        <TypeToConfirmDialog
            v-model="confirmationInput"
            :open="forceDeleteDialogOpen"
            :title="`Hapus permanen ${forceDeleteTarget?.title ?? ''}?`"
            description="Tindakan ini tidak dapat dibatalkan. Data acara, pendaftaran, dan jawaban formulir akan terhapus selamanya."
            :expected-text="forceDeleteTarget?.title ?? ''"
            input-label="Ketik judul acara untuk konfirmasi."
            confirm-text="Hapus permanen"
            cancel-text="Batal"
            :loading="isForceDeleting"
            @confirm="confirmForceDelete"
            @cancel="cancelForceDelete"
            @update:open="dismissForceDelete"
        />

        <EmptyState
            v-if="eventsList.length === 0"
            kind="filter"
            title="Tidak ada acara di arsip"
            description="Acara yang dihapus akan muncul di sini dan dapat dipulihkan."
        />

        <AppPagination
            v-if="events.last_page > 1"
            :current-page="events.current_page"
            :last-page="events.last_page"
            :total="events.total"
            :per-page="events.per_page"
            @page-change="goToPage"
            @per-page-change="changePerPage"
        />
    </div>
</template>
