import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import type { InertiaForm } from '@inertiajs/vue3';
import { replaceHistoryQuery } from '@/adapters/browser/historyQuery';
import { routes } from '@/lib/routes';
import { emptyUserAccountForm, userAccountFormFromManagedUser } from '@/lib/userAccountFormValues';
import { validateUserAccountForm } from '@/lib/userAccountFormValidation';
import type {
    IManagedUser,
    IUserAccountForm,
    IUserIndexQuery,
    IUserRoleOption,
    TUserAccountFormMode,
} from '@/types/user-management';

/** Nama lama dipertahankan agar pemanggil panel tidak ikut berubah. */
export type TUserAccountPanelMode = TUserAccountFormMode;

/** Filter direktori ditambah halaman aktif; `page` dipakai agar redirect simpan kembali ke halaman asal. */
export interface IUserAccountPanelQuery extends IUserIndexQuery {
    page?: number;
}

export interface IUserAccountPanelOptions {
    query: IUserAccountPanelQuery;
    users: IManagedUser[];
    roleOptions: IUserRoleOption[];
}

/** Filter asal yang dikirim balik ke server agar pencarian tidak hilang setelah simpan. */
interface IUserReturnFilters {
    search?: string;
    role?: string;
    page?: number;
}

export interface IUserAccountPanel {
    isOpen: ComputedRef<boolean>;
    mode: Ref<TUserAccountPanelMode>;
    activeUser: Ref<IManagedUser | null>;
    form: InertiaForm<IUserAccountForm>;
    openCreate: () => void;
    openEdit: (user: IManagedUser) => void;
    close: () => void;
    submit: () => void;
}

/**
 * Panel create/edit akun: satu form Inertia untuk dua mode, deep link `?create`/`?edit`,
 * dan URL yang selalu mencerminkan panel yang sedang terbuka tanpa request tambahan.
 */
export function useUserAccountPanel(options: IUserAccountPanelOptions): IUserAccountPanel {
    const { query, users, roleOptions } = options;
    const opened = ref<boolean>(false);
    const mode = ref<TUserAccountFormMode>('create');
    const activeUser = ref<IManagedUser | null>(null);
    const isOpen = computed<boolean>(() => opened.value);

    const form = useForm<IUserAccountForm>(emptyUserAccountForm(roleOptions));

    /** Ganti seluruh isi form sekaligus; error validasi mode sebelumnya tidak boleh menempel. */
    function applyFormValues(values: IUserAccountForm): void {
        Object.assign(form, values);
        form.clearErrors();
    }

    function openCreate(): void {
        mode.value = 'create';
        activeUser.value = null;
        applyFormValues(emptyUserAccountForm(roleOptions));
        opened.value = true;
        replaceHistoryQuery({ edit: null, create: '1' });
    }

    function openEdit(user: IManagedUser): void {
        mode.value = 'edit';
        activeUser.value = user;
        applyFormValues(userAccountFormFromManagedUser(user));
        opened.value = true;
        replaceHistoryQuery({ edit: user.id, create: null });
    }

    function close(): void {
        opened.value = false;
        activeUser.value = null;
        replaceHistoryQuery({ edit: null, create: null });
    }

    /** Filter asal tanpa nilai kosong; server memakainya untuk redirect setelah simpan. */
    function buildReturnFilters(): IUserReturnFilters {
        const filters: IUserReturnFilters = {};
        const search = query.search.trim();
        if (search !== '') filters.search = search;
        if (query.role !== null && query.role !== '') filters.role = query.role;
        if (query.page !== undefined && query.page > 1) filters.page = query.page;

        return filters;
    }

    /** Nilai form sebagai objek biasa; validasi lokal tidak membaca state Inertia langsung. */
    function currentValues(): IUserAccountForm {
        return {
            name: form.name,
            email: form.email,
            password: form.password,
            password_confirmation: form.password_confirmation,
            role: form.role,
        };
    }

    function submit(): void {
        if (form.processing) return;

        const validationErrors = validateUserAccountForm(currentValues(), mode.value);
        if (Object.keys(validationErrors).length > 0) {
            form.clearErrors();
            form.setError(validationErrors);
            return;
        }

        const returnFilters = buildReturnFilters();
        form.transform((values: IUserAccountForm): object => ({ ...values, return_filters: returnFilters }));

        const submitOptions = {
            preserveScroll: true,
            onSuccess: (): void => {
                close();
            },
        };

        if (mode.value === 'edit') {
            const target = activeUser.value;
            if (target === null) return;

            form.put(routes.admin.users.update(target.id), submitOptions);
            return;
        }

        form.post(routes.admin.users.store, submitOptions);
    }

    /** Buka panel dari deep link; `create` menang atas `edit` agar perilakunya pasti. */
    function openFromQuery(): void {
        if (query.create === true) {
            openCreate();
            return;
        }

        const editId = query.edit;
        if (editId === null || editId === '') return;

        const target = users.find((user) => user.id === editId);
        if (target === undefined) {
            replaceHistoryQuery({ edit: null });
            return;
        }

        openEdit(target);
    }

    openFromQuery();

    return { isOpen, mode, activeUser, form, openCreate, openEdit, close, submit };
}
