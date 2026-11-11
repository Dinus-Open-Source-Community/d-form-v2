import { describe, expect, it } from 'vitest';
import {
    HttpRequestError,
    isHttpRequestError,
    readPublicMessage,
    readValidationErrors,
    toHttpErrorKind,
} from '@/adapters/http/httpError';

describe('toHttpErrorKind', () => {
    it('maps known statuses to a stable kind', () => {
        expect(toHttpErrorKind(401)).toBe('unauthorized');
        expect(toHttpErrorKind(419)).toBe('unauthorized');
        expect(toHttpErrorKind(403)).toBe('forbidden');
        expect(toHttpErrorKind(404)).toBe('not_found');
        expect(toHttpErrorKind(409)).toBe('conflict');
        expect(toHttpErrorKind(422)).toBe('validation');
        expect(toHttpErrorKind(429)).toBe('rate_limited');
        expect(toHttpErrorKind(418)).toBe('client');
        expect(toHttpErrorKind(500)).toBe('server');
        expect(toHttpErrorKind(503)).toBe('server');
    });
});

describe('readValidationErrors', () => {
    it('keeps string and string-array messages only', () => {
        expect(
            readValidationErrors({
                errors: {
                    email: ['Sudah dipakai', 42],
                    title: 'Wajib diisi',
                    ignored: { nested: true },
                },
            })
        ).toEqual({ email: ['Sudah dipakai'], title: 'Wajib diisi' });
    });

    it('returns null for a missing or empty bag', () => {
        expect(readValidationErrors(null)).toBeNull();
        expect(readValidationErrors({ errors: {} })).toBeNull();
        expect(readValidationErrors({ message: 'x' })).toBeNull();
    });
});

describe('readPublicMessage', () => {
    it('reads a trimmed message only', () => {
        expect(readPublicMessage({ message: '  halo  ' })).toBe('halo');
        expect(readPublicMessage({ message: '' })).toBeNull();
        expect(readPublicMessage('halo')).toBeNull();
    });
});

describe('HttpRequestError', () => {
    it('is detectable through the runtime guard', () => {
        const error = new HttpRequestError({ kind: 'network', status: null, message: 'gagal' });

        expect(isHttpRequestError(error)).toBe(true);
        expect(isHttpRequestError(new Error('gagal'))).toBe(false);
        expect(error.name).toBe('HttpRequestError');
    });

    it('defaults optional fields without losing the payload', () => {
        const body = { message: 'x' };
        const error = new HttpRequestError({ kind: 'validation', status: 422, message: 'x', body });

        expect(error.validationErrors).toBeNull();
        expect(error.retryAfterSeconds).toBeNull();
        expect(error.body).toBe(body);
    });
});
