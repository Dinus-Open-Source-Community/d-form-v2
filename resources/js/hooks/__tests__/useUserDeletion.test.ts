import { describe, expect, it, vi } from 'vitest';
import { router } from '@inertiajs/vue3';
import { useUserDeletion } from '@/hooks/useUserDeletion';
import type { IManagedUser } from '@/types/user-management';

const toastMock = vi.hoisted(() => ({
    showErrorToast: vi.fn(),
}));

vi.mock('@/hooks/useToastNotifications', () => ({
    useToastNotifications: () => toastMock,
}));

vi.mock('@inertiajs/vue3', () => ({
    router: {
        delete: vi.fn(),
    },
}));

const routerDeleteMock = vi.mocked(router.delete);

const MANAGED_USER: IManagedUser = {
    id: 'uuid-1',
    name: 'Budi Santoso',
    email: 'budi@example.test',
    roles: ['member'],
    avatar_url: null,
    can_edit: true,
    can_delete: true,
    created_at: '2026-01-10T08:00:00Z',
};

/** Callback visit yang direkam dari `router.delete`, tanpa menghiraukan argumennya. */
interface IRecordedDeleteOptions {
    onStart?: (...args: never[]) => void;
    onSuccess?: (...args: never[]) => void;
    onError?: (...args: never[]) => void;
    onFinish?: (...args: never[]) => void;
}

describe('useUserDeletion', () => {
    it('confirm deletes the target and closes the dialog', () => {
        routerDeleteMock.mockImplementation(
            (_url: Parameters<typeof router.delete>[0], options?: IRecordedDeleteOptions): void => {
                options?.onStart?.();
                options?.onSuccess?.();
                options?.onFinish?.();
            }
        );

        const deletion = useUserDeletion();

        deletion.requestDelete(MANAGED_USER);
        expect(deletion.showDeleteModal.value).toBe(true);

        deletion.confirmDelete();

        expect(routerDeleteMock).toHaveBeenCalledTimes(1);
        expect(routerDeleteMock).toHaveBeenCalledWith(
            `/admin/users/${MANAGED_USER.id}`,
            expect.objectContaining({ preserveScroll: true })
        );
        expect(deletion.showDeleteModal.value).toBe(false);
        expect(deletion.isDeleting.value).toBe(false);
    });

    it('still deletes when the dialog closes before the confirm handler runs', () => {
        routerDeleteMock.mockImplementation(
            (_url: Parameters<typeof router.delete>[0], options?: IRecordedDeleteOptions): void => {
                options?.onStart?.();
                options?.onSuccess?.();
                options?.onFinish?.();
            }
        );

        const deletion = useUserDeletion();

        deletion.requestDelete(MANAGED_USER);
        deletion.dismissDelete();
        deletion.confirmDelete();

        expect(routerDeleteMock).toHaveBeenCalledTimes(1);
        expect(deletion.showDeleteModal.value).toBe(false);
    });

    it('keeps the dialog open and toasts on error', () => {
        routerDeleteMock.mockImplementation(
            (_url: Parameters<typeof router.delete>[0], options?: IRecordedDeleteOptions): void => {
                options?.onStart?.();
                options?.onError?.();
                options?.onFinish?.();
            }
        );

        const deletion = useUserDeletion();

        deletion.requestDelete(MANAGED_USER);
        deletion.confirmDelete();

        expect(deletion.showDeleteModal.value).toBe(true);
        expect(deletion.isDeleting.value).toBe(false);
        expect(toastMock.showErrorToast).toHaveBeenCalledTimes(1);
    });

    it('requestDelete ignores a user without can_delete', () => {
        const deletion = useUserDeletion();

        deletion.requestDelete({ ...MANAGED_USER, can_delete: false });

        expect(deletion.showDeleteModal.value).toBe(false);

        deletion.confirmDelete();
        expect(routerDeleteMock).not.toHaveBeenCalled();
    });

    it('cancelDelete drops the target without sending a request', () => {
        const deletion = useUserDeletion();

        deletion.requestDelete(MANAGED_USER);
        deletion.cancelDelete();

        expect(deletion.showDeleteModal.value).toBe(false);
        expect(routerDeleteMock).not.toHaveBeenCalled();
    });
});
