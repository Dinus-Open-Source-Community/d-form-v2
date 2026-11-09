import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, reactive } from 'vue';
import { useOtpCountdown } from '@/hooks/useOtpCountdown';
import type { IUseOtpCountdownOptions } from '@/hooks/useOtpCountdown';

const CLIENT_NOW = new Date('2026-11-09T00:00:00.000Z');
const CLIENT_NOW_MS = CLIENT_NOW.getTime();

/** ISO8601 pada `offsetSeconds` detik setelah jam klien yang dimock. */
function isoAt(offsetSeconds: number): string {
    return new Date(CLIENT_NOW_MS + offsetSeconds * 1000).toISOString();
}

function createOptions(overrides: Partial<IUseOtpCountdownOptions> = {}): IUseOtpCountdownOptions {
    return {
        serverNow: isoAt(0),
        expiresAt: null,
        resendAvailableAt: null,
        lockedUntil: null,
        ...overrides,
    };
}

beforeEach(() => {
    vi.useFakeTimers();
    vi.setSystemTime(CLIENT_NOW);
});

afterEach(() => {
    vi.useRealTimers();
});

describe('useOtpCountdown', () => {
    it('counts expiresIn down from the server-corrected now', () => {
        const state = useOtpCountdown(createOptions({ expiresAt: isoAt(90) }));

        expect(state.expiresIn.value).toBe(90);
        expect(state.isExpired.value).toBe(false);
    });

    it('applies the server clock offset exactly once', () => {
        // Server 5 menit di depan klien, deadline 90 detik dari jam server.
        const ahead = useOtpCountdown(createOptions({ serverNow: isoAt(300), expiresAt: isoAt(390) }));
        expect(ahead.expiresIn.value).toBe(90);

        // Server 5 menit di belakang klien, deadline tetap 90 detik dari jam server.
        const behind = useOtpCountdown(createOptions({ serverNow: isoAt(-300), expiresAt: isoAt(-210) }));
        expect(behind.expiresIn.value).toBe(90);

        // Offset tidak dikali dua: deadline 120 detik dari klien dengan offset +30 -> sisa 90.
        const once = useOtpCountdown(createOptions({ serverNow: isoAt(30), expiresAt: isoAt(120) }));
        expect(once.expiresIn.value).toBe(90);
    });

    it('derives resendIn from resendAvailableAt', () => {
        const state = useOtpCountdown(createOptions({ resendAvailableAt: isoAt(45) }));

        expect(state.resendIn.value).toBe(45);
    });

    it('derives isLocked from lockedUntil', () => {
        expect(useOtpCountdown(createOptions({ lockedUntil: isoAt(30) })).isLocked.value).toBe(true);
        expect(useOtpCountdown(createOptions({ lockedUntil: isoAt(-1) })).isLocked.value).toBe(false);
        expect(useOtpCountdown(createOptions()).isLocked.value).toBe(false);
    });

    it('returns zero counts and no lock when every deadline is null', () => {
        const state = useOtpCountdown(createOptions());

        expect(state.expiresIn.value).toBe(0);
        expect(state.resendIn.value).toBe(0);
        expect(state.isLocked.value).toBe(false);
        // Tidak ada kode aktif berarti kode dianggap tidak berlaku.
        expect(state.isExpired.value).toBe(true);
    });

    it('treats an unparseable deadline as absent', () => {
        const state = useOtpCountdown(createOptions({ expiresAt: 'not-a-date' }));

        expect(state.expiresIn.value).toBe(0);
        expect(state.isExpired.value).toBe(true);
    });

    it('clamps past deadlines to zero and marks the code expired', () => {
        const state = useOtpCountdown(
            createOptions({ expiresAt: isoAt(-5), resendAvailableAt: isoAt(-5), lockedUntil: isoAt(-5) })
        );

        expect(state.expiresIn.value).toBe(0);
        expect(state.resendIn.value).toBe(0);
        expect(state.isLocked.value).toBe(false);
        expect(state.isExpired.value).toBe(true);
    });

    it('recomputes values while timers advance', async () => {
        const options = reactive(createOptions({ expiresAt: isoAt(10) }));
        const state = useOtpCountdown(options);

        expect(state.expiresIn.value).toBe(10);

        // Tanpa komponen, onMounted tidak berjalan; watcher deadline adalah jalur restart
        // yang sama dengan Inertia saat props di-patch, dan itu yang menyalakan interval.
        options.resendAvailableAt = isoAt(30);
        await nextTick();

        vi.advanceTimersByTime(4_000);

        expect(state.expiresIn.value).toBe(6);
        expect(state.resendIn.value).toBe(26);

        vi.advanceTimersByTime(30_000);

        expect(state.expiresIn.value).toBe(0);
        expect(state.resendIn.value).toBe(0);
        expect(state.isExpired.value).toBe(true);
    });
});
