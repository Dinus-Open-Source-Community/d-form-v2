import { describe, expect, it } from 'vitest';
import { isInternalPath, isPersistableImageUrl, isSafeDownloadUrl, isSafeNavigationUrl } from '@/lib/urlPolicy';

describe('urlPolicy', () => {
    it('accepts only single-slash internal paths', () => {
        expect(isInternalPath('/dashboard')).toBe(true);
        expect(isInternalPath('//evil.test')).toBe(false);
        expect(isInternalPath('/\\evil.test')).toBe(false);
        expect(isInternalPath('/\t/evil.test')).toBe(false);
        expect(isInternalPath('/\n/evil.test')).toBe(false);
        expect(isInternalPath('/\r/evil.test')).toBe(false);
        expect(isInternalPath('https://evil.test')).toBe(false);
    });

    it('allows http/https and internal paths for navigation', () => {
        expect(isSafeNavigationUrl('/dashboard')).toBe(true);
        expect(isSafeNavigationUrl('https://accounts.google.com/o/oauth2')).toBe(true);
        expect(isSafeNavigationUrl('http://localhost:3000/x')).toBe(true);
        expect(isSafeNavigationUrl('javascript:alert(1)')).toBe(false);
        expect(isSafeNavigationUrl('data:text/html,x')).toBe(false);
        expect(isSafeNavigationUrl('//evil.test')).toBe(false);
    });

    it('allows blob downloads but not data/javascript', () => {
        expect(isSafeDownloadUrl('/admin/export?format=csv')).toBe(true);
        expect(isSafeDownloadUrl('https://example.test/file.pdf')).toBe(true);
        expect(isSafeDownloadUrl('blob:https://example.test/uuid')).toBe(true);
        expect(isSafeDownloadUrl('data:text/html,x')).toBe(false);
        expect(isSafeDownloadUrl('javascript:alert(1)')).toBe(false);
    });

    it('rejects temporary image urls', () => {
        expect(isPersistableImageUrl('/storage/a.png')).toBe(true);
        expect(isPersistableImageUrl('data:image/png;base64,x')).toBe(false);
        expect(isPersistableImageUrl('blob:https://example.test/uuid')).toBe(false);
        expect(isPersistableImageUrl('   ')).toBe(false);
    });
});
