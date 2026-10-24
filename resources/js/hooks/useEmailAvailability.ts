import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import axios from 'axios';
import EmailAvailabilityController from '@/actions/App/Http/Controllers/Auth/EmailAvailabilityController';

export type TEmailAvailabilityStatus = 'idle' | 'checking' | 'available' | 'taken' | 'error';

export interface IUseEmailAvailabilityOptions {
    /** Nilai email yang dipantau; perubahan memicu debounce lalu pengecekan. */
    email: Ref<string>;
    /** Jeda sebelum request dikirim, default 500 ms. */
    debounceMs?: number;
}

export interface IUseEmailAvailabilityState {
    status: Ref<TEmailAvailabilityStatus>;
    message: ComputedRef<string>;
}

/** Bentuk respons endpoint; nilai dicek ketat sebelum dipakai. */
interface IEmailAvailabilityResponse {
    available: boolean;
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
            const response = await axios.post<IEmailAvailabilityResponse>(
                EmailAvailabilityController.url(),
                { email: normalizedEmail },
                { signal: controller.signal },
            );

            if (controller.signal.aborted || !isStillCurrent(normalizedEmail)) return;
            status.value = response.data.available === true ? 'available' : 'taken';
        } catch (error) {
            if (controller.signal.aborted || !isStillCurrent(normalizedEmail)) return;
            // 422 = input belum valid menurut server; selain itu perlakukan sebagai gangguan jaringan.
            const isUnprocessable = axios.isAxiosError(error) && error.response?.status === 422;
            status.value = isUnprocessable ? 'idle' : 'error';
        } finally {
            if (activeController === controller) activeController = null;
        }
    }

    /** Validasi ringan lalu jadwalkan pengecekan setelah debounce. */
    function handleEmailChange(value: string): void {
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

    onBeforeUnmount(() => {
        clearDebounceTimer();
        abortActiveRequest();
    });

    return { status, message };
}
