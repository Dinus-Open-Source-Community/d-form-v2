import { ref, type Ref } from 'vue';
import { listDuCourses } from '@/adapters/http/duCourses';
import { DU_CATALOG_ERROR_MESSAGE } from '@/constants/du';
import type { IDuCourseOption } from '@/types/du';

/**
 * Cache module-level per URL: semua field editor memakai satu respons katalog
 * per sesi, sehingga membuka sheet field lain tidak memicu request baru.
 */
const catalogCache = new Map<string, IDuCourseOption[]>();

interface IDuCourseCatalogState {
    catalog: Ref<IDuCourseOption[]>;
    loading: Ref<boolean>;
    error: Ref<string>;
    /** Muat ulang paksa (mis. setelah `Sync kelas`). */
    refresh: () => Promise<boolean>;
    /** Pakai cache bila ada; request hanya saat cache kosong. */
    ensureLoaded: () => Promise<void>;
    /** Ganti isi cache dari respons sync; `false` bila payload tidak layak dipakai. */
    setCatalog: (courses: readonly IDuCourseOption[]) => boolean;
}

/** Katalog kelas DU aktif untuk kartu mapping opsi field kelas. */
export function useDuCourseCatalog(listUrl: string): IDuCourseCatalogState {
    const catalog = ref<IDuCourseOption[]>(catalogCache.get(listUrl) ?? []);
    const loading = ref(false);
    const error = ref('');
    // Respons GET yang tersusul `setCatalog` tidak boleh menimpa katalog yang lebih baru.
    let requestSeq = 0;

    function applyCatalog(courses: IDuCourseOption[]): void {
        catalog.value = courses;
        catalogCache.set(listUrl, courses);
    }

    async function refresh(): Promise<boolean> {
        if (loading.value) return false;

        const seq = ++requestSeq;

        loading.value = true;
        error.value = '';

        try {
            const courses = await listDuCourses(listUrl);

            if (seq !== requestSeq) return false;

            applyCatalog(courses);

            return true;
        } catch {
            if (seq === requestSeq) error.value = DU_CATALOG_ERROR_MESSAGE;

            return false;
        } finally {
            loading.value = false;
        }
    }

    function setCatalog(courses: readonly IDuCourseOption[]): boolean {
        if (courses.some((course) => course.uid === '' || course.title === '')) return false;

        requestSeq += 1;
        applyCatalog(courses.map((course) => ({ uid: course.uid, title: course.title })));
        error.value = '';

        return true;
    }

    async function ensureLoaded(): Promise<void> {
        if (catalog.value.length > 0) return;

        await refresh();
    }

    return { catalog, loading, error, refresh, ensureLoaded, setCatalog };
}
