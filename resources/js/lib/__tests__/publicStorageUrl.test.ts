import { describe, expect, it } from 'vitest'
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl'

describe('normalizePublicStorageUrl', () => {
    it('returns an empty string for blank input', () => {
        expect(normalizePublicStorageUrl('')).toBe('')
        expect(normalizePublicStorageUrl('   \n\t ')).toBe('')
    })

    it('passes through inline data and blob previews', () => {
        expect(normalizePublicStorageUrl('data:image/png;base64,AAAA')).toBe('data:image/png;base64,AAAA')
        expect(normalizePublicStorageUrl('blob:http://localhost/1234')).toBe('blob:http://localhost/1234')
    })

    it('passes through absolute http(s) URLs (case-insensitive)', () => {
        expect(normalizePublicStorageUrl('https://cdn.example.com/a.png')).toBe('https://cdn.example.com/a.png')
        expect(normalizePublicStorageUrl('HTTP://cdn.example.com/a.png')).toBe('HTTP://cdn.example.com/a.png')
    })

    it('passes through paths that are already absolute', () => {
        expect(normalizePublicStorageUrl('/images/logo.png')).toBe('/images/logo.png')
    })

    it('maps relative storage paths into /storage', () => {
        expect(normalizePublicStorageUrl('form-images/a.png')).toBe('/storage/form-images/a.png')
        expect(normalizePublicStorageUrl('/form-images/a.png')).toBe('/form-images/a.png')
    })

    it('preserves any input that already starts with a slash', () => {
        expect(normalizePublicStorageUrl('///form-images/a.png')).toBe('///form-images/a.png')
    })

    it('keeps unknown schemes inert as storage paths', () => {
        const result = normalizePublicStorageUrl('javascript:alert(1)')
        expect(result).toBe('/storage/javascript:alert(1)')
        expect(result.startsWith('/storage/')).toBe(true)
    })
})
