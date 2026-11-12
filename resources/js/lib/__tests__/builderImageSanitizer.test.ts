import { describe, expect, it } from 'vitest';
import {
    cleanStoredImageUrl,
    isBlobUrl,
    isDataUrl,
    isLocalOnlyImageUrl,
    sanitizeBuilderSnapshot,
    stripLocalImageUrlsDeep,
} from '@/lib/builderImageSanitizer';

describe('isDataUrl / isBlobUrl', () => {
    it.each([
        ['data:image/png;base64,AAAA', true, false],
        ['  data:text/plain;base64,AAAA', true, false],
        ['blob:https://dform.test/1234', false, true],
        [' blob:https://dform.test/1234', false, true],
        ['https://dform.test/storage/banner.png', false, false],
        ['/storage/banner.png', false, false],
        ['', false, false],
    ])('menilai %j sebagai data=%s blob=%s', (value: string, data: boolean, blob: boolean) => {
        expect(isDataUrl(value)).toBe(data);
        expect(isBlobUrl(value)).toBe(blob);
    });

    it.each([null, undefined, 42, [], {}, true])('menolak nilai non-string %j', (value: unknown) => {
        expect(isDataUrl(value)).toBe(false);
        expect(isBlobUrl(value)).toBe(false);
        expect(isLocalOnlyImageUrl(value)).toBe(false);
    });

    it('isLocalOnlyImageUrl adalah gabungan data/blob', () => {
        expect(isLocalOnlyImageUrl('data:image/png;base64,AAAA')).toBe(true);
        expect(isLocalOnlyImageUrl('blob:https://dform.test/1234')).toBe(true);
        expect(isLocalOnlyImageUrl(' https://dform.test/a.png ')).toBe(false);
        expect(isLocalOnlyImageUrl('')).toBe(false);
    });
});

describe('cleanStoredImageUrl', () => {
    it.each([
        [' https://dform.test/storage/a.png ', 'https://dform.test/storage/a.png'],
        ['/storage/a.png', '/storage/a.png'],
        ['data:image/png;base64,AAAA', ''],
        ['blob:https://dform.test/1234', ''],
        ['   ', ''],
        ['', ''],
    ])('membersihkan %j menjadi %j', (value: string, expected: string) => {
        expect(cleanStoredImageUrl(value)).toBe(expected);
    });

    it.each([null, undefined, 7, {}, []])('nilai non-string %j menjadi string kosong', (value: unknown) => {
        expect(cleanStoredImageUrl(value)).toBe('');
    });
});

describe('stripLocalImageUrlsDeep', () => {
    it('mengganti URL lokal sementara secara rekursif tanpa menyentuh URL server', () => {
        const input: Record<string, unknown> = {
            bannerUrl: 'blob:https://dform.test/1',
            title: 'Form',
            fields: [
                { name: 'photo', metadata: { imageUrl: 'data:image/png;base64,AAAA', caption: 'foto' } },
                { name: 'plain', metadata: { imageUrl: 'https://dform.test/storage/a.png' } },
            ],
            nested: { deep: ['blob:https://dform.test/2', 5, null, true] },
        };

        expect(stripLocalImageUrlsDeep(input)).toEqual({
            bannerUrl: '',
            title: 'Form',
            fields: [
                { name: 'photo', metadata: { imageUrl: '', caption: 'foto' } },
                { name: 'plain', metadata: { imageUrl: 'https://dform.test/storage/a.png' } },
            ],
            nested: { deep: ['', 5, null, true] },
        });
    });

    it('meneruskan nilai primitif non-string apa adanya', () => {
        expect(stripLocalImageUrlsDeep(5)).toBe(5);
        expect(stripLocalImageUrlsDeep(null)).toBeNull();
        expect(stripLocalImageUrlsDeep(undefined)).toBeUndefined();
        expect(stripLocalImageUrlsDeep(true)).toBe(true);
    });
});

describe('sanitizeBuilderSnapshot', () => {
    it('membuang data/blob URL dari snapshot JSON', () => {
        const snapshot = JSON.stringify({
            fields: [{ metadata: { imageUrl: 'blob:https://dform.test/1' } }],
            bannerUrl: 'data:image/png;base64,AAAA',
            title: 'Judul',
        });

        const sanitized: unknown = JSON.parse(sanitizeBuilderSnapshot(snapshot));

        expect(sanitized).toEqual({
            fields: [{ metadata: { imageUrl: '' } }],
            bannerUrl: '',
            title: 'Judul',
        });
    });

    it('mengembalikan string asal bila JSON tidak valid', () => {
        expect(sanitizeBuilderSnapshot('{bukan json')).toBe('{bukan json');
    });

    it('idempoten: sanitasi dua kali menghasilkan payload sama', () => {
        const once = sanitizeBuilderSnapshot(JSON.stringify({ bannerUrl: 'blob:x', title: 'A' }));

        expect(sanitizeBuilderSnapshot(once)).toBe(once);
    });
});
