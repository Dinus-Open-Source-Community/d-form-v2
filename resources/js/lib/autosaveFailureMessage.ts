import { humanizeErrorMessage } from '@/lib/error-message';

const AUTOSAVE_FAILURE_FALLBACK = 'Gagal menyimpan otomatis. Perubahan tetap ada di kanvas.';

/**
 * Pesan toast kegagalan autosave: humanisasi pesan mentah, dan pakai copy
 * generik saat pesan kosong. Murni, sehingga dapat diuji tanpa mock toast.
 */
export function buildAutosaveFailureMessage(raw: string): string {
    return humanizeErrorMessage(raw) || AUTOSAVE_FAILURE_FALLBACK;
}
