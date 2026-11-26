import { onScopeDispose, ref } from 'vue';
import type { Ref } from 'vue';

/** Lama ikon centang tampil sebelum kembali ke ikon salin. */
export const COPY_FEEDBACK_DURATION_MS = 1000;

export interface ICopyFeedback {
    /** True selama umpan balik konfirmasi masih aktif. */
    active: Ref<boolean>;
    /** Nyalakan ulang umpan balik; panggilan berulang me-reset timer, bukan menumpuk. */
    trigger: () => void;
}

/**
 * Umpan balik visual singkat untuk aksi salin: `active` menyala saat `trigger`
 * dipanggil dan kembali false setelah `durationMs`.
 *
 * Timer dibersihkan saat effect scope berhenti (komponen unmount), sehingga tidak
 * ada timer yang menulis ke ref milik komponen yang sudah hilang.
 */
export function useCopyFeedback(durationMs: number = COPY_FEEDBACK_DURATION_MS): ICopyFeedback {
    const active = ref(false);
    let timer: ReturnType<typeof setTimeout> | null = null;

    function clearTimer(): void {
        if (timer === null) return;

        clearTimeout(timer);
        timer = null;
    }

    function trigger(): void {
        clearTimer();
        active.value = true;
        timer = setTimeout((): void => {
            active.value = false;
            timer = null;
        }, durationMs);
    }

    onScopeDispose((): void => {
        clearTimer();
        active.value = false;
    });

    return { active, trigger };
}
