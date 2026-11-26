import { DEFAULT_PAGE_SKELETON, PAGE_SKELETON_RULES } from '@/constants/inertiaSkeletons';
import type { ISkeletonContext, TSkeletonKey } from '@/types/skeleton';

/** Basis parse untuk URL relatif; host tidak dipakai karena hanya pathname yang dibaca. */
const PARSE_BASE_URL = 'http://localhost';

/**
 * Kunci skeleton untuk sebuah URL. Query dan hash diabaikan karena hanya pathname yang
 * menentukan halaman; URL kosong atau tidak dapat di-parse memakai fallback.
 */
export function resolveSkeletonKey(url: string, context: ISkeletonContext): TSkeletonKey {
    if (url.trim() === '') return DEFAULT_PAGE_SKELETON;

    let pathname: string;

    try {
        pathname = new URL(url, PARSE_BASE_URL).pathname;
    } catch {
        return DEFAULT_PAGE_SKELETON;
    }

    for (const rule of PAGE_SKELETON_RULES) {
        if (rule.when !== undefined && !rule.when(context)) continue;
        if (rule.pattern.test(pathname)) return rule.skeleton;
    }

    return DEFAULT_PAGE_SKELETON;
}
