import { describe, expect, it } from 'vitest';

import {
    buildFieldLabelMap,
    getFieldError,
    humanizeErrorMessage,
    humanizeFieldKey,
    normalizeErrorText,
    parseApiErrorMessage,
    parseValidationErrors,
    resolveFieldLabel,
    sanitizeApiMessageText,
    stripValidationSummary,
} from '@/lib/error-message';
import { describeRequestError } from '@/lib/requestErrorText';

function makeField(overrides: Partial<IFormField> = {}): IFormField {
    return { id: 'f1', type: 'input', label: 'Field', name: 'field', order: 1, metadata: {}, ...overrides };
}

describe('normalizeErrorText', () => {
    it('trims a single message', () => {
        expect(normalizeErrorText('  Wajib diisi  ')).toBe('Wajib diisi');
    });

    it('takes the first item of an array', () => {
        expect(normalizeErrorText(['  Pertama  ', 'kedua'])).toBe('Pertama');
    });

    it('returns an empty string for absent values', () => {
        expect(normalizeErrorText(null)).toBe('');
        expect(normalizeErrorText(undefined)).toBe('');
        expect(normalizeErrorText([])).toBe('');
        expect(normalizeErrorText('   ')).toBe('');
    });
});

describe('humanizeFieldKey', () => {
    it('uses a static label when one exists', () => {
        expect(humanizeFieldKey('email')).toBe('Email');
        expect(humanizeFieldKey('title')).toBe('Judul');
    });

    it('names a bundle slot by field label and participant position', () => {
        expect(humanizeFieldKey('bundle__full_name__2')).toBe('Full Name (Peserta 4)');
    });

    it('numbers team member emails from one', () => {
        expect(humanizeFieldKey('team_member_emails.0')).toBe('Email anggota 1');
        expect(humanizeFieldKey('team_member_emails.1')).toBe('Email anggota 2');
    });

    it('title-cases an unknown nested key', () => {
        expect(humanizeFieldKey('first_name')).toBe('First Name');
        expect(humanizeFieldKey('profile.nickname')).toBe('Profile');
    });

    it('prefers a caller-supplied field label', () => {
        expect(humanizeFieldKey('custom_field', { fieldLabels: { custom_field: 'Label Khusus' } })).toBe(
            'Label Khusus'
        );
    });
});

describe('resolveFieldLabel', () => {
    it('resolves static, caller-provided, and form-builder labels', () => {
        expect(resolveFieldLabel('email')).toBe('Email');
        expect(resolveFieldLabel('nickname', { fieldLabels: { nickname: 'Nama panggilan' } })).toBe('Nama panggilan');
        expect(resolveFieldLabel('x', { fields: [makeField({ name: 'x', label: '  Label X  ' })] })).toBe('Label X');
    });

    it('falls back to title case', () => {
        expect(resolveFieldLabel('first_name')).toBe('First Name');
    });
});

describe('humanizeErrorMessage', () => {
    it('translates a known backend message', () => {
        expect(humanizeErrorMessage('Form deleted.')).toBe('Form berhasil dihapus.');
        expect(humanizeErrorMessage(['Form deleted.'])).toBe('Form berhasil dihapus.');
    });

    it('translates a Laravel required rule using the field label', () => {
        expect(humanizeErrorMessage('The name field is required.', 'name')).toBe('Nama wajib diisi.');
    });

    it('translates a Laravel email rule using the field label', () => {
        expect(humanizeErrorMessage('The email field must be a valid email address.', 'email')).toBe(
            'Email harus berupa alamat email yang valid.'
        );
    });

    it('injects the field label into an unknown message that mentions the key', () => {
        expect(humanizeErrorMessage('email is broken', 'email')).toBe('Email is broken');
    });

    it('returns an unknown message unchanged without a field key', () => {
        expect(humanizeErrorMessage('Something exploded')).toBe('Something exploded');
        expect(humanizeErrorMessage('')).toBe('');
    });
});

describe('getFieldError', () => {
    it('returns a humanized message for a present key', () => {
        expect(getFieldError({ email: ['Form deleted.'] }, 'email')).toBe('Form berhasil dihapus.');
    });

    it('returns undefined for empty or missing keys', () => {
        expect(getFieldError({ email: '   ' }, 'email')).toBeUndefined();
        expect(getFieldError({}, 'email')).toBeUndefined();
    });
});

