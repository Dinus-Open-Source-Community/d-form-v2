<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import AppPagination from '@/components/core/AppPagination.vue';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import UserAccountPanel from '@/components/modules/users/UserAccountPanel.vue';
import UserDirectoryCard from '@/components/modules/users/UserDirectoryCard.vue';
import UserDirectoryEmptyState from '@/components/modules/users/UserDirectoryEmptyState.vue';
import UserDirectoryTable from '@/components/modules/users/UserDirectoryTable.vue';
import UserDirectoryToolbar from '@/components/modules/users/UserDirectoryToolbar.vue';
import { Button } from '@/components/ui/button';
import { USER_INDEX_PER_PAGE_OPTIONS } from '@/constants/userManagement';
import { setTopbar } from '@/hooks/useDashboardTopbar';
import { usePaginationNavigation } from '@/hooks/usePaginationNavigation';
import { useUserAccountPanel } from '@/hooks/useUserAccountPanel';
import { useUserDeletion } from '@/hooks/useUserDeletion';
import { useUserDirectoryFilters } from '@/hooks/useUserDirectoryFilters';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import type { IUserAccountForm, IUserIndexQuery, IUserRoleOption, IUsersPaginator } from '@/types/user-management';

defineOptions({ layout: DashboardLayout });

const props = defineProps<{
    users: IUsersPaginator;
    roleOptions: IUserRoleOption[];
    query: IUserIndexQuery;
}>();

const { search, role, isLoading, hasActiveFilters, resetFilters } = useUserDirectoryFilters(props.query);

const { isOpen, mode, form, openCreate, openEdit, close, submit } = useUserAccountPanel({
    query: props.query,
    users: props.users.data,
    roleOptions: props.roleOptions,
});

const { deleteTarget, showDeleteModal, isDeleting, requestDelete, cancelDelete, dismissDelete, confirmDelete } =
    useUserDeletion();

const { goToPage, changePerPage } = usePaginationNavigation();

onMounted(() => {
    setTopbar({ title: 'Pengguna', subtitle: 'Kelola akses akun' });
});

/** Satu formatter dipakai header dan toolbar agar angka selalu seragam. */
const countFormatter = new Intl.NumberFormat('id-ID');
const perPageOptions: number[] = [...USER_INDEX_PER_PAGE_OPTIONS];

const accountSummary = computed<string>(
    () => `${countFormatter.format(props.users.total)} akun, urut dari yang terbaru.`
);

const resultSummary = computed<string>(() => {
    const total = props.users.total;
    if (total === 0) return 'Tidak ada akun untuk ditampilkan';

    const first = (props.users.current_page - 1) * props.users.per_page + 1;
    const last = Math.min(props.users.current_page * props.users.per_page, total);

    return `Menampilkan ${countFormatter.format(first)}–${countFormatter.format(last)} dari ${countFormatter.format(total)} akun`;
});

const deleteDescription = computed<string>(() => {
    const name = deleteTarget.value?.name ?? '';

    return `Akun "${name}" akan dihapus dan tidak bisa dipakai masuk lagi.`;
});

/** Nilai field diedit di sini karena form milik halaman, bukan milik panel. */
function handleFieldUpdate(field: keyof IUserAccountForm, value: string): void {
    form[field] = value;
}
</script>

<template>
    <Head title="Pengguna" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <h1 class="font-display text-foreground text-2xl font-semibold sm:text-3xl">Semua akun</h1>
                <p class="text-muted-foreground mt-1.5 text-sm">{{ accountSummary }}</p>
            </div>
            <Button data-testid="user-create-trigger" class="w-full sm:w-auto" @click="openCreate">
                <Plus class="size-4" aria-hidden="true" />
                Buat akun
            </Button>
        </div>

        <section
            data-testid="user-directory"
            class="border-border bg-card overflow-hidden rounded-2xl border shadow-xs"
        >
            <UserDirectoryToolbar
                v-model:search="search"
                v-model:role="role"
                :role-options="roleOptions"
                :result-summary="resultSummary"
            />

            <div :aria-busy="isLoading ? 'true' : undefined">
                <div
                    v-if="isLoading"
                    data-testid="user-directory-skeleton"
                    aria-hidden="true"
                    class="divide-border/70 divide-y"
                >
                    <div v-for="row in 3" :key="row" class="flex items-center gap-3 px-4 py-4">
                        <div class="bg-muted/40 size-10 shrink-0 animate-pulse rounded-full" />
                        <div class="min-w-0 flex-1 space-y-2">
                            <div class="bg-muted/40 h-3 w-40 max-w-[60%] animate-pulse rounded-full" />
                            <div class="bg-muted/40 h-3 w-56 max-w-[80%] animate-pulse rounded-full" />
                        </div>
                        <div class="bg-muted/40 hidden h-5 w-16 animate-pulse rounded-full lg:block" />
                        <div class="bg-muted/40 hidden h-9 w-24 animate-pulse rounded-lg lg:block" />
                    </div>
                </div>

                <template v-else-if="users.data.length > 0">
                    <UserDirectoryTable
                        :users="users.data"
                        :role-options="roleOptions"
                        @edit="openEdit"
                        @delete="requestDelete"
                    />

                    <div class="grid gap-3 p-4 sm:grid-cols-2 lg:hidden">
                        <UserDirectoryCard
                            v-for="row in users.data"
                            :key="row.id"
                            :user="row"
                            :role-options="roleOptions"
                            @edit="openEdit"
                            @delete="requestDelete"
                        />
                    </div>
                </template>

                <UserDirectoryEmptyState
                    v-else
                    :has-active-filters="hasActiveFilters"
                    @create="openCreate"
                    @reset="resetFilters"
                />
            </div>

            <div v-if="users.total > 0" class="border-border border-t px-4 py-3">
                <AppPagination
                    :current-page="users.current_page"
                    :last-page="users.last_page"
                    :total="users.total"
                    :per-page="users.per_page"
                    :per-page-options="perPageOptions"
                    @page-change="goToPage"
                    @per-page-change="changePerPage"
                />
            </div>
        </section>

        <UserAccountPanel
            :is-open="isOpen"
            :mode="mode"
            :form="form"
            :role-options="roleOptions"
            @close="close"
            @submit="submit"
            @update:field="handleFieldUpdate"
        />

        <ConfirmationModal
            :open="showDeleteModal"
            title="Hapus akun?"
            :description="deleteDescription"
            confirm-text="Hapus akun"
            cancel-text="Batal"
            variant="destructive"
            :loading="isDeleting"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
            @update:open="
                (open) => {
                    if (!open) dismissDelete();
                }
            "
        />
    </div>
</template>
