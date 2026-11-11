/** Interval dasar polling feed scan lintas meja (ms). */
export const FEED_POLL_MS = 2000;

const FEED_BACKOFF_CAP_MS = 30_000;

/** Return the bounded exponential delay for the next feed request. */
export function nextFeedDelayMs(consecutiveFailures: number): number {
    if (!Number.isFinite(consecutiveFailures) || consecutiveFailures <= 0) {
        return FEED_POLL_MS;
    }

    const exponent = Math.floor(consecutiveFailures) - 1;

    return Math.min(FEED_POLL_MS * 2 ** exponent, FEED_BACKOFF_CAP_MS);
}
