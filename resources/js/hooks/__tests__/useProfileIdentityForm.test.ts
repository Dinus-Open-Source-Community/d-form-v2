import { describe, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import { routes } from '@/lib/routes';
import { useProfileIdentityForm } from '@/hooks/useProfileIdentityForm';
import type { IProfileIdentityFormValues } from '@/types/profile';

const inertiaMock = vi.hoisted(() => ({
    useForm: vi.fn(),
}));

const toastMock = vi.hoisted(() => ({
    handleInertiaFormErrors: vi.fn(),
    showInfoToast: vi.fn(),
    showSuccessToast: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: inertiaMock.useForm,
}));

vi.mock('@/hooks/useToastNotifications', () => ({
    useToastNotifications: () => toastMock,
}));

interface IFakeIdentityForm extends IProfileIdentityFormValues {
    processing: boolean;
    errors: Record<string, string>;
    transform: (callback: (values: IProfileIdentityFormValues) => object) => IFakeIdentityForm;
    patch: ReturnType<typeof vi.fn>;
    defaults: (fields: Partial<IProfileIdentityFormValues>) => IFakeIdentityForm;
}

interface IIdentityHarness {
    form: IFakeIdentityForm;
    submitted: object[];
}

/** Form Inertia tiruan: menangkap payload setelah `transform` dan memicu `onSuccess`. */
function createFakeIdentityForm(): IIdentityHarness {
    let transform = (values: IProfileIdentityFormValues): object => values;
    const submitted: object[] = [];

    const form = reactive<IFakeIdentityForm>({
        name: 'Budi Santoso',
        email: 'budi@example.test',
        processing: false,
        errors: {},
        transform: (callback): IFakeIdentityForm => {
            transform = callback;
            return form;
        },
        patch: vi.fn((url: string, options?: { onSuccess?: () => void }): void => {
            submitted.push(transform({ name: form.name, email: form.email }));
            options?.onSuccess?.();
        }),
        defaults: (fields): IFakeIdentityForm => {
            Object.assign(form, fields);
            return form;
        },
    });

    return { form, submitted };
}

function mountIdentityForm(): IIdentityHarness & { identity: ReturnType<typeof useProfileIdentityForm> } {
    const harness = createFakeIdentityForm();
    inertiaMock.useForm.mockReturnValue(harness.form);

    const identity = useProfileIdentityForm({
        id: 'uuid-1',
        name: 'Budi Santoso',
        email: 'budi@example.test',
        avatar: null,
        has_local_password: true,
    });

    return { ...harness, identity };
}

describe('useProfileIdentityForm', () => {
    it('starts clean and becomes dirty only when a field differs', () => {
        const { identity, form } = mountIdentityForm();

        expect(identity.isDirty.value).toBe(false);

        form.name = 'Budi Santoso Baru';
        expect(identity.isDirty.value).toBe(true);

        form.name = 'Budi Santoso';
        expect(identity.isDirty.value).toBe(false);
    });

    it('submits only name and email with preserved scroll', () => {
        const { identity, form, submitted } = mountIdentityForm();
        form.name = 'Budi Santoso Baru';
        form.email = 'budi.baru@example.test';

        identity.submit();

        expect(form.patch).toHaveBeenCalledTimes(1);
        expect(form.patch).toHaveBeenCalledWith(
            routes.dashboard.profile,
            expect.objectContaining({ preserveScroll: true })
        );
        expect(submitted[0]).toEqual({ name: 'Budi Santoso Baru', email: 'budi.baru@example.test' });
        expect(identity.isDirty.value).toBe(false);
    });

    it('skips the request when nothing changed', () => {
        const { identity, form } = mountIdentityForm();

        identity.submit();

        expect(form.patch).not.toHaveBeenCalled();
        expect(toastMock.showInfoToast).toHaveBeenCalledTimes(1);
    });

    it('forwards server validation errors to the shared toast handler', () => {
        const { identity, form } = mountIdentityForm();
        form.name = 'Budi Santoso Baru';
        form.patch.mockImplementationOnce(
            (url: string, options?: { onError?: (errors: Record<string, string>) => void }): void => {
                options?.onError?.({ email: 'Email sudah dipakai.' });
            }
        );

        identity.submit();

        expect(toastMock.handleInertiaFormErrors).toHaveBeenCalledTimes(1);
        expect(toastMock.handleInertiaFormErrors.mock.calls[0]?.[1]?.title).toBe('Gagal menyimpan profil');
    });
});
