import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, ref } from 'vue';
import { checkEmailAvailability } from '@/adapters/http/auth';
import type { IEmailAvailabilityResult } from '@/adapters/http/auth';
import { HttpRequestError } from '@/adapters/http/httpError';
import { useEmailAvailability } from '@/hooks/useEmailAvailability';

vi.mock('@/adapters/http/auth', () => ({
    checkEmailAvailability: vi.fn(),
}));

const checkEmailAvailabilityMock = vi.mocked(checkEmailAvailability);

const DEBOUNCE_MS = 50;
const VALID_EMAIL = 'pendaftar@example.test';

interface IDeferredCheck {
    promise: Promise<IEmailAvailabilityResult>;
    resolve: (value: IEmailAvailabilityResult) => void;
    reject: (reason: unknown) => void;
}

/** Deferred agar test bisa mengendalikan kapan request "selesai" atau gagal. */
function createDeferredCheck(): IDeferredCheck {
    let resolveFn: (value: IEmailAvailabilityResult) => void = () => {};
    let rejectFn: (reason: unknown) => void = () => {};
    const promise = new Promise<IEmailAvailabilityResult>((resolve, reject) => {
        resolveFn = resolve;
        rejectFn = reject;
    });

    return { promise, resolve: resolveFn, reject: rejectFn };
}

/** Jalankan timer debounce lalu biarkan microtask status memperbarui ref. */
async function advanceDebounce(): Promise<void> {
    await vi.advanceTimersByTimeAsync(DEBOUNCE_MS + 1);
    await nextTick();
}

beforeEach(() => {
    vi.useFakeTimers();
    checkEmailAvailabilityMock.mockReset();
    checkEmailAvailabilityMock.mockResolvedValue({ available: true });
});

afterEach(() => {
    vi.clearAllTimers();
    vi.useRealTimers();
});

