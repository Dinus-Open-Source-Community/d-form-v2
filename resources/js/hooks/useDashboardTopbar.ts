import { inject, provide, ref } from 'vue';
import type { InjectionKey, Ref } from 'vue';

/**
 * State judul/subjudul untuk DashboardTopbar.
 * Halaman dashboard memanggil setTopbar() (biasanya di setup/onMounted) agar
 * topbar menampilkan judul halaman yang eksplisit, bukan parsing judul dokumen.
 * State di-reset oleh layout saat komponen halaman berganti (watch page.component).
 */

interface ITopbarState {
    title: string | null;
    subtitle?: string | null;
}

export interface ITopbarContext {
    title: Ref<string | null>;
    subtitle: Ref<string | null>;
    setTopbar: (state: ITopbarState) => void;
    clearTopbar: () => void;
}

const topbarKey: InjectionKey<ITopbarContext> = Symbol('DashboardTopbar');

/** Creates isolated topbar state for one dashboard layout/provider. */
export function provideTopbar(): ITopbarContext {
    const title = ref<string | null>(null);
    const subtitle = ref<string | null>(null);
    const context: ITopbarContext = {
        title,
        subtitle,
        setTopbar: (state: ITopbarState): void => {
            title.value = state.title ?? null;
            subtitle.value = state.subtitle ?? null;
        },
        clearTopbar: (): void => {
            title.value = null;
            subtitle.value = null;
        },
    };
    provide(topbarKey, context);
    return context;
}

/** Side effect: set judul/subjudul topbar global; komponen halaman memanggilnya saat mount. */
export function setTopbar(state: ITopbarState): void {
    requireTopbarContext().setTopbar(state);
}

/** Side effect: kosongkan state topbar; dipanggil layout saat komponen halaman berganti. */
export function clearTopbar(): void {
    requireTopbarContext().clearTopbar();
}

/** Baca state topbar global (ref bersama di level modul). */
export function useTopbar(): Pick<ITopbarContext, 'title' | 'subtitle' | 'clearTopbar'> {
    const context = requireTopbarContext();
    return { title: context.title, subtitle: context.subtitle, clearTopbar: context.clearTopbar };
}

function requireTopbarContext(): ITopbarContext {
    const context = inject(topbarKey);
    if (context === undefined) {
        throw new Error('useTopbar must be used within a dashboard topbar provider');
    }
    return context;
}
