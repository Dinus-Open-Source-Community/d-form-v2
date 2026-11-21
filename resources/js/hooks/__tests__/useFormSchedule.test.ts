import { describe, expect, it, vi } from 'vitest';
import { useFormSchedule } from '@/hooks/useFormSchedule';

vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn(), delete: vi.fn() } }));

describe('useFormSchedule', () => {
    it('menolak tanggal ganda sebelum mengirim permintaan', () => {
        const schedule = useFormSchedule(
            [{ id: 'a', session_date: '2026-12-07', label: null, attendance_count: 0 }],
            'event',
            'form'
        );
        const result = schedule.addSession('2026-12-07');
        expect(result).toBe(false);
        expect(schedule.error.value).toBe('Tanggal ini sudah ada di jadwal.');
    });

    it('menyinkronkan sesi saat payload halaman berubah', () => {
        const schedule = useFormSchedule(
            [{ id: 'a', session_date: '2026-12-07', label: null, attendance_count: 0 }],
            'event',
            'form'
        );
        schedule.syncSessions([{ id: 'b', session_date: '2026-12-08', label: null, attendance_count: 0 }]);
        expect(schedule.sessions.value).toEqual([
            { id: 'b', session_date: '2026-12-08', label: null, attendance_count: 0 },
        ]);
    });

    it('menonaktifkan hapus saat tanggal sudah punya absensi', () => {
        const schedule = useFormSchedule(
            [{ id: 'a', session_date: '2026-12-07', label: null, attendance_count: 3 }],
            'event',
            'form'
        );
        expect(schedule.canRemove('a')).toBe(false);
    });
});
