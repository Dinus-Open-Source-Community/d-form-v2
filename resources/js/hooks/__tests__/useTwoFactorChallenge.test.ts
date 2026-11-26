import { reactive } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useTwoFactorChallenge } from '@/hooks/useTwoFactorChallenge';
import type { ITwoFactorChallengeStatus } from '@/hooks/useTwoFactorChallenge';

const CLIENT_NOW = new Date('2026-11-18T09:00:00.000Z');

/** ISO8601 pada `offsetSeconds` detik setelah jam klien yang dimock. */
function isoAt(offsetSeconds: number): string {
    return new Date(CLIENT_NOW.getTime() + offsetSeconds * 1000).toISOString();
}

const inertiaMock = vi.hoisted(() => ({
    usePage: vi.fn(),
    router: {
        post: vi.fn(),
    },
}));

const httpMock = vi.hoisted(() => ({
    requestJsonWithStatus: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: inertiaMock.usePage,
    router: inertiaMock.router,
}));

vi.mock('@/adapters/http/httpClient', () => ({
    requestJsonWithStatus: httpMock.requestJsonWithStatus,
}));

function mountChallenge(overrides: Partial<ITwoFactorChallengeStatus> = {}) {
    inertiaMock.usePage.mockReturnValue({ props: reactive({ errors: {} }) });

    const status = reactive<ITwoFactorChallengeStatus>({
        enabled: true,
        attemptsRemaining: 5,
        lockedUntil: null,
        ...overrides,
    });

    return useTwoFactorChallenge({ status });
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(CLIENT_NOW);
});

afterEach(() => {
    vi.useRealTimers();
});

describe('useTwoFactorChallenge', () => {
    it('follows the refreshed status after the server rejects the pin', () => {
        inertiaMock.usePage.mockReturnValue({ props: reactive({ errors: {} }) });

        // Inertia mengganti objek props (`status`) tanpa remount halaman, jadi hook harus
        // membaca nilai terbaru, bukan salinan saat setup.
        const pageProps = reactive<{ status: ITwoFactorChallengeStatus }>({
            status: { enabled: true, attemptsRemaining: 5, lockedUntil: null },
        });
        const state = useTwoFactorChallenge({ status: () => pageProps.status });

        expect(state.attemptsRemaining.value).toBe(5);

        pageProps.status = { enabled: true, attemptsRemaining: 4, lockedUntil: null };

        expect(state.attemptsRemaining.value).toBe(4);

        pageProps.status = { enabled: true, attemptsRemaining: 0, lockedUntil: isoAt(60) };

        expect(state.attemptsRemaining.value).toBe(0);
        expect(state.lockedUntil.value).toBe(isoAt(60));
        expect(state.isLocked.value).toBe(true);
    });

    it('exposes the remaining attempts from the server status', () => {
        const state = mountChallenge({ attemptsRemaining: 2 });

        expect(state.attemptsRemaining.value).toBe(2);
        expect(state.isLocked.value).toBe(false);
    });

    it('locks the form while the lock window is open', () => {
        const state = mountChallenge({ attemptsRemaining: 0, lockedUntil: isoAt(60) });

        state.pin.value = '123456';

        expect(state.isLocked.value).toBe(true);
        expect(state.canSubmit.value).toBe(false);

        state.submit();

        expect(inertiaMock.router.post).not.toHaveBeenCalled();
    });

    it('ignores a lock window that already ended', () => {
        const state = mountChallenge({ attemptsRemaining: 5, lockedUntil: isoAt(-30) });

        expect(state.isLocked.value).toBe(false);
    });

    it('blocks submit below six digits', () => {
        const state = mountChallenge();
        state.pin.value = '12345';

        expect(state.canSubmit.value).toBe(false);

        state.submit();

        expect(inertiaMock.router.post).not.toHaveBeenCalled();
    });

    it('posts a complete pin to the challenge endpoint', () => {
        const state = mountChallenge();
        state.pin.value = '123456';

        expect(state.canSubmit.value).toBe(true);

        state.submit();

        expect(inertiaMock.router.post).toHaveBeenCalledWith(
            '/auth/two-factor',
            { pin: '123456' },
            expect.objectContaining({ preserveScroll: true })
        );
    });

    it('moves the recovery flow from requesting a code to entering it', async () => {
        httpMock.requestJsonWithStatus.mockResolvedValue({
            status: 200,
            retryAfterSeconds: null,
            data: { expiresAt: isoAt(300), resendAvailableAt: isoAt(60) },
        });

        const state = mountChallenge();

        expect(state.recoveryStep.value).toBe('request');

        await state.sendRecovery();

        expect(httpMock.requestJsonWithStatus).toHaveBeenCalledWith(
            expect.objectContaining({ url: '/auth/two-factor/recovery', method: 'POST' }),
            expect.any(Function)
        );
        expect(state.recoveryStep.value).toBe('code');
    });

    it('moves the recovery flow from the code step to the new pin step', async () => {
        const state = mountChallenge();
        state.recoveryStep.value = 'code';
        state.recoveryCode.value = '123456';

        httpMock.requestJsonWithStatus.mockResolvedValue({
            status: 200,
            retryAfterSeconds: null,
            data: { confirmed: true },
        });

        expect(state.canConfirmRecovery.value).toBe(true);

        await state.confirmRecovery();

        expect(httpMock.requestJsonWithStatus).toHaveBeenCalledWith(
            expect.objectContaining({ url: '/auth/two-factor/recovery/confirm', method: 'POST' }),
            expect.any(Function)
        );
        expect(state.recoveryStep.value).toBe('pin');
    });

    it('keeps the code step and shows the server message when the send is refused', async () => {
        const serverMessage = 'Kode pemulihan tidak dapat dikirim sekarang. Tunggu sebentar lalu coba lagi.';
        httpMock.requestJsonWithStatus.mockResolvedValue({
            status: 429,
            retryAfterSeconds: null,
            data: { message: serverMessage },
        });

        const state = mountChallenge();

        await state.sendRecovery();

        expect(state.recoveryStep.value).toBe('request');
        expect(state.recoveryNotice.value).toBe(serverMessage);
    });

    it('requires a matching six digit confirmation before saving the new pin', () => {
        const state = mountChallenge();
        state.recoveryStep.value = 'pin';

        state.newPin.value = '123456';
        state.newPinConfirmation.value = '12345';
        expect(state.canSubmitNewPin.value).toBe(false);

        state.newPinConfirmation.value = '123456';
        expect(state.canSubmitNewPin.value).toBe(true);

        state.submitNewPin();

        expect(inertiaMock.router.post).toHaveBeenCalledWith(
            '/auth/two-factor/recovery/pin',
            { pin: '123456' },
            expect.objectContaining({ preserveScroll: true })
        );
    });
});
