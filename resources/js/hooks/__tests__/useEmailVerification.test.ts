import { reactive } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useEmailVerification } from '@/hooks/useEmailVerification';
import type { IVerifyEmailPageProps } from '@/hooks/useEmailVerification';

const CLIENT_NOW = new Date('2026-11-18T09:00:00.000Z');

/** ISO8601 pada `offsetSeconds` detik setelah jam klien yang dimock. */
function isoAt(offsetSeconds: number): string {
    return new Date(CLIENT_NOW.getTime() + offsetSeconds * 1000).toISOString();
}

const inertiaMock = vi.hoisted(() => ({
    usePage: vi.fn(),
    router: {
        post: vi.fn(),
        reload: vi.fn(),
        visit: vi.fn(),
    },
}));

const authMock = vi.hoisted(() => ({
    verifyOtpCode: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: inertiaMock.usePage,
    router: inertiaMock.router,
}));

vi.mock('@/adapters/http/auth', () => ({
    verifyOtpCode: authMock.verifyOtpCode,
}));

function createPageProps(overrides: Partial<IVerifyEmailPageProps> = {}): IVerifyEmailPageProps {
    return {
        email: 'budi@example.test',
        verified: false,
        expiresAt: isoAt(300),
        serverNow: isoAt(0),
        attemptsRemaining: 3,
        sendsRemaining: 3,
        lockedUntil: null,
        resendAvailableAt: null,
        ...overrides,
    };
}

function mountEmailVerification(overrides: Partial<IVerifyEmailPageProps> = {}) {
    inertiaMock.usePage.mockReturnValue({ props: reactive({ errors: {} }) });

    return useEmailVerification(reactive(createPageProps(overrides)));
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(CLIENT_NOW);
});

afterEach(() => {
    vi.useRealTimers();
});

describe('useEmailVerification', () => {
    it('mirrors the server status object without inventing values', () => {
        const state = mountEmailVerification({
            expiresAt: isoAt(90),
            serverNow: isoAt(0),
            attemptsRemaining: 2,
            sendsRemaining: 1,
            lockedUntil: isoAt(120),
            resendAvailableAt: isoAt(30),
        });

        expect(state.status.value).toEqual({
            expiresAt: isoAt(90),
            attemptsRemaining: 2,
            sendsRemaining: 1,
            lockedUntil: isoAt(120),
            resendAvailableAt: isoAt(30),
        });
        expect(state.hasActiveCode.value).toBe(true);
    });

    it('treats a missing expiry as no active code', () => {
        const state = mountEmailVerification({ expiresAt: null, attemptsRemaining: 0 });

        expect(state.status.value.expiresAt).toBeNull();
        expect(state.hasActiveCode.value).toBe(false);
    });

    it('blocks submit below six digits', async () => {
        const state = mountEmailVerification();
        state.code.value = '12345';

        expect(state.canSubmit.value).toBe(false);

        await state.submit();

        expect(authMock.verifyOtpCode).not.toHaveBeenCalled();
    });

    it('blocks submit when no code has been issued', async () => {
        const state = mountEmailVerification({ expiresAt: null });
        state.code.value = '123456';

        expect(state.canSubmit.value).toBe(false);

        await state.submit();

        expect(authMock.verifyOtpCode).not.toHaveBeenCalled();
    });

    it('sends a complete code to the confirmation endpoint', async () => {
        authMock.verifyOtpCode.mockResolvedValue({
            status: 422,
            verifiedRedirect: null,
            message: 'Kode verifikasi salah. Periksa kembali atau kirim ulang kode.',
            retryAfterSeconds: null,
        });

        const state = mountEmailVerification();
        state.code.value = '123456';

        expect(state.canSubmit.value).toBe(true);

        await state.submit();

        expect(authMock.verifyOtpCode).toHaveBeenCalledWith('/email/verify/confirm', '123456');
        expect(state.codeError.value).toBe('Kode verifikasi salah. Periksa kembali atau kirim ulang kode.');
        expect(state.code.value).toBe('');
    });

    it('restarts the countdown from the status returned by a resend', () => {
        inertiaMock.usePage.mockReturnValue({ props: reactive({ errors: {} }) });

        // Resend mengembalikan props baru (expiresAt/resendAvailableAt/sendsRemaining) tanpa
        // remount; hitung mundur dan kuota kirim harus langsung memakai nilai baru.
        const pageProps = reactive<IVerifyEmailPageProps>(
            createPageProps({ expiresAt: null, resendAvailableAt: null, sendsRemaining: 3 })
        );
        const state = useEmailVerification(pageProps);

        expect(state.hasActiveCode.value).toBe(false);
        expect(state.expiresIn.value).toBe(0);

        pageProps.serverNow = isoAt(0);
        pageProps.expiresAt = isoAt(300);
        pageProps.resendAvailableAt = isoAt(60);
        pageProps.sendsRemaining = 2;

        expect(state.hasActiveCode.value).toBe(true);
        expect(state.expiresIn.value).toBe(300);
        expect(state.resendIn.value).toBe(60);
        expect(state.isExpired.value).toBe(false);
        expect(state.isResendBlocked.value).toBe(true);
        expect(state.status.value.sendsRemaining).toBe(2);
    });

    it('holds the resend action during the cooldown', () => {
        const blocked = mountEmailVerification({ resendAvailableAt: isoAt(30) });

        expect(blocked.isResendBlocked.value).toBe(true);

        blocked.resend();

        expect(inertiaMock.router.post).not.toHaveBeenCalled();
    });

    it('posts the resend request once the cooldown is over', () => {
        const free = mountEmailVerification({ resendAvailableAt: null });

        expect(free.isResendBlocked.value).toBe(false);

        free.resend();

        expect(inertiaMock.router.post).toHaveBeenCalledWith(
            '/email/verify/send',
            {},
            expect.objectContaining({ preserveScroll: true })
        );
    });
});
