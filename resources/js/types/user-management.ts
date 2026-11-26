/**
 * Kontrak data direktori pengguna di `/admin/users`.
 *
 * `can_edit`/`can_delete` berasal dari policy server, bukan dihitung ulang di klien,
 * sehingga UI dan endpoint memakai satu sumber aturan akses.
 */
export interface IManagedUser {
    id: string;
    name: string;
    email: string;
    roles: string[];
    avatar_url: string | null;
    can_edit: boolean;
    can_delete: boolean;
    created_at: string | null;
}

/** Opsi role beserta konsekuensi aksesnya; deskripsi dipakai kartu role di panel. */
export interface IUserRoleOption {
    value: string;
    label: string;
    description: string;
}

/** Bentuk paginator Laravel yang dipakai direktori pengguna. */
export interface IUsersPaginator {
    data: IManagedUser[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

/** Filter aktif direktori; `edit` dan `create` adalah deep link panel, bukan filter. */
export interface IUserIndexQuery {
    search: string;
    role: string | null;
    per_page: number;
    edit: string | null;
    create: boolean;
}

/** Isi form akun; dipakai baik untuk create maupun edit. */
export interface IUserAccountForm {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
    role: string;
}

/** Mode form akun: akun baru atau perubahan akun yang sudah ada. */
export type TUserAccountFormMode = 'create' | 'edit';
