import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { routes } from '@/lib/routes';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import type { IManagedUser } from '@/types/user-management';

/** Pesan gagal yang aman ditampilkan; detail teknis tetap di log server. */
const DELETE_FAILURE_MESSAGE = 'Akun gagal dihapus. Coba lagi.';

export interface IUserDeletion {
    deleteTarget: Ref<IManagedUser | null>;
    showDeleteModal: ComputedRef<boolean>;
    isDeleting: Ref<boolean>;
    requestDelete: (user: IManagedUser) => void;
    cancelDelete: () => void;
    dismissDelete: () => void;
    confirmDelete: () => void;
}

/**
 * Penghapusan akun dengan konfirmasi, dipakai direktori dan halaman detail.
 * Target hanya dipasang bila server mengizinkan (`can_delete`).
 *
 * `AlertDialogAction` menutup dialog sebelum handler `confirm` sempat berjalan, jadi
 * penutupan dari dialog (`dismissDelete`) tidak boleh menghapus target. Hanya `cancelDelete`
 * dan kesuksesan request yang membersihkannya; kegagalan membuka dialog lagi.
 */
export function useUserDeletion(): IUserDeletion {
    const { showErrorToast } = useToastNotifications();
    const deleteTarget = ref<IManagedUser | null>(null);
    const isDialogOpen = ref<boolean>(false);
    const isDeleting = ref<boolean>(false);
    const showDeleteModal = computed<boolean>(() => isDialogOpen.value);

    function requestDelete(user: IManagedUser): void {
        if (!user.can_delete) return;

        deleteTarget.value = user;
        isDialogOpen.value = true;
    }

    function cancelDelete(): void {
        if (isDeleting.value) return;

        isDialogOpen.value = false;
        deleteTarget.value = null;
    }

    /** Tutup karena dialog menutup sendiri; target tetap disimpan untuk handler confirm. */
    function dismissDelete(): void {
        isDialogOpen.value = false;
    }

    function confirmDelete(): void {
        const target = deleteTarget.value;
        if (target === null || isDeleting.value) return;

        isDeleting.value = true;
        router.delete(routes.admin.users.destroy(target.id), {
            preserveScroll: true,
            onStart: (): void => {
                isDeleting.value = true;
            },
            onSuccess: (): void => {
                isDialogOpen.value = false;
                deleteTarget.value = null;
            },
            onError: (): void => {
                showErrorToast(DELETE_FAILURE_MESSAGE);
                isDialogOpen.value = true;
            },
            onFinish: (): void => {
                isDeleting.value = false;
            },
        });
    }

    return { deleteTarget, showDeleteModal, isDeleting, requestDelete, cancelDelete, dismissDelete, confirmDelete };
}
