import { describe, expect, it } from 'vitest';
import {
    buildDuOptionMappingRows,
    classifyDuOptionRows,
    duCatalogStatus,
    duFlagState,
    readDuOptionMap,
    storedDuMappingState,
    suggestDuCourse,
    withDuOptionMapping,
} from '@/components/modules/builder/duFieldMapping';
import type { IDuCourseOption } from '@/types/du';
import type { IFieldOptionEntry } from '@/types/form-builder';

const CATALOG: IDuCourseOption[] = [
    { uid: 'course-ai', title: 'AI Engineering' },
    { uid: 'course-ds', title: 'Data Science' },
    { uid: 'course-dupe', title: 'DevOps' },
    { uid: 'course-dupe-2', title: 'DevOps' },
];

describe('suggestDuCourse', () => {
    it('menyarankan course dari judul yang cocok persis dan unik', () => {
        expect(suggestDuCourse('AI Engineering', CATALOG)).toEqual({
            uid: 'course-ai',
            title: 'AI Engineering',
        });
    });

    it('menyarankan course dari UID yang cocok persis dan unik', () => {
        expect(suggestDuCourse('course-ds', CATALOG)).toEqual({
            uid: 'course-ds',
            title: 'Data Science',
        });
    });

    it('tidak menyarankan apa pun saat kecocokan ambigu', () => {
        expect(suggestDuCourse('DevOps', CATALOG)).toBeNull();
    });

    it('tidak menyarankan apa pun saat tidak ada kecocokan', () => {
        expect(suggestDuCourse('Cyber Security', CATALOG)).toBeNull();
        expect(suggestDuCourse('   ', CATALOG)).toBeNull();
    });
});

describe('withDuOptionMapping', () => {
    it('menambah mapping baru tanpa menghapus mapping opsi lain', () => {
        const current = {
            'Data Science': { course_uid: 'course-ds', course_title: 'Data Science' },
        };

        const next = withDuOptionMapping(current, 'AI Engineer', {
            uid: 'course-ai',
            title: 'AI Engineering',
        });

        expect(next).toEqual({
            'Data Science': { course_uid: 'course-ds', course_title: 'Data Science' },
            'AI Engineer': { course_uid: 'course-ai', course_title: 'AI Engineering' },
        });
        expect(current).toEqual({
            'Data Science': { course_uid: 'course-ds', course_title: 'Data Science' },
        });
    });

    it('mengganti course untuk opsi yang sudah dipetakan', () => {
        const next = withDuOptionMapping(
            { 'AI Engineer': { course_uid: 'course-lama', course_title: 'Course Lama' } },
            'AI Engineer',
            { uid: 'course-ai', title: 'AI Engineering' }
        );

        expect(next['AI Engineer']).toEqual({ course_uid: 'course-ai', course_title: 'AI Engineering' });
    });
});

describe('readDuOptionMap', () => {
    it('membaca entri valid dan membuang entri rusak', () => {
        const map = readDuOptionMap({
            du_option_map: {
                'AI Engineer': { course_uid: 'course-ai', course_title: 'AI Engineering' },
                'Data Science': { course_uid: '', course_title: 'Kosong' },
                DevOps: 'bukan objek',
                'Cyber Security': { course_uid: 'course-cs' },
            },
        });

        expect(map).toEqual({
            'AI Engineer': { course_uid: 'course-ai', course_title: 'AI Engineering' },
            'Cyber Security': { course_uid: 'course-cs', course_title: '' },
        });
    });

    it('mengembalikan objek kosong saat metadata tidak memiliki mapping', () => {
        expect(readDuOptionMap({})).toEqual({});
        expect(readDuOptionMap({ du_option_map: [] })).toEqual({});
    });
});

describe('buildDuOptionMappingRows', () => {
    const OPTIONS: IFieldOptionEntry[] = [
        { id: 'o1', type: 'text', label: 'AI Engineer', imageUrl: '' },
        { id: 'o2', type: 'image', label: '   ', imageUrl: '/storage/kosong.png' },
        { id: 'o3', type: 'text', label: 'Data Science', imageUrl: '' },
    ];

    it('melewati opsi tanpa label agar tidak membuat key mapping kosong', () => {
        const rows = buildDuOptionMappingRows(OPTIONS, {}, []);

        expect(rows.map((row) => row.value)).toEqual(['AI Engineer', 'Data Science']);
    });

    it('menyertakan course aktif dan saran hanya untuk opsi berlabel', () => {
        const rows = buildDuOptionMappingRows(
            OPTIONS,
            { 'AI Engineer': { course_uid: 'course-ai', course_title: 'AI Engineering' } },
            CATALOG
        );

        expect(rows[0]?.activeCourse?.uid).toBe('course-ai');
        expect(rows[0]?.suggestion).toBeNull();
        expect(rows[1]?.stored).toBeNull();
        expect(rows[1]?.suggestion?.uid).toBe('course-ds');
    });
});

describe('duFlagState', () => {
    it('mengaktifkan toggle saat belum ada owner dan field punya opsi', () => {
        expect(duFlagState({ eligible: true, isOwner: false, hasOptions: true, hasOtherOwner: false })).toBe('enabled');
    });

    it('menonaktifkan toggle dengan alasan tambah opsi saat field belum punya opsi', () => {
        expect(duFlagState({ eligible: true, isOwner: false, hasOptions: false, hasOtherOwner: false })).toBe(
            'disabled-no-options'
        );
    });

    it('menonaktifkan toggle saat field lain menjadi owner', () => {
        expect(duFlagState({ eligible: true, isOwner: false, hasOptions: true, hasOtherOwner: true })).toBe(
            'disabled-owner'
        );
        expect(duFlagState({ eligible: true, isOwner: false, hasOptions: false, hasOtherOwner: true })).toBe(
            'disabled-owner'
        );
    });

    it('mengaktifkan toggle pada owner agar bisa dimatikan', () => {
        expect(duFlagState({ eligible: true, isOwner: true, hasOptions: false, hasOtherOwner: false })).toBe('enabled');
    });

    it('menyembunyikan toggle pada field yang tidak eligible', () => {
        expect(duFlagState({ eligible: false, isOwner: false, hasOptions: true, hasOtherOwner: false })).toBe('hidden');
    });
});

describe('classifyDuOptionRows', () => {
    it('membedakan field tanpa opsi dari opsi yang semuanya tanpa label', () => {
        expect(classifyDuOptionRows(0, 0)).toBe('no-options');
        expect(classifyDuOptionRows(2, 0)).toBe('unlabeled-only');
        expect(classifyDuOptionRows(2, 1)).toBe('ready');
    });
});

describe('duCatalogStatus', () => {
    it('memprioritaskan loading lalu error sebelum isi katalog', () => {
        expect(duCatalogStatus(true, false, 3)).toBe('loading');
        expect(duCatalogStatus(false, true, 3)).toBe('error');
        expect(duCatalogStatus(false, false, 0)).toBe('empty');
        expect(duCatalogStatus(false, false, 2)).toBe('ready');
    });
});

describe('storedDuMappingState', () => {
    it('tidak mengklaim course hilang saat katalog dimuat atau gagal dimuat', () => {
        expect(storedDuMappingState(false, false, 'ready')).toBe('none');
        expect(storedDuMappingState(true, true, 'ready')).toBe('active');
        expect(storedDuMappingState(true, false, 'loading')).toBe('loading');
        expect(storedDuMappingState(true, false, 'error')).toBe('error');
        expect(storedDuMappingState(true, false, 'empty')).toBe('missing');
        expect(storedDuMappingState(true, false, 'ready')).toBe('missing');
    });
});
