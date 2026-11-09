import { ref } from 'vue';
import type { Ref } from 'vue';

/**
 * State judul/subjudul untuk DashboardTopbar.
 * Halaman dashboard memanggil setTopbar() (biasanya di setup/onMounted) agar
 * topbar menampilkan judul halaman yang eksplisit, bukan parsing judul dokumen.
 * State di-reset oleh layout saat komponen halaman berganti (watch page.component).
 */

interface TopbarState {
    title: string | null;
    subtitle?: string | null;
}

const title = ref<string | null>(null);
const subtitle = ref<string | null>(null);

/** Side effect: set judul/subjudul topbar global; komponen halaman memanggilnya saat mount. */
export function setTopbar(state: TopbarState): void {
    title.value = state.title ?? null;
    subtitle.value = state.subtitle ?? null;
}

/** Side effect: kosongkan state topbar; dipanggil layout saat komponen halaman berganti. */
export function clearTopbar(): void {
    title.value = null;
    subtitle.value = null;
}

/** Baca state topbar global (ref bersama di level modul). */
export function useTopbar(): { title: Ref<string | null>; subtitle: Ref<string | null> } {
    return { title, subtitle };
}
