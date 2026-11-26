import { describe, expect, it } from 'vitest';
import { buildFormSubmissionsUrl } from '@/lib/buildFormSubmissionsUrl';
import type { IFormSubmissionQuery } from '@/types/formSubmissions';

const EMPTY: IFormSubmissionQuery = { search: null, review_status: null, referral: null };

describe('buildFormSubmissionsUrl', () => {
    it('keeps the answers tab with no filters', () => {
        expect(buildFormSubmissionsUrl({ pathname: '/admin/forms/1', filters: EMPTY })).toBe(
            '/admin/forms/1?tab=jawaban'
        );
    });

    it('drops a whitespace-only search', () => {
        expect(buildFormSubmissionsUrl({ pathname: '/admin/forms/1', filters: { ...EMPTY, search: '   ' } })).toBe(
            '/admin/forms/1?tab=jawaban'
        );
    });

    it('trims the search term and keeps the documented key order', () => {
        expect(
            buildFormSubmissionsUrl({
                pathname: '/admin/forms/1',
                filters: { search: '  budi  ', review_status: 'accepted', referral: 'with' },
                perPage: 50,
            })
        ).toBe('/admin/forms/1?tab=jawaban&search=budi&review_status=accepted&referral=with&per_page=50');
    });

    it('encodes a search term with spaces', () => {
        expect(
            buildFormSubmissionsUrl({ pathname: '/admin/forms/1', filters: { ...EMPTY, search: 'budi santoso' } })
        ).toBe('/admin/forms/1?tab=jawaban&search=budi+santoso');
    });

    it('never writes the page parameter so a filter change starts from page one', () => {
        expect(
            buildFormSubmissionsUrl({ pathname: '/admin/forms/1', filters: { ...EMPTY, referral: 'without' } })
        ).not.toContain('page=');
    });
});
