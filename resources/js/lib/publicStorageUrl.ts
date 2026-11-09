/**
 * Normalizes a stored public asset reference into a URL an `<img>`/`<a>` can use.
 *
 * Passes through `data:`, `blob:`, `http(s)://`, and already-absolute paths
 * (`/x`). Anything else is treated as a path inside the public disk and
 * prefixed with `/storage/` (leading slashes stripped). Unknown schemes such as
 * `javascript:` therefore stay inert relative paths, never executable URLs.
 * Blank input yields `''`.
 */
export function normalizePublicStorageUrl(raw: string): string {
    const trimmed = raw.trim()
    if (!trimmed) return ''
    if (trimmed.startsWith('data:')) return trimmed
    if (trimmed.startsWith('blob:')) return trimmed
    if (/^https?:\/\//i.test(trimmed)) return trimmed
    if (trimmed.startsWith('/')) return trimmed
    return `/storage/${trimmed.replace(/^\/+/, '')}`
}
