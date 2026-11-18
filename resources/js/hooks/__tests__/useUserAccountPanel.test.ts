import { describe, expect, it, vi } from 'vitest';
import { routes } from '@/lib/routes';
import { useUserAccountPanel } from '@/hooks/useUserAccountPanel';
import type { IUserAccountPanel, IUserAccountPanelOptions } from '@/hooks/useUserAccountPanel';
import type { IManagedUser, IUserAccountForm, IUserRoleOption } from '@/types/user-management';

const inertiaMock = vi.hoisted(() => ({
    useForm: vi.fn(),
}));

const historyMock = vi.hoisted(() => ({
    replaceHistoryQuery: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: inertiaMock.useForm,
}));

vi.mock('@/adapters/browser/historyQuery', () => ({
    replaceHistoryQuery: historyMock.replaceHistoryQuery,
}));

const ROLE_OPTIONS: IUserRoleOption[] = [
    { value: 'admin', label: 'Admin', description: 'Kelola event dan formulir.' },
    { value: 'member', label: 'Member', description: 'Ikut event dan isi formulir.' },
];

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

const BASE_QUERY = { search: '', role: null, per_page: 20, edit: null, create: false };

interface IFakeForm extends IUserAccountForm {
    processing: boolean;
    clearErrors: () => void;
    setError: (errors: Record<string, string>) => void;
    transform: (callback: (values: IUserAccountForm) => object) => void;
    post: ReturnType<typeof vi.fn>;
    put: ReturnType<typeof vi.fn>;
}

interface ISubmittedForm {
    url: string;
    payload: object;
}

interface IFakeFormHarness {
    form: IFakeForm;
    submitted: ISubmittedForm[];
}

/** Form Inertia tiruan: menangkap payload setelah `transform` dan memicu `onSuccess`. */
function createFakeForm(initial: IUserAccountForm): IFakeFormHarness {
    let transform = (values: IUserAccountForm): object => values;
    const submitted: ISubmittedForm[] = [];

    const readValues = (): IUserAccountForm => ({
        name: form.name,
        email: form.email,
        password: form.password,
        password_confirmation: form.password_confirmation,
        role: form.role,
    });

    const submit = (url: string, options?: { onSuccess?: () => void }): void => {
        submitted.push({ url, payload: transform(readValues()) });
        options?.onSuccess?.();
    };

    const form: IFakeForm = {
        ...initial,
        processing: false,
        clearErrors: vi.fn(),
        setError: vi.fn(),
        transform: (callback): void => {
            transform = callback;
        },
        post: vi.fn(submit),
        put: vi.fn(submit),
    };

    return { form, submitted };
}

interface IMountOverrides {
    query?: Partial<IUserAccountPanelOptions['query']>;
    users?: IManagedUser[];
}

interface IMountedPanel {
    panel: IUserAccountPanel;
    form: IFakeForm;
    submitted: ISubmittedForm[];
}

function mountPanel(overrides: IMountOverrides = {}): IMountedPanel {
    const harness = createFakeForm({ name: '', email: '', password: '', password_confirmation: '', role: 'member' });
    inertiaMock.useForm.mockReturnValue(harness.form);

    const panel = useUserAccountPanel({
        query: { ...BASE_QUERY, ...overrides.query },
        users: overrides.users ?? [MANAGED_USER],
        roleOptions: ROLE_OPTIONS,
    });

    return { panel, form: harness.form, submitted: harness.submitted };
}

