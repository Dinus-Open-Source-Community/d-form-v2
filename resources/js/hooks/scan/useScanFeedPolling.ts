import type { IScanFeedPayload } from '@/types/scan';
import { FEED_POLL_MS, nextFeedDelayMs } from '@/lib/qrScanFeed';

export interface IScanFeedPollingOptions {
    fetchFeed: (signal: AbortSignal) => Promise<IScanFeedPayload>;
    onPayload: (payload: IScanFeedPayload) => void;
    onSuccess: () => void;
    onFailure: () => void;
    isAbortError: (error: unknown) => boolean;
    pollIntervalMs?: number;
}

export interface IScanFeedPolling {
    start: () => void;
    stop: () => void;
    isRunning: () => boolean;
}

export function createScanFeedPolling(options: IScanFeedPollingOptions): IScanFeedPolling {
    let running = false;
    let timer: ReturnType<typeof setTimeout> | null = null;
    let controller: AbortController | null = null;
    let failures = 0;
    let generation = 0;

    const clearTimer = (): void => {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
    };

    const schedule = (delayMs: number): void => {
        clearTimer();
        if (!running) return;
        timer = setTimeout(() => {
            timer = null;
            void run();
        }, delayMs);
    };

    const run = async (): Promise<void> => {
        if (!running || controller !== null) return;
        const requestController = new AbortController();
        controller = requestController;
        const requestGeneration = generation;

        try {
            const payload = await options.fetchFeed(requestController.signal);
            if (!running || generation !== requestGeneration || controller !== requestController) return;
            options.onPayload(payload);
            failures = 0;
            options.onSuccess();
            schedule(options.pollIntervalMs ?? FEED_POLL_MS);
        } catch (error: unknown) {
            if (
                running &&
                generation === requestGeneration &&
                controller === requestController &&
                !options.isAbortError(error)
            ) {
                failures += 1;
                options.onFailure();
                schedule(nextFeedDelayMs(failures));
            }
        } finally {
            if (controller === requestController) controller = null;
        }
    };

    return {
        start: (): void => {
            if (running) return;
            running = true;
            void run();
        },
        stop: (): void => {
            generation += 1;
            running = false;
            clearTimer();
            controller?.abort();
            controller = null;
        },
        isRunning: (): boolean => running,
    };
}
