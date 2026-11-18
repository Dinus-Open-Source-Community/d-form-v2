import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useArchivedEventsPage } from '@/hooks/useArchivedEventsPage';

/** Callback visit Inertia yang direkam; test memicunya manual untuk menguji lifecycle. */
interface IRecordedVisitOptions {
    preserveScroll?: boolean;
    preserveState?: boolean;
    onSuccess?: () => void;
    onError?: (errors: Record<string, string>) => void;
    onFinish?: () => void;
}

/** Wayfinder dan router dimock dengan signature sempit agar assertion tidak butuh cast. */
const wayfinderMock = vi.hoisted(() => ({
    forceDelete: vi.fn<(args: string | { event: string }) => { url: string; method: string }>(),
    restore: vi.fn<(args: string | { event: string }) => { url: string; method: string }>(),
}));

const routerMock = vi.hoisted(() => ({
    post: vi.fn<(url: string, data: Record<string, unknown>, options?: IRecordedVisitOptions) => void>(),
    delete: vi.fn<(url: string, options?: IRecordedVisitOptions) => void>(),
}));

const toastMock = vi.hoisted(() => ({
    showSuccessToast: vi.fn(),
    handleInertiaFormErrors: vi.fn(),
}));

vi.mock('@/actions/App/Http/Controllers/Dashboard/Events/ArchivedEventController', () => ({
    destroy: wayfinderMock.forceDelete,
}));

vi.mock('@/actions/App/Http/Controllers/Dashboard/Events/EventController', () => ({
    restore: wayfinderMock.restore,
}));

vi.mock('@/hooks/useToastNotifications', () => ({
    useToastNotifications: () => toastMock,
}));

vi.mock('@inertiajs/vue3', () => ({
    router: routerMock,
}));

function readEventId(args: string | { event: string }): string {
    return typeof args === 'string' ? args : args.event;
}

function buildEvent(overrides: Partial<IEvent> = {}): IEvent {
    return {
        id: 'event-1',
        slug: 'arsip-2026',
        title: 'Arsip',
        description: 'Acara yang sudah diarsipkan',
        start_date: '2026-01-10T08:00:00Z',
        end_date: '2026-01-10T10:00:00Z',
        registration_start: '2025-12-01T08:00:00Z',
        registration_end: '2026-01-05T08:00:00Z',
        location: 'Gedung A',
        quota: 100,
        registered_count: 40,
        banner: 'banner.png',
        banner_url: null,
        price: 0,
        session: [],
        category: [],
        status: 'published',
        registration_status: 'closed',
        deleted_at: '2026-02-01T08:00:00Z',
        created_at: '2025-11-01T08:00:00Z',
        updated_at: '2026-02-01T08:00:00Z',
        ...overrides,
    };
}

/** Options visit terakhir dari mock; `undefined` berarti visit tidak pernah dikirim. */
function lastPostOptions(): IRecordedVisitOptions | undefined {
    return routerMock.post.mock.calls.at(-1)?.[2];
}

function lastDeleteOptions(): IRecordedVisitOptions | undefined {
    return routerMock.delete.mock.calls.at(-1)?.[1];
}

beforeEach(() => {
    wayfinderMock.forceDelete.mockImplementation((args) => ({
        url: `/admin/events/${readEventId(args)}/force`,
        method: 'delete',
    }));
    wayfinderMock.restore.mockImplementation((args) => ({
        url: `/admin/events/${readEventId(args)}/restore`,
        method: 'post',
    }));
});

