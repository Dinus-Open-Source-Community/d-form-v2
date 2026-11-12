/**
 * Klasifikasi kegagalan autosave: kapan harus berhenti (target sudah tidak ada /
 * sesi habis / akses dicabut) dan kapan cukup retry. Murni agar bisa diuji per status.
 */

import { parseApiErrorMessage } from '@/lib/error-message';

/** `terminal` = hentikan autosave halaman; `retryable` = tampilkan aksi coba lagi. */
export type TAutosaveFailureKind = 'terminal' | 'retryable';

/** Aksi yang ditawarkan toast. */
export type TAutosaveFailureAction = 'reload' | 'retry' | 'none';

export interface IAutosaveFailureDecision {
    kind: TAutosaveFailureKind;
    action: TAutosaveFailureAction;
    message: string;
}

const DEFAULT_FALLBACK = 'Gagal menyimpan otomatis. Perubahan tetap ada di kanvas.';

/** Status yang berarti target autosave sudah tidak bisa dipakai lagi. */
const TERMINAL_STATUSES: ReadonlyMap<number, { message: string; action: TAutosaveFailureAction }> = new Map([
    [
        404,
        {
            message:
                'Draf atau acara ini sudah tidak tersedia (mungkin dihapus). Muat ulang halaman untuk memulai lagi.',
            action: 'reload',
        },
    ],
    [
        410,
        {
            message:
                'Draf atau acara ini sudah tidak tersedia (mungkin dihapus). Muat ulang halaman untuk memulai lagi.',
            action: 'reload',
        },
    ],
    [419, { message: 'Sesi Anda berakhir. Muat ulang halaman lalu coba lagi.', action: 'reload' }],
    [403, { message: 'Anda tidak lagi punya akses ke form ini.', action: 'none' }],
]);

/**
 * Petakan status HTTP + body ke keputusan UI. Pesan retryable diturunkan dari body
 * (`parseApiErrorMessage`: bag validasi dulu) sehingga JSON/ringkasan tidak bocor.
 */
export function classifyAutosaveFailure(status: number | null, body?: unknown): IAutosaveFailureDecision {
    const terminal = status === null ? undefined : TERMINAL_STATUSES.get(status);

    if (terminal !== undefined) {
        return { kind: 'terminal', action: terminal.action, message: terminal.message };
    }

    return {
        kind: 'retryable',
        action: 'retry',
        message: parseApiErrorMessage(body, DEFAULT_FALLBACK, status) || DEFAULT_FALLBACK,
    };
}
