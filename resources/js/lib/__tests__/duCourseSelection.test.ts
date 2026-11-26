import { describe, expect, it } from 'vitest';
import {
    canApplyCourseSelection,
    missingCourseUids,
    readSelectedCourseUids,
    removeSelectedCourseUid,
    selectActiveCourseUids,
} from '@/lib/duCourseSelection';
import type { IDuCourseOption } from '@/types/du';

const CATALOG: IDuCourseOption[] = [
    { uid: 'course-a', title: 'Alpha' },
    { uid: 'course-b', title: 'Beta' },
    { uid: 'course-c', title: 'Gamma' },
];

describe('readSelectedCourseUids', () => {
    it('mengembalikan null bila metadata belum punya pilihan eksplisit', () => {
        expect(readSelectedCourseUids({})).toBeNull();
        expect(readSelectedCourseUids({ selected_course_uids: 'course-a' })).toBeNull();
    });

    it('membersihkan entri non-string dan duplikat tanpa mengubah urutan', () => {
        expect(readSelectedCourseUids({ selected_course_uids: ['course-b', '', 42, 'course-a', 'course-b'] })).toEqual([
            'course-b',
            'course-a',
        ]);
    });

    it('mempertahankan array kosong sebagai pilihan eksplisit tanpa kelas', () => {
        expect(readSelectedCourseUids({ selected_course_uids: [] })).toEqual([]);
    });
});

describe('selectActiveCourseUids', () => {
    it('menganggap semua katalog aktif terpilih bila pilihan tidak ada', () => {
        expect(selectActiveCourseUids(null, CATALOG)).toEqual(['course-a', 'course-b', 'course-c']);
    });

    it('mengambil irisan pilihan dengan katalog aktif dalam urutan katalog', () => {
        expect(selectActiveCourseUids(['course-c', 'course-a', 'course-gone'], CATALOG)).toEqual([
            'course-a',
            'course-c',
        ]);
    });

    it('tidak memilih apa pun untuk pilihan eksplisit yang kosong', () => {
        expect(selectActiveCourseUids([], CATALOG)).toEqual([]);
    });
});

describe('missingCourseUids', () => {
    it('tidak punya sisa untuk pilihan implisit', () => {
        expect(missingCourseUids(null, CATALOG)).toEqual([]);
    });

    it('mengembalikan UID terpilih yang tidak ada di katalog aktif', () => {
        expect(missingCourseUids(['course-a', 'course-gone', 'course-lost'], CATALOG)).toEqual([
            'course-gone',
            'course-lost',
        ]);
    });

    it('tidak punya sisa untuk pilihan eksplisit yang kosong', () => {
        expect(missingCourseUids([], CATALOG)).toEqual([]);
    });

    /** Baris terlihat di dialog = course katalog yang dipilih + baris UID hilang. */
    function visibleRowUids(draft: string[]): string[] {
        return [
            ...CATALOG.filter((course) => draft.includes(course.uid)).map((course) => course.uid),
            ...missingCourseUids(draft, CATALOG),
        ];
    }

    it('baris hilang mengikuti draf: hapus satuan mengurangi baris dan count', () => {
        const draft = ['course-a', 'course-gone'];

        expect(visibleRowUids(draft)).toEqual(['course-a', 'course-gone']);
        expect(visibleRowUids(draft)).toHaveLength(draft.length);

        const afterRemove = draft.filter((uid) => uid !== 'course-gone');

        expect(visibleRowUids(afterRemove)).toEqual(['course-a']);
        expect(visibleRowUids(afterRemove)).toHaveLength(afterRemove.length);
    });

    it('Kosongkan menghapus semua baris hilang dan count menjadi nol', () => {
        const draft = ['course-gone', 'course-lost'];

        expect(missingCourseUids(draft, CATALOG)).toHaveLength(2);

        const cleared: string[] = [];

        expect(visibleRowUids(cleared)).toEqual([]);
        expect(cleared).toHaveLength(0);
    });
});

describe('canApplyCourseSelection', () => {
    it('menolak simpan selama katalog masih dimuat', () => {
        expect(canApplyCourseSelection(true, '')).toBe(false);
    });

    it('menolak simpan saat katalog gagal dimuat', () => {
        expect(canApplyCourseSelection(false, 'Gagal memuat katalog kelas DU. Coba lagi.')).toBe(false);
    });

    it('mengizinkan simpan setelah katalog termuat tanpa error', () => {
        expect(canApplyCourseSelection(false, '')).toBe(true);
    });
});

describe('removeSelectedCourseUid', () => {
    it('menulis array eksplisit tanpa UID yang dihapus bila pilihan masih implisit', () => {
        expect(removeSelectedCourseUid(null, ['course-a', 'course-b'], 'course-a')).toEqual(['course-b']);
    });

    it('menghapus UID dari pilihan eksplisit dan mempertahankan sisanya', () => {
        expect(removeSelectedCourseUid(['course-a', 'course-b'], ['course-a', 'course-b'], 'course-a')).toEqual([
            'course-b',
        ]);
    });

    it('menghapus UID terakhir menjadi pilihan eksplisit yang kosong', () => {
        expect(removeSelectedCourseUid(['course-a'], ['course-a'], 'course-a')).toEqual([]);
    });
});
