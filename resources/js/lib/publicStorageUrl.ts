import { isInternalPath } from '@/lib/urlPolicy';

/**
 * Normalizes a stored public asset reference into a URL an `<img>`/`<a>` can use.
 *
 * Accepted forms:
 * - `data:` and `blob:` previews;
 * - explicit absolute `http(s)://` URLs (the banner feature allows external images);
 * - same-origin absolute paths such as `/images/logo.png`;
 * - relative public-disk paths, prefixed with `/storage/` (leading slashes stripped).
 *
 * Rejected (returns `''` so the element stays inert): any input containing
 * tab/newline (WHATWG strips them before parsing, turning `/\t/host` into a
 * network-path reference), network-path references (`//host/x.png`,
 * `\\host\x.png`, `/\host/x.png`), and ambiguous multi-slash forms (`///x`).
 * Browsers treat those as a different origin, so accepting them would make the
 * app request third-party hosts. Unknown schemes such as `javascript:` stay
 * inert relative storage paths and never become executable.
 * Blank input yields `''`.
 */
export function normalizePublicStorageUrl(raw: string): string {
    const trimmed = raw.trim();
    if (!trimmed) return '';
    if (/[\t\n\r]/.test(trimmed)) return '';
    if (trimmed.startsWith('data:')) return trimmed;
    if (trimmed.startsWith('blob:')) return trimmed;
    if (/^https?:\/\//i.test(trimmed)) return trimmed;
    if (isNetworkPathReference(trimmed)) return '';
    if (trimmed.startsWith('/')) return isInternalPath(trimmed) ? trimmed : '';
    return `/storage/${trimmed.replace(/^\/+/, '')}`;
}

/** Leading separator run of two or more `/` or `\` characters escapes the origin. */
function isNetworkPathReference(value: string): boolean {
    return /^[\\/]{2}/.test(value);
}
