import type { Page } from '@playwright/test';

declare global {
    interface Window {
        /** Jumlah `AudioContext` yang dibuat halaman sejak probe dipasang. */
        __scanAudioContextCount?: number;
    }
}

/**
 * Pasang penghitung konstruksi `AudioContext` sebelum script halaman berjalan.
 * Dipakai untuk membuktikan feedback scan berbunyi tepat sekali per hasil scan
 * (tidak ada playback ganda), tanpa bergantung pada output audio perangkat.
 */
export async function installAudioContextProbe(page: Page): Promise<void> {
    await page.addInitScript((): void => {
        window.__scanAudioContextCount = 0;

        if (typeof window.AudioContext === 'undefined') {
            return;
        }

        const OriginalAudioContext = window.AudioContext;

        class CountingAudioContext extends OriginalAudioContext {
            constructor(options?: AudioContextOptions) {
                super(options);
                window.__scanAudioContextCount = (window.__scanAudioContextCount ?? 0) + 1;
            }
        }

        window.AudioContext = CountingAudioContext;
    });
}

/** Baca jumlah `AudioContext` yang dibuat sejak probe dipasang. */
export async function readAudioContextCount(page: Page): Promise<number> {
    return page.evaluate((): number => window.__scanAudioContextCount ?? 0);
}
