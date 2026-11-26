import { REFERRAL_POSITION_END, REFERRAL_POSITION_START } from '@/constants/referral';
import type { TReferralPosition } from '@/types/referralPlacement';

/** Bentuk minimum field yang dibutuhkan resolver posisi. */
export interface IReferralPlacementField {
    id: string;
}

/** Nilai asing (kosong, non-string, field yang sudah dihapus) jatuh ke `end`. */
export function normalizeReferralPosition(raw: unknown): TReferralPosition {
    if (typeof raw !== 'string' || raw.trim() === '') return REFERRAL_POSITION_END;
    if (raw === REFERRAL_POSITION_START || raw === REFERRAL_POSITION_END) return raw;

    return raw.trim();
}

/**
 * Indeks sisip blok referral di dalam satu bagian (section), atau `null` bila
 * blok tidak berada di bagian itu.
 *
 * `all` adalah daftar field terurut seluruh form, `section` adalah subset field
 * milik satu bagian (urutan sama).
 */
export function referralInsertIndexWithinSection(
    all: readonly IReferralPlacementField[],
    section: readonly IReferralPlacementField[],
    position: unknown
): number | null {
    if (section.length === 0) return null;

    const normalized = normalizeReferralPosition(position);
    const firstGlobalIndex = all.findIndex((field) => field.id === section[0]?.id);
    const lastGlobalIndex = all.findIndex((field) => field.id === section[section.length - 1]?.id);

    if (normalized === REFERRAL_POSITION_START) return firstGlobalIndex === 0 ? 0 : null;
    if (normalized === REFERRAL_POSITION_END) return lastGlobalIndex === all.length - 1 ? section.length : null;

    const anchorIndex = section.findIndex((field) => field.id === normalized);

    if (anchorIndex !== -1) return anchorIndex + 1;

    // Anchor hilang: perlakukan sebagai `end` agar blok tidak menghilang dari form.
    return lastGlobalIndex === all.length - 1 ? section.length : null;
}

/**
 * Nilai posisi baru setelah blok dijatuhkan pada `localIndex` di dalam `section`.
 * Indeks 0 memakai field global sebelumnya sebagai anchor supaya blok tetap bisa
 * berpindah ke bagian ini.
 */
export function referralPositionForSectionIndex(
    all: readonly IReferralPlacementField[],
    section: readonly IReferralPlacementField[],
    localIndex: number
): TReferralPosition {
    const clamped = Math.max(0, Math.min(localIndex, section.length));
    const previous = clamped === 0 ? undefined : section[clamped - 1];

    if (previous !== undefined) return previous.id;

    const first = section[0];
    if (first === undefined) return REFERRAL_POSITION_END;

    const globalIndex = all.findIndex((field) => field.id === first.id);

    if (globalIndex <= 0) return REFERRAL_POSITION_START;

    return all[globalIndex - 1]?.id ?? REFERRAL_POSITION_END;
}
