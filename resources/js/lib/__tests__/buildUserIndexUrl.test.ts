import { describe, expect, it } from 'vitest';
import { buildUserIndexUrl } from '@/lib/buildUserIndexUrl';

describe('buildUserIndexUrl', () => {
    it('returns the bare index path when every filter is empty', () => {
        expect(buildUserIndexUrl({})).toBe('/admin/users');
    });

    it('drops whitespace search, null role, and the first page', () => {
        expect(buildUserIndexUrl({ search: '   ', role: null, page: 1 })).toBe('/admin/users');
    });

    it('trims the search term before it reaches the server', () => {
        expect(buildUserIndexUrl({ search: '  budi  ' })).toBe('/admin/users?search=budi');
    });

    it('keeps per_page when the caller provides it', () => {
        expect(buildUserIndexUrl({ perPage: 50 })).toBe('/admin/users?per_page=50');
    });

    it('encodes search and keeps the documented key order', () => {
        expect(
            buildUserIndexUrl({ search: 'budi santoso', role: 'admin', page: 2, perPage: 50 })
        ).toBe('/admin/users?search=budi+santoso&role=admin&page=2&per_page=50');
    });

    it('adds edit and create without other filters', () => {
        expect(buildUserIndexUrl({ edit: 'uuid-1' })).toBe('/admin/users?edit=uuid-1');
        expect(buildUserIndexUrl({ create: true })).toBe('/admin/users?create=1');
    });

    it('keeps create before edit when both are set', () => {
        expect(buildUserIndexUrl({ create: true, edit: 'uuid-1' })).toBe('/admin/users?create=1&edit=uuid-1');
    });
});
