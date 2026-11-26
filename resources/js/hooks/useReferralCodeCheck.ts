import { onBeforeUnmount, ref, toValue, watch } from 'vue';
import type { MaybeRefOrGetter, Ref } from 'vue';
import { checkReferralCode } from '@/adapters/http/referralCheck';
import { isHttpRequestError } from '@/adapters/http/httpError';
import { REFERRAL_CHECK_DEBOUNCE_MS } from '@/constants/referral';
import type { IFormPriceQuote } from '@/types/form';

export type TReferralCheckStatus = 'idle' | 'checking' | 'valid' | 'invalid';

export interface IUseReferralCodeCheckOptions {
    /** Endpoint `POST { code }`. */
    url: MaybeRefOrGetter<string>;
    /** Nilai input kode referral yang dipantau. */
    code: MaybeRefOrGetter<string>;
    /** `false` menghentikan debounce/request, mis. saat form diblokir. */
    enabled: MaybeRefOrGetter<boolean>;
}

export interface IReferralCodeCheck {
    status: Ref<TReferralCheckStatus>;
    message: Ref<string>;
    /** Quote harga dari server; null sampai ada kode valid. */
    price: Ref<IFormPriceQuote | null>;
    /** Jalankan pengecekan langsung tanpa debounce; dipakai test. */
    check: () => Promise<void>;
}

const VALID_MESSAGE = 'Kode referral valid.';
const INVALID_FALLBACK_MESSAGE = 'Kode referral tidak valid.';
const CHECK_FAILED_MESSAGE = 'Gagal memeriksa kode referral.';

/** Spasi luar dan kapitalisasi tidak mengubah identitas kode. */
function normalizeReferralCode(code: string): string {
    return code.trim().toUpperCase();
}

/**
 * Cek kode referral secara debounce saat peserta mengetik. Request berjalan
 * dibatalkan saat kode berubah atau komponen dilepas; respons usang diabaikan.
 */
export function useReferralCodeCheck(options: IUseReferralCodeCheckOptions): IReferralCodeCheck {
    const status = ref<TReferralCheckStatus>('idle');
    const message = ref('');
    const price = ref<IFormPriceQuote | null>(null);
    let debounceTimer: ReturnType<typeof setTimeout> | null = null;
    let activeController: AbortController | null = null;
    // Naik setiap check dimulai; mencegah respons lama menimpa status terbaru.
    let requestSequence = 0;

    function clearDebounceTimer(): void {
        if (debounceTimer === null) return;
        clearTimeout(debounceTimer);
        debounceTimer = null;
    }

    function abortActiveRequest(): void {
        if (activeController === null) return;
        activeController.abort();
        activeController = null;
    }

    function isEffectEnabled(): boolean {
        return toValue(options.enabled);
    }

    function resetToIdle(): void {
        status.value = 'idle';
        message.value = '';
        price.value = null;
    }

    async function runCheck(normalizedCode: string): Promise<void> {
        if (normalizedCode === '' || !isEffectEnabled()) return;

        abortActiveRequest();
        const controller = new AbortController();
        activeController = controller;
        const sequence = ++requestSequence;

        try {
            const result = await checkReferralCode(toValue(options.url), normalizedCode, controller.signal);

            if (sequence !== requestSequence || controller.signal.aborted) return;

            if (result.valid) {
                status.value = 'valid';
                message.value = VALID_MESSAGE;
                price.value = result.price;
                return;
            }

            status.value = 'invalid';
            message.value = result.message ?? INVALID_FALLBACK_MESSAGE;
            price.value = null;
        } catch (error) {
            if (sequence !== requestSequence || controller.signal.aborted) return;
            if (isHttpRequestError(error) && error.kind === 'aborted') return;
            status.value = 'invalid';
            message.value = CHECK_FAILED_MESSAGE;
            price.value = null;
        } finally {
            if (activeController === controller) activeController = null;
        }
    }

    function scheduleCheck(): void {
        clearDebounceTimer();
        abortActiveRequest();

        if (!isEffectEnabled()) {
            resetToIdle();
            return;
        }

        const normalizedCode = normalizeReferralCode(toValue(options.code));
        if (normalizedCode === '') {
            resetToIdle();
            return;
        }

        status.value = 'checking';
        message.value = '';
        price.value = null;
        debounceTimer = setTimeout(() => {
            debounceTimer = null;
            void runCheck(normalizedCode);
        }, REFERRAL_CHECK_DEBOUNCE_MS);
    }

    async function check(): Promise<void> {
        clearDebounceTimer();
        await runCheck(normalizeReferralCode(toValue(options.code)));
    }

    watch(() => [normalizeReferralCode(toValue(options.code)), isEffectEnabled()] as const, scheduleCheck, {
        immediate: true,
    });

    onBeforeUnmount(() => {
        clearDebounceTimer();
        abortActiveRequest();
    });

    return { status, message, price, check };
}
