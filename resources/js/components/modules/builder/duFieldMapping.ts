import { isRecord } from '@/lib/isRecord';
import type { IDuCourseOption, IDuCourseMappingEntry, TDuOptionMap } from '@/types/du';
import type { IFieldOptionEntry } from '@/types/form-builder';

/**
 * Helper murni kartu mapping redeem DU: baca peta dari metadata, cari saran
 * course eksak, susun baris status, dan hasilkan peta berikutnya tanpa memutasi input.
 */

export type TDuCatalogStatus = 'loading' | 'error' | 'empty' | 'ready';

export interface IDuOptionMappingRow {
    value: string;
    stored: IDuCourseMappingEntry | null;
    activeCourse: IDuCourseOption | null;
    suggestion: IDuCourseOption | null;
}

export type TDuStoredMappingState = 'none' | 'active' | 'loading' | 'error' | 'missing';

export type TDuOptionRowsState = 'no-options' | 'unlabeled-only' | 'ready';

export type TDuFlagState = 'hidden' | 'enabled' | 'disabled-owner' | 'disabled-no-options';

export interface IDuFlagStateInput {
    eligible: boolean;
    isOwner: boolean;
    hasOptions: boolean;
    hasOtherOwner: boolean;
}

/**
 * Keputusan toggle redeem DU. Owner yang sedang aktif selalu bisa mematikan;
 * field lain diblokir selama owner ada, dan field tanpa opsi belum bisa menjadi owner.
 */
export function duFlagState({ eligible, isOwner, hasOptions, hasOtherOwner }: IDuFlagStateInput): TDuFlagState {
    if (!eligible) return 'hidden';
    if (isOwner) return 'enabled';
    if (hasOtherOwner) return 'disabled-owner';
    if (!hasOptions) return 'disabled-no-options';

    return 'enabled';
}

/** Status katalog untuk memilih copy state, tanpa menebak isi katalog. */
export function duCatalogStatus(loading: boolean, hasError: boolean, catalogSize: number): TDuCatalogStatus {
    if (loading) return 'loading';
    if (hasError) return 'error';

    return catalogSize > 0 ? 'ready' : 'empty';
}

/**
 * Saran otomatis hanya untuk satu kecocokan eksak judul atau UID. Ambigu atau
 * kosong menghasilkan `null`; admin tetap harus menekan `Gunakan`.
 */
export function suggestDuCourse(optionLabel: string, catalog: readonly IDuCourseOption[]): IDuCourseOption | null {
    const wanted = optionLabel.trim();

    if (wanted === '') return null;

    const matches = catalog.filter((course) => course.title === wanted || course.uid === wanted);

    return matches.length === 1 ? matches[0] : null;
}

/**
 * Baris mapping per opsi teks. Opsi tanpa label (mis. radio gambar saja) tidak
 * punya nilai jawaban, jadi dilewati dan tidak pernah menjadi key `""`.
 */
export function buildDuOptionMappingRows(
    options: readonly IFieldOptionEntry[],
    mapping: TDuOptionMap,
    catalog: readonly IDuCourseOption[]
): IDuOptionMappingRow[] {
    return options.flatMap((option) => {
        const value = option.label.trim();

        if (value === '') return [];

        const stored = mapping[value] ?? null;
        const activeCourse =
            stored === null ? null : (catalog.find((course) => course.uid === stored.course_uid) ?? null);

        return [
            {
                value,
                stored,
                activeCourse,
                suggestion: stored === null ? suggestDuCourse(value, catalog) : null,
            },
        ];
    });
}

/**
 * Bedakan field tanpa opsi dari field yang semua opsinya tanpa label (mis. radio
 * gambar saja). Opsi tanpa label tidak memiliki nilai jawaban sehingga tidak bisa dipetakan.
 */
export function classifyDuOptionRows(optionCount: number, rowCount: number): TDuOptionRowsState {
    if (optionCount === 0) return 'no-options';

    return rowCount === 0 ? 'unlabeled-only' : 'ready';
}

/**
 * Status mapping terhadap katalog. Saat katalog masih dimuat/gagal, course yang
 * belum terlihat tidak boleh diklaim sudah hilang dari katalog aktif.
 */
export function storedDuMappingState(
    hasStored: boolean,
    isActive: boolean,
    catalogStatus: TDuCatalogStatus
): TDuStoredMappingState {
    if (!hasStored) return 'none';
    if (isActive) return 'active';
    if (catalogStatus === 'loading') return 'loading';
    if (catalogStatus === 'error') return 'error';

    return 'missing';
}

/** Peta berikutnya untuk satu nilai opsi; entri lain dipertahankan. */
export function withDuOptionMapping(current: TDuOptionMap, optionValue: string, course: IDuCourseOption): TDuOptionMap {
    const entry: IDuCourseMappingEntry = { course_uid: course.uid, course_title: course.title };

    return { ...current, [optionValue]: entry };
}

/** Baca `metadata.du_option_map`; entri tanpa `course_uid` valid diabaikan. */
export function readDuOptionMap(metadata: Record<string, unknown>): TDuOptionMap {
    const raw = metadata.du_option_map;

    if (!isRecord(raw)) return {};

    const map: TDuOptionMap = {};

    for (const [optionValue, entry] of Object.entries(raw)) {
        if (!isRecord(entry)) continue;

        const uid = entry.course_uid;

        if (typeof uid !== 'string' || uid === '') continue;

        const title = entry.course_title;

        map[optionValue] = {
            course_uid: uid,
            course_title: typeof title === 'string' ? title : '',
        };
    }

    return map;
}
