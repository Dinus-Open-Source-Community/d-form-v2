import { describe, expect, it } from 'vitest';
import { userRoleLabel } from '@/lib/userRoleLabel';
import type { IUserRoleOption } from '@/types/user-management';

const ROLE_OPTIONS: IUserRoleOption[] = [
    { value: 'admin', label: 'Admin', description: 'Kelola event dan formulir.' },
    { value: 'member', label: 'Member', description: 'Ikut event dan isi formulir.' },
];

describe('userRoleLabel', () => {
    it('uses the server label for an assignable role', () => {
        expect(userRoleLabel('member', ROLE_OPTIONS)).toBe('Member');
    });

    it('labels the system super-admin role without an option entry', () => {
        expect(userRoleLabel('super-admin', ROLE_OPTIONS)).toBe('Super Admin');
    });

    it('falls back to the raw role name when the server does not know it', () => {
        expect(userRoleLabel('auditor', ROLE_OPTIONS)).toBe('auditor');
    });
});
