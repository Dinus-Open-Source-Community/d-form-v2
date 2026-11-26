import { describe, expect, it, vi } from 'vitest';
import { useDuCourseSync } from '@/hooks/useDuCourseSync';
import { syncDuCourses } from '@/adapters/http/duCourses';

const { showErrorToastMock } = vi.hoisted(() => ({ showErrorToastMock: vi.fn() }));

vi.mock('@/adapters/http/duCourses', () => ({ syncDuCourses: vi.fn() }));
vi.mock('@/hooks/useToastNotifications', () => ({
    useToastNotifications: () => ({ showErrorToast: showErrorToastMock }),
}));

const syncMock = vi.mocked(syncDuCourses);

// `clearMocks` hanya membersihkan riwayat call, bukan implementasi; tiap test
// memasang implementasinya sendiri. `mockReset()` di Vitest 5.1 membuat
// rejection `mockRejectedValue` terbaca sebagai unhandled.
describe('useDuCourseSync', () => {
    it('stores courses and clears syncing on success', async () => {
        syncMock.mockResolvedValue({ courses: [{ uid: 'a', title: 'Kelas A' }], syncedAt: '2026-11-24T00:00:00Z' });
        const state = useDuCourseSync('/admin/du/courses/sync');

        const promise = state.sync();
        expect(state.syncing.value).toBe(true);
        await promise;

        expect(state.syncing.value).toBe(false);
        expect(state.courses.value).toEqual([{ uid: 'a', title: 'Kelas A' }]);
        expect(state.error.value).toBe('');
    });

    it('surfaces a safe message on failure', async () => {
        syncMock.mockRejectedValue(new Error('boom'));
        const state = useDuCourseSync('/admin/du/courses/sync');

        await state.sync();

        expect(state.syncing.value).toBe(false);
        expect(state.error.value).toBe('Gagal memuat katalog kelas DU. Coba lagi.');
        expect(showErrorToastMock).toHaveBeenCalledWith('Gagal memuat katalog kelas DU. Coba lagi.');
    });
});
