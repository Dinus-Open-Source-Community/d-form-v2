import { describe, expect, it } from 'vitest';
import { validateUserAccountForm } from '@/lib/userAccountFormValidation';
import type { IUserAccountForm } from '@/types/user-management';

const VALID: IUserAccountForm = {
    name: 'Budi Santoso',
    email: 'budi@example.test',
    password: 'rahasia123',
    password_confirmation: 'rahasia123',
    role: 'member',
};

describe('validateUserAccountForm', () => {
    it('accepts a complete create form', () => {
        expect(validateUserAccountForm(VALID, 'create')).toEqual({});
    });

    it('requires name, email, password, confirmation, and role on create', () => {
        expect(
            validateUserAccountForm(
                { name: '  ', email: '', password: '', password_confirmation: '', role: '' },
                'create'
            )
        ).toEqual({
            name: 'Nama wajib diisi.',
            email: 'Email wajib diisi.',
            password: 'Password wajib diisi.',
            password_confirmation: 'Ulangi password wajib diisi.',
            role: 'Role wajib dipilih.',
        });
    });

    it('rejects a malformed email', () => {
        expect(validateUserAccountForm({ ...VALID, email: 'budi.example.test' }, 'create')).toEqual({
            email: 'Format email tidak valid.',
        });
    });

    it('rejects a password shorter than the minimum', () => {
        expect(
            validateUserAccountForm({ ...VALID, password: 'pendek', password_confirmation: 'pendek' }, 'create')
        ).toEqual({ password: 'Password minimal 8 karakter.' });
    });

    it('rejects a confirmation that does not match', () => {
        expect(validateUserAccountForm({ ...VALID, password_confirmation: 'beda12345' }, 'create')).toEqual({
            password_confirmation: 'Konfirmasi password tidak cocok.',
        });
    });

    it('keeps the password optional on edit', () => {
        expect(
            validateUserAccountForm(
                { ...VALID, password: '', password_confirmation: '', role: 'admin' },
                'edit'
            )
        ).toEqual({});
    });

    it('still validates a password typed on edit', () => {
        expect(
            validateUserAccountForm({ ...VALID, password: 'pendek', password_confirmation: 'beda12345' }, 'edit')
        ).toEqual({
            password: 'Password minimal 8 karakter.',
            password_confirmation: 'Konfirmasi password tidak cocok.',
        });
    });

    it('requires a role on edit because saving always writes one', () => {
        expect(validateUserAccountForm({ ...VALID, role: '' }, 'edit')).toEqual({
            role: 'Role wajib dipilih.',
        });
    });
});