describe('useArchivedEventsPage', () => {
    it('confirmation hanya cocok setelah judul target diketik persis (trim di kedua sisi)', () => {
        const page = useArchivedEventsPage();

        expect(page.isConfirmationMatched.value).toBe(false);

        page.requestForceDelete(buildEvent());
        expect(page.confirmationInput.value).toBe('');
        expect(page.isConfirmationMatched.value).toBe(false);

        page.confirmationInput.value = 'Ars';
        expect(page.isConfirmationMatched.value).toBe(false);

        page.confirmationInput.value = '  Arsip  ';
        expect(page.isConfirmationMatched.value).toBe(true);
    });

    it('confirmForceDelete tidak mengirim request selama input belum cocok', () => {
        const page = useArchivedEventsPage();

        page.requestForceDelete(buildEvent());
        page.confirmationInput.value = 'Arsip salah';

        page.confirmForceDelete();

        expect(routerMock.delete).not.toHaveBeenCalled();
        expect(page.isForceDeleting.value).toBe(false);
        expect(page.forceDeleteDialogOpen.value).toBe(true);
    });

    it('confirmForceDelete tanpa target tidak mengirim request', () => {
        const page = useArchivedEventsPage();

        page.confirmForceDelete();

        expect(routerMock.delete).not.toHaveBeenCalled();
    });

    it('confirmForceDelete mengirim delete ke URL target dan menandai in-flight saat cocok', () => {
        const page = useArchivedEventsPage();
        const event = buildEvent();

        page.requestForceDelete(event);
        page.confirmationInput.value = 'Arsip';

        page.confirmForceDelete();

        expect(routerMock.delete).toHaveBeenCalledTimes(1);
        expect(routerMock.delete).toHaveBeenCalledWith(
            `/admin/events/${event.id}/force`,
            expect.objectContaining({ preserveScroll: true })
        );
        expect(page.isForceDeleting.value).toBe(true);
    });

    it('confirmRestore memposting return_to archived dengan preserveScroll dan preserveState', () => {
        const page = useArchivedEventsPage();
        const event = buildEvent();

        page.requestRestore(event);
        page.confirmRestore();

        expect(routerMock.post).toHaveBeenCalledTimes(1);
        expect(routerMock.post).toHaveBeenCalledWith(
            `/admin/events/${event.id}/restore`,
            { return_to: 'archived' },
            expect.objectContaining({ preserveScroll: true, preserveState: true })
        );
        expect(page.isRestoring.value).toBe(true);
    });

    it('confirmRestore tanpa target tidak mengirim request', () => {
        const page = useArchivedEventsPage();

        page.confirmRestore();

        expect(routerMock.post).not.toHaveBeenCalled();
        expect(page.isRestoring.value).toBe(false);
    });

    it('onFinish force delete menutup dialog, mengosongkan target, dan mereset flag', () => {
        const page = useArchivedEventsPage();

        page.requestForceDelete(buildEvent());
        page.confirmationInput.value = 'Arsip';
        page.confirmForceDelete();

        lastDeleteOptions()?.onFinish?.();

        expect(page.forceDeleteDialogOpen.value).toBe(false);
        expect(page.forceDeleteTarget.value).toBeNull();
        expect(page.confirmationInput.value).toBe('');
        expect(page.isForceDeleting.value).toBe(false);
    });

    it('onFinish restore menutup dialog, mengosongkan target, dan mereset flag', () => {
        const page = useArchivedEventsPage();

        page.requestRestore(buildEvent());
        page.confirmRestore();

        lastPostOptions()?.onFinish?.();

        expect(page.restoreDialogOpen.value).toBe(false);
        expect(page.restoreTarget.value).toBeNull();
        expect(page.confirmationInput.value).toBe('');
        expect(page.isRestoring.value).toBe(false);
    });

    it('dismissForceDelete sebelum confirmForceDelete tetap menghapus (urutan tutup reka-ui)', () => {
        const page = useArchivedEventsPage();
        const event = buildEvent();

        page.requestForceDelete(event);
        page.confirmationInput.value = 'Arsip';

        // `AlertDialogAction` menutup dialog lebih dulu; handler confirm setelahnya harus
        // masih melihat target dan gate yang cocok, bukan no-op.
        page.dismissForceDelete();
        expect(page.forceDeleteDialogOpen.value).toBe(false);
        expect(page.forceDeleteTarget.value).toStrictEqual(event);
        expect(page.confirmationInput.value).toBe('Arsip');

        page.confirmForceDelete();

        expect(routerMock.delete).toHaveBeenCalledTimes(1);
        expect(routerMock.delete).toHaveBeenCalledWith(
            `/admin/events/${event.id}/force`,
            expect.objectContaining({ preserveScroll: true })
        );
        expect(page.isForceDeleting.value).toBe(true);
    });

    it('dismissRestore sebelum confirmRestore tetap memulihkan target', () => {
        const page = useArchivedEventsPage();
        const event = buildEvent();

        page.requestRestore(event);
        page.dismissRestore();

        expect(page.restoreDialogOpen.value).toBe(false);
        expect(page.restoreTarget.value).toStrictEqual(event);

        page.confirmRestore();

        expect(routerMock.post).toHaveBeenCalledTimes(1);
        expect(routerMock.post.mock.calls.at(-1)?.[1]).toEqual({ return_to: 'archived' });
        expect(page.isRestoring.value).toBe(true);
    });

    it('cancelForceDelete dan cancelRestore mengosongkan input serta target', () => {
        const page = useArchivedEventsPage();

        page.requestForceDelete(buildEvent());
        page.confirmationInput.value = 'Arsip';
        page.cancelForceDelete();
        expect(page.confirmationInput.value).toBe('');
        expect(page.forceDeleteDialogOpen.value).toBe(false);
        expect(page.forceDeleteTarget.value).toBeNull();

        page.confirmationInput.value = 'Arsip';
        page.requestRestore(buildEvent());
        page.cancelRestore();
        expect(page.confirmationInput.value).toBe('');
        expect(page.restoreDialogOpen.value).toBe(false);
        expect(page.restoreTarget.value).toBeNull();
    });

    it('sukses force delete tidak memunculkan toast klien karena backend sudah mem-flash', () => {
        const page = useArchivedEventsPage();

        page.requestForceDelete(buildEvent());
        page.confirmationInput.value = 'Arsip';
        page.confirmForceDelete();
        lastDeleteOptions()?.onSuccess?.();

        expect(routerMock.delete).toHaveBeenCalledTimes(1);
        expect(toastMock.showSuccessToast).not.toHaveBeenCalled();
    });

    it('error force delete diteruskan ke handleInertiaFormErrors dengan judul spesifik', () => {
        const page = useArchivedEventsPage();
        const errors: Record<string, string> = { event: 'Tidak dapat dihapus.' };

        page.requestForceDelete(buildEvent());
        page.confirmationInput.value = 'Arsip';
        page.confirmForceDelete();
        lastDeleteOptions()?.onError?.(errors);

        expect(toastMock.handleInertiaFormErrors).toHaveBeenCalledWith(errors, {
            title: 'Gagal menghapus permanen acara',
        });
    });

    it('sukses restore tidak memunculkan toast klien karena backend sudah mem-flash', () => {
        const page = useArchivedEventsPage();

        page.requestRestore(buildEvent());
        page.confirmRestore();
        lastPostOptions()?.onSuccess?.();

        expect(routerMock.post).toHaveBeenCalledTimes(1);
        expect(toastMock.showSuccessToast).not.toHaveBeenCalled();
    });

    it('error restore diteruskan ke handleInertiaFormErrors dengan judul spesifik', () => {
        const page = useArchivedEventsPage();
        const errors: Record<string, string> = { event: 'Tidak dapat dipulihkan.' };

        page.requestRestore(buildEvent());
        page.confirmRestore();
        lastPostOptions()?.onError?.(errors);

        expect(toastMock.handleInertiaFormErrors).toHaveBeenCalledWith(errors, {
            title: 'Gagal memulihkan acara',
        });
    });
});
