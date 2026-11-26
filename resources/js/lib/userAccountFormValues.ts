import type { IManagedUser, IUserAccountForm, IUserRoleOption } from '@/types/user-management';

/** Role cadangan saat server tidak mengirim opsi role. */
const FALLBACK_ROLE = 'member';

/** Role default akun baru: hak paling kecil di antara role yang bisa diberikan. */
const DEFAULT_ROLE = 'member';

/** Form kosong untuk membuat akun; default ke role dengan hak paling kecil. */
export function emptyUserAccountForm(roleOptions: IUserRoleOption[]): IUserAccountForm {
    const preferred = roleOptions.find((option) => option.value === DEFAULT_ROLE)?.value;

    return {
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: preferred ?? roleOptions[0]?.value ?? FALLBACK_ROLE,
    };
}

/**
 * Form edit dari satu baris direktori; password selalu kosong sampai pengguna mengisinya.
 * Akun tanpa role dibiarkan kosong agar menyimpan tidak diam-diam memberi role `member`.
 */
export function userAccountFormFromManagedUser(user: IManagedUser): IUserAccountForm {
    return {
        name: user.name,
        email: user.email,
        password: '',
        password_confirmation: '',
        role: user.roles[0] ?? '',
    };
}
