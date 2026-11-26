import { requestJson } from '@/adapters/http/httpClient';
import { isRecord } from '@/lib/isRecord';
import type { IDuCourseOption, IDuCourseSyncResult } from '@/types/du';

/** Satu baris katalog valid hanya bila `uid` dan `title` string tidak kosong. */
function parseCourseOption(value: unknown): IDuCourseOption | null {
    if (!isRecord(value)) return null;

    const { uid, title } = value;

    if (typeof uid !== 'string' || uid === '') return null;
    if (typeof title !== 'string' || title === '') return null;

    return { uid, title };
}

/** Parser ketat daftar katalog aktif (`GET /admin/du/courses`). */
function parseCourseList(payload: unknown): IDuCourseOption[] | null {
    if (!isRecord(payload)) return null;

    const { courses } = payload;

    if (!Array.isArray(courses)) return null;

    const parsed: IDuCourseOption[] = [];

    for (const row of courses) {
        const option = parseCourseOption(row);

        if (option === null) return null;

        parsed.push(option);
    }

    return parsed;
}

/** Parser ketat response sync: bentuk lain dianggap gagal, bukan ditebak. */
function parseCourseSync(payload: unknown): IDuCourseSyncResult | null {
    if (!isRecord(payload)) return null;

    const { courses, synced_at } = payload;

    if (!Array.isArray(courses)) return null;
    if (typeof synced_at !== 'string' || synced_at === '') return null;

    const parsed: IDuCourseOption[] = [];

    for (const row of courses) {
        const option = parseCourseOption(row);

        if (option === null) return null;

        parsed.push(option);
    }

    return { courses: parsed, syncedAt: synced_at };
}

/** Baca katalog kelas DU aktif dari cache server; `url` berasal dari Wayfinder. */
export async function listDuCourses(url: string): Promise<IDuCourseOption[]> {
    return requestJson({ url, method: 'GET' }, parseCourseList);
}

/** Sinkronkan katalog kelas DU dari server; `url` berasal dari Wayfinder. */
export async function syncDuCourses(url: string): Promise<IDuCourseSyncResult> {
    return requestJson({ url, method: 'POST' }, parseCourseSync);
}
