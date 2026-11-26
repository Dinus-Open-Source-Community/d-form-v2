import { ref, type Ref } from 'vue';
import { syncDuCourses } from '@/adapters/http/duCourses';
import { DU_CATALOG_ERROR_MESSAGE } from '@/constants/du';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import type { IDuCourseOption } from '@/types/du';

interface IDuCourseSyncState {
    syncing: Ref<boolean>;
    error: Ref<string>;
    courses: Ref<IDuCourseOption[]>;
    /** Waktu sinkron terakhir (ISO8601); kosong bila belum pernah sukses di sesi ini. */
    syncedAt: Ref<string>;
    /** `true` bila katalog berhasil dimuat. */
    sync: () => Promise<boolean>;
}

/** Sinkronisasi katalog kelas DU untuk kartu field sistem builder. */
export function useDuCourseSync(syncUrl: string): IDuCourseSyncState {
    const { showErrorToast } = useToastNotifications();
    const syncing = ref(false);
    const error = ref('');
    const courses = ref<IDuCourseOption[]>([]);
    const syncedAt = ref('');

    async function sync(): Promise<boolean> {
        if (syncing.value) return false;

        syncing.value = true;
        error.value = '';

        try {
            const result = await syncDuCourses(syncUrl);

            courses.value = result.courses;
            syncedAt.value = result.syncedAt;

            return true;
        } catch {
            error.value = DU_CATALOG_ERROR_MESSAGE;
            showErrorToast(DU_CATALOG_ERROR_MESSAGE);

            return false;
        } finally {
            syncing.value = false;
        }
    }

    return { syncing, error, courses, syncedAt, sync };
}
