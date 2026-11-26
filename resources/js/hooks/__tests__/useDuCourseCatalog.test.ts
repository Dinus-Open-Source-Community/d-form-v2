import { describe, expect, it, vi } from 'vitest';
import { useDuCourseCatalog } from '@/hooks/useDuCourseCatalog';
import { listDuCourses } from '@/adapters/http/duCourses';
import type { IDuCourseOption } from '@/types/du';

vi.mock('@/adapters/http/duCourses', () => ({ listDuCourses: vi.fn() }));

const listMock = vi.mocked(listDuCourses);

// Cache hook bersifat module-level dan di-key per URL, jadi setiap test memakai
// URL sendiri agar hasil test sebelumnya tidak bocor.
describe('useDuCourseCatalog', () => {
    it('memuat katalog aktif saat pertama dipakai', async () => {
        listMock.mockResolvedValue([
            { uid: 'course-ai', title: 'AI Engineering' },
            { uid: 'course-ds', title: 'Data Science' },
        ]);
        const state = useDuCourseCatalog('/admin/du/courses?success');

        const loading = state.ensureLoaded();

        expect(state.loading.value).toBe(true);
        await loading;

        expect(state.catalog.value).toEqual([
            { uid: 'course-ai', title: 'AI Engineering' },
            { uid: 'course-ds', title: 'Data Science' },
        ]);
        expect(state.error.value).toBe('');
        expect(listMock).toHaveBeenCalledTimes(1);
    });

    it('menampilkan pesan aman saat respons tidak valid', async () => {
        listMock.mockRejectedValue(new Error('kontrak rusak'));
        const state = useDuCourseCatalog('/admin/du/courses?malformed');

        await state.ensureLoaded();

        expect(state.catalog.value).toEqual([]);
        expect(state.error.value).toBe('Gagal memuat katalog kelas DU. Coba lagi.');
        expect(listMock).toHaveBeenCalledTimes(1);
    });

    it('memakai cache untuk field editor berikutnya dan refresh memaksa request baru', async () => {
        listMock.mockResolvedValue([{ uid: 'course-ai', title: 'AI Engineering' }]);
        const first = useDuCourseCatalog('/admin/du/courses?cache');

        await first.ensureLoaded();

        const second = useDuCourseCatalog('/admin/du/courses?cache');
        await second.ensureLoaded();

        expect(second.catalog.value).toEqual([{ uid: 'course-ai', title: 'AI Engineering' }]);
        expect(listMock).toHaveBeenCalledTimes(1);

        listMock.mockResolvedValue([{ uid: 'course-devops', title: 'DevOps' }]);
        await second.refresh();

        expect(second.catalog.value).toEqual([{ uid: 'course-devops', title: 'DevOps' }]);
        expect(listMock).toHaveBeenCalledTimes(2);
    });

    it('mengganti katalog dari respons sync dan mengabaikan GET yang tersusul', async () => {
        let resolveList: (courses: IDuCourseOption[]) => void = () => {};
        listMock.mockReturnValue(
            new Promise<IDuCourseOption[]>((resolve) => {
                resolveList = resolve;
            })
        );
        const state = useDuCourseCatalog('/admin/du/courses?sync');

        const pending = state.ensureLoaded();

        expect(state.setCatalog([{ uid: 'course-sync', title: 'Sync' }])).toBe(true);
        expect(state.catalog.value).toEqual([{ uid: 'course-sync', title: 'Sync' }]);

        resolveList([{ uid: 'course-stale', title: 'Stale' }]);
        await pending;

        expect(state.catalog.value).toEqual([{ uid: 'course-sync', title: 'Sync' }]);
    });

    it('menolak katalog sync yang tidak lengkap', () => {
        const state = useDuCourseCatalog('/admin/du/courses?invalid');

        expect(state.setCatalog([{ uid: '', title: 'Tanpa UID' }])).toBe(false);
        expect(state.catalog.value).toEqual([]);
    });
});
