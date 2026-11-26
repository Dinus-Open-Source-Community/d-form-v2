import { computed, ref, toValue, watch, type ComputedRef, type MaybeRefOrGetter, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { syncDuCourses } from '@/adapters/http/duCourses';
import { issueDuRedeem, saveDuFieldMapping } from '@/adapters/http/duMapping';
import { isHttpRequestError } from '@/adapters/http/httpError';
import {
    DU_CATALOG_ERROR_MESSAGE,
    DU_ISSUE_ERROR_MESSAGE,
    DU_MAPPING_SAVE_ERROR_MESSAGE,
    DU_RESEND_ERROR_MESSAGE,
} from '@/constants/du';
import { describeRequestError } from '@/lib/requestErrorText';
import { routes } from '@/lib/routes';
import type { IDuCourseOption, IDuIssuance, IDuMapping, IDuSubmission, TDuRedeemStatus } from '@/types/du';

/** Status yang masih boleh diterbitkan ulang; `issued` dan `unknown` tidak. */
const RETRYABLE_STATUSES: ReadonlySet<TDuRedeemStatus> = new Set(['pending', 'failed']);

export interface IUseDuAnswerRedeemOptions {
    /** Endpoint `PUT {form}/du-field-map`. */
    mappingUrl: MaybeRefOrGetter<string>;
    /** Endpoint `POST .../submissions/{answer}/du-issuance`. */
    issueUrl: MaybeRefOrGetter<string>;
    submission: MaybeRefOrGetter<IDuSubmission | null>;
    /** Id jawaban yang sedang ditampilkan; respons lama tidak boleh menimpa jawaban lain. */
    answerId: MaybeRefOrGetter<string | null>;
    /** Hanya jawaban berstatus review `accepted` yang boleh menerbitkan kode. */
    reviewAccepted: MaybeRefOrGetter<boolean>;
    catalog: MaybeRefOrGetter<readonly IDuCourseOption[]>;
}

export interface IDuAnswerRedeem {
    mapping: Ref<IDuMapping | null>;
    issuance: Ref<IDuIssuance | null>;
    /**
     * `true` bila review accepted, status retryable, nilai kelas ada, dan server
     * mengizinkan (`can_issue`) atau mapping baru saja dibuat dari drawer.
     */
    canIssue: ComputedRef<boolean>;
    saving: Ref<boolean>;
    issuing: Ref<boolean>;
    /** Gagal simpan mapping; ditampilkan di blok mapping. */
    saveError: Ref<string>;
    /** Gagal terbitkan kode; ditampilkan di blok penerbitan. */
    issueError: Ref<string>;
    /** Simpan mapping satu nilai opsi; `false` bila gagal atau input belum lengkap. */
    saveMapping: (courseUid: string) => Promise<boolean>;
    /** Terbitkan kode; tidak pernah dijalankan untuk status `unknown` atau `issued`. */
    issue: () => Promise<boolean>;
    /** Aksi pemulihan kode terbit yang sedang berjalan; `null` bila idle. */
    recovering: Ref<'resend' | 'reissue' | null>;
    /** Aksi pemulihan terakhir yang berhasil; menentukan gaya pesan hasil. */
    lastRecovery: Ref<'resend' | 'reissue' | null>;
    /** Gagal kirim ulang/terbitkan ulang; ditampilkan di blok penerbitan. */
    recoveryError: Ref<string>;
    /** Kirim ulang email dengan kode yang sama; `false` bila gagal. */
    resendEmail: () => Promise<boolean>;
    /** Terbitkan kode baru; kode lama dipertahankan bila DU menolak. */
    reissueCode: () => Promise<boolean>;
    /** Sedang menyinkronkan katalog DU dari drawer. */
    syncingCatalog: Ref<boolean>;
    /** Gagal sync katalog DU; ditampilkan dekat tombol sync. */
    catalogSyncError: Ref<string>;
    /** Sync katalog DU lalu muat ulang prop `duCatalog`; `false` bila gagal. */
    syncCatalog: () => Promise<boolean>;
}

function requestErrorMessage(error: unknown, fallback: string): string {
    return isHttpRequestError(error) ? (describeRequestError(error) ?? fallback) : fallback;
}

/**
 * State aksi redeem DU di drawer jawaban. Mapping dan issuance disimpan lokal
 * agar status langsung berubah tanpa menunggu reload prop Inertia.
 */
export function useDuAnswerRedeem(options: IUseDuAnswerRedeemOptions): IDuAnswerRedeem {
    const submission = computed<IDuSubmission | null>(() => toValue(options.submission));
    const mapping = ref<IDuMapping | null>(null);
    const issuance = ref<IDuIssuance | null>(null);
    const saving = ref(false);
    const issuing = ref(false);
    const syncingCatalog = ref(false);
    const saveError = ref('');
    const issueError = ref('');
    const catalogSyncError = ref('');
    const recovering = ref<'resend' | 'reissue' | null>(null);
    const lastRecovery = ref<'resend' | 'reissue' | null>(null);
    const recoveryError = ref('');
    /**
     * Naik setiap jawaban yang dipilih berganti. Respons lama yang selesai setelah
     * pergantian tidak boleh menimpa mapping/issuance jawaban yang baru.
     */
    const requestToken = ref(0);

    watch(
        submission,
        (next: IDuSubmission | null): void => {
            requestToken.value += 1;
            mapping.value = next?.mapping ?? null;
            issuance.value = next?.issuance ?? null;
            saving.value = false;
            issuing.value = false;
            saveError.value = '';
            issueError.value = '';
            recovering.value = null;
            lastRecovery.value = null;
            recoveryError.value = '';
        },
        { immediate: true }
    );

    /** True bila jawaban yang diminta saat request dimulai masih yang ditampilkan. */
    function isCurrentRequest(token: number, answerId: string | null): boolean {
        return requestToken.value === token && toValue(options.answerId) === answerId;
    }

    const retryable = computed<boolean>(() => {
        const status = issuance.value?.status ?? null;

        return status === null || RETRYABLE_STATUSES.has(status);
    });

    /** Mapping baru dibuat lokal padahal payload server belum punya mapping. */
    const newlyMapped = computed<boolean>(
        () => submission.value !== null && submission.value.mapping === null && mapping.value !== null
    );

    const canIssue = computed<boolean>(() => {
        const current = submission.value;

        if (current === null || !toValue(options.reviewAccepted)) return false;
        if (current.class_value === null) return false;

        return retryable.value && (current.can_issue || newlyMapped.value);
    });

    async function saveMapping(courseUid: string): Promise<boolean> {
        const current = submission.value;

        if (saving.value) return false;
        if (current === null || current.class_field === null || current.class_value === null) return false;
        if (courseUid === '') return false;

        const token = requestToken.value;
        const answerId = toValue(options.answerId);

        saving.value = true;
        saveError.value = '';

        try {
            const entry = await saveDuFieldMapping(toValue(options.mappingUrl), {
                field_id: current.class_field.id,
                option_value: current.class_value,
                course_uid: courseUid,
            });

            if (!isCurrentRequest(token, answerId)) return false;

            mapping.value = {
                ...entry,
                is_active: toValue(options.catalog).some((course) => course.uid === entry.course_uid),
            };

            return true;
        } catch (failure: unknown) {
            if (!isCurrentRequest(token, answerId)) return false;

            saveError.value = requestErrorMessage(failure, DU_MAPPING_SAVE_ERROR_MESSAGE);

            return false;
        } finally {
            if (isCurrentRequest(token, answerId)) saving.value = false;
        }
    }

    /** Sync katalog DU global lalu muat ulang prop `duCatalog`; tidak menampilkan toast. */
    async function syncCatalog(): Promise<boolean> {
        if (syncingCatalog.value) return false;

        syncingCatalog.value = true;
        catalogSyncError.value = '';

        try {
            await syncDuCourses(routes.admin.duCourses.sync);
            // Inertia v2 reload selalu mempertahankan scroll.
            router.reload({ only: ['duCatalog'] });

            return true;
        } catch {
            catalogSyncError.value = DU_CATALOG_ERROR_MESSAGE;

            return false;
        } finally {
            syncingCatalog.value = false;
        }
    }

    async function issue(): Promise<boolean> {
        if (issuing.value || !canIssue.value) return false;

        const token = requestToken.value;
        const answerId = toValue(options.answerId);

        issuing.value = true;
        issueError.value = '';

        try {
            const result = await issueDuRedeem(toValue(options.issueUrl), 'issue');

            if (!isCurrentRequest(token, answerId)) return false;

            issuance.value = result;

            return true;
        } catch (failure: unknown) {
            if (!isCurrentRequest(token, answerId)) return false;

            issueError.value = requestErrorMessage(failure, DU_ISSUE_ERROR_MESSAGE);

            return false;
        } finally {
            if (isCurrentRequest(token, answerId)) issuing.value = false;
        }
    }

    /**
     * Aksi pemulihan untuk kode yang sudah terbit: `resend` memakai kode yang sama,
     * `reissue` meminta kode baru dan backend mempertahankan kode lama bila DU menolak.
     */
    async function recover(action: 'resend' | 'reissue'): Promise<boolean> {
        if (recovering.value !== null) return false;
        if (issuance.value?.status !== 'issued') return false;

        const token = requestToken.value;
        const answerId = toValue(options.answerId);

        recovering.value = action;
        recoveryError.value = '';

        try {
            const result = await issueDuRedeem(toValue(options.issueUrl), action);

            if (!isCurrentRequest(token, answerId)) return false;

            issuance.value = result;
            lastRecovery.value = action;

            return true;
        } catch (failure: unknown) {
            if (!isCurrentRequest(token, answerId)) return false;

            recoveryError.value = requestErrorMessage(
                failure,
                action === 'resend' ? DU_RESEND_ERROR_MESSAGE : DU_ISSUE_ERROR_MESSAGE
            );

            return false;
        } finally {
            if (isCurrentRequest(token, answerId)) recovering.value = null;
        }
    }

    async function resendEmail(): Promise<boolean> {
        return recover('resend');
    }

    async function reissueCode(): Promise<boolean> {
        return recover('reissue');
    }

    return {
        mapping,
        issuance,
        canIssue,
        saving,
        issuing,
        saveError,
        issueError,
        saveMapping,
        issue,
        recovering,
        lastRecovery,
        recoveryError,
        resendEmail,
        reissueCode,
        syncingCatalog,
        catalogSyncError,
        syncCatalog,
    };
}
