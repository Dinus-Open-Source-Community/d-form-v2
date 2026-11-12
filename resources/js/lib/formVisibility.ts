/**
 * Satu implementasi untuk toggle opsi visibilitas form (Public/Participant/Admin).
 * Dipakai panel pengaturan di halaman edit dan di tab pengaturan halaman create/wizard,
 * supaya urutan nilai yang dikirim ke backend selalu sama di semua surface.
 */

/**
 * Kembalikan daftar visibilitas baru: `value` ditambahkan di akhir bila `checked`,
 * atau dibuang seluruh kemunculannya bila tidak. Nilai lain dan urutannya dipertahankan.
 */
export function toggleVisibilitySelection(current: readonly string[], value: string, checked: boolean): string[] {
    const withoutValue = current.filter((item) => item !== value);

    return checked ? [...withoutValue, value] : withoutValue;
}
