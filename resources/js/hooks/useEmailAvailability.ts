import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { checkEmailAvailability } from '@/adapters/http/auth';
import { isHttpRequestError } from '@/adapters/http/httpError';
import EmailAvailabilityController from '@/actions/App/Http/Controllers/Auth/EmailAvailabilityController';

export type TEmailAvailabilityStatus = 'idle' | 'checking' | 'available' | 'taken' | 'error';

export interface IUseEmailAvailabilityOptions {
    /** Nilai email yang dipantau; perubahan memicu debounce lalu pengecekan. */
    email: Ref<string>;
    /** Jeda sebelum request dikirim, default 500 ms. */
    debounceMs?: number;
    /**
     * Pemutus seluruh efek hook. Saat `false` watcher email menjadi no-op dan
     * timer/request berjalan dibatalkan. Default `true`. Dipakai panel register
     * yang tetap ter-mount ketika tab lain aktif.
     */
    enabled?: Ref<boolean> | boolean;
}

export interface IUseEmailAvailabilityState {
    status: Ref<TEmailAvailabilityStatus>;
    message: ComputedRef<string>;
}

const DEFAULT_DEBOUNCE_MS = 500;
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const STATUS_MESSAGES: Record<TEmailAvailabilityStatus, string> = {
    idle: '',
    checking: '',
    available: 'Email tersedia.',
    taken: 'Email sudah terdaftar.',
    error: 'Tidak dapat memeriksa email.',
};

/**
 * Cek ketersediaan email secara debounce tanpa submit.
 * Dipakai form daftar untuk memberi umpan balik status di dalam input.
 */
export function useEmailAvailability(options: IUseEmailAvailabilityOptions): IUseEmailAvailabilityState {
    const debounceMs = options.debounceMs ?? DEFAULT_DEBOUNCE_MS;
    const status = ref<TEmailAvailabilityStatus>('idle');
    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let activeController: AbortController | null = null;
    // Email yang status finalnya (`available`/`taken`) sedang ditampilkan.
    let resolvedEmail: string | null = null;

    const message = computed<string>(() => STATUS_MESSAGES[status.value]);

    /** Hentikan timer debounce yang belum sempat mengirim request. */
    function clearDebounceTimer(): void {
        if (debounceTimer === null) return;
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }

    /** Batalkan request berjalan agar respons usang tidak menimpa status terbaru. */
    function abortActiveRequest(): void {
        if (activeController === null) return;
        activeController.abort();
        activeController = null;
    }

    /** True bila email saat ini masih sama dengan email yang menunggu respons. */
    function isStillCurrent(normalizedEmail: string): boolean {
        return options.email.value.trim() === normalizedEmail;
    }

    /** Kirim satu pengecekan; respons yang sudah usang atau dibatalkan diabaikan. */
    async function checkAvailability(normalizedEmail: string): Promise<void> {
        abortActiveRequest();
        const controller = new AbortController();
        activeController = controller;

        try {
            const result = await checkEmailAvailability(
                EmailAvailabilityController.url(),
                normalizedEmail,
                controller.signal,
            );

            if (controller.signal.aborted || !isStillCurrent(normalizedEmail)) return;
            status.value = result.available === true ? 'available' : 'taken';
            resolvedEmail = normalizedEmail;
        } catch (error) {
            if (controller.signal.aborted || !isStillCurrent(normalizedEmail)) return;
            if (isHttpRequestError(error) && error.kind === 'aborted') return;
            // 422 = input belum valid menurut server; selain itu perlakukan sebagai gangguan jaringan.
            status.value = isHttpRequestError(error) && error.kind === 'validation' ? 'idle' : 'error';
        } finally {
            if (activeController === controller) activeController = null;
        }
    }

    /** True bila efek hook boleh berjalan (default: selalu aktif). */
    function isEffectEnabled(): boolean {
        const enabled = options.enabled;
        if (enabled === undefined) return true;
        return typeof enabled === 'boolean' ? enabled : enabled.value;
    }

    /** Hentikan timer/request berjalan lalu kembalikan status ke idle. */
    function resetAvailability(): void {
        clearDebounceTimer();
        abortActiveRequest();
        resolvedEmail = null;
        status.value = 'idle';
    }

    /** Validasi ringan lalu jadwalkan pengecekan setelah debounce. */
    function handleEmailChange(value: string): void {
        if (!isEffectEnabled()) return;

        clearDebounceTimer();
        abortActiveRequest();

        const normalizedEmail = value.trim();
        if (!EMAIL_PATTERN.test(normalizedEmail)) {
            status.value = 'idle';
            return;
        }

        status.value = 'checking';
        debounceTimer = setTimeout(() => {
            debounceTimer = null;
            void checkAvailability(normalizedEmail);
        }, debounceMs);
    }

    watch(options.email, handleEmailChange, { immediate: true });

    // Saat panel diaktifkan, cek nilai email terkini; saat dinonaktifkan, hentikan
    // semua efek agar panel tersembunyi tidak memicu request atau mengubah status.
    watch(isEffectEnabled, (enabled: boolean) => {
        if (!enabled) {
            clearDebounceTimer();
            abortActiveRequest();
            // Cek yang terpotong tidak boleh tampak masih berjalan.
            if (status.value === 'checking') status.value = 'idle';
            return;
        }
        // Hasil final hanya valid untuk email yang benar-benar di-resolve; bila email
        // berubah saat panel tersembunyi, nilai lama tidak boleh menutupi email baru.
        const hasFinalStatusForCurrentEmail =
            (status.value === 'available' || status.value === 'taken') &&
            resolvedEmail === options.email.value.trim();
        if (hasFinalStatusForCurrentEmail) return;
        handleEmailChange(options.email.value);
    });

    onBeforeUnmount(resetAvailability);

    return { status, message };
}
