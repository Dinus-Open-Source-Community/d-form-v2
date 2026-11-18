import { USER_PASSWORD_MIN_LENGTH } from '@/constants/userManagement';
import type { IUserAccountForm, TUserAccountFormMode } from '@/types/user-management';

/** Pesan per field mengikuti teks Form Request server agar pengguna melihat kalimat yang sama. */
export type TUserAccountFormErrors = Partial<Record<keyof IUserAccountForm, string>>;

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

/** Checkbox role wajib karena setiap simpan menulis satu role (`syncRoles([$role])`). */
const ROLE_REQUIRED_MESSAGE = 'Role wajib dipilih.';

function validateEmail(email: string): string | null {
    if (email === '') return 'Email wajib diisi.';
    return EMAIL_PATTERN.test(email) ? null : 'Format email tidak valid.';
}

function validatePassword(values: IUserAccountForm, mode: TUserAccountFormMode): TUserAccountFormErrors {
    const errors: TUserAccountFormErrors = {};

    if (values.password === '') {
        if (mode === 'create') errors.password = 'Password wajib diisi.';
    } else if (values.password.length < USER_PASSWORD_MIN_LENGTH) {
        errors.password = `Password minimal ${USER_PASSWORD_MIN_LENGTH} karakter.`;
    }

    if (values.password_confirmation === '') {
        if (mode === 'create') errors.password_confirmation = 'Ulangi password wajib diisi.';
    } else if (values.password !== values.password_confirmation) {
        errors.password_confirmation = 'Konfirmasi password tidak cocok.';
    }

    return errors;
}

/**
 * Validasi form akun sebelum request dikirim. Aturan dan pesan mengikuti `StoreUserRequest`
 * dan `UpdateUserRequest`; server tetap otoritatif untuk email unik dan aturan lain.
 */
export function validateUserAccountForm(
    values: IUserAccountForm,
    mode: TUserAccountFormMode
): TUserAccountFormErrors {
    const errors: TUserAccountFormErrors = {};

    if (values.name.trim() === '') errors.name = 'Nama wajib diisi.';

    const emailError = validateEmail(values.email.trim());
    if (emailError !== null) errors.email = emailError;

    Object.assign(errors, validatePassword(values, mode));

    if (values.role === '') errors.role = ROLE_REQUIRED_MESSAGE;

    return errors;
}
