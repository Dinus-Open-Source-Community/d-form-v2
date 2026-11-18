import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { destroy as forceDeleteEvent } from '@/actions/App/Http/Controllers/Dashboard/Events/ArchivedEventController';
import { restore as restoreEvent } from '@/actions/App/Http/Controllers/Dashboard/Events/EventController';
import { useToastNotifications } from '@/hooks/useToastNotifications';

/**
 * Aksi halaman daftar acara terarsip: pulihkan dan hapus permanen.
 *
 * Hapus permanen dijaga gate konfirmasi ketik judul: `confirmForceDelete` tidak
 * mengirim request selama `confirmationInput` (setelah trim) tidak sama dengan
 * judul target (setelah trim).
 *
 * Dua jenis penutupan dialog dibedakan: `dismiss*` (dipanggil dari `update:open`
 * karena `AlertDialogAction` menutup dialog sebelum handler confirm berjalan)
 * hanya menutup dialog dan mempertahankan target + input; `cancel*` (tombol batal
 * eksplisit) dan `onFinish` membersihkan target serta input.
 *
 * Toast sukses bukan tanggung jawab hook: kedua endpoint sudah mengirim flash
 * `toast` dari backend pada redirect-nya, jadi toast klien akan menggandakannya.
 */
export interface IArchivedEventsPage {
    restoreTarget: Ref<IEvent | null>;
    forceDeleteTarget: Ref<IEvent | null>;
    restoreDialogOpen: Ref<boolean>;
    forceDeleteDialogOpen: Ref<boolean>;
    confirmationInput: Ref<string>;
    isConfirmationMatched: ComputedRef<boolean>;
    isRestoring: Ref<boolean>;
    isForceDeleting: Ref<boolean>;
    requestRestore: (event: IEvent) => void;
    cancelRestore: () => void;
    dismissRestore: () => void;
    confirmRestore: () => void;
    requestForceDelete: (event: IEvent) => void;
    cancelForceDelete: () => void;
    dismissForceDelete: () => void;
    confirmForceDelete: () => void;
}

/** Path daftar acara terarsip; dipakai sebagai tujuan kembali setelah restore. */
const ARCHIVED_EVENTS_RETURN_TO = 'archived';

export function useArchivedEventsPage(): IArchivedEventsPage {
    const { handleInertiaFormErrors } = useToastNotifications();

    const restoreTarget = ref<IEvent | null>(null);
    const forceDeleteTarget = ref<IEvent | null>(null);
    const restoreDialogOpen = ref(false);
    const forceDeleteDialogOpen = ref(false);
    const confirmationInput = ref('');
    const isRestoring = ref(false);
    const isForceDeleting = ref(false);

    const isConfirmationMatched = computed<boolean>(() => {
        const target = forceDeleteTarget.value;
        if (target === null) return false;

        return confirmationInput.value.trim() === target.title.trim();
    });

    function clearConfirmationInput(): void {
        confirmationInput.value = '';
    }

    /** Buka dialog restore untuk satu acara; input konfirmasi selalu mulai kosong. */
    function requestRestore(event: IEvent): void {
        restoreTarget.value = event;
        restoreDialogOpen.value = true;
        clearConfirmationInput();
    }

    function cancelRestore(): void {
        restoreDialogOpen.value = false;
        restoreTarget.value = null;
        clearConfirmationInput();
    }

    /** Tutup karena dialog menutup sendiri; target tetap disimpan untuk handler confirm. */
    function dismissRestore(): void {
        restoreDialogOpen.value = false;
    }

    function closeRestoreDialog(): void {
        restoreDialogOpen.value = false;
        restoreTarget.value = null;
        clearConfirmationInput();
        isRestoring.value = false;
    }

    function confirmRestore(): void {
        const target = restoreTarget.value;
        if (target === null || isRestoring.value) return;

        isRestoring.value = true;
        router.post(
            restoreEvent(target.id).url,
            { return_to: ARCHIVED_EVENTS_RETURN_TO },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => handleInertiaFormErrors(errors, { title: 'Gagal memulihkan acara' }),
                onFinish: closeRestoreDialog,
            }
        );
    }

    /** Buka dialog hapus permanen; gate konfirmasi direset agar judul harus diketik ulang. */
    function requestForceDelete(event: IEvent): void {
        forceDeleteTarget.value = event;
        forceDeleteDialogOpen.value = true;
        clearConfirmationInput();
    }

    function cancelForceDelete(): void {
        forceDeleteDialogOpen.value = false;
        forceDeleteTarget.value = null;
        clearConfirmationInput();
    }

    /**
     * Tutup karena dialog menutup sendiri (`update:open`). Target dan input TIDAK
     * dikosongkan: `AlertDialogAction` menutup dialog lebih dulu, lalu handler confirm
     * masih harus melihat target dan gate yang cocok agar hapus permanen tidak jadi no-op.
     */
    function dismissForceDelete(): void {
        forceDeleteDialogOpen.value = false;
    }

    function closeForceDeleteDialog(): void {
        forceDeleteDialogOpen.value = false;
        forceDeleteTarget.value = null;
        clearConfirmationInput();
        isForceDeleting.value = false;
    }

    function confirmForceDelete(): void {
        const target = forceDeleteTarget.value;
        if (!isConfirmationMatched.value || target === null || isForceDeleting.value) return;

        isForceDeleting.value = true;
        router.delete(forceDeleteEvent({ event: target.id }).url, {
            preserveScroll: true,
            onError: (errors) => handleInertiaFormErrors(errors, { title: 'Gagal menghapus permanen acara' }),
            onFinish: closeForceDeleteDialog,
        });
    }

    return {
        restoreTarget,
        forceDeleteTarget,
        restoreDialogOpen,
        forceDeleteDialogOpen,
        confirmationInput,
        isConfirmationMatched,
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
    };
}
