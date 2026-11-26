import { computed, ref, type ComputedRef, type Ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { update as updateReferralSettingsRoute } from '@/routes/dashboard/events/forms/referral';
import {
    destroy as destroyReferralCodeRoute,
    store as storeReferralCodeRoute,
    update as updateReferralCodeRoute,
} from '@/routes/dashboard/events/forms/referral-codes';
import type { IReferralCode, IReferralCodeInput, IReferralPayload } from '@/types/referral';

/** Prop Inertia yang dimuat ulang setelah setiap mutasi referral. */
const REFERRAL_RELOAD_KEYS = ['referral', 'submissions', 'submissionsCount', 'submissionsPagination'];

export interface IUseFormReferralCodesOptions {
    eventId: string;
    formId: string;
    payload: () => IReferralPayload;
    /** Pathname halaman form tanpa query, dipakai navigasi urutan Jawaban. */
    currentPath: () => string;
}

export interface IUseFormReferralCodes {
    codes: ComputedRef<IReferralCode[]>;
    enabled: ComputedRef<boolean>;
    processing: Ref<boolean>;
    fieldErrors: Ref<Record<string, string>>;
    errorMessage: Ref<string>;
    createCode: (input: IReferralCodeInput, onSuccess?: () => void) => void;
    updateCode: (id: string, input: Partial<IReferralCodeInput>, onSuccess?: () => void) => void;
    deleteCode: (id: string, onSuccess?: () => void) => void;
    toggleEnabled: (value: boolean) => void;
    clearErrors: () => void;
}

/** Semua HTTP kode referral ada di sini; komponen hanya memanggil method dan membaca state. */
export function useFormReferralCodes(options: IUseFormReferralCodesOptions): IUseFormReferralCodes {
    const processing = ref(false);
    const fieldErrors = ref<Record<string, string>>({});
    const errorMessage = ref('');

    const codes = computed<IReferralCode[]>(() => options.payload().codes);
    const enabled = computed<boolean>(() => options.payload().enabled);

    function clearErrors(): void {
        fieldErrors.value = {};
        errorMessage.value = '';
    }

    function onError(errors: Record<string, string>): void {
        fieldErrors.value = errors;
        errorMessage.value = '';
    }

    /** `false` menahan modal error Inertia; pesan ditampilkan di kartu referral. */
    function onHttpException(response: { status: number }): boolean {
        errorMessage.value =
            response.status === 409
                ? 'Kode yang sudah dipakai tidak bisa dihapus. Nonaktifkan saja.'
                : 'Permintaan referral gagal. Coba lagi.';

        return false;
    }

    function routeArgs(): { event: string; form: string } {
        return { event: options.eventId, form: options.formId };
    }

    function createCode(input: IReferralCodeInput, onSuccess?: () => void): void {
        clearErrors();
        processing.value = true;

        router.post(
            storeReferralCodeRoute.url(routeArgs()),
            { ...input },
            {
                preserveScroll: true,
                preserveState: true,
                only: REFERRAL_RELOAD_KEYS,
                onError,
                onHttpException,
                onSuccess,
                onFinish: () => {
                    processing.value = false;
                },
            }
        );
    }

    function updateCode(id: string, input: Partial<IReferralCodeInput>, onSuccess?: () => void): void {
        clearErrors();
        processing.value = true;

        router.patch(
            updateReferralCodeRoute.url({ ...routeArgs(), referralCode: id }),
            { ...input },
            {
                preserveScroll: true,
                preserveState: true,
                only: REFERRAL_RELOAD_KEYS,
                onError,
                onHttpException,
                onSuccess,
                onFinish: () => {
                    processing.value = false;
                },
            }
        );
    }

    function deleteCode(id: string, onSuccess?: () => void): void {
        clearErrors();
        processing.value = true;

        router.delete(destroyReferralCodeRoute.url({ ...routeArgs(), referralCode: id }), {
            preserveScroll: true,
            preserveState: true,
            only: REFERRAL_RELOAD_KEYS,
            onError,
            onHttpException,
            onSuccess,
            onFinish: () => {
                processing.value = false;
            },
        });
    }

    function toggleEnabled(value: boolean): void {
        clearErrors();
        processing.value = true;

        router.patch(
            updateReferralSettingsRoute.url(routeArgs()),
            { enabled: value },
            {
                preserveScroll: true,
                preserveState: true,
                only: REFERRAL_RELOAD_KEYS,
                onError,
                onHttpException,
                onFinish: () => {
                    processing.value = false;
                },
            }
        );
    }

    return {
        codes,
        enabled,
        processing,
        fieldErrors,
        errorMessage,
        createCode,
        updateCode,
        deleteCode,
        toggleEnabled,
        clearErrors,
    };
}
