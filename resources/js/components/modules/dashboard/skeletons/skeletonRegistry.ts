import type { Component } from 'vue';
import type { TSkeletonKey } from '@/types/skeleton';

type TSkeletonLoader = () => Promise<{ default: Component }>;

/**
 * Peta kunci ke loader dinamis. `Record` penuh membuat TypeScript menolak kunci yang lupa didaftarkan.
 * Setiap loader berada di chunk terpisah agar first paint tidak menanggung berat skeleton.
 */
const SKELETON_LOADERS: Record<TSkeletonKey, TSkeletonLoader> = {
    'dashboard-index': () => import('./pages/DashboardIndexSkeleton.vue'),
    'member-dashboard': () => import('./pages/MemberDashboardSkeleton.vue'),
    'events-index': () => import('./pages/EventsIndexSkeleton.vue'),
    'events-show': () => import('./pages/EventsShowSkeleton.vue'),
    'users-index': () => import('./pages/UsersIndexSkeleton.vue'),
    'recruitment-index': () => import('./pages/RecruitmentIndexSkeleton.vue'),
    'recruitment-period': () => import('./pages/RecruitmentPeriodSkeleton.vue'),
    'form-builder': () => import('./pages/FormBuilderSkeleton.vue'),
    'user-events': () => import('./pages/UserEventsSkeleton.vue'),
    'user-event-detail': () => import('./pages/UserEventDetailSkeleton.vue'),
    list: () => import('./archetypes/ListPageSkeleton.vue'),
    detail: () => import('./archetypes/DetailPageSkeleton.vue'),
    form: () => import('./archetypes/FormPageSkeleton.vue'),
    generic: () => import('./archetypes/GenericPageSkeleton.vue'),
};

/** Type guard runtime untuk kunci yang datang dari URL, bukan dari kode. */
function isRegisteredKey(key: string): key is TSkeletonKey {
    return Object.prototype.hasOwnProperty.call(SKELETON_LOADERS, key);
}

/** True bila kunci punya loader; dipakai test dan diagnostik. */
export function isSkeletonKeyRegistered(key: string): boolean {
    return isRegisteredKey(key);
}

/** Komponen skeleton untuk kunci tersebut; `null` berarti host memakai `SkeletonFallback`. */
export async function loadSkeletonScreen(key: string): Promise<Component | null> {
    if (!isRegisteredKey(key)) return null;

    try {
        const module = await SKELETON_LOADERS[key]();

        return module.default;
    } catch {
        return null;
    }
}

/** Memuat semua chunk skeleton sekali agar sudah siap sebelum pengguna berpindah halaman. */
export function preloadSkeletonScreens(): void {
    for (const loader of Object.values(SKELETON_LOADERS)) {
        void loader().catch(() => undefined);
    }
}
