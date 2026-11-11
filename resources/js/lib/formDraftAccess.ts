/**
 * Keputusan UI halaman "buat form" berdasarkan hasil pemanggilan endpoint draf.
 * Draf bersifat creator-only: selain pembuat acara, backend menjawab 403 dan halaman
 * harus memakai jalur simpan lama (`POST /forms`) tanpa indikator autosave.
 * Fungsi murni agar perilaku 403 bisa diuji tanpa memasang halaman.
 */

/** Hasil pemanggilan `POST /admin/events/{event}/forms/draft`. */
export type TDraftAccess = 'available' | 'forbidden' | 'retryable-error';

/** Jalur simpan yang dipakai tombol Save Form. */
export type TCreateSaveMode = 'publish-draft' | 'store-form';

export interface IDraftAccessDecision {
    /** Tampilkan indikator autosave. */
    autosaveEnabled: boolean;
    saveMode: TCreateSaveMode;
    /** Status yang ditampilkan indikator bila autosave tidak bisa dipakai. */
    status: 'idle' | 'error';
}

/** Petakan hasil draf ke perilaku halaman create. */
export function resolveDraftAccessDecision(access: TDraftAccess): IDraftAccessDecision {
    if (access === 'forbidden') {
        // Bukan error yang bisa di-retry: draf milik pembuat acara.
        return { autosaveEnabled: false, saveMode: 'store-form', status: 'idle' };
    }

    if (access === 'retryable-error') {
        return { autosaveEnabled: false, saveMode: 'publish-draft', status: 'error' };
    }

    return { autosaveEnabled: true, saveMode: 'publish-draft', status: 'idle' };
}
