import { pad2 } from '@/lib/shadcnDateFormat';

/**
 * Format sisa detik menjadi `mm:ss` untuk hitung mundur.
 * Nilai negatif/NaN dianggap 0 supaya tampilan tidak pernah "-1:-1".
 */
export function formatCountdownClock(totalSeconds: number): string {
    const safeSeconds = Number.isFinite(totalSeconds) ? Math.max(0, Math.floor(totalSeconds)) : 0;

    return `${pad2(Math.floor(safeSeconds / 60))}:${pad2(safeSeconds % 60)}`;
}
