import { onScopeDispose, ref, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { currentPathname } from '@/adapters/browser/navigation';
import { resolveSkeletonKey } from '@/lib/navigationSkeleton';
import type { ISkeletonContext, TSkeletonKey } from '@/types/skeleton';

/** Kunjungan lebih cepat dari ini tidak memunculkan skeleton agar tidak berkedip. */
export const NAVIGATION_SKELETON_DELAY_MS = 120;

/** Basis parse untuk URL relatif; host tidak dipakai karena hanya pathname yang dibaca. */
const PARSE_BASE_URL = 'http://localhost';

export interface INavigationSkeletonOptions {
    /** Sumber pathname saat ini; default memakai adapter browser. */
    readonly currentPathname?: () => string;
    /** Capability pengguna untuk memilih skeleton pada URL yang sama. */
    readonly context?: () => ISkeletonContext;
}

export interface INavigationSkeleton {
    readonly pending: Ref<boolean>;
    readonly skeletonKey: Ref<TSkeletonKey | null>;
}

function readPathname(url: string): string {
    try {
        return new URL(url, PARSE_BASE_URL).pathname;
    } catch {
        return '';
    }
}

/**
 * Memantau kunjungan Inertia antar-halaman dan menyediakan kunci skeleton tujuan.
 * Reload di dalam halaman (`preserveState` pada pathname yang sama) dan method non-GET dilewati.
 */
export function useNavigationSkeleton(options: INavigationSkeletonOptions = {}): INavigationSkeleton {
    const pending = ref(false);
    const skeletonKey = ref<TSkeletonKey | null>(null);
    let delayTimer: ReturnType<typeof setTimeout> | null = null;

    function clear(): void {
        if (delayTimer !== null) {
            clearTimeout(delayTimer);
            delayTimer = null;
        }

        pending.value = false;
        skeletonKey.value = null;
    }

    const offStart = router.on('start', (event) => {
        const visit = event.detail.visit;

        if (visit.method.toLowerCase() !== 'get') return;

        const sourcePathname = options.currentPathname?.() ?? currentPathname();
        const visitUrl = visit.url.toString();

        if (visit.preserveState === true && readPathname(visitUrl) === sourcePathname) return;

        clear();
        skeletonKey.value = resolveSkeletonKey(visitUrl, options.context?.() ?? { canManageEvents: false });

        delayTimer = setTimeout(() => {
            delayTimer = null;
            pending.value = true;
        }, NAVIGATION_SKELETON_DELAY_MS);
    });

    const offFinish = router.on('finish', clear);
    const offCancel = router.on('cancel', clear);

    onScopeDispose(() => {
        clear();
        offStart();
        offFinish();
        offCancel();
    });

    return { pending, skeletonKey };
}
