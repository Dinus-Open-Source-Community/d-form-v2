import { onMounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { showFlashToast } from '@/lib/error-message';

type FlashToast = { type?: string; message?: string } | null | undefined;

/**
 * Side effect: tampilkan toast dari `page.flash.toast` saat mount dan tiap perubahan.
 * Sengaja ditunda ke `onMounted` agar Toaster layout sudah terpasang pada hard load.
 */
export function usePageFlashToast(): void {
    const page = usePage();

    // Toast harus dipanggil setelah Toaster ter-mount: pada hard load (mis. redirect
    // callback OAuth) layout baru memasang Toaster setelah setup selesai.
    const showToast = (): void => {
        showFlashToast((page.flash as { toast?: FlashToast } | undefined)?.toast);
    };

    onMounted(showToast);
    watch(() => (page.flash as { toast?: FlashToast } | undefined)?.toast, showToast);
}
