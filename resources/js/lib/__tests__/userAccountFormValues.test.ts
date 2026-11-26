import { describe, expect, it } from 'vitest';
import { emptyUserAccountForm, userAccountFormFromManagedUser } from '@/lib/userAccountFormValues';
import type { IManagedUser, IUserRoleOption } from '@/types/user-management';

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

describe('emptyUserAccountForm', () => {
    it('defaults to the least privileged role and clears every text field', () => {
        expect(emptyUserAccountForm(ROLE_OPTIONS)).toEqual({
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            role: 'member',
        });
    });

    it('falls back to the first option when member is not assignable', () => {
        expect(emptyUserAccountForm([ROLE_OPTIONS[0]])).toEqual({
            name: '',
            email: '',
            password: '',
            password_confirmation: '',
            role: 'admin',
        });
    });

    it('falls back to member when the server sends no role options', () => {
        expect(emptyUserAccountForm([]).role).toBe('member');
    });
});

describe('userAccountFormFromManagedUser', () => {
    it('keeps the first role and clears both password fields', () => {
        expect(userAccountFormFromManagedUser(MANAGED_USER)).toEqual({
            name: 'Budi Santoso',
            email: 'budi@example.test',
            password: '',
            password_confirmation: '',
            role: 'member',
        });
    });

    it('keeps the role empty when the account has none, so saving cannot grant one silently', () => {
        expect(userAccountFormFromManagedUser({ ...MANAGED_USER, roles: [] }).role).toBe('');
    });
});