describe('parseValidationErrors', () => {
    it('drops empty values and builds one labeled line', () => {
        expect(parseValidationErrors({ name: '', email: 'Form deleted.' })).toEqual([
            {
                key: 'email',
                label: 'Email',
                message: 'Form berhasil dihapus.',
                line: 'Email: Form berhasil dihapus.',
            },
        ]);
    });

    it('returns an empty list when nothing is invalid', () => {
        expect(parseValidationErrors({})).toEqual([]);
        expect(parseValidationErrors({ name: '' })).toEqual([]);
    });
});

describe('parseApiErrorMessage', () => {
    it('uses the body message when present', () => {
        expect(parseApiErrorMessage({ message: 'Form deleted.' })).toBe('Form berhasil dihapus.');
    });

    it('uses the first validation error when the message is empty', () => {
        expect(parseApiErrorMessage({ message: '  ', errors: { name: ['The name field is required.'] } })).toBe(
            'Nama wajib diisi.'
        );
    });

    it('falls back for unusable bodies', () => {
        expect(parseApiErrorMessage({})).toBe('Terjadi kesalahan. Coba lagi.');
        expect(parseApiErrorMessage(null)).toBe('Terjadi kesalahan. Coba lagi.');
        expect(parseApiErrorMessage(['x'])).toBe('Terjadi kesalahan. Coba lagi.');
        expect(parseApiErrorMessage({ errors: 'x' })).toBe('Terjadi kesalahan. Coba lagi.');
        expect(parseApiErrorMessage('text')).toBe('Terjadi kesalahan. Coba lagi.');
        expect(parseApiErrorMessage({}, 'Fallback khusus')).toBe('Fallback khusus');
    });

    it('reads the first validation message from an array without a body message', () => {
        expect(parseApiErrorMessage({ errors: { payload: ['Tidak dikenali.'] } })).toBe('Tidak dikenali.');
    });

    it('memprioritaskan bag errors untuk 422 agar pesan validasi Inggris tidak bocor', () => {
        expect(
            parseApiErrorMessage(
                { message: 'The raw field is required.', errors: { raw: ['The raw field is required.'] } },
                'Data tidak valid.',
                422
            )
        ).toBe('Raw wajib diisi.');
    });

    it('tetap memakai message yang bisa di-humanize saat 422 tanpa errors', () => {
        expect(parseApiErrorMessage({ message: 'Form deleted.' }, 'Data tidak valid.', 422)).toBe(
            'Form berhasil dihapus.'
        );
    });

    it('mempertahankan urutan message lebih dulu untuk status non-422', () => {
        expect(
            parseApiErrorMessage({
                message: 'Form deleted.',
                errors: { name: ['The name field is required.'] },
            })
        ).toBe('Form berhasil dihapus.');
    });
});

describe('buildFieldLabelMap', () => {
    it('maps trimmed labels and skips blank ones', () => {
        const map = buildFieldLabelMap([
            makeField({ name: 'a', label: '  Label A  ' }),
            makeField({ name: 'b', label: '   ' }),
            makeField({ name: 'c', label: 'Label C' }),
        ]);

        expect(map).toEqual({ a: 'Label A', c: 'Label C' });
    });
});

describe('describeRequestError', () => {
    it('senyap untuk request yang dibatalkan', () => {
        expect(describeRequestError({ kind: 'aborted', status: null })).toBeNull();
    });

    it('memakai pesan koneksi untuk kegagalan network', () => {
        expect(describeRequestError({ kind: 'network', status: null })).toBe(
            'Gagal terhubung ke server. Periksa koneksi internet, lalu coba lagi.'
        );
    });

    it('memakai pesan batas waktu untuk timeout', () => {
        expect(describeRequestError({ kind: 'timeout', status: null })).toBe(
            'Server tidak merespons tepat waktu. Coba lagi.'
        );
    });

    it('menghormati Retry-After saat rate limited', () => {
        expect(describeRequestError({ kind: 'rate_limited', status: 429, retryAfterSeconds: 45 })).toBe(
            'Terlalu banyak percobaan. Coba lagi dalam 45 detik.'
        );
    });

    it('memakai pesan 429 default saat Retry-After tidak ada', () => {
        expect(describeRequestError({ kind: 'rate_limited', status: 429 })).toBe(
            'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.'
        );
    });

    it('memakai pesan server generik untuk kegagalan 5xx', () => {
        expect(describeRequestError({ kind: 'server', status: 500 })).toBe(
            'Terjadi kesalahan server. Coba lagi beberapa saat lagi.'
        );
    });

    it('memakai pesan respons tidak valid untuk invalid_response', () => {
        expect(describeRequestError({ kind: 'invalid_response', status: 200 })).toBe(
            'Server mengirim respons yang tidak valid. Coba lagi beberapa saat lagi.'
        );
    });

    it('memakai pesan generik untuk client dan unknown tanpa membaca status', () => {
        expect(describeRequestError({ kind: 'client', status: 400 })).toBe('Permintaan gagal diproses. Coba lagi.');
        expect(describeRequestError({ kind: 'unknown', status: 200 })).toBe('Permintaan gagal diproses. Coba lagi.');
    });
});

