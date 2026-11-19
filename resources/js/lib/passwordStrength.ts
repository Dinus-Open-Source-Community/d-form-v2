import type { IPasswordRule } from '@/types/auth';

/** Jumlah segmen indikator kekuatan; level 0–4 dipetakan ke segmen terisi. */
export const PASSWORD_STRENGTH_MAX_SEGMENTS = 4;

/** Panjang minimum kata sandi baru; sama dengan `min:8` pada `RegisterStoreRequest` dan `Password::min(8)` profil. */
export const PASSWORD_MIN_LENGTH = 8;

/** Kalimat pendamping di bawah indikator kekuatan; dipakai halaman daftar dan profil apa adanya. */
export const PASSWORD_STRENGTH_HINT = 'Minimal 8 karakter dengan huruf besar dan angka.';

/** Pesan penolakan submit; teksnya milik halaman daftar dan dipakai juga oleh halaman profil. */
const PASSWORD_TOO_SHORT_MESSAGE = 'Password must be at least 8 characters.';
const PASSWORD_MISMATCH_MESSAGE = 'Password does not match';

/**
 * Tiga kriteria kekuatan kata sandi baru, urut dari yang paling dasar. Urutannya
 * menentukan tampilan daftar aturan di halaman daftar.
 */
export function passwordStrengthRules(password: string): IPasswordRule[] {
    return [
        { label: 'Minimal 8 karakter', met: password.length >= PASSWORD_MIN_LENGTH },
        { label: 'Huruf besar', met: /[A-Z]/.test(password) },
        { label: 'Angka', met: /\d/.test(password) },
    ];
}

/** Segmen terisi: kosong = 0, sudah mengetik = 1, lalu +1 untuk tiap kriteria (maksimal 4). */
export function passwordStrengthSegmentCount(password: string): number {
    if (password.length === 0) return 0;

    const metRuleCount = passwordStrengthRules(password).filter((rule) => rule.met).length;

    return Math.min(PASSWORD_STRENGTH_MAX_SEGMENTS, metRuleCount + 1);
}

/**
 * Guard sebelum request kata sandi baru dikirim: hanya panjang minimum dan
 * kecocokan konfirmasi, sama seperti yang diperiksa halaman daftar. Huruf besar
 * dan angka tetap berupa saran di indikator kekuatan, bukan penolakan.
 *
 * @returns Pesan untuk pengguna, atau `null` bila lolos guard.
 */
export function passwordSubmitBlockerMessage(password: string, confirmation: string): string | null {
    if (password.length < PASSWORD_MIN_LENGTH) return PASSWORD_TOO_SHORT_MESSAGE;
    if (password !== confirmation) return PASSWORD_MISMATCH_MESSAGE;

    return null;
}