describe('useEmailAvailability', () => {
    it('tidak memicu request saat efek dinonaktifkan, termasuk nilai awal dan perubahan email', async () => {
        const email = ref(VALID_EMAIL);
        const enabled = ref(false);
        const state = useEmailAvailability({ email, enabled, debounceMs: DEBOUNCE_MS });

        await advanceDebounce();

        expect(checkEmailAvailabilityMock).not.toHaveBeenCalled();
        expect(state.status.value).toBe('idle');

        email.value = 'pendaftar-lain@example.test';
        await nextTick();
        await advanceDebounce();

        expect(checkEmailAvailabilityMock).not.toHaveBeenCalled();
        expect(state.status.value).toBe('idle');
    });

    it('menjalankan satu request setelah debounce ketika efek diaktifkan', async () => {
        const email = ref(VALID_EMAIL);
        const enabled = ref(false);
        const state = useEmailAvailability({ email, enabled, debounceMs: DEBOUNCE_MS });

        enabled.value = true;
        await nextTick();

        expect(state.status.value).toBe('checking');
        expect(checkEmailAvailabilityMock).not.toHaveBeenCalled();

        await advanceDebounce();

        expect(checkEmailAvailabilityMock).toHaveBeenCalledTimes(1);
        expect(checkEmailAvailabilityMock.mock.calls[0]?.[1]).toBe(VALID_EMAIL);
        expect(state.status.value).toBe('available');
    });

    it('tidak memicu request setelah dinonaktifkan sebelum debounce selesai', async () => {
        const email = ref(VALID_EMAIL);
        const enabled = ref(true);
        const state = useEmailAvailability({ email, enabled, debounceMs: DEBOUNCE_MS });

        expect(state.status.value).toBe('checking');

        enabled.value = false;
        await nextTick();

        expect(state.status.value).toBe('idle');

        await advanceDebounce();

        expect(checkEmailAvailabilityMock).not.toHaveBeenCalled();
        expect(state.status.value).toBe('idle');
    });

    it('mengabaikan respons request yang sudah dibatalkan saat dinonaktifkan', async () => {
        const deferred = createDeferredCheck();
        checkEmailAvailabilityMock.mockImplementation(
            (_url: string, _email: string, signal?: AbortSignal): Promise<IEmailAvailabilityResult> =>
                new Promise<IEmailAvailabilityResult>((resolve, reject) => {
                    signal?.addEventListener('abort', () => reject(new DOMException('aborted', 'AbortError')));
                    void deferred.promise.then(resolve, reject);
                })
        );

        const email = ref(VALID_EMAIL);
        const enabled = ref(true);
        const state = useEmailAvailability({ email, enabled, debounceMs: DEBOUNCE_MS });

        await advanceDebounce();
        expect(checkEmailAvailabilityMock).toHaveBeenCalledTimes(1);
        expect(state.status.value).toBe('checking');

        enabled.value = false;
        await nextTick();
        expect(state.status.value).toBe('idle');

        // Respons yang datang terlambat tidak boleh menimpa status panel tersembunyi.
        deferred.resolve({ available: true });
        await nextTick();
        await nextTick();

        expect(state.status.value).toBe('idle');
    });

    it('tidak mengulang request untuk email yang sudah selesai dicek saat tab dibuka lagi', async () => {
        const email = ref(VALID_EMAIL);
        const enabled = ref(true);
        const state = useEmailAvailability({ email, enabled, debounceMs: DEBOUNCE_MS });

        await advanceDebounce();
        expect(checkEmailAvailabilityMock).toHaveBeenCalledTimes(1);
        expect(state.status.value).toBe('available');

        enabled.value = false;
        await nextTick();
        enabled.value = true;
        await nextTick();
        await advanceDebounce();

        expect(checkEmailAvailabilityMock).toHaveBeenCalledTimes(1);
        expect(state.status.value).toBe('available');
    });

    it('memeriksa ulang email berbeda yang diubah saat panel tersembunyi tanpa mempertahankan status email lama', async () => {
        const emailA = 'pendaftar-a@example.test';
        const emailB = 'pendaftar-b@example.test';
        const email = ref(emailA);
        const enabled = ref(true);
        const state = useEmailAvailability({ email, enabled, debounceMs: DEBOUNCE_MS });

        checkEmailAvailabilityMock.mockResolvedValueOnce({ available: true });
        await advanceDebounce();
        expect(state.status.value).toBe('available');
        expect(checkEmailAvailabilityMock).toHaveBeenCalledTimes(1);

        enabled.value = false;
        await nextTick();

        // Email berubah saat panel tersembunyi: efek tidak berjalan dan status A tetap.
        email.value = emailB;
        await nextTick();
        expect(state.status.value).toBe('available');
        expect(checkEmailAvailabilityMock).toHaveBeenCalledTimes(1);

        enabled.value = true;
        await nextTick();
        // Email B belum pernah dicek, jadi status A tidak boleh dipertahankan.
        expect(state.status.value).toBe('checking');
        expect(checkEmailAvailabilityMock).toHaveBeenCalledTimes(1);

        checkEmailAvailabilityMock.mockResolvedValueOnce({ available: false });
        await advanceDebounce();

        expect(checkEmailAvailabilityMock).toHaveBeenCalledTimes(2);
        expect(checkEmailAvailabilityMock.mock.calls[1]?.[1]).toBe(emailB);
        expect(state.status.value).toBe('taken');
    });

    it('menandai email tersedia dan terpakai sesuai respons', async () => {
        const email = ref(VALID_EMAIL);
        const state = useEmailAvailability({ email, debounceMs: DEBOUNCE_MS });

        checkEmailAvailabilityMock.mockResolvedValueOnce({ available: true });
        await advanceDebounce();
        expect(state.status.value).toBe('available');
        expect(state.message.value).toBe('Email tersedia.');

        checkEmailAvailabilityMock.mockResolvedValueOnce({ available: false });
        email.value = 'sudah-dipakai@example.test';
        await nextTick();
        await advanceDebounce();
        expect(state.status.value).toBe('taken');
        expect(state.message.value).toBe('Email sudah terdaftar.');
    });

    it('memetakan gangguan jaringan ke status error dan validasi 422 ke idle', async () => {
        const email = ref(VALID_EMAIL);
        const state = useEmailAvailability({ email, debounceMs: DEBOUNCE_MS });

        checkEmailAvailabilityMock.mockRejectedValueOnce(
            new HttpRequestError({ kind: 'server', status: 500, message: 'boom' })
        );
        await advanceDebounce();
        expect(state.status.value).toBe('error');

        checkEmailAvailabilityMock.mockRejectedValueOnce(
            new HttpRequestError({ kind: 'validation', status: 422, message: 'invalid' })
        );
        email.value = 'validasi@example.test';
        await nextTick();
        await advanceDebounce();
        expect(state.status.value).toBe('idle');
    });

    it('tidak mengirim request untuk email yang tidak valid', async () => {
        const email = ref('bukan-email');
        const state = useEmailAvailability({ email, debounceMs: DEBOUNCE_MS });

        await advanceDebounce();

        expect(checkEmailAvailabilityMock).not.toHaveBeenCalled();
        expect(state.status.value).toBe('idle');
    });
});
