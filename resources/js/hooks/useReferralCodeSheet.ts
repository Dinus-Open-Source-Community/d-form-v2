import { computed, reactive, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import {
    buildReferralCodeInput,
    createReferralCodeForm,
    formatReferralCodePreview,
    referralCodeFormFromCode,
} from '@/lib/formReferral';
import type { IReferralCode, IReferralCodeForm, IReferralCodeInput } from '@/types/referral';

export interface IReferralCodeSheetOptions {
    create: (input: IReferralCodeInput, onSuccess: () => void) => void;
    update: (id: string, input: Partial<IReferralCodeInput>, onSuccess: () => void) => void;
}

export interface IReferralCodeSheet {
    isOpen: Ref<boolean>;
    editingCode: Ref<IReferralCode | null>;
    form: IReferralCodeForm;
    title: ComputedRef<string>;
    description: ComputedRef<string>;
    codePreview: ComputedRef<string>;
    identityLocked: ComputedRef<boolean>;
    openCreate: () => void;
    openEdit: (code: IReferralCode) => void;
    close: () => void;
    submit: () => void;
}

/**
 * Drawer create/edit kode referral: satu form untuk dua mode, payload dibangun
 * `buildReferralCodeInput`, dan drawer menutup sendiri setelah simpan sukses.
 */
export function useReferralCodeSheet(options: IReferralCodeSheetOptions): IReferralCodeSheet {
    const isOpen = ref<boolean>(false);
    const editingCode = ref<IReferralCode | null>(null);
    const form = reactive<IReferralCodeForm>(createReferralCodeForm());

    const identityLocked = computed<boolean>(() => editingCode.value !== null && !editingCode.value.can_edit_identity);
    const title = computed<string>(() => (editingCode.value === null ? 'Kode referral baru' : 'Ubah kode referral'));
    const description = computed<string>(() =>
        editingCode.value === null
            ? 'Atur siapa yang memberi kode dan bagaimana kode dibuat.'
            : `Ubah pengaturan kode ${editingCode.value.code}.`
    );
    const codePreview = computed<string>(() => formatReferralCodePreview(form));

    /** Salin nilai form sekaligus; nilai lama tidak boleh tertinggal antar mode. */
    function applyForm(values: IReferralCodeForm): void {
        Object.assign(form, values);
    }

    function openCreate(): void {
        editingCode.value = null;
        applyForm(createReferralCodeForm());
        isOpen.value = true;
    }

    function openEdit(code: IReferralCode): void {
        editingCode.value = code;
        applyForm(referralCodeFormFromCode(code));
        isOpen.value = true;
    }

    function close(): void {
        isOpen.value = false;
        editingCode.value = null;
    }

    function submit(): void {
        const payload = buildReferralCodeInput(form);

        if (editingCode.value === null) {
            options.create(payload, close);

            return;
        }

        options.update(editingCode.value.id, payload, close);
    }

    return {
        isOpen,
        editingCode,
        form,
        title,
        description,
        codePreview,
        identityLocked,
        openCreate,
        openEdit,
        close,
        submit,
    };
}
