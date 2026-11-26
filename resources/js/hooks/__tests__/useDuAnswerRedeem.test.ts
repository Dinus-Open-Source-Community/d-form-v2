import { describe, expect, it, vi } from 'vitest';
import { nextTick, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { syncDuCourses } from '@/adapters/http/duCourses';
import { issueDuRedeem, saveDuFieldMapping } from '@/adapters/http/duMapping';
import { HttpRequestError } from '@/adapters/http/httpError';
import { useDuAnswerRedeem } from '@/hooks/useDuAnswerRedeem';
import type { IDuCourseMappingEntry, IDuCourseOption, IDuMapping, IDuSubmission } from '@/types/du';

vi.mock('@inertiajs/vue3', () => ({ router: { reload: vi.fn() } }));
vi.mock('@/adapters/http/duMapping', () => ({ saveDuFieldMapping: vi.fn(), issueDuRedeem: vi.fn() }));
vi.mock('@/adapters/http/duCourses', () => ({ syncDuCourses: vi.fn() }));

const saveMock = vi.mocked(saveDuFieldMapping);
const issueMock = vi.mocked(issueDuRedeem);
const syncCoursesMock = vi.mocked(syncDuCourses);
const reloadMock = vi.mocked(router.reload);

const MAPPING_URL = '/admin/events/event-1/forms/form-1/du-field-map';
const ISSUE_URL = '/admin/events/event-1/forms/form-1/submissions/answer-1/du-issuance';
const CATALOG: IDuCourseOption[] = [{ uid: 'course-ai', title: 'AI Engineer' }];
const ACTIVE_MAPPING: IDuMapping = { course_uid: 'course-ai', course_title: 'AI Engineer', is_active: true };

function submission(overrides: Partial<IDuSubmission> = {}): IDuSubmission {
    return {
        enabled: true,
        class_field: { id: 'field-1', name: 'class', label: 'Class' },
        class_value: 'AI Engineer',
        mapping: null,
        suggestion: { course_uid: 'course-ai', course_title: 'AI Engineer', is_active: true },
        issuance: null,
        can_issue: false,
        ...overrides,
    };
}

function mappedSubmission(overrides: Partial<IDuSubmission> = {}): IDuSubmission {
    return submission({ mapping: ACTIVE_MAPPING, suggestion: null, can_issue: true, ...overrides });
}

function createRedeem(payload: IDuSubmission | null, reviewAccepted = true) {
    return useDuAnswerRedeem({
        mappingUrl: MAPPING_URL,
        issueUrl: ISSUE_URL,
        submission: payload,
        answerId: 'answer-1',
        reviewAccepted,
        catalog: CATALOG,
    });
}

describe('useDuAnswerRedeem', () => {
    it('menyimpan mapping opsi kelas dan menandai course katalog aktif', async () => {
        saveMock.mockResolvedValue({ course_uid: 'course-ai', course_title: 'AI Engineer' });
        const redeem = createRedeem(submission());

        const saving = redeem.saveMapping('course-ai');

        expect(redeem.saving.value).toBe(true);
        await expect(saving).resolves.toBe(true);

        expect(saveMock).toHaveBeenCalledWith(MAPPING_URL, {
            field_id: 'field-1',
            option_value: 'AI Engineer',
            course_uid: 'course-ai',
        });
        expect(redeem.mapping.value).toEqual(ACTIVE_MAPPING);
        expect(redeem.canIssue.value).toBe(true);
        expect(redeem.saveError.value).toBe('');
    });

    it('mempertahankan status belum terhubung saat mapping gagal disimpan', async () => {
        saveMock.mockRejectedValue(new HttpRequestError({ kind: 'validation', status: 422, message: 'invalid' }));
        const redeem = createRedeem(submission());

        await expect(redeem.saveMapping('course-ai')).resolves.toBe(false);

        expect(redeem.mapping.value).toBeNull();
        expect(redeem.canIssue.value).toBe(false);
        expect(redeem.saveError.value).toBe('Data yang dikirim tidak valid. Periksa kembali formulir.');
        expect(redeem.saving.value).toBe(false);
    });

    it('menolak simpan mapping tanpa field atau nilai kelas', async () => {
        const redeem = createRedeem(submission({ class_field: null, class_value: null }));

        await expect(redeem.saveMapping('course-ai')).resolves.toBe(false);

        expect(saveMock).not.toHaveBeenCalled();
    });

    it('menerbitkan kode dan mengunci penerbitan ulang setelah issued', async () => {
        issueMock.mockResolvedValue({ status: 'issued', code: 'DU-TEST-1', message: null });
        const redeem = createRedeem(mappedSubmission());

        await expect(redeem.issue()).resolves.toBe(true);

        expect(issueMock).toHaveBeenCalledWith(ISSUE_URL, 'issue');
        expect(redeem.issuance.value).toEqual({ status: 'issued', code: 'DU-TEST-1', message: null });
        expect(redeem.canIssue.value).toBe(false);
    });

    it('menampilkan error penerbitan terpisah dan tetap mengizinkan percobaan berikutnya', async () => {
        issueMock.mockRejectedValue(new HttpRequestError({ kind: 'server', status: 500, message: 'boom' }));
        const redeem = createRedeem(mappedSubmission());

        await expect(redeem.issue()).resolves.toBe(false);

        expect(redeem.issuance.value).toBeNull();
        expect(redeem.issueError.value).toBe('Terjadi kesalahan server. Coba lagi beberapa saat lagi.');
        expect(redeem.saveError.value).toBe('');
        expect(redeem.canIssue.value).toBe(true);
    });

    it('memisahkan error mapping dari error penerbitan', async () => {
        saveMock.mockRejectedValue(new HttpRequestError({ kind: 'validation', status: 422, message: 'invalid' }));
        const redeem = createRedeem(submission());

        await redeem.saveMapping('course-ai');

        expect(redeem.saveError.value).not.toBe('');
        expect(redeem.issueError.value).toBe('');
    });

    it('menghormati can_issue=false server walau status pending masih retryable', async () => {
        const redeem = createRedeem(
            mappedSubmission({
                can_issue: false,
                issuance: { status: 'pending', code: null, message: null },
            })
        );

        expect(redeem.canIssue.value).toBe(false);
        await expect(redeem.issue()).resolves.toBe(false);
        expect(issueMock).not.toHaveBeenCalled();
    });

    it('mengizinkan penerbitan setelah mapping baru dibuat saat can_issue=false dan status pending', async () => {
        saveMock.mockResolvedValue({ course_uid: 'course-ai', course_title: 'AI Engineer' });
        issueMock.mockResolvedValue({ status: 'pending', code: null, message: null });
        const redeem = createRedeem(
            submission({
                can_issue: false,
                issuance: { status: 'pending', code: null, message: null },
            })
        );

        expect(redeem.canIssue.value).toBe(false);

        await redeem.saveMapping('course-ai');

        expect(redeem.canIssue.value).toBe(true);
        await expect(redeem.issue()).resolves.toBe(true);
        expect(issueMock).toHaveBeenCalledWith(ISSUE_URL, 'issue');
    });

    it('memakai can_issue=true server untuk status pending', async () => {
        issueMock.mockResolvedValue({ status: 'pending', code: null, message: null });
        const redeem = createRedeem(
            mappedSubmission({
                can_issue: true,
                issuance: { status: 'pending', code: null, message: null },
            })
        );

        expect(redeem.canIssue.value).toBe(true);
        await expect(redeem.issue()).resolves.toBe(true);
        expect(issueMock).toHaveBeenCalledTimes(1);
    });

    it('mengizinkan penerbitan ulang status failed', async () => {
        issueMock.mockResolvedValue({ status: 'issued', code: 'DU-TEST-2', message: null });
        const redeem = createRedeem(
            mappedSubmission({
                issuance: { status: 'failed', code: null, message: 'DU rejected request.' },
            })
        );

        expect(redeem.canIssue.value).toBe(true);
        await expect(redeem.issue()).resolves.toBe(true);
        expect(issueMock).toHaveBeenCalledWith(ISSUE_URL, 'issue');
    });

    it('tidak menerbitkan ulang status unknown', async () => {
        const redeem = createRedeem(
            mappedSubmission({
                issuance: { status: 'unknown', code: null, message: null },
                can_issue: false,
            })
        );

        await expect(redeem.issue()).resolves.toBe(false);

        expect(redeem.canIssue.value).toBe(false);
        expect(issueMock).not.toHaveBeenCalled();
    });

    it('tidak menerbitkan ulang status issued', async () => {
        const redeem = createRedeem(
            mappedSubmission({
                issuance: { status: 'issued', code: 'DU-OLD', message: null },
                can_issue: false,
            })
        );

        await expect(redeem.issue()).resolves.toBe(false);

        expect(redeem.canIssue.value).toBe(false);
        expect(issueMock).not.toHaveBeenCalled();
    });

    it('menolak penerbitan untuk jawaban yang belum accepted', async () => {
        const redeem = createRedeem(mappedSubmission(), false);

        await expect(redeem.issue()).resolves.toBe(false);

        expect(redeem.canIssue.value).toBe(false);
        expect(issueMock).not.toHaveBeenCalled();
    });

    it('menolak penerbitan saat nilai kelas tidak tercatat', async () => {
        const redeem = createRedeem(mappedSubmission({ class_value: null }));

        expect(redeem.canIssue.value).toBe(false);
        await expect(redeem.issue()).resolves.toBe(false);
        expect(issueMock).not.toHaveBeenCalled();
    });

    it('mengabaikan respons mapping lama setelah drawer berpindah jawaban', async () => {
        let resolveSave: (entry: IDuCourseMappingEntry) => void = () => undefined;
        saveMock.mockReturnValue(
            new Promise<IDuCourseMappingEntry>((resolve) => {
                resolveSave = resolve;
            })
        );

        const selected = ref<IDuSubmission | null>(submission());
        const answerId = ref<string | null>('answer-1');
        const redeem = useDuAnswerRedeem({
            mappingUrl: MAPPING_URL,
            issueUrl: ISSUE_URL,
            submission: () => selected.value,
            answerId: () => answerId.value,
            reviewAccepted: true,
            catalog: CATALOG,
        });

        const pendingSave = redeem.saveMapping('course-ai');

        answerId.value = 'answer-2';
        selected.value = submission({ class_value: 'DevOps' });
        await nextTick();

        resolveSave({ course_uid: 'course-ai', course_title: 'AI Engineer' });

        await expect(pendingSave).resolves.toBe(false);
        expect(redeem.mapping.value).toBeNull();
        expect(redeem.canIssue.value).toBe(false);
        expect(redeem.saving.value).toBe(false);
        expect(redeem.saveError.value).toBe('');
    });

    it('mengabaikan respons penerbitan lama setelah drawer berpindah jawaban', async () => {
        let resolveIssue: (result: { status: 'issued'; code: string; message: null }) => void = () => undefined;
        issueMock.mockReturnValue(
            new Promise((resolve) => {
                resolveIssue = resolve;
            })
        );

        const selected = ref<IDuSubmission | null>(mappedSubmission());
        const answerId = ref<string | null>('answer-1');
        const redeem = useDuAnswerRedeem({
            mappingUrl: MAPPING_URL,
            issueUrl: ISSUE_URL,
            submission: () => selected.value,
            answerId: () => answerId.value,
            reviewAccepted: true,
            catalog: CATALOG,
        });

        const pendingIssue = redeem.issue();

        answerId.value = 'answer-2';
        selected.value = mappedSubmission({ can_issue: false });
        await nextTick();

        resolveIssue({ status: 'issued', code: 'DU-STALE', message: null });

        await expect(pendingIssue).resolves.toBe(false);
        expect(redeem.issuance.value).toBeNull();
        expect(redeem.issuing.value).toBe(false);
        expect(redeem.issueError.value).toBe('');
    });

    it('mengirim ulang email kode lama tanpa mengganti kode', async () => {
        const issued: IDuSubmission = mappedSubmission({
            issuance: { status: 'issued', code: 'DU-OLD', message: null },
        });
        issueMock.mockResolvedValue({
            status: 'issued',
            code: 'DU-OLD',
            message: 'Email dikirim ulang.',
            created_new_code: false,
        });
        const redeem = createRedeem(issued);

        await expect(redeem.resendEmail()).resolves.toBe(true);

        expect(issueMock).toHaveBeenCalledWith(ISSUE_URL, 'resend');
        expect(redeem.issuance.value?.code).toBe('DU-OLD');
        expect(redeem.lastRecovery.value).toBe('resend');
        expect(redeem.recovering.value).toBeNull();
    });

    it('menerbitkan kode baru dan menyimpan kode pengganti', async () => {
        const issued: IDuSubmission = mappedSubmission({
            issuance: { status: 'issued', code: 'DU-OLD', message: null },
        });
        issueMock.mockResolvedValue({
            status: 'issued',
            code: 'DU-NEW',
            message: null,
            created_new_code: true,
        });
        const redeem = createRedeem(issued);

        await expect(redeem.reissueCode()).resolves.toBe(true);

        expect(issueMock).toHaveBeenCalledWith(ISSUE_URL, 'reissue');
        expect(redeem.issuance.value?.code).toBe('DU-NEW');
        expect(redeem.lastRecovery.value).toBe('reissue');
    });

    it('mempertahankan kode lama saat DU menolak penerbitan ulang', async () => {
        const issued: IDuSubmission = mappedSubmission({
            issuance: { status: 'issued', code: 'DU-OLD', message: null },
        });
        issueMock.mockResolvedValue({
            status: 'issued',
            code: 'DU-OLD',
            message: 'Penerbitan ulang gagal; kode lama tetap berlaku.',
            created_new_code: false,
        });
        const redeem = createRedeem(issued);

        await expect(redeem.reissueCode()).resolves.toBe(true);

        expect(redeem.issuance.value?.code).toBe('DU-OLD');
        expect(redeem.issuance.value?.created_new_code).toBe(false);
    });

    it('menampilkan error saat aksi pemulihan gagal dan tidak berjalan untuk kode belum terbit', async () => {
        issueMock.mockRejectedValue(new HttpRequestError({ kind: 'server', status: 500, message: 'boom' }));
        const issued: IDuSubmission = mappedSubmission({
            issuance: { status: 'issued', code: 'DU-OLD', message: null },
        });
        const redeem = createRedeem(issued);

        await expect(redeem.resendEmail()).resolves.toBe(false);
        expect(redeem.recoveryError.value).not.toBe('');
        expect(redeem.recovering.value).toBeNull();

        issueMock.mockClear();
        const notIssued = createRedeem(mappedSubmission());

        await expect(notIssued.reissueCode()).resolves.toBe(false);
        expect(issueMock).not.toHaveBeenCalled();
    });

    it('sync katalog DU memuat ulang prop duCatalog setelah sukses', async () => {
        syncCoursesMock.mockResolvedValue({ courses: CATALOG, syncedAt: '2026-01-01T00:00:00Z' });
        const redeem = createRedeem(submission());

        await expect(redeem.syncCatalog()).resolves.toBe(true);

        expect(syncCoursesMock).toHaveBeenCalledTimes(1);
        expect(reloadMock).toHaveBeenCalledWith({ only: ['duCatalog'] });
        expect(redeem.catalogSyncError.value).toBe('');
        expect(redeem.syncingCatalog.value).toBe(false);
    });

    it('sync katalog DU menampilkan error lokal tanpa memuat ulang saat gagal', async () => {
        syncCoursesMock.mockRejectedValue(new HttpRequestError({ kind: 'server', status: 500, message: 'boom' }));
        const redeem = createRedeem(submission());

        await expect(redeem.syncCatalog()).resolves.toBe(false);

        expect(redeem.catalogSyncError.value).toBe('Gagal memuat katalog kelas DU. Coba lagi.');
        expect(reloadMock).not.toHaveBeenCalled();
        expect(redeem.syncingCatalog.value).toBe(false);
    });

    it('mengabaikan sync katalog DU kedua saat request pertama masih berjalan', async () => {
        let resolveSync: (result: { courses: IDuCourseOption[]; syncedAt: string }) => void = () => undefined;
        syncCoursesMock.mockReturnValue(
            new Promise((resolve) => {
                resolveSync = resolve;
            })
        );
        const redeem = createRedeem(submission());

        const first = redeem.syncCatalog();
        const second = redeem.syncCatalog();

        await expect(second).resolves.toBe(false);
        expect(syncCoursesMock).toHaveBeenCalledTimes(1);

        resolveSync({ courses: CATALOG, syncedAt: '2026-01-01T00:00:00Z' });
        await expect(first).resolves.toBe(true);
        expect(reloadMock).toHaveBeenCalledTimes(1);
    });

    it('memuat ulang state lokal saat jawaban yang dipilih berganti', async () => {
        const selected = ref<IDuSubmission | null>(submission());
        const redeem = useDuAnswerRedeem({
            mappingUrl: MAPPING_URL,
            issueUrl: ISSUE_URL,
            submission: () => selected.value,
            answerId: 'answer-1',
            reviewAccepted: true,
            catalog: CATALOG,
        });

        saveMock.mockResolvedValue({ course_uid: 'course-ai', course_title: 'AI Engineer' });
        await redeem.saveMapping('course-ai');

        expect(redeem.mapping.value).not.toBeNull();

        selected.value = submission({ class_value: 'DevOps' });
        await nextTick();

        expect(redeem.mapping.value).toBeNull();
        expect(redeem.canIssue.value).toBe(false);
    });
});
