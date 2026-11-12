import { readNavigator } from '@/adapters/browser/environment';

/** Nada feedback hasil scan. */
export type TScanFeedback = 'success' | 'already' | 'invalid';

/** Frekuensi nada per status; nilai dibedakan agar operator bisa membedakan tanpa melihat layar. */
const FEEDBACK_FREQUENCY: Record<TScanFeedback, number> = {
    success: 880,
    already: 440,
    invalid: 200,
};

/** Durasi nada per status (ms). */
const FEEDBACK_DURATION: Record<TScanFeedback, number> = {
    success: 160,
    already: 320,
    invalid: 160,
};

/** Bunyikan nada singkat untuk hasil scan; gagal senyap bila Web Audio tidak tersedia. */
export function playScanFeedback(status: TScanFeedback): void {
    vibrate(50);

    try {
        if (typeof AudioContext === 'undefined') return;

        const context = new AudioContext();
        const oscillator = context.createOscillator();
        const gain = context.createGain();
        oscillator.connect(gain);
        gain.connect(context.destination);
        oscillator.frequency.value = FEEDBACK_FREQUENCY[status];
        oscillator.start();
        globalThis.setTimeout((): void => {
            oscillator.stop();
            void context.close();
        }, FEEDBACK_DURATION[status]);
    } catch {
        return;
    }
}

/** Getarkan perangkat bila API tersedia; no-op di desktop. */
export function vibrate(durationMs: number): void {
    const browserNavigator = readNavigator();
    if (browserNavigator === null || typeof browserNavigator.vibrate !== 'function') return;

    browserNavigator.vibrate(durationMs);
}
