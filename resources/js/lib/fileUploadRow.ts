/**
 * Turunan tampilan satu baris berkas unggahan: fase, label status, dan teks
 * persentase. Murni (tanpa DOM/komponen) agar bisa diuji tanpa memasang Vue;
 * ikon tipe berkas dipetakan terpisah di `lib/fileTypeIcon`.
 */

/** Fase visual baris. `idle` tidak merender baris. */
export type TUploadRowPhase = 'idle' | 'uploading' | 'completed' | 'failed';

/** Kontrak state kartu unggah; `error` adalah nama lama untuk fase `failed`. */
export type TUploadRowState = TUploadRowPhase | 'error';

const PHASE_LABELS: Record<TUploadRowPhase, string> = {
    idle: '',
    uploading: 'Sedang mengunggah…',
    completed: 'Selesai',
    failed: 'Gagal',
};

/** Normalisasi state kartu; `error` (warisan) dan `failed` adalah fase yang sama. */
export function uploadRowPhase(state: TUploadRowState): TUploadRowPhase {
    return state === 'error' ? 'failed' : state;
}

/** Label status berbahasa Indonesia untuk baris; `idle` tidak punya label. */
export function uploadRowStatusLabel(phase: TUploadRowPhase): string {
    return PHASE_LABELS[phase];
}

/** Persentase terbulatkan 0..100 sebagai label (`50%`); nilai tidak valid menjadi `0%`. */
export function uploadRowPercentLabel(percent: number): string {
    if (!Number.isFinite(percent)) return '0%';

    return `${Math.min(100, Math.max(0, Math.round(percent)))}%`;
}
