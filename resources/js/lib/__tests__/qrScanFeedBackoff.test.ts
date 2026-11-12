import { describe, expect, it } from 'vitest';
import { FEED_POLL_MS, nextFeedDelayMs } from '@/lib/qrScanUi';

describe('nextFeedDelayMs', () => {
    it('memakai interval dasar saat belum ada kegagalan', () => {
        expect(nextFeedDelayMs(0)).toBe(FEED_POLL_MS);
    });

    it('memperlakukan nilai negatif, NaN, dan Infinity sebagai tanpa kegagalan', () => {
        expect(nextFeedDelayMs(-3)).toBe(FEED_POLL_MS);
        expect(nextFeedDelayMs(Number.NaN)).toBe(FEED_POLL_MS);
        expect(nextFeedDelayMs(Number.POSITIVE_INFINITY)).toBe(FEED_POLL_MS);
    });

    it('menaikkan jeda eksponensial 1..4 kegagalan', () => {
        expect(nextFeedDelayMs(1)).toBe(2000);
        expect(nextFeedDelayMs(2)).toBe(4000);
        expect(nextFeedDelayMs(3)).toBe(8000);
        expect(nextFeedDelayMs(4)).toBe(16_000);
    });

    it('membatasi jeda di 30 detik setelah lima kegagalan atau lebih', () => {
        expect(nextFeedDelayMs(5)).toBe(30_000);
        expect(nextFeedDelayMs(12)).toBe(30_000);
    });

    it('membulatkan jumlah kegagalan pecahan ke bawah', () => {
        expect(nextFeedDelayMs(2.7)).toBe(4000);
    });
});
