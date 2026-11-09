import { toast } from 'vue-sonner';
import { humanizeErrorMessage } from '@/lib/error-message';

/**
 * Toast kegagalan autosave dengan aksi retry. Satu implementasi untuk semua
 * halaman builder agar pesan, humanisasi, dan label aksi tidak berbeda.
 *
 * @param message pesan mentah dari hook (bisa berasal dari `HttpRequestError`).
 * @param retry aksi retry milik halaman; dipanggil saat user menekan "Coba lagi".
 */
export function notifyAutosaveFailure(message: string, retry: () => void): void {
    toast.error(humanizeErrorMessage(message) || 'Gagal menyimpan otomatis. Perubahan tetap ada di kanvas.', {
        action: {
            label: 'Coba lagi',
            onClick: retry,
        },
    });
}
