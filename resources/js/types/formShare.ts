import type { ComputedRef, Ref } from 'vue';

export interface IFormSharePayload {
    participant_path: string;
    admin_path: string;
    short_path: string | null;
    barcode_path: string;
    update_url: string;
    alias: string | null;
    can_share_participant: boolean;
}

export type TFormShareCopyStatus = 'idle' | 'participant' | 'admin' | 'short' | 'error';
export type TFormShareMutationStatus = 'idle' | 'saving' | 'success' | 'error';
export type TFormShareBarcodeStatus = 'idle' | 'loading' | 'loaded' | 'error';

export interface IFormShareActions {
    payload: Ref<IFormSharePayload>;
    alias: Ref<string>;
    setAlias: (value: string) => void;
    participantUrl: ComputedRef<string>;
    adminUrl: ComputedRef<string>;
    shortUrl: ComputedRef<string>;
    barcodeUrl: ComputedRef<string>;
    canShareParticipant: ComputedRef<boolean>;
    draftExplanation: ComputedRef<string>;
    copyStatus: Ref<TFormShareCopyStatus>;
    mutationStatus: Ref<TFormShareMutationStatus>;
    barcodeVisible: Ref<boolean>;
    barcodeStatus: Ref<TFormShareBarcodeStatus>;
    barcodeAttempt: Ref<number>;
    errorMessage: Ref<string>;
    aliasErrorMessage: Ref<string>;
    successMessage: ComputedRef<string>;
    isSaving: ComputedRef<boolean>;
    copyParticipantLink: () => Promise<boolean>;
    copyAdminLink: () => Promise<boolean>;
    copyShortLink: () => Promise<boolean>;
    saveAlias: () => Promise<boolean>;
    showBarcode: () => void;
    hideBarcode: () => void;
    markBarcodeLoaded: () => void;
    markBarcodeFailed: () => void;
    retryBarcode: () => void;
}
