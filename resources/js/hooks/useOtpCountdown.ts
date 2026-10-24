import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';

/** Jeda tick hitung mundur; satu detik cukup karena tampilan hanya menampilkan mm:ss. */
const TICK_MS = 1000;

export interface IUseOtpCountdownOptions {
    /** Waktu server saat halaman dirender; dipakai mengoreksi jam klien yang meleset. */
    serverNow: string;
    /** Batas berlaku kode (ISO8601) atau null bila tidak ada kode aktif. */
    expiresAt: string | null;
    /** Batas kirim ulang berikutnya (ISO8601) atau null bila sudah boleh kirim. */
    resendAvailableAt: string | null;
    /** Batas lock kirim (ISO8601) atau null bila tidak ada lock. */
    lockedUntil: string | null;
}

export interface IUseOtpCountdownState {
    /** Sisa detik sebelum kode kedaluwarsa. */
    expiresIn: ComputedRef<number>;
    /** Sisa detik sebelum kirim ulang diizinkan (mencakup cooldown dan lock). */
    resendIn: ComputedRef<number>;
    /** True bila kode sudah tidak berlaku. */
    isExpired: ComputedRef<boolean>;
    /** True bila lock kirim masih berjalan menurut deadline + offset server. */
    isLocked: ComputedRef<boolean>;
}

/** Ubah ISO8601 menjadi epoch ms; null/NaN dianggap "tidak ada batas". */
function parseDeadline(value: string | null): number | null {
    if (value === null) return null;

    const parsed = Date.parse(value);

    return Number.isNaN(parsed) ? null : parsed;
}

/**
 * Hitung mundur berbasis jam server untuk halaman OTP.
 * Offset diambil sekali saat mount agar jam klien yang salah tidak menggeser sisa waktu.
 */
export function useOtpCountdown(options: IUseOtpCountdownOptions): IUseOtpCountdownState {
    const offsetMs = (parseDeadline(options.serverNow) ?? Date.now()) - Date.now();
    const nowMs: Ref<number> = ref(Date.now() + offsetMs);

    let timer: ReturnType<typeof setInterval> | null = null;

    /** Sisa detik menuju deadline; 0 bila deadline kosong atau sudah lewat. */
    function secondsUntil(deadline: number | null): number {
        if (deadline === null) return 0;

        return Math.max(0, Math.ceil((deadline - nowMs.value) / 1000));
    }

    const expiresIn = computed<number>(() => secondsUntil(parseDeadline(options.expiresAt)));

    const resendIn = computed<number>(() => secondsUntil(parseDeadline(options.resendAvailableAt)));

    const isExpired = computed<boolean>(() => expiresIn.value === 0);

    const isLocked = computed<boolean>(() => secondsUntil(parseDeadline(options.lockedUntil)) > 0);

    /** Hentikan interval; aman dipanggil berkali-kali. */
    function stopTimer(): void {
        if (timer === null) return;

        clearInterval(timer);
        timer = null;
    }

    // Interval baru dibuat setelah mount agar tidak ada timer menggantung saat render SSR.
    onMounted(() => {
        if (expiresIn.value === 0 && resendIn.value === 0) return;

        timer = setInterval(() => {
            nowMs.value = Date.now() + offsetMs;

            if (expiresIn.value === 0 && resendIn.value === 0) stopTimer();
        }, TICK_MS);
    });

    onBeforeUnmount(stopTimer);

    return { expiresIn, resendIn, isExpired, isLocked };
}
