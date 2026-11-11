import { describe, expect, it } from 'vitest';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';

describe('normalizePublicStorageUrl', () => {
    it('returns an empty string for blank input', () => {
        expect(normalizePublicStorageUrl('')).toBe('');
        expect(normalizePublicStorageUrl('   \n\t ')).toBe('');
    });

    it('passes through inline data and blob previews', () => {
        expect(normalizePublicStorageUrl('data:image/png;base64,AAAA')).toBe('data:image/png;base64,AAAA');
        expect(normalizePublicStorageUrl('blob:http://localhost/1234')).toBe('blob:http://localhost/1234');
    });

    it('passes through absolute http(s) URLs (case-insensitive)', () => {
        expect(normalizePublicStorageUrl('https://cdn.example.com/a.png')).toBe('https://cdn.example.com/a.png');
        expect(normalizePublicStorageUrl('HTTP://cdn.example.com/a.png')).toBe('HTTP://cdn.example.com/a.png');
    });

    it('passes through paths that are already absolute', () => {
        expect(normalizePublicStorageUrl('/images/logo.png')).toBe('/images/logo.png');
    });

    it('maps relative storage paths into /storage', () => {
        expect(normalizePublicStorageUrl('form-images/a.png')).toBe('/storage/form-images/a.png');
        expect(normalizePublicStorageUrl('/form-images/a.png')).toBe('/form-images/a.png');
    });

    it('preserves any same-origin input that already starts with a single slash', () => {
        expect(normalizePublicStorageUrl('/images/logo.png')).toBe('/images/logo.png');
    });

    it('rejects control characters that the WHATWG parser strips into a network path', () => {
        expect(normalizePublicStorageUrl('/\t/evil.example/x.png')).toBe('');
        expect(normalizePublicStorageUrl('/\n/evil')).toBe('');
        expect(normalizePublicStorageUrl('/\r/evil')).toBe('');
    });

    it.each([
        ['network path //host', '//host', ''],
        ['backslash host', '\\\\host\\x.png', ''],
        ['triple slash', '///x', ''],
        ['storage path', '/storage/x', '/storage/x'],
        ['data URL', 'data:image/png;base64,AAAA', 'data:image/png;base64,AAAA'],
        ['absolute https', 'https://x', 'https://x'],
    ])('handles %s', (_label: string, input: string, expected: string) => {
        expect(normalizePublicStorageUrl(input)).toBe(expected);
    });

    it('rejects network-path references instead of passing them through', () => {
        expect(normalizePublicStorageUrl('//evil.example/x.png')).toBe('');
        expect(normalizePublicStorageUrl('\\\\evil.example\\x.png')).toBe('');
        expect(normalizePublicStorageUrl('/\\evil.example/x.png')).toBe('');
        expect(normalizePublicStorageUrl('\\/evil.example/x.png')).toBe('');
    });

    it('rejects ambiguous multi-slash paths that browsers may read as an authority', () => {
        expect(normalizePublicStorageUrl('///form-images/a.png')).toBe('');
        expect(normalizePublicStorageUrl('////evil.example/a.png')).toBe('');
    });

    it('keeps unknown schemes inert as storage paths', () => {
        const result = normalizePublicStorageUrl('javascript:alert(1)');
        expect(result).toBe('/storage/javascript:alert(1)');
        expect(result.startsWith('/storage/')).toBe(true);
    });
});

/**
 * `components/core/EventBannerImage.vue` hanya memanggil helper ini (tanpa cabang
 * sendiri), jadi tabel ini adalah kontrak normalisasi banner yang dipakai 6 consumer.
 */
describe('EventBannerImage banner source contract', () => {
    it.each([
        ['network path //host', '//evil.example/x.png', ''],
        ['backslash host', '/\\evil.example/x.png', ''],
        ['ambiguous triple slash', '///x', ''],
        ['inline data preview', 'data:image/png;base64,AAAA', 'data:image/png;base64,AAAA'],
        ['absolute storage path', '/storage/x', '/storage/x'],
        ['raw public-disk path', 'events/banners/x.jpg', '/storage/events/banners/x.jpg'],
    ])('resolves %s', (_label: string, input: string, expected: string) => {
        expect(normalizePublicStorageUrl(input)).toBe(expected);
    });
});
