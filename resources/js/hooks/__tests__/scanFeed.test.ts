import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createScanFeedPolling } from '@/hooks/scan/useScanFeedPolling';
import { nextFeedDelayMs } from '@/lib/qrScanFeed';
import type { IScanFeedPayload } from '@/types/scan';

const payload: IScanFeedPayload = { cursor: 'next', rows: [] };

describe('FE_AUDIT_006_F2 scan feed polling', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('FE_AUDIT_006_F2 starts, ticks, and schedules the next request', async () => {
        const fetchFeed = vi.fn<(signal: AbortSignal) => Promise<IScanFeedPayload>>().mockResolvedValue(payload);
        const polling = createScanFeedPolling({
            fetchFeed,
            onPayload: vi.fn(),
            onSuccess: vi.fn(),
            onFailure: vi.fn(),
            isAbortError: () => false,
            pollIntervalMs: 10,
        });

        polling.start();
        await Promise.resolve();
        expect(fetchFeed).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(10);
        expect(fetchFeed).toHaveBeenCalledTimes(2);
    });

    it('FE_AUDIT_006_F2 aborts pending work and leaves no timer after stop', async () => {
        let requestSignal: AbortSignal | undefined;
        let resolveRequest: (value: IScanFeedPayload) => void = () => undefined;
        const fetchFeed = vi
            .fn<(signal: AbortSignal) => Promise<IScanFeedPayload>>()
            .mockImplementation((signal: AbortSignal) => {
                requestSignal = signal;
                return new Promise<IScanFeedPayload>((resolve) => {
                    resolveRequest = resolve;
                });
            });
        const onPayload = vi.fn();
        const polling = createScanFeedPolling({
            fetchFeed,
            onPayload,
            onSuccess: vi.fn(),
            onFailure: vi.fn(),
            isAbortError: () => false,
        });

        polling.start();
        polling.stop();
        expect(requestSignal?.aborted).toBe(true);
        expect(vi.getTimerCount()).toBe(0);
        resolveRequest(payload);
        await Promise.resolve();
        expect(onPayload).not.toHaveBeenCalled();
        expect(polling.isRunning()).toBe(false);
    });

    it('FE_AUDIT_006_F2 ignores rejection from a prior run after stop and restart', async () => {
        let rejectFirst: (reason: Error) => void = () => undefined;
        const firstRequest = new Promise<IScanFeedPayload>((_resolve, reject) => {
            rejectFirst = reject;
        });
        const fetchFeed = vi
            .fn<(signal: AbortSignal) => Promise<IScanFeedPayload>>()
            .mockReturnValueOnce(firstRequest)
            .mockResolvedValue(payload);
        const onFailure = vi.fn();
        const polling = createScanFeedPolling({
            fetchFeed,
            onPayload: vi.fn(),
            onSuccess: vi.fn(),
            onFailure,
            isAbortError: () => false,
            pollIntervalMs: 20,
        });

        polling.start();
        polling.stop();
        polling.start();
        await Promise.resolve();
        rejectFirst(new Error('stale failure'));
        await Promise.resolve();
        expect(onFailure).not.toHaveBeenCalled();
        expect(vi.getTimerCount()).toBe(1);
    });

    it('FE_AUDIT_006_F2 abort failure does not schedule a timer', async () => {
        const fetchFeed = vi
            .fn<(signal: AbortSignal) => Promise<IScanFeedPayload>>()
            .mockRejectedValue(new Error('abort'));
        const polling = createScanFeedPolling({
            fetchFeed,
            onPayload: vi.fn(),
            onSuccess: vi.fn(),
            onFailure: vi.fn(),
            isAbortError: () => true,
        });

        polling.start();
        await Promise.resolve();
        expect(vi.getTimerCount()).toBe(0);
    });

    it('FE_AUDIT_006_F2 marks errors stale and backs off', async () => {
        const onFailure = vi.fn();
        const fetchFeed = vi
            .fn<(signal: AbortSignal) => Promise<IScanFeedPayload>>()
            .mockRejectedValue(new Error('offline'));
        const polling = createScanFeedPolling({
            fetchFeed,
            onPayload: vi.fn(),
            onSuccess: vi.fn(),
            onFailure,
            isAbortError: () => false,
        });

        polling.start();
        await Promise.resolve();
        expect(onFailure).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(nextFeedDelayMs(1));
        expect(fetchFeed).toHaveBeenCalledTimes(2);
    });
});

describe('FE_AUDIT_008 qr scan feed delay', () => {
    it('FE_AUDIT_008 returns bounded exponential delays', () => {
        expect(nextFeedDelayMs(0)).toBe(2000);
        expect(nextFeedDelayMs(3)).toBe(8000);
        expect(nextFeedDelayMs(99)).toBe(30000);
    });
});
