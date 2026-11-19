import { onScopeDispose, ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { currentPathname } from '@/adapters/browser/navigation';

/** Basis parse untuk URL relatif; host tidak dipakai karena hanya pathname yang dibaca. */
const PARSE_BASE_URL = 'http://localhost';

export interface IInertiaReloadingOptions {
    /** Sumber pathname saat ini; default memakai adapter browser. */
    readonly currentPathname?: () => string;
}

export interface IInertiaReloading {
    readonly reloading: Ref<boolean>;
}

function readPathname(url: string): string {
    try {
        return new URL(url, PARSE_BASE_URL).pathname;
    } catch {
        return '';
    }
}

/**
 * Menandai reload data di dalam halaman (filter, pencarian, pagination) agar area terkait
 * bisa menampilkan skeleton tanpa memunculkan skeleton satu layar.
 */
export function useInertiaReloading(options: IInertiaReloadingOptions = {}): IInertiaReloading {
    const reloading = ref(false);

    const offStart = router.on('start', (event) => {
        const visit = event.detail.visit;

        if (visit.method.toLowerCase() !== 'get') return;

        const sourcePathname = options.currentPathname?.() ?? currentPathname();

        if (visit.preserveState !== true || readPathname(visit.url.toString()) !== sourcePathname) return;

        reloading.value = true;
    });

    function clear(): void {
        reloading.value = false;
    }

    const offFinish = router.on('finish', clear);
    const offCancel = router.on('cancel', clear);

    onScopeDispose(() => {
        offStart();
        offFinish();
        offCancel();
    });

    return { reloading };
}
