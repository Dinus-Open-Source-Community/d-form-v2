import type { Ref } from 'vue';
import { isHttpRequestError, toHttpErrorKind } from '@/adapters/http/httpError';
import { submitScan } from '@/adapters/http/scan';
import { describeRequestError } from '@/lib/requestErrorText';
import { extractQrCandidate, type IScanResult } from '@/lib/qrScanUi';
import { humanizeErrorMessage, parseApiErrorMessage } from '@/lib/error-message';
import { formatGlobalScanEventTitle, scanKindFromApiType } from '@/lib/qrScanFormat';
import type { IScanEnvelope, IScanErrorBody } from '@/types/scan';

export interface IScanSubmissionTarget {
    id: string;
    kind: 'event' | 'oprec';
}

export interface IScanSubmissionOptions {
    storeUrl: string;
    deskId: string;
    selectedTarget: Readonly<Ref<IScanSubmissionTarget | null>>;
    latestResult: Ref<IScanResult | null>;
    registrationCodeInput: Ref<string>;
    scanBusy: Ref<boolean>;
    onResult: (result: IScanResult) => void;
    showErrorToast: (message: string, options?: { description?: string }) => void;
    showSuccessToast: (message: string, options?: { description?: string }) => void;
    showWarningToast: (message: string, options?: { description?: string }) => void;
}

