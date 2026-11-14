import { computed, ref, watch } from 'vue';
import { copyTextToClipboard } from '@/adapters/browser/clipboard';
import { readWindow } from '@/adapters/browser/environment';
import { updateFormShareLink } from '@/adapters/http/formShare';
import type {
    IFormShareActions,
    IFormSharePayload,
    TFormShareBarcodeStatus,
    TFormShareCopyStatus,
    TFormShareMutationStatus,
} from '@/types/formShare';

const DRAFT_EXPLANATION = 'Terbitkan formulir terlebih dahulu untuk membagikan link peserta.';

function toAbsoluteUrl(path: string): string {
    const browserWindow = readWindow();
    if (browserWindow === null) return path;

    return new URL(path, browserWindow.location.origin).toString();
}

function readErrorMessage(error: unknown): string {
    return error instanceof Error ? error.message : 'Alias tidak dapat disimpan. Coba lagi.';
}

export function useFormShareActions(initialPayload: IFormSharePayload): IFormShareActions {
    const payload = ref<IFormSharePayload>(initialPayload);
    const alias = ref(initialPayload.alias ?? '');
    const copyStatus = ref<TFormShareCopyStatus>('idle');
    const mutationStatus = ref<TFormShareMutationStatus>('idle');
    const barcodeVisible = ref(false);
    const barcodeStatus = ref<TFormShareBarcodeStatus>('idle');
    const barcodeAttempt = ref(0);
    const errorMessage = ref('');
    const aliasErrorMessage = ref('');
    const canShareParticipant = computed<boolean>(() => payload.value.can_share_participant);
    const participantUrl = computed<string>(() => toAbsoluteUrl(payload.value.participant_path));
    const adminUrl = computed<string>(() => toAbsoluteUrl(payload.value.admin_path));
    const shortUrl = computed<string>(() =>
        payload.value.short_path === null ? '' : toAbsoluteUrl(payload.value.short_path)
    );
    const barcodeUrl = computed<string>(() => toAbsoluteUrl(payload.value.barcode_path));
    const draftExplanation = computed<string>(() => (canShareParticipant.value ? '' : DRAFT_EXPLANATION));
    const isSaving = computed<boolean>(() => mutationStatus.value === 'saving');
    const successMessage = computed<string>(() =>
        mutationStatus.value === 'success' ? 'Alias berhasil disimpan.' : ''
    );

    watch(canShareParticipant, (canShare: boolean): void => {
        if (!canShare) hideBarcode();
    });

    async function copyLink(path: string, status: Exclude<TFormShareCopyStatus, 'idle' | 'error'>): Promise<boolean> {
        if (status !== 'admin' && !canShareParticipant.value) return false;
        if (path === '') return false;

        errorMessage.value = '';
        const copied = await copyTextToClipboard(path);
        copyStatus.value = copied ? status : 'error';
        if (!copied) errorMessage.value = 'Link tidak dapat disalin. Periksa izin clipboard browser.';

        return copied;
    }

    async function saveAlias(): Promise<boolean> {
        if (!canShareParticipant.value || isSaving.value) return false;

        errorMessage.value = '';
        aliasErrorMessage.value = '';
        copyStatus.value = 'idle';
        mutationStatus.value = 'saving';

        try {
            const refreshed = await updateFormShareLink({
                url: payload.value.update_url,
                alias: alias.value.trim() === '' ? null : alias.value.trim(),
            });
            payload.value = refreshed;
            alias.value = refreshed.alias ?? '';
            mutationStatus.value = 'success';

            return true;
        } catch (error) {
            const message = readErrorMessage(error);
            aliasErrorMessage.value = message;
            mutationStatus.value = 'error';

            return false;
        }
    }

    function showBarcode(): void {
        if (!canShareParticipant.value) return;

        barcodeVisible.value = true;
        barcodeStatus.value = 'loading';
    }

    function hideBarcode(): void {
        barcodeVisible.value = false;
        barcodeStatus.value = 'idle';
    }

    function markBarcodeLoaded(): void {
        if (barcodeVisible.value) barcodeStatus.value = 'loaded';
    }

    function markBarcodeFailed(): void {
        if (barcodeVisible.value) barcodeStatus.value = 'error';
    }

    function retryBarcode(): void {
        if (!canShareParticipant.value || !barcodeVisible.value) return;

        barcodeAttempt.value += 1;
        barcodeStatus.value = 'loading';
    }

    function setAlias(value: string): void {
        alias.value = value;
    }

    return {
        payload,
        alias,
        setAlias,
        participantUrl,
        adminUrl,
        shortUrl,
        barcodeUrl,
        canShareParticipant,
        draftExplanation,
        copyStatus,
        mutationStatus,
        barcodeVisible,
        barcodeStatus,
        barcodeAttempt,
        errorMessage,
        aliasErrorMessage,
        successMessage,
        isSaving,
        copyParticipantLink: () => copyLink(participantUrl.value, 'participant'),
        copyAdminLink: () => copyLink(adminUrl.value, 'admin'),
        copyShortLink: () => copyLink(shortUrl.value, 'short'),
        saveAlias,
        showBarcode,
        hideBarcode,
        markBarcodeLoaded,
        markBarcodeFailed,
        retryBarcode,
    };
}
