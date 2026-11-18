import type { IUserRoleOption } from '@/types/user-management';

/** Role sistem yang tidak pernah bisa diberikan dari UI, hanya ada lewat seeder/CLI. */
const SUPER_ADMIN_LABEL = 'Super Admin';

/**
 * Label tampilan untuk satu role. `roleOptions` hanya memuat role yang bisa diberikan,
 * sehingga role sistem (super-admin) butuh label tetap agar tidak tampil sebagai slug mentah.
 */
export function userRoleLabel(role: string, roleOptions: IUserRoleOption[]): string {
    const match = roleOptions.find((option) => option.value === role);

    if (match !== undefined) {
        return match.label;
    }

    if (role === 'super-admin') {
        return SUPER_ADMIN_LABEL;
    }

    return role;
}
