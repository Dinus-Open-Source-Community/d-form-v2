/**
 * Menghapus tag HTML dan memadatkan spasi — dipakai untuk meta description / JSON-LD teks.
 * Entity teks umum (`&amp;`) ikut di-decode; `<`/`>` tetap dibiarkan ter-encode.
 */
export function stripHtmlToText(html: string | undefined | null, maxLength = 320): string {
    if (html == null || html === '') {
        return '';
    }
    const withoutTags = html
        .replace(/<script[\s\S]*?<\/script>/gi, ' ')
        .replace(/<style[\s\S]*?<\/style>/gi, ' ')
        .replace(/<[^>]+>/g, ' ');
    let text = decodeEntities(withoutTags).replace(/\s+/g, ' ').trim();

    if (text.length > maxLength) {
        text = text.slice(0, Math.max(0, maxLength - 1)).trimEnd() + '…';
    }

    return text;
}

const DECODABLE_ENTITIES: Record<string, string> = {
    amp: '&',
    quot: '"',
    apos: "'",
    nbsp: ' ',
};

const ENTITY_PATTERN = /&(#x[0-9a-f]+|#[0-9]+|[a-z][a-z0-9]*);/gi;

/**
 * Decode entity HTML umum tanpa DOM (aman SSR); `<`/`>` tidak di-decode agar
 * hasilnya tidak membuka/menutup tag di konteks render lain.
 */
function decodeEntities(text: string): string {
    return text.replace(ENTITY_PATTERN, (match: string, code: string): string => {
        if (code.startsWith('#')) {
            const isHex = code[1]?.toLowerCase() === 'x';
            const point = Number.parseInt(code.slice(isHex ? 2 : 1), isHex ? 16 : 10);
            if (!Number.isInteger(point) || point < 0 || point > 0x10ffff) {
                return match;
            }
            const char = String.fromCodePoint(point);
            return char === '<' || char === '>' ? match : char;
        }
        return DECODABLE_ENTITIES[code.toLowerCase()] ?? match;
    });
}