describe('pesan validasi form dari backend', () => {
    const translations: Array<[string, string]> = [
        ['Please enter the form title.', 'Judul form wajib diisi.'],
        ['Please enter a description for the form.', 'Deskripsi form wajib diisi.'],
        ['Please select when the form closes.', 'Pilih tanggal tutup form.'],
        ['Please select who can see this form.', 'Pilih siapa yang bisa melihat form ini.'],
        ['Please select at least one visibility option.', 'Pilih minimal satu opsi visibilitas.'],
        ['Please provide form fields to update.', 'Sertakan minimal satu field form.'],
    ];

    for (const [raw, expected] of translations) {
        it(`menerjemahkan "${raw}"`, () => {
            expect(humanizeErrorMessage(raw)).toBe(expected);
            expect(humanizeErrorMessage(raw, 'title')).toBe(expected);
        });
    }

    it('setiap pesan custom form punya terjemahan Indonesia', () => {
        const untranslated = translations.map(([raw]) => raw).filter((raw) => humanizeErrorMessage(raw) === raw);

        expect(untranslated).toEqual([]);
    });
});

describe('stripValidationSummary', () => {
    const cases: Array<[string, string]> = [
        ['Please enter the form title. (and 2 more errors)', 'Please enter the form title.'],
        ['Judul wajib diisi. (and 1 more error)', 'Judul wajib diisi.'],
        ['Judul wajib diisi.', 'Judul wajib diisi.'],
        ['(and 3 more errors) awal', '(and 3 more errors) awal'],
    ];

    for (const [raw, expected] of cases) {
        it(`membersihkan "${raw}"`, () => {
            expect(stripValidationSummary(raw)).toBe(expected);
        });
    }

    it('ringkasan Laravel tidak tampil lewat humanizeErrorMessage', () => {
        expect(humanizeErrorMessage('Please enter the form title. (and 2 more errors)')).toBe(
            'Judul form wajib diisi.'
        );
    });
});

describe('sanitizeApiMessageText', () => {
    it('membuka string JSON berisi message', () => {
        expect(sanitizeApiMessageText('{"message":"Please enter the form title."}')).toBe(
            'Please enter the form title.'
        );
    });

    it('membuka string JSON berisi errors dan memilih pesan field pertama (sudah diterjemahkan)', () => {
        expect(
            sanitizeApiMessageText(
                JSON.stringify({
                    message: 'Please enter the form title. (and 2 more errors)',
                    errors: { title: ['Please enter the form title.'], description: ['x'] },
                })
            )
        ).toBe('Judul form wajib diisi.');
    });

    it('membiarkan teks biasa apa adanya', () => {
        expect(sanitizeApiMessageText('Gagal menyimpan form.')).toBe('Gagal menyimpan form.');
        expect(sanitizeApiMessageText('{bukan json')).toBe('{bukan json');
    });
});

describe('parseApiErrorMessage untuk ringkasan dan string JSON', () => {
    it('membuang suffix ringkasan saat hanya body.message tersedia', () => {
        expect(parseApiErrorMessage({ message: 'Please enter the form title. (and 2 more errors)' })).toBe(
            'Judul form wajib diisi.'
        );
    });

    it('membuka body yang datang sebagai string JSON', () => {
        expect(
            parseApiErrorMessage(
                JSON.stringify({
                    message: 'Please enter the form title. (and 2 more errors)',
                    errors: { description: ['Please enter a description for the form.'] },
                }),
                'Fallback',
                422
            )
        ).toBe('Deskripsi form wajib diisi.');
    });

    it('memprioritaskan bag validasi di atas ringkasan message (422)', () => {
        expect(
            parseApiErrorMessage(
                {
                    message: 'Please enter the form title. (and 2 more errors)',
                    errors: {
                        title: ['Please enter the form title.'],
                        description: ['Please enter a description for the form.'],
                        closed_at: ['Please select when the form closes.'],
                    },
                },
                'Fallback',
                422
            )
        ).toBe('Judul form wajib diisi.');
    });

    it('tidak pernah memakai teks ringkasan mentah', () => {
        const message = parseApiErrorMessage({ message: 'The raw field is required. (and 4 more errors)' }, 'Fallback');

        expect(message.includes('(and ')).toBe(false);
    });
});
