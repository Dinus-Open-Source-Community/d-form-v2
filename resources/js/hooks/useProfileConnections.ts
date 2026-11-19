import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { assignUrl } from '@/adapters/browser/navigation';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import { routes } from '@/lib/routes';
import type { TProfileProvider } from '@/types/profile';

/** Aksi baris koneksi akun; status koneksi sendiri tetap berasal dari prop server. */
export interface IProfileConnectionActions {
    /** Provider yang permintaan pemutusannya sedang berjalan; `null` saat diam. */
    pendingProvider: Ref<TProfileProvider | null>;
    /** Provider yang menunggu konfirmasi pemutusan; `null` saat dialog tertutup. */
    disconnectTarget: Ref<TProfileProvider | null>;
    isConfirmOpen: ComputedRef<boolean>;
    /** Mulai alur OAuth; halaman ditinggalkan sehingga tidak ada state tertunda. */
    connect: (provider: TProfileProvider) => void;
    /** Buka konfirmasi pemutusan untuk satu provider. */
    requestDisconnect: (provider: TProfileProvider) => void;
    /** Kirim DELETE untuk provider yang sedang dikonfirmasi. */
    confirmDisconnect: () => void;
    /** Tutup konfirmasi tanpa memutus koneksi. */
    cancelDisconnect: () => void;
}

/**
 * Aksi hubungkan dan putuskan koneksi OAuth. `connect` menyerahkan alurnya ke
 * server (`assignUrl`) karena intent bind disimpan di session dan callback
 * provider harus kembali ke `#koneksi`; `disconnect` memakai `router.delete`
 * supaya daftar koneksi diperbarui tanpa memuat ulang halaman. Toast sukses
 * datang dari flash server, jadi hook ini hanya menangani kegagalan.
 */
export function useProfileConnections(): IProfileConnectionActions {
    const { handleInertiaFormErrors } = useToastNotifications();
    const pendingProvider = ref<TProfileProvider | null>(null);
    const disconnectTarget = ref<TProfileProvider | null>(null);

    const isConfirmOpen = computed<boolean>(() => disconnectTarget.value !== null);

    function connect(provider: TProfileProvider): void {
        if (pendingProvider.value !== null) return;

        assignUrl(routes.dashboard.profileConnectionRedirect(provider));
    }

    function requestDisconnect(provider: TProfileProvider): void {
        if (pendingProvider.value !== null) return;

        disconnectTarget.value = provider;
    }

    function confirmDisconnect(): void {
        const provider = disconnectTarget.value;
        if (provider === null || pendingProvider.value !== null) return;

        pendingProvider.value = provider;
        router.delete(routes.dashboard.profileConnectionDestroy(provider), {
            preserveScroll: true,
            onSuccess: () => {
                disconnectTarget.value = null;
            },
            onError: (errors: Record<string, string>) => {
                handleInertiaFormErrors(errors, { title: 'Gagal memutus koneksi akun' });
            },
            onFinish: () => {
                pendingProvider.value = null;
            },
        });
    }

    function cancelDisconnect(): void {
        // Target tidak dibersihkan saat request berjalan: dialog juga tertutup
        // lewat event close setelah konfirmasi diklik.
        if (pendingProvider.value !== null) return;

        disconnectTarget.value = null;
    }

    return {
        pendingProvider,
        disconnectTarget,
        isConfirmOpen,
        connect,
        requestDisconnect,
        confirmDisconnect,
        cancelDisconnect,
    };
}
