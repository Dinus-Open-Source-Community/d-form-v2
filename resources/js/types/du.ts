/** Satu kelas DU dari katalog cache server. */
export interface IDuCourseOption {
    uid: string;
    title: string;
}

/** Satu entri `du_option_map`: course DU yang terhubung ke satu nilai opsi kelas. */
export interface IDuCourseMappingEntry {
    course_uid: string;
    course_title: string;
}

/**
 * Peta opsi kelas immutable ke course DU, disimpan di
 * `form_fields.metadata.du_option_map`. Key = nilai opsi (juga nilai jawaban).
 */
export type TDuOptionMap = Record<string, IDuCourseMappingEntry>;

/** Hasil sinkronisasi katalog kelas DU (`POST /admin/du/courses/sync`). */
export interface IDuCourseSyncResult {
    courses: IDuCourseOption[];
    syncedAt: string;
}

/** Status penerbitan kode redeem; `unknown` tidak pernah diulang otomatis. */
export type TDuRedeemStatus = 'pending' | 'issued' | 'failed' | 'unknown';

/**
 * Satu course DU yang terhubung ke nilai opsi kelas. `is_active` berasal dari cache
 * server: mapping lama tetap terhubung walau course sudah tidak aktif (DU berwenang).
 */
export interface IDuMapping {
    course_uid: string;
    course_title: string;
    is_active: boolean;
}

/** Status penerbitan kode redeem satu jawaban. */
export interface IDuIssuance {
    status: TDuRedeemStatus;
    code: string | null;
    message: string | null;
    /** `true` hanya bila aksi reissue menghasilkan kode baru; absent pada payload lama. */
    created_new_code?: boolean;
}

/** Field kelas yang menjadi sumber redeem DU pada satu form. */
export interface IDuClassField {
    id: string;
    name: string;
    label: string;
}

/** Payload `submission.du` dari drawer jawaban (Task 3). */
export interface IDuSubmission {
    enabled: boolean;
    class_field: IDuClassField | null;
    class_value: string | null;
    mapping: IDuMapping | null;
    suggestion: IDuMapping | null;
    issuance: IDuIssuance | null;
    can_issue: boolean;
}
