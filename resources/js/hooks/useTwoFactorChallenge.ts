import { computed, ref, toValue } from 'vue';
import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { requestJsonWithStatus } from '@/adapters/http/httpClient';
import { readPublicMessage } from '@/adapters/http/httpError';
import { isRecord } from '@/lib/isRecord';
import { humanizeErrorMessage } from '@/lib/error-message';
import { verify as challengeVerifyRoute } from '@/routes/two-factor';
import {
    confirm as recoveryConfirmRoute,
    pin as recoveryPinRoute,
    send as recoverySendRoute,
} from '@/routes/two-factor/recovery';

/** Panjang PIN; sama dengan validasi server (`digits:6` dari `config('otp.length')`). */
export const TWO_FACTOR_PIN_LENGTH = 6;

/** Langkah pemulihan: minta kode, masukkan kode, lalu buat PIN baru. */
export type TTwoFactorRecoveryStep = 'request' | 'code' | 'pin';

/** Status dari `TwoFactorPinService::status()` lewat `TwoFactorChallengeController::show`. */
export interface ITwoFactorChallengeStatus {
    enabled: boolean;
    attemptsRemaining: number;
    lockedUntil: string | null;
}

/** Status aman saat hook dipakai hanya untuk langkah pemulihan. */
const DEFAULT_CHALLENGE_STATUS: ITwoFactorChallengeStatus = {
    enabled: false,
    attemptsRemaining: 0,
    lockedUntil: null,
};

export interface IUseTwoFactorChallengeOptions {
    /**
     * Status milik layar tantangan. Kirim getter (`() => props.status`) dari halaman agar
     * status baru dari server setelah PIN ditolak ikut terbaca; nilai biasa tetap diterima
     * untuk pemakai yang statusnya tidak berubah.
     */
    status?: MaybeRefOrGetter<ITwoFactorChallengeStatus>;
}

export interface ITwoFactorChallengeState {
    /** PIN gabungan 6 digit; dipakai `v-model` kotak PIN. */
    pin: Ref<string>;
    /** Pesan penolakan PIN dari server (`errors.pin`). */
    pinError: ComputedRef<string | undefined>;
    /** Pesan umum saat permintaan tidak sampai ke server. */
    notice: Ref<string | undefined>;
    isSubmitting: Ref<boolean>;
    canSubmit: ComputedRef<boolean>;
    attemptsRemaining: ComputedRef<number>;
    lockedUntil: ComputedRef<string | null>;
    isLocked: ComputedRef<boolean>;
    submit: () => void;
    /** Langkah pemulihan yang sedang tampil. */
    recoveryStep: Ref<TTwoFactorRecoveryStep>;
    recoveryCode: Ref<string>;
    recoveryNotice: Ref<string | undefined>;
    isSendingRecovery: Ref<boolean>;
    isConfirmingRecovery: Ref<boolean>;
    canConfirmRecovery: ComputedRef<boolean>;
    newPin: Ref<string>;
    newPinConfirmation: Ref<string>;
    newPinError: ComputedRef<string | undefined>;
    isSavingNewPin: Ref<boolean>;
    canSubmitNewPin: ComputedRef<boolean>;
    sendRecovery: () => Promise<void>;
    confirmRecovery: () => Promise<void>;
    submitNewPin: () => void;
    /** Kembali ke langkah minta kode tanpa mengubah PIN apa pun. */
    restartRecovery: () => void;
}

/** Deadline ISO8601 yang masih di masa depan; nilai rusak/kosong dianggap tidak ada. */
function isFutureDeadline(value: string | null): boolean {
    if (value === null) return false;

    const parsed = Date.parse(value);

    return !Number.isNaN(parsed) && parsed > Date.now();
}

/**
 * Satu pemilik state layar faktor kedua: verifikasi PIN dan pemulihan lewat OTP email.
 * Verifikasi PIN dan penyimpanan PIN baru memakai kunjungan Inertia karena server
 * menjawab dengan redirect; permintaan dan konfirmasi OTP memakai jalur JSON agar
 * langkah pemulihan tidak hilang saat props dimuat ulang.
 */
