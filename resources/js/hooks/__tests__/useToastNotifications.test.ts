import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('vue-sonner', () => {
    const toast = Object.assign(vi.fn(), {
        error: vi.fn(),
        success: vi.fn(),
        info: vi.fn(),
        warning: vi.fn(),
    });

    return { toast };
});

import { toast } from 'vue-sonner';
import { useToastNotifications } from '@/hooks/useToastNotifications';

const defaultToast = vi.mocked(toast);
const errorToast = vi.mocked(toast.error);
const successToast = vi.mocked(toast.success);
const infoToast = vi.mocked(toast.info);
const warningToast = vi.mocked(toast.warning);

describe('useToastNotifications', () => {
    beforeEach(() => {
        defaultToast.mockClear();
        errorToast.mockClear();
        successToast.mockClear();
        infoToast.mockClear();
        warningToast.mockClear();
    });

    it('returns a stable object across calls', () => {
        expect(useToastNotifications()).toBe(useToastNotifications());
    });

    it('shows a single validation error with its humanized label', () => {
        useToastNotifications().showValidationErrorToast(
            { email: 'The email field is required.' },
            { title: 'Gagal masuk' }
        );

        expect(errorToast).toHaveBeenCalledWith('Gagal masuk', {
            description: 'Email: Email wajib diisi.',
            duration: 6000,
        });
    });

    it('joins multiple validation errors and scales the duration', () => {
        useToastNotifications().showValidationErrorToast({
            email: 'The email field is required.',
            name: 'The name field is required.',
        });

        expect(errorToast).toHaveBeenCalledWith('Validasi gagal', {
            description: 'Email: Email wajib diisi.\nNama: Nama wajib diisi.',
            duration: 5600,
        });
    });

    it('still shows a generic toast when the error bag is empty', () => {
        useToastNotifications().showValidationErrorToast({});

        expect(errorToast).toHaveBeenCalledWith('Validasi gagal', {
            description: 'Periksa kembali formulir dan lengkapi field yang ditandai.',
        });
    });

    it('forwards Inertia onError bags to the validation toast', () => {
        useToastNotifications().handleInertiaFormErrors(
            { email: 'The email field is required.' },
            { title: 'Gagal mendaftar' }
        );

        expect(errorToast).toHaveBeenCalledTimes(1);
        expect(errorToast.mock.calls[0]?.[0]).toBe('Gagal mendaftar');
    });

    it('prefers the API body message for HTTP errors', () => {
        useToastNotifications().showHttpErrorToast(422, { message: 'Form deleted.' });

        expect(errorToast).toHaveBeenCalledWith('Form berhasil dihapus.');
    });

    it('falls back to the default message per status', () => {
        useToastNotifications().showHttpErrorToast(404);

        expect(errorToast).toHaveBeenCalledWith('Data tidak ditemukan.');
    });

    it('honours per-status overrides', () => {
        useToastNotifications().showHttpErrorToast(503, undefined, { 503: 'Layanan sibuk.' });

        expect(errorToast).toHaveBeenCalledWith('Layanan sibuk.');
    });

    it('humanizes free-form messages before showing them', () => {
        useToastNotifications().showErrorToast('Password does not match.');

        expect(errorToast).toHaveBeenCalledWith('Konfirmasi kata sandi tidak cocok.', {
            description: undefined,
            duration: undefined,
        });
    });

    it('uses the title as heading and the message as description when provided', () => {
        useToastNotifications().showErrorToast('Password does not match.', { title: 'Gagal' });

        expect(errorToast).toHaveBeenCalledWith('Gagal', {
            description: 'Konfirmasi kata sandi tidak cocok.',
            duration: undefined,
        });
    });

    it('maps flash success to a success toast and other types to error', () => {
        const notifications = useToastNotifications();

        notifications.showFlashToast({ type: 'success', message: 'Login success' });
        notifications.showFlashToast({ type: 'error', message: 'Login failed' });
        notifications.showFlashToast(null);

        expect(successToast).toHaveBeenCalledWith('Berhasil masuk.');
        expect(errorToast).toHaveBeenCalledWith('Gagal masuk. Periksa email dan kata sandi.');
        expect(errorToast).toHaveBeenCalledTimes(1);
    });

    it('keeps the autosave retry action', () => {
        useToastNotifications().notifyAutosaveFailure('gagal', vi.fn());

        const call = errorToast.mock.calls[0];
        expect(call?.[0]).toBe('gagal');

        const options = call?.[1];
        const action = options?.action;
        if (action === undefined || !('label' in action)) {
            throw new Error('expected an action toast');
        }

        expect(action.label).toBe('Coba lagi');
        expect(typeof action.onClick).toBe('function');
        expect(options?.duration).toBe(12_000);
    });

    it('shows success, info, and warning toasts with passthrough options', () => {
        const notifications = useToastNotifications();

        notifications.showSuccessToast('Disimpan.', { duration: 5000, id: 'save-result' });
        notifications.showInfoToast('Mengekspor…');
        notifications.showWarningToast('Kamera terhenti.', { description: 'Mulai ulang kamera.' });
        notifications.showToast('Riwayat scan dibersihkan');

        expect(successToast).toHaveBeenCalledWith('Disimpan.', {
            description: undefined,
            duration: 5000,
            id: 'save-result',
        });
        expect(infoToast).toHaveBeenCalledWith('Mengekspor…', undefined);
        expect(warningToast).toHaveBeenCalledWith('Kamera terhenti.', {
            description: 'Mulai ulang kamera.',
            duration: undefined,
            id: undefined,
        });
        expect(defaultToast).toHaveBeenCalledWith('Riwayat scan dibersihkan', undefined);
    });
});