export function useScanSubmission(
    options: IScanSubmissionOptions
): (raw: string, source: 'camera' | 'manual') => Promise<void> {
    const submit = async (raw: string, source: 'camera' | 'manual'): Promise<void> => {
        if (options.scanBusy.value) return;
        const trimmed = raw.trim();
        if (trimmed.length === 0) {
            options.showErrorToast('Isi kode registrasi terlebih dahulu.');
            return;
        }
        options.scanBusy.value = true;
        const rawDisplay = extractQrCandidate(trimmed);
        const pushFailureResult = (): void =>
            options.onResult({
                name: 'Kesalahan jaringan',
                email: '-',
                status: 'invalid',
                source,
                rawCode: rawDisplay,
                eventKind: options.latestResult.value?.eventKind ?? 'event',
                eventTitle: options.latestResult.value?.eventTitle ?? '-',
                queueNumber: null,
            });
        const showFailureToast = (message: string | null): void => {
            if (message === null) {
                options.showErrorToast('Permintaan gagal', { description: 'Coba lagi dalam beberapa saat.' });
                return;
            }
            options.showErrorToast(message);
        };
        const pushGenericScanError = (error: unknown): void => {
            if (isHttpRequestError(error) && error.kind === 'aborted') return;
            pushFailureResult();
            showFailureToast(
                isHttpRequestError(error)
                    ? describeRequestError({
                          kind: error.kind,
                          status: error.status,
                          retryAfterSeconds: error.retryAfterSeconds,
                      })
                    : null
            );
        };
        const handleScanStatusError = (
            status: number,
            body: IScanErrorBody | null,
            retryAfterSeconds: number | null
        ): boolean => {
            if (status === 409) {
                const kind = scanKindFromApiType(body?.type);
                const title = formatGlobalScanEventTitle(kind, body?.eventTitle ?? '');
                const fallbackQueue =
                    options.latestResult.value?.eventKind === kind ? options.latestResult.value.queueNumber : null;
                const msg = humanizeErrorMessage(body?.message ?? 'Peserta sudah pernah scan.');
                if (kind === 'oprec') {
                    const identifier = body?.attendee?.registration_number?.trim() || '-';
                    const name = body?.attendee?.name?.trim() || 'Sudah terdaftar hadir';
                    options.onResult({
                        name,
                        email: identifier,
                        status: 'already',
                        source,
                        rawCode: rawDisplay,
                        eventKind: kind,
                        eventTitle: title,
                        queueNumber: body?.attendee?.queue_number ?? fallbackQueue,
                    });
                    options.showWarningToast(msg, { description: `${name} · ${identifier}` });
                } else {
                    const email = body?.attendee?.email?.trim() || '-';
                    const name = body?.attendee?.name?.trim() || 'Sudah terdaftar hadir';
                    options.onResult({
                        name,
                        email,
                        status: 'already',
                        source,
                        rawCode: rawDisplay,
                        eventKind: kind,
                        eventTitle: title,
                        queueNumber: null,
                    });
                    options.showWarningToast(msg, { description: email !== '-' ? `${name} · ${email}` : name });
                }
                return true;
            }
            if (status === 422) {
                options.onResult({
                    name: 'Tidak dapat diproses',
                    email: '-',
                    status: 'invalid',
                    source,
                    rawCode: rawDisplay,
                    eventKind: options.latestResult.value?.eventKind ?? 'event',
                    eventTitle: options.latestResult.value?.eventTitle ?? '-',
                    queueNumber: null,
                });
                options.showErrorToast(parseApiErrorMessage(body, 'Data tidak valid.', status));
                return true;
            }
            if (status === 429) {
                options.onResult({
                    name: 'Terlalu banyak scan',
                    email: '-',
                    status: 'invalid',
                    source,
                    rawCode: rawDisplay,
                    eventKind: options.latestResult.value?.eventKind ?? 'event',
                    eventTitle: options.latestResult.value?.eventTitle ?? '-',
                    queueNumber: null,
                });
                options.showErrorToast(
                    describeRequestError({ kind: 'rate_limited', status, retryAfterSeconds }) ?? 'Terlalu banyak scan',
                    { description: 'Tunggu sebentar sebelum memindai lagi.' }
                );
                return true;
            }
            return false;
        };
        try {
            const selected = options.selectedTarget.value;
            const payload: Record<string, string> = { raw: trimmed, desk: options.deskId };
            if (selected?.kind === 'oprec') payload.recruitment_session_id = selected.id;
            const result = await submitScan({ url: options.storeUrl, payload });
            if (result.status >= 200 && result.status < 300) {
                if (result.envelope === null) {
                    options.onResult({
                        name: 'Respons tidak valid',
                        email: '-',
                        status: 'invalid',
                        source,
                        rawCode: rawDisplay,
                        eventKind: options.latestResult.value?.eventKind ?? 'event',
                        eventTitle: options.latestResult.value?.eventTitle ?? '-',
                        queueNumber: null,
                    });
                    options.showErrorToast('Respons server tidak sesuai kontrak.', {
                        description: 'Coba lagi atau hubungi admin.',
                    });
                    return;
                }
                const data: IScanEnvelope = result.envelope;
                const kind = scanKindFromApiType(data.type);
                const title = formatGlobalScanEventTitle(kind, data.eventTitle ?? '');
                const identifier =
                    kind === 'oprec'
                        ? data.attendee.registration_number?.trim() || '-'
                        : data.attendee.email?.trim() || '-';
                const resultEntry: IScanResult = {
                    name: data.attendee.name?.trim() || 'Tanpa nama',
                    email: identifier,
                    status: 'success',
                    source,
                    rawCode: rawDisplay,
                    eventKind: kind,
                    eventTitle: title,
                    queueNumber: null,
                };
                options.showSuccessToast(resultEntry.name === 'Tanpa nama' ? 'Check-in berhasil.' : resultEntry.name, {
                    description:
                        kind === 'oprec'
                            ? `${data.attendee.interview_status_label ?? 'ruang tunggu'} — arahkan ke ruang tunggu`
                            : 'Boleh masuk — tiket dikirim ke email',
                });
                options.onResult(resultEntry);
                if (source === 'manual') options.registrationCodeInput.value = '';
                return;
            }
            if (!handleScanStatusError(result.status, result.errorBody, result.retryAfterSeconds)) {
                pushFailureResult();
                showFailureToast(
                    describeRequestError({
                        kind: toHttpErrorKind(result.status),
                        status: result.status,
                        retryAfterSeconds: result.retryAfterSeconds,
                    })
                );
            }
        } catch (error: unknown) {
            pushGenericScanError(error);
        } finally {
            options.scanBusy.value = false;
        }
    };
    return submit;
}
