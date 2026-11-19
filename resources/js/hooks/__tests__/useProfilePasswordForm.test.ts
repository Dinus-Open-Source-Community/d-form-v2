import { reactive } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import { routes } from '@/lib/routes';
import { useProfilePasswordForm } from '@/hooks/useProfilePasswordForm';
import type { IProfilePasswordFormValues } from '@/types/profile';

const inertiaMock = vi.hoisted(() => ({
    useForm: vi.fn(),
}));

const toastMock = vi.hoisted(() => ({
    handleInertiaFormErrors: vi.fn(),
    showErrorToast: vi.fn(),
    showInfoToast: vi.fn(),
    showSuccessToast: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: inertiaMock.useForm,
}));

vi.mock('@/hooks/useToastNotifications', () => ({
    useToastNotifications: () => toastMock,
}));

interface IFakePasswordForm extends IProfilePasswordFormValues {
    processing: boolean;
    errors: Record<string, string>;
    transform: (callback: (values: IProfilePasswordFormValues) => object) => IFakePasswordForm;
    put: ReturnType<typeof vi.fn>;
    reset: () => IFakePasswordForm;
    dontRemember: (...fields: Array<keyof IProfilePasswordFormValues>) => IFakePasswordForm;
}

interface IPasswordHarness {
    form: IFakePasswordForm;
    submitted: object[];
}

/** Form Inertia tiruan: menangkap payload setelah `transform` dan memicu `onSuccess`. */
function createFakePasswordForm(): IPasswordHarness {
    let transform = (values: IProfilePasswordFormValues): object => values;
    const submitted: object[] = [];

    const readValues = (): IProfilePasswordFormValues => ({
        current_password: form.current_password,
        password: form.password,
        password_confirmation: form.password_confirmation,
    });

    const form = reactive<IFakePasswordForm>({
        current_password: '',
        password: '',
        password_confirmation: '',
        processing: false,
        errors: {},
        transform: (callback): IFakePasswordForm => {
            transform = callback;
            return form;
        },
        put: vi.fn((url: string, options?: { onSuccess?: () => void }): void => {
            submitted.push(transform(readValues()));
            options?.onSuccess?.();
        }),
        reset: vi.fn((): IFakePasswordForm => {
            form.current_password = '';
            form.password = '';
            form.password_confirmation = '';
            return form;
        }),
        dontRemember: vi.fn((): IFakePasswordForm => form),
    });

    return { form, submitted };
}

function mountPasswordForm(hasLocalPassword: boolean): IPasswordHarness & {
    password: ReturnType<typeof useProfilePasswordForm>;
} {
    const harness = createFakePasswordForm();
    inertiaMock.useForm.mockReturnValue(harness.form);

    const password = useProfilePasswordForm({
        id: 'uuid-1',
        name: 'Budi Santoso',
        email: 'budi@example.test',
        avatar: null,
        has_local_password: hasLocalPassword,
    });

    return { ...harness, password };
}

describe('useProfilePasswordForm', () => {
    it('keeps each password field hidden by default', () => {
        const { password } = mountPasswordForm(true);

        expect(password.visibility.current.value).toBe(false);
        expect(password.visibility.next.value).toBe(false);
        expect(password.visibility.confirm.value).toBe(false);
    });

    it('toggles only the visibility of the requested field', () => {
        const { password } = mountPasswordForm(true);

        password.toggleVisibility('next');

        expect(password.visibility.next.value).toBe(true);
        expect(password.visibility.current.value).toBe(false);
        expect(password.visibility.confirm.value).toBe(false);

        password.toggleVisibility('confirm');

        expect(password.visibility.next.value).toBe(true);
        expect(password.visibility.confirm.value).toBe(true);

        password.toggleVisibility('next');

        expect(password.visibility.next.value).toBe(false);
        expect(password.visibility.confirm.value).toBe(true);
    });

    it('reports dirty as soon as any password field is filled', () => {
        const { password, form } = mountPasswordForm(true);

        expect(password.isDirty.value).toBe(false);

        form.password = 'rahasia123';
        expect(password.isDirty.value).toBe(true);
    });

    it('omits current_password when the account has no local password', () => {
        const { password, form, submitted } = mountPasswordForm(false);
        form.password = 'rahasia123';
        form.password_confirmation = 'rahasia123';

        password.submit();

        expect(password.hasLocalPassword.value).toBe(false);
        expect(form.put).toHaveBeenCalledWith(
            routes.dashboard.profilePassword,
            expect.objectContaining({ preserveScroll: true })
        );
        expect(submitted[0]).toEqual({ password: 'rahasia123', password_confirmation: 'rahasia123' });
        expect(form.current_password).toBe('');
    });

    it('includes current_password when the account already has a password', () => {
        const { password, form, submitted } = mountPasswordForm(true);
        form.current_password = 'lama12345';
        form.password = 'rahasia123';
        form.password_confirmation = 'rahasia123';

        password.submit();

        expect(password.hasLocalPassword.value).toBe(true);
        expect(submitted[0]).toEqual({
            current_password: 'lama12345',
            password: 'rahasia123',
            password_confirmation: 'rahasia123',
        });
        expect(form.reset).toHaveBeenCalledTimes(1);
    });

    it('keeps all password fields out of remembered state', () => {
        const { form } = mountPasswordForm(true);

        expect(form.dontRemember).toHaveBeenCalledWith('current_password', 'password', 'password_confirmation');
    });

    it('skips the request when nothing was typed', () => {
        const { password, form } = mountPasswordForm(true);

        password.submit();

        expect(form.put).not.toHaveBeenCalled();
        expect(toastMock.showInfoToast).toHaveBeenCalledTimes(1);
    });

    it('blocks a new password shorter than eight characters without calling the request', () => {
        const { password, form } = mountPasswordForm(true);
        form.current_password = 'lama12345';
        form.password = 'Rahasia';
        form.password_confirmation = 'Rahasia';

        password.submit();

        expect(form.put).not.toHaveBeenCalled();
        expect(toastMock.showErrorToast).toHaveBeenCalledWith('Password must be at least 8 characters.');
    });

    it('blocks a confirmation that does not match the new password without calling the request', () => {
        const { password, form } = mountPasswordForm(true);
        form.current_password = 'lama12345';
        form.password = 'Rahasia123';
        form.password_confirmation = 'Rahasia124';

        password.submit();

        expect(form.put).not.toHaveBeenCalled();
        expect(toastMock.showErrorToast).toHaveBeenCalledWith('Password does not match');
    });

    it('submits once the password is long enough and the confirmation matches', () => {
        const { password, form, submitted } = mountPasswordForm(true);
        form.current_password = 'lama12345';
        form.password = 'Rahasia123';
        form.password_confirmation = 'Rahasia123';

        password.submit();

        expect(toastMock.showErrorToast).not.toHaveBeenCalled();
        expect(submitted[0]).toEqual({
            current_password: 'lama12345',
            password: 'Rahasia123',
            password_confirmation: 'Rahasia123',
        });
    });
});
