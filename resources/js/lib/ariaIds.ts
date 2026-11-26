/**
 * Gabungkan id untuk atribut `aria-describedby` tanpa duplikat.
 * Nilai kosong berarti atribut tidak perlu dipasang sama sekali.
 */
export function joinAriaIds(...ids: Array<string | undefined>): string | undefined {
    const uniqueIds = [...new Set(ids.filter((id): id is string => id !== undefined && id !== ''))];

    return uniqueIds.length === 0 ? undefined : uniqueIds.join(' ');
}
