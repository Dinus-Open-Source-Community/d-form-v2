import { ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { updateStatus as updateEventStatus } from '@/actions/App/Http/Controllers/Dashboard/Events/EventController';
import { useToastNotifications } from '@/hooks/useToastNotifications';

export type TEventPublishStatus = IEvent['status'];

export interface IEventStatusToggle {
    /** Status tujuan yang menunggu konfirmasi; `null` saat modal tertutup. */
    targetStatus: Ref<TEventPublishStatus | null>;
    confirmOpen: Ref<boolean>;
    isSubmitting: Ref<boolean>;
    /** Buka modal konfirmasi untuk status tujuan; diabaikan saat request berjalan. */
    requestStatus: (status: TEventPublishStatus) => void;
    /** Kirim PATCH status setelah user mengonfirmasi. */
    confirm: () => void;
    /** Tutup modal tanpa mengirim request. */
    cancel: () => void;
}

/**
 * Aksi ubah status publikasi event (`draft` ⇄ `published`) beserta state modal
 * konfirmasinya. Sukses hanya menutup modal karena toast sukses dikirim server
 * lewat flash; kegagalan validasi diteruskan ke toast form.
 */
export function useEventStatusToggle(eventId: string): IEventStatusToggle {
    const { handleInertiaFormErrors } = useToastNotifications();
    const targetStatus = ref<TEventPublishStatus | null>(null);
    const confirmOpen = ref(false);
    const isSubmitting = ref(false);

    function requestStatus(status: TEventPublishStatus): void {
        if (isSubmitting.value) return;
        targetStatus.value = status;
        confirmOpen.value = true;
    }

    function cancel(): void {
        // Jangan membersihkan `targetStatus` di sini: dialog juga tertutup saat
        // tombol konfirmasi diklik, dan event close itu bisa berjalan sebelum
        // `confirm()`. Target dibersihkan setelah request sukses.
        if (isSubmitting.value) return;
        confirmOpen.value = false;
    }

    function confirm(): void {
        const status = targetStatus.value;
        if (status === null || isSubmitting.value) return;

        isSubmitting.value = true;
        router.patch(
            updateEventStatus.url(eventId),
            { status },
            {
                preserveScroll: true,
                onSuccess: () => {
                    confirmOpen.value = false;
                    targetStatus.value = null;
                },
                onError: (errors: Record<string, string>) => {
                    handleInertiaFormErrors(errors, { title: 'Gagal mengubah status event' });
                },
                onFinish: () => {
                    isSubmitting.value = false;
                },
            }
        );
    }

    return { targetStatus, confirmOpen, isSubmitting, requestStatus, confirm, cancel };
}
