export function toCategoryList(value: unknown): string[] {
    if (Array.isArray(value)) {
        return value.map((v) => String(v).trim()).filter(Boolean);
    }
    if (typeof value === 'string') {
        return value
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean);
    }
    return [];
}

export function primaryCategory(value: unknown): string {
    return toCategoryList(value)[0] ?? '';
}
