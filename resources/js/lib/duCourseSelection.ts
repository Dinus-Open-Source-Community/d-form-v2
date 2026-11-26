import type { IDuCourseOption } from '@/types/du';

/**
 * Baca pilihan kelas DU dari `metadata.selected_course_uids`.
 *
 * `null` berarti metadata belum punya pilihan eksplisit (semua opsi saat ini dianggap
 * terpilih); array (termasuk kosong) berarti pilihan eksplisit admin.
 */
export function readSelectedCourseUids(metadata: Record<string, unknown>): string[] | null {
    const raw = metadata.selected_course_uids;

    if (!Array.isArray(raw)) return null;

    const uids: string[] = [];

    for (const value of raw) {
        if (typeof value === 'string' && value !== '' && !uids.includes(value)) uids.push(value);
    }

    return uids;
}

/** UID katalog aktif yang masuk pilihan; `null` memilih seluruh katalog. */
export function selectActiveCourseUids(selectedUids: string[] | null, catalog: readonly IDuCourseOption[]): string[] {
    if (selectedUids === null) return catalog.map((course) => course.uid);

    const allowed = new Set(selectedUids);

    return catalog.filter((course) => allowed.has(course.uid)).map((course) => course.uid);
}

/** UID terpilih yang tidak ada di katalog aktif, tanpa duplikat; `null` tidak punya sisa. */
export function missingCourseUids(selectedUids: string[] | null, catalog: readonly IDuCourseOption[]): string[] {
    if (selectedUids === null) return [];

    const active = new Set(catalog.map((course) => course.uid));

    return [...new Set(selectedUids.filter((uid) => !active.has(uid)))];
}

/** Pilihan hanya boleh disimpan bila katalog aktif sudah termuat tanpa error. */
export function canApplyCourseSelection(loading: boolean, error: string): boolean {
    return !loading && error === '';
}

/** Hapus satu UID dari pilihan eksplisit; basis implisit diperluas ke opsi saat ini. */
export function removeSelectedCourseUid(
    selectedUids: string[] | null,
    currentOptionUids: readonly string[],
    uid: string
): string[] {
    const base = selectedUids ?? [...currentOptionUids];

    return base.filter((value) => value !== uid);
}
