import { describe, expect, it } from 'vitest';
import { isHttpUrl } from '@/lib/isHttpUrl';

describe('isHttpUrl', () => {
    it.each(['http://example.test', 'https://example.test/path?query=1', '  https://example.test  '])(
        'accepts %j',
        (value: string) => {
            expect(isHttpUrl(value)).toBe(true);
        }
    );

    it.each([
        'ftp://example.test',
        'mailto:user@example.test',
        'javascript:alert(1)',
        '/relative/path',
        'example.test',
        '',
        '   ',
    ])('rejects %j', (value: string) => {
        expect(isHttpUrl(value)).toBe(false);
    });

    it('rejects non-string values', () => {
        expect(isHttpUrl(42)).toBe(false);
        expect(isHttpUrl(null)).toBe(false);
        expect(isHttpUrl(undefined)).toBe(false);
        expect(isHttpUrl({ href: 'https://example.test' })).toBe(false);
    });
});
