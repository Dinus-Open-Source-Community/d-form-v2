import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, ref } from 'vue';
import { checkReferralCode } from '@/adapters/http/referralCheck';
import type { IReferralCodeCheckResult } from '@/adapters/http/referralCheck';
import { HttpRequestError } from '@/adapters/http/httpError';
import { REFERRAL_CHECK_DEBOUNCE_MS } from '@/constants/referral';
import { useReferralCodeCheck } from '@/hooks/useReferralCodeCheck';

vi.mock('@/adapters/http/referralCheck', () => ({
    checkReferralCode: vi.fn(),
}));

const checkReferralCodeMock = vi.mocked(checkReferralCode);

interface IDeferredCheck {
    promise: Promise<IReferralCodeCheckResult>;
    resolve: (value: IReferralCodeCheckResult) => void;
}

/** Deferred agar test bisa mengendalikan kapan respons request datang. */
function createDeferredCheck(): IDeferredCheck {
    let resolveFn: (value: IReferralCodeCheckResult) => void = () => {};
    const promise = new Promise<IReferralCodeCheckResult>((resolve) => {
        resolveFn = resolve;
    });

    return { promise, resolve: resolveFn };
}

/** Jalankan timer debounce lalu biarkan microtask status memperbarui ref. */
async function advanceDebounce(): Promise<void> {
    await vi.advanceTimersByTimeAsync(REFERRAL_CHECK_DEBOUNCE_MS);
    await nextTick();
}

beforeEach(() => {
    vi.useFakeTimers();
    checkReferralCodeMock.mockReset();
    checkReferralCodeMock.mockResolvedValue({ valid: true, message: null, price: null });
});

afterEach(() => {
    vi.clearAllTimers();
    vi.useRealTimers();
});

describe('useReferralCodeCheck', () => {
    it('mengirim satu request setelah debounce, bukan sebelum', async () => {
        const state = useReferralCodeCheck({
            url: () => '/referral-check',
            code: ref('abc-123'),
            enabled: () => true,
        });

        expect(state.status.value).toBe('checking');

        await vi.advanceTimersByTimeAsync(REFERRAL_CHECK_DEBOUNCE_MS - 1);
        expect(checkReferralCodeMock).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(1);
        expect(checkReferralCodeMock).toHaveBeenCalledTimes(1);
        expect(checkReferralCodeMock.mock.calls[0]?.[1]).toBe('ABC-123');
        expect(state.status.value).toBe('valid');
        expect(state.message.value).toBe('Kode referral valid.');
    });

    it('kode kosong tetap idle tanpa request', async () => {
        const state = useReferralCodeCheck({
            url: () => '/referral-check',
            code: ref('   '),
            enabled: () => true,
        });

        await advanceDebounce();

        expect(checkReferralCodeMock).not.toHaveBeenCalled();
        expect(state.status.value).toBe('idle');
        expect(state.message.value).toBe('');
    });

    it('mengabaikan respons lama setelah kode berubah', async () => {
        const first = createDeferredCheck();
        const second = createDeferredCheck();
        checkReferralCodeMock.mockReturnValueOnce(first.promise).mockReturnValueOnce(second.promise);

        const code = ref('KODE-LAMA');
        const state = useReferralCodeCheck({ url: () => '/referral-check', code, enabled: () => true });

        await advanceDebounce();
        expect(checkReferralCodeMock).toHaveBeenCalledTimes(1);

        code.value = 'KODE-BARU';
        await nextTick();
        await advanceDebounce();
        expect(checkReferralCodeMock).toHaveBeenCalledTimes(2);

        first.resolve({ valid: false, message: 'Kode lama tidak valid.', price: null });
        await nextTick();
        expect(state.status.value).toBe('checking');

        second.resolve({ valid: true, message: null, price: null });
        await nextTick();
        expect(state.status.value).toBe('valid');
        expect(state.message.value).toBe('Kode referral valid.');
    });

    it('menampilkan pesan server saat kode ditolak', async () => {
        checkReferralCodeMock.mockResolvedValue({
            valid: false,
            message: 'Kode referral sudah kedaluwarsa.',
            price: null,
        });

        const state = useReferralCodeCheck({
            url: () => '/referral-check',
            code: ref('kadaluwarsa'),
            enabled: () => true,
        });

        await state.check();

        expect(state.status.value).toBe('invalid');
        expect(state.message.value).toBe('Kode referral sudah kedaluwarsa.');
    });

    it('memakai pesan fallback saat kode ditolak tanpa pesan server', async () => {
        checkReferralCodeMock.mockResolvedValue({ valid: false, message: null, price: null });

        const state = useReferralCodeCheck({
            url: () => '/referral-check',
            code: ref('salah'),
            enabled: () => true,
        });

        await state.check();

        expect(state.status.value).toBe('invalid');
        expect(state.message.value).toBe('Kode referral tidak valid.');
    });

    it('memetakan kegagalan request ke status invalid', async () => {
        checkReferralCodeMock.mockRejectedValueOnce(
            new HttpRequestError({ kind: 'server', status: 500, message: 'boom' })
        );

        const state = useReferralCodeCheck({
            url: () => '/referral-check',
            code: ref('apa-saja'),
            enabled: () => true,
        });

        await state.check();

        expect(state.status.value).toBe('invalid');
        expect(state.message.value).toBe('Gagal memeriksa kode referral.');
    });

    it('menyimpan quote harga valid dan mereset saat kode dikosongkan', async () => {
        const code = ref('abc-123');
        const quote = {
            base: 95_000,
            discount_type: 'percentage' as const,
            discount_value: 20,
            discount: 19_000,
            final: 76_000,
        };
        checkReferralCodeMock.mockResolvedValue({ valid: true, message: null, price: quote });

        const state = useReferralCodeCheck({
            url: () => '/referral-check',
            code,
            enabled: () => true,
        });

        await advanceDebounce();
        expect(state.price.value).toEqual(quote);

        code.value = '';
        await advanceDebounce();
        expect(state.price.value).toBeNull();
    });

    it('tidak memeriksa saat dinonaktifkan', async () => {
        const enabled = ref(false);
        const state = useReferralCodeCheck({
            url: () => '/referral-check',
            code: ref('abc'),
            enabled,
        });

        await advanceDebounce();

        expect(checkReferralCodeMock).not.toHaveBeenCalled();
        expect(state.status.value).toBe('idle');
    });
});
