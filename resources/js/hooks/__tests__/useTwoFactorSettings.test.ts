import { describe, expect, it, vi } from 'vitest';
import { useTwoFactorSettings } from '@/hooks/useTwoFactorSettings';
import type { IUseTwoFactorSettingsOptions } from '@/hooks/useTwoFactorSettings';

const inertiaMock = vi.hoisted(() => ({
    usePage: vi.fn(),
    router: {
        post: vi.fn(),
        put: vi.fn(),
        delete: vi.fn(),
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: inertiaMock.usePage,
    router: inertiaMock.router,
}));

function mountSettings(options: Partial<IUseTwoFactorSettingsOptions> = {}) {
    inertiaMock.usePage.mockReturnValue({ props: { errors: {} } });

    return useTwoFactorSettings({
        pageProps: { two_factor: { enabled: false, attemptsRemaining: 5, lockedUntil: null } },
        hasLocalPassword: true,
        ...options,
    });
}

describe('useTwoFactorSettings', () => {
    it('reads the pin status from the page props', () => {
        const off = mountSettings();

        expect(off.status.value).toEqual({ enabled: false, attemptsRemaining: 5, lockedUntil: null });

        const on = mountSettings({
            pageProps: { two_factor: { enabled: true, attemptsRemaining: 4, lockedUntil: null } },
        });

        expect(on.status.value.enabled).toBe(true);
    });

    it('treats missing page props as an inactive pin', () => {
        const state = mountSettings({ pageProps: {} });

        expect(state.status.value.enabled).toBe(false);
    });

    it('requires the current password to enable the pin on a password account', () => {
        const state = mountSettings();
        state.open('enable');
        state.newPin.value = '123456';
        state.newPinConfirmation.value = '123456';

        expect(state.requiresCurrentPassword.value).toBe(true);
        expect(state.canSubmit.value).toBe(false);

        state.currentPassword.value = 'rahasia123';

        expect(state.canSubmit.value).toBe(true);
    });

    it('leaves the password out for an account without a local password', () => {
        const state = mountSettings({ hasLocalPassword: false });
        state.open('enable');
        state.newPin.value = '123456';
        state.newPinConfirmation.value = '123456';

        expect(state.requiresCurrentPassword.value).toBe(false);
        expect(state.canSubmit.value).toBe(true);

        state.submit();

        expect(inertiaMock.router.post).toHaveBeenCalledWith(
            '/profile/two-factor',
            { pin: '123456' },
            expect.anything()
        );
    });

    it('blocks a new pin whose confirmation does not match', () => {
        const state = mountSettings({ hasLocalPassword: false });
        state.open('enable');
        state.newPin.value = '123456';
        state.newPinConfirmation.value = '123455';

        expect(state.canSubmit.value).toBe(false);
        expect(state.errors.value.pin_confirmation).toBe('Konfirmasi PIN belum sama.');
    });

    it('requires the current pin to change the pin', () => {
        const state = mountSettings();
        state.open('change');
        state.currentPassword.value = 'rahasia123';
        state.newPin.value = '123456';
        state.newPinConfirmation.value = '123456';

        expect(state.requiresCurrentPin.value).toBe(true);
        expect(state.canSubmit.value).toBe(false);

        state.currentPin.value = '654321';

        expect(state.canSubmit.value).toBe(true);

        state.submit();

        expect(inertiaMock.router.put).toHaveBeenCalledWith(
            '/profile/two-factor',
            { pin: '123456', current_pin: '654321', current_password: 'rahasia123' },
            expect.anything()
        );
    });

    it('requires the current pin to disable the pin', () => {
        const state = mountSettings();
        state.open('disable');
        state.currentPassword.value = 'rahasia123';

        expect(state.canSubmit.value).toBe(false);

        state.currentPin.value = '654321';

        expect(state.canSubmit.value).toBe(true);

        state.submit();

        expect(inertiaMock.router.delete).toHaveBeenCalledWith(
            '/profile/two-factor',
            expect.objectContaining({
                data: { current_pin: '654321', current_password: 'rahasia123' },
                preserveScroll: true,
            })
        );
    });

    it('opens and closes the dialog', () => {
        const state = mountSettings();

        expect(state.isOpen.value).toBe(false);

        state.open('change');

        expect(state.isOpen.value).toBe(true);
        expect(state.mode.value).toBe('change');

        state.close();

        expect(state.isOpen.value).toBe(false);
        expect(state.mode.value).toBeNull();
    });

    it('keeps the dialog open while the request is running', () => {
        const state = mountSettings({ hasLocalPassword: false });
        state.open('enable');
        state.newPin.value = '123456';
        state.newPinConfirmation.value = '123456';

        inertiaMock.router.post.mockImplementationOnce(
            (_url: string, _data: object, options?: { onStart?: () => void }) => {
                options?.onStart?.();
            }
        );

        state.submit();

        expect(state.isSubmitting.value).toBe(true);

        state.close();

        expect(state.isOpen.value).toBe(true);
    });

    it('keeps the enable dialog open and surfaces the server message on failure', () => {
        const state = mountSettings();
        state.open('enable');
        state.currentPassword.value = 'rahasia123';
        state.newPin.value = '123456';
        state.newPinConfirmation.value = '123456';

        inertiaMock.router.post.mockImplementationOnce(
            (_url: string, _data: object, options?: { onError?: (errors: Record<string, string>) => void }) => {
                options?.onError?.({ pin: 'PIN sudah aktif. Gunakan Ubah PIN untuk menggantinya.' });
            }
        );

        state.submit();

        expect(state.isOpen.value).toBe(true);
        expect(state.errors.value.pin).toBe('PIN sudah aktif. Gunakan Ubah PIN untuk menggantinya.');
    });

    it('opens the create dialog when the switch is turned on, without calling the API yet', () => {
        const state = mountSettings({ hasLocalPassword: false });

        expect(state.checked.value).toBe(false);

        state.requestToggle(true);

        expect(state.isOpen.value).toBe(true);
        expect(state.mode.value).toBe('enable');
        expect(state.checked.value).toBe(false);
        expect(inertiaMock.router.post).not.toHaveBeenCalled();
    });

    it('opens the disable dialog when the switch is turned off', () => {
        const state = mountSettings({
            pageProps: { two_factor: { enabled: true, attemptsRemaining: 5, lockedUntil: null } },
        });

        expect(state.checked.value).toBe(true);

        state.requestToggle(false);

        expect(state.isOpen.value).toBe(true);
        expect(state.mode.value).toBe('disable');
        expect(inertiaMock.router.delete).not.toHaveBeenCalled();
    });

    it('restores the switch from the server prop when the dialog is cancelled', () => {
        const state = mountSettings({
            pageProps: { two_factor: { enabled: true, attemptsRemaining: 5, lockedUntil: null } },
        });

        state.requestToggle(false);
        state.close();

        expect(state.isOpen.value).toBe(false);
        expect(state.checked.value).toBe(true);
    });

    it('flips the switch on after a successful enable', () => {
        const state = mountSettings({ hasLocalPassword: false });
        state.requestToggle(true);
        state.newPin.value = '123456';
        state.newPinConfirmation.value = '123456';

        inertiaMock.router.post.mockImplementationOnce(
            (_url: string, _data: object, options?: { onSuccess?: () => void }) => {
                options?.onSuccess?.();
            }
        );

        state.submit();

        expect(state.isOpen.value).toBe(false);
        expect(state.checked.value).toBe(true);
    });

    it('snaps the switch back to the server state when disabling fails', () => {
        const state = mountSettings({ hasLocalPassword: false });

        // Aktifkan lebih dulu supaya sakelar menyala.
        state.requestToggle(true);
        state.newPin.value = '123456';
        state.newPinConfirmation.value = '123456';
        inertiaMock.router.post.mockImplementationOnce(
            (
                _url: string,
                _data: object,
                options?: { onStart?: () => void; onSuccess?: () => void; onFinish?: () => void }
            ) => {
                options?.onStart?.();
                options?.onSuccess?.();
                options?.onFinish?.();
            }
        );
        state.submit();
        expect(state.checked.value).toBe(true);

        // Nonaktifkan ditolak server: sakelar harus kembali ke status server (mati).
        state.requestToggle(false);
        state.currentPin.value = '654321';
        inertiaMock.router.delete.mockImplementationOnce(
            (
                _url: string,
                _options?: {
                    onStart?: () => void;
                    onError?: (errors: Record<string, string>) => void;
                    onFinish?: () => void;
                }
            ) => {
                _options?.onStart?.();
                _options?.onError?.({ current_pin: 'PIN salah. Sisa percobaan: 4.' });
                _options?.onFinish?.();
            }
        );
        state.submit();

        expect(state.isOpen.value).toBe(true);
        expect(state.checked.value).toBe(false);
    });
});
