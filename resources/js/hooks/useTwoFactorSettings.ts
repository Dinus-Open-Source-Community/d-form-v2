import { computed, ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { isRecord } from '@/lib/isRecord';
import { humanizeErrorMessage } from '@/lib/error-message';
import type { TValidationErrors } from '@/lib/error-message';
import { routes } from '@/lib/routes';
import { TWO_FACTOR_PIN_LENGTH } from '@/hooks/useTwoFactorChallenge';

/** Dialog yang bisa dibuka dari kartu PIN. */
export type TTwoFactorPinDialogMode = 'enable' | 'change' | 'disable';

/** Status PIN dari `TwoFactorPinService::status()`; `attemptsRemaining` dipakai saat lock. */
export interface ITwoFactorPinStatus {
    enabled: boolean;
    attemptsRemaining: number;
    lockedUntil: string | null;
}

export interface IUseTwoFactorSettingsOptions {
    /**
     * Props halaman profil; `two_factor` dikirim `ProfileController::show`. Bentuk yang
     * tidak dikenal dibaca dengan guard dan dianggap "belum aktif".
     */
    pageProps: unknown;
    /** True bila akun punya kata sandi lokal; akun OAuth-only tidak ditanya kata sandi. */
    hasLocalPassword: boolean;
}

export interface ITwoFactorSettingsState {
    status: ComputedRef<ITwoFactorPinStatus>;
    mode: Ref<TTwoFactorPinDialogMode | null>;
    isOpen: ComputedRef<boolean>;
    isSubmitting: Ref<boolean>;
    currentPassword: Ref<string>;
    currentPin: Ref<string>;
    newPin: Ref<string>;
    newPinConfirmation: Ref<string>;
    requiresCurrentPassword: ComputedRef<boolean>;
    requiresCurrentPin: ComputedRef<boolean>;
    requiresNewPin: ComputedRef<boolean>;
    /** Status sakelar: benar hanya bila server (atau aksi sesi ini) menyatakan PIN aktif. */
    checked: ComputedRef<boolean>;
    canSubmit: ComputedRef<boolean>;
    /** Error per field, sudah diterjemahkan; kunci mengikuti nama field server. */
    errors: ComputedRef<Record<string, string>>;
    /** Judul dialog sesuai aksi yang dipilih. */
    dialogTitle: ComputedRef<string>;
    /** Label tombol simpan di dialog. */
    submitLabel: ComputedRef<string>;
    open: (mode: TTwoFactorPinDialogMode) => void;
    /** Niat pengguna pada sakelar: buka dialog yang sesuai, tanpa mengubah status lebih dulu. */
    requestToggle: (next: boolean) => void;
    close: () => void;
    submit: () => void;
}

/** Status aman saat server tidak mengirim `two_factor`: PIN dianggap belum aktif. */
const INACTIVE_PIN_STATUS: ITwoFactorPinStatus = {
    enabled: false,
    attemptsRemaining: 0,
    lockedUntil: null,
};

const DIALOG_TITLES: Readonly<Record<TTwoFactorPinDialogMode, string>> = {
    enable: 'Aktifkan PIN',
    change: 'Ubah PIN',
    disable: 'Nonaktifkan PIN',
};

const SUBMIT_LABELS: Readonly<Record<TTwoFactorPinDialogMode, string>> = {
    enable: 'Simpan PIN',
    change: 'Simpan PIN baru',
    disable: 'Nonaktifkan PIN',
};

/** Baca status PIN dari props halaman; bentuk yang tidak dikenal dianggap belum aktif. */
function readPinStatus(pageProps: unknown): ITwoFactorPinStatus {
    if (!isRecord(pageProps) || !isRecord(pageProps.two_factor)) return INACTIVE_PIN_STATUS;

    const { enabled, attemptsRemaining, lockedUntil } = pageProps.two_factor;

    return {
        enabled: enabled === true,
        attemptsRemaining: typeof attemptsRemaining === 'number' ? attemptsRemaining : 0,
        lockedUntil: typeof lockedUntil === 'string' ? lockedUntil : null,
    };
}

/**
 * Aksi kartu PIN di bagian Keamanan profil: buka dialog, validasi input, dan kirim
 * aksi ke server. Dialog tetap terbuka saat gagal agar pesan server terbaca, lalu
 * ditutup dan status lokal diperbarui saat berhasil.
 */
export function useTwoFactorSettings(options: IUseTwoFactorSettingsOptions): ITwoFactorSettingsState {
    const page = usePage();

    const mode: Ref<TTwoFactorPinDialogMode | null> = ref(null);
    const isSubmitting: Ref<boolean> = ref(false);
    const currentPassword: Ref<string> = ref('');
    const currentPin: Ref<string> = ref('');
    const newPin: Ref<string> = ref('');
    const newPinConfirmation: Ref<string> = ref('');
    const submitErrors: Ref<TValidationErrors> = ref({});
    /** Hasil aksi pada sesi ini; menimpa props sampai halaman dimuat ulang. */
    const enabledAfterAction: Ref<boolean | null> = ref(null);

    const serverStatus = computed<ITwoFactorPinStatus>(() => readPinStatus(options.pageProps));

    const status = computed<ITwoFactorPinStatus>(() => ({
        ...serverStatus.value,
        enabled: enabledAfterAction.value ?? serverStatus.value.enabled,
    }));

    const isOpen = computed<boolean>(() => mode.value !== null);

    /** Sakelar mengikuti status server; aksi yang gagal tidak meninggalkan state palsu. */
    const checked = computed<boolean>(() => status.value.enabled);

    const requiresCurrentPassword = computed<boolean>(() => options.hasLocalPassword);

    const requiresCurrentPin = computed<boolean>(() => mode.value === 'change' || mode.value === 'disable');

    const requiresNewPin = computed<boolean>(() => mode.value === 'enable' || mode.value === 'change');

    const dialogTitle = computed<string>(() => (mode.value === null ? '' : DIALOG_TITLES[mode.value]));

    const submitLabel = computed<string>(() => (mode.value === null ? '' : SUBMIT_LABELS[mode.value]));

    const errors = computed<Record<string, string>>(() => {
        const pageErrors = page.props.errors;
        const messages: Record<string, string> = {
            current_password:
                humanizeErrorMessage(submitErrors.value.current_password) ||
                humanizeErrorMessage(pageErrors.current_password),
            current_pin:
                humanizeErrorMessage(submitErrors.value.current_pin) || humanizeErrorMessage(pageErrors.current_pin),
            pin: humanizeErrorMessage(submitErrors.value.pin) || humanizeErrorMessage(pageErrors.pin),
            pin_confirmation: '',
        };

        if (requiresNewPin.value && newPinConfirmation.value !== '' && newPinConfirmation.value !== newPin.value) {
            messages.pin_confirmation = 'Konfirmasi PIN belum sama.';
        }

        return messages;
    });

    const canSubmit = computed<boolean>(() => {
        if (mode.value === null || isSubmitting.value) return false;
        if (requiresCurrentPassword.value && currentPassword.value === '') return false;
        if (requiresCurrentPin.value && currentPin.value.length !== TWO_FACTOR_PIN_LENGTH) return false;
        if (requiresNewPin.value) {
            return newPin.value.length === TWO_FACTOR_PIN_LENGTH && newPinConfirmation.value === newPin.value;
        }

        return true;
    });

    function clearFields(): void {
        currentPassword.value = '';
        currentPin.value = '';
        newPin.value = '';
        newPinConfirmation.value = '';
        submitErrors.value = {};
    }

    function open(next: TTwoFactorPinDialogMode): void {
        if (isSubmitting.value) return;

        clearFields();
        mode.value = next;
    }

    function close(): void {
        if (isSubmitting.value) return;

        mode.value = null;
        clearFields();
    }

    /**
     * Sakelar tidak pernah menyalak sendiri: niat menyalakan membuka dialog buat PIN,
     * niat mematikan membuka dialog nonaktifkan. Batal berarti status tetap seperti server.
     */
    function requestToggle(next: boolean): void {
        if (isSubmitting.value) return;

        if (next && !checked.value) {
            open('enable');

            return;
        }

        if (!next && checked.value) open('disable');
    }

    /** Isi body sesuai aksi: kata sandi hanya ikut bila akun memang punya kata sandi lokal. */
    function buildPayload(): Record<string, string> {
        const payload: Record<string, string> = {};

        if (requiresNewPin.value) payload.pin = newPin.value;
        if (requiresCurrentPin.value) payload.current_pin = currentPin.value;
        if (requiresCurrentPassword.value) payload.current_password = currentPassword.value;

        return payload;
    }

    function submit(): void {
        const action = mode.value;

        if (action === null || !canSubmit.value) return;

        const payload = buildPayload();

        submitErrors.value = {};
        // Ditandai sejak klik agar dialog tidak menutup sendiri sebelum server menjawab.
        isSubmitting.value = true;

        const callbacks = {
            preserveScroll: true,
            onStart: (): void => {
                isSubmitting.value = true;
            },
            onFinish: (): void => {
                isSubmitting.value = false;
            },
            onSuccess: (): void => {
                enabledAfterAction.value = action === 'enable' ? true : action === 'disable' ? false : null;
                mode.value = null;
                clearFields();
            },
            onError: (validationErrors: TValidationErrors): void => {
                submitErrors.value = validationErrors;
                // Sakelar kembali ke status server, bukan ke niat pengguna.
                enabledAfterAction.value = null;
            },
        };

        if (action === 'enable') {
            router.post(routes.dashboard.profileTwoFactorEnable, payload, callbacks);

            return;
        }

        if (action === 'change') {
            router.put(routes.dashboard.profileTwoFactorChange, payload, callbacks);

            return;
        }

        router.delete(routes.dashboard.profileTwoFactorDisable, { data: payload, ...callbacks });
    }

    return {
        status,
        mode,
        isOpen,
        isSubmitting,
        currentPassword,
        currentPin,
        newPin,
        newPinConfirmation,
        requiresCurrentPassword,
        requiresCurrentPin,
        requiresNewPin,
        checked,
        canSubmit,
        errors,
        dialogTitle,
        submitLabel,
        open,
        requestToggle,
        close,
        submit,
    };
}