describe('useUserAccountPanel', () => {
    it('opens in edit mode with the selected row values', () => {
        const { panel, form } = mountPanel();

        panel.openEdit(MANAGED_USER);

        expect(panel.isOpen.value).toBe(true);
        expect(panel.mode.value).toBe('edit');
        expect(panel.activeUser.value?.id).toBe('uuid-1');
        expect(form.name).toBe('Budi Santoso');
        expect(form.email).toBe('budi@example.test');
        expect(form.role).toBe('member');
        expect(form.password).toBe('');
        expect(form.password_confirmation).toBe('');
    });

    it('opens create mode with empty fields and the least privileged role', () => {
        const { panel, form } = mountPanel();

        panel.openCreate();

        expect(panel.isOpen.value).toBe(true);
        expect(panel.mode.value).toBe('create');
        expect(panel.activeUser.value).toBeNull();
        expect(form.name).toBe('');
        expect(form.email).toBe('');
        expect(form.password).toBe('');
        expect(form.password_confirmation).toBe('');
        expect(form.role).toBe('member');
    });

    it('blocks submit and surfaces validation errors when required fields are empty', () => {
        const { panel, form, submitted } = mountPanel();

        panel.openCreate();
        panel.submit();

        expect(form.post).not.toHaveBeenCalled();
        expect(form.put).not.toHaveBeenCalled();
        expect(submitted).toHaveLength(0);
        expect(form.setError).toHaveBeenCalledWith({
            name: 'Nama wajib diisi.',
            email: 'Email wajib diisi.',
            password: 'Password wajib diisi.',
            password_confirmation: 'Ulangi password wajib diisi.',
        });
    });

    it('submits create to store and closes on success', () => {
        const { panel, form, submitted } = mountPanel();

        panel.openCreate();
        form.name = 'Akun Baru';
        form.email = 'akun-baru@example.test';
        form.password = 'rahasia123';
        form.password_confirmation = 'rahasia123';
        panel.submit();

        expect(form.post).toHaveBeenCalledTimes(1);
        expect(form.post).toHaveBeenCalledWith(routes.admin.users.store, expect.objectContaining({ preserveScroll: true }));
        expect(submitted[0]?.payload).toEqual({
            name: 'Akun Baru',
            email: 'akun-baru@example.test',
            password: 'rahasia123',
            password_confirmation: 'rahasia123',
            role: 'member',
            return_filters: {},
        });
        expect(panel.isOpen.value).toBe(false);
    });

    it('submits edit to update url with return filters and closes on success', () => {
        const { panel, form, submitted } = mountPanel({ query: { search: 'budi', role: 'member', page: 2 } });

        panel.openEdit(MANAGED_USER);
        panel.submit();

        expect(form.put).toHaveBeenCalledTimes(1);
        expect(form.put).toHaveBeenCalledWith(
            routes.admin.users.update(MANAGED_USER.id),
            expect.objectContaining({ preserveScroll: true })
        );
        expect(submitted[0]?.payload).toEqual({
            name: 'Budi Santoso',
            email: 'budi@example.test',
            password: '',
            password_confirmation: '',
            role: 'member',
            return_filters: { search: 'budi', role: 'member', page: 2 },
        });
        expect(panel.isOpen.value).toBe(false);
    });

    it('ignores an edit deep link whose id is not in the current page', () => {
        const { panel } = mountPanel({ query: { edit: 'uuid-missing' } });

        expect(panel.isOpen.value).toBe(false);
        expect(historyMock.replaceHistoryQuery).toHaveBeenCalledWith({ edit: null });
    });

    it('opens edit mode from a deep link whose id is on the current page', () => {
        const { panel } = mountPanel({ query: { edit: MANAGED_USER.id } });

        expect(panel.isOpen.value).toBe(true);
        expect(panel.mode.value).toBe('edit');
        expect(panel.activeUser.value?.id).toBe(MANAGED_USER.id);
    });

    it('opens create mode when the query carries create true', () => {
        const { panel } = mountPanel({ query: { create: true } });

        expect(panel.isOpen.value).toBe(true);
        expect(panel.mode.value).toBe('create');
        expect(panel.activeUser.value).toBeNull();
    });

    it('prefers create over edit when both query flags are set', () => {
        const { panel } = mountPanel({ query: { create: true, edit: MANAGED_USER.id } });

        expect(panel.mode.value).toBe('create');
        expect(panel.activeUser.value).toBeNull();
    });

    it('closes and clears create and edit query on close', () => {
        const { panel } = mountPanel({ query: { create: true } });

        panel.close();

        expect(panel.isOpen.value).toBe(false);
        expect(historyMock.replaceHistoryQuery).toHaveBeenLastCalledWith({ edit: null, create: null });
    });
});
