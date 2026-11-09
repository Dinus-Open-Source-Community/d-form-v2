/**
 * Pure checkbox-answer helpers for form fill (domain logic, no UI/framework deps).
 */

export function isCheckboxOptionSelected(selected: unknown, option: string): boolean {
    return Array.isArray(selected) && selected.includes(option);
}

/**
 * Kembalikan salinan daftar jawaban checkbox dengan `option` ditambah/dibuang.
 * Nilai non-array dianggap kosong; masukan tidak dimutasi.
 */
export function toggleCheckboxSelection(selected: unknown, option: string, checked: boolean): string[] {
    const current = Array.isArray(selected) ? [...(selected as string[])] : [];
    return checked ? [...current, option] : current.filter((value) => value !== option);
}