export function useTwoFactorChallenge(options: IUseTwoFactorChallengeOptions = {}): ITwoFactorChallengeState {
    const page = usePage();
    const challengeStatus = computed<ITwoFactorChallengeStatus>(() =>
        options.status === undefined ? DEFAULT_CHALLENGE_STATUS : toValue(options.status)
    );

    const pin: Ref<string> = ref('');
    const notice: Ref<string | undefined> = ref(undefined);
    const isSubmitting: Ref<boolean> = ref(false);

    const recoveryStep: Ref<TTwoFactorRecoveryStep> = ref('request');
    const recoveryCode: Ref<string> = ref('');
    const recoveryNotice: Ref<string | undefined> = ref(undefined);
    const isSendingRecovery: Ref<boolean> = ref(false);
    const isConfirmingRecovery: Ref<boolean> = ref(false);
    const newPin: Ref<string> = ref('');
    const newPinConfirmation: Ref<string> = ref('');
    const isSavingNewPin: Ref<boolean> = ref(false);

    const attemptsRemaining = computed<number>(() => challengeStatus.value.attemptsRemaining);

    const lockedUntil = computed<string | null>(() => challengeStatus.value.lockedUntil);

    const isLocked = computed<boolean>(() => isFutureDeadline(challengeStatus.value.lockedUntil));

    const pinError = computed<string | undefined>(() => humanizeErrorMessage(page.props.errors.pin) || undefined);

    const canSubmit = computed<boolean>(
        () => pin.value.length === TWO_FACTOR_PIN_LENGTH && !isSubmitting.value && !isLocked.value
    );

    const canConfirmRecovery = computed<boolean>(
        () => recoveryCode.value.length === TWO_FACTOR_PIN_LENGTH && !isConfirmingRecovery.value
    );

    const newPinError = computed<string | undefined>(() => {
        if (newPinConfirmation.value !== '' && newPinConfirmation.value !== newPin.value) {
            return 'Konfirmasi PIN belum sama.';
        }

        return humanizeErrorMessage(page.props.errors.pin) || undefined;
    });

    const canSubmitNewPin = computed<boolean>(
        () =>
            newPin.value.length === TWO_FACTOR_PIN_LENGTH &&
            newPinConfirmation.value === newPin.value &&
            !isSavingNewPin.value
    );

    /** Kirim PIN ke halaman tantangan; kegagalan mengosongkan kotak dan menampilkan pesan server. */
    function submit(): void {
        if (!canSubmit.value) return;

        notice.value = undefined;

        router.post(
            challengeVerifyRoute.url(),
            { pin: pin.value },
            {
                preserveScroll: true,
                onStart: () => {
                    isSubmitting.value = true;
                },
                onError: () => {
                    pin.value = '';
                },
                onFinish: () => {
                    isSubmitting.value = false;
                },
            }
        );
    }

    /** Minta kode pemulihan; sukses memindahkan layar ke langkah memasukkan kode. */
    async function sendRecovery(): Promise<void> {
        if (isSendingRecovery.value) return;

        isSendingRecovery.value = true;
        recoveryNotice.value = undefined;

        try {
            const result = await requestJsonWithStatus({ url: recoverySendRoute.url(), method: 'POST' }, (payload) =>
                isRecord(payload) ? payload : null
            );

            if (result.status >= 200 && result.status < 300) {
                recoveryCode.value = '';
                recoveryStep.value = 'code';

                return;
            }

            recoveryNotice.value =
                readPublicMessage(result.data) ??
                'Kode pemulihan tidak dapat dikirim sekarang. Tunggu sebentar lalu coba lagi.';
        } catch {
            recoveryNotice.value = 'Tidak dapat menghubungi server. Periksa koneksi lalu coba lagi.';
        } finally {
            isSendingRecovery.value = false;
        }
    }

    /** Cocokkan kode pemulihan; sukses hanya membuka langkah PIN baru, faktor kedua belum lolos. */
    async function confirmRecovery(): Promise<void> {
        if (!canConfirmRecovery.value) return;

        isConfirmingRecovery.value = true;
        recoveryNotice.value = undefined;

        try {
            const result = await requestJsonWithStatus(
                { url: recoveryConfirmRoute.url(), method: 'POST', body: { code: recoveryCode.value } },
                (payload) => (isRecord(payload) ? payload : null)
            );

            if (result.status >= 200 && result.status < 300) {
                recoveryCode.value = '';
                recoveryStep.value = 'pin';

                return;
            }

            recoveryNotice.value =
                readPublicMessage(result.data) ?? 'Kode pemulihan salah. Periksa kembali atau kirim ulang kode.';
        } catch {
            recoveryNotice.value = 'Tidak dapat menghubungi server. Periksa koneksi lalu coba lagi.';
        } finally {
            isConfirmingRecovery.value = false;
        }
    }

    /** Simpan PIN baru; server menandai sesi lolos lalu mengarahkan ke halaman tujuan. */
    function submitNewPin(): void {
        if (!canSubmitNewPin.value) return;

        router.post(
            recoveryPinRoute.url(),
            { pin: newPin.value },
            {
                preserveScroll: true,
                onStart: () => {
                    isSavingNewPin.value = true;
                },
                onFinish: () => {
                    isSavingNewPin.value = false;
                },
            }
        );
    }

    function restartRecovery(): void {
        recoveryStep.value = 'request';
        recoveryCode.value = '';
        recoveryNotice.value = undefined;
        newPin.value = '';
        newPinConfirmation.value = '';
    }

    return {
        pin,
        pinError,
        notice,
        isSubmitting,
        canSubmit,
        attemptsRemaining,
        lockedUntil,
        isLocked,
        submit,
        recoveryStep,
        recoveryCode,
        recoveryNotice,
        isSendingRecovery,
        isConfirmingRecovery,
        canConfirmRecovery,
        newPin,
        newPinConfirmation,
        newPinError,
        isSavingNewPin,
        canSubmitNewPin,
        sendRecovery,
        confirmRecovery,
        submitNewPin,
        restartRecovery,
    };
}
