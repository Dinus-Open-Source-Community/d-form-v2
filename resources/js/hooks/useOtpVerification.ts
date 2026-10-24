import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import type { InertiaForm } from '@inertiajs/vue3';
import { resend as resendOtp, verify as verifyOtp } from '@/actions/App/Http/Controllers/Auth/VerifyOtpController';
import CancelPendingRegistrationController from '@/actions/App/Http/Controllers/Auth/CancelPendingRegistrationController';
import { getFieldError } from '@/lib/error-message';
import type { ValidationErrors } from '@/lib/error-message';

/** Panjang kode OTP registrasi; harus sama dengan validasi server (`digits:6`). */
export const OTP_CODE_LENGTH = 6;

export interface IUseOtpVerificationOptions {
    /** Kode sudah kedaluwarsa menurut hitung mundur server. */
    isExpired: ComputedRef<boolean>;
    /** Sisa detik sebelum kirim ulang diizinkan. */
    resendIn: ComputedRef<number>;
}

export interface IUseOtpVerificationState {
    /** Form kode; error dari server tersedia di `form.errors` dan halaman. */
    form: InertiaForm<{ code: string }>;
    /** Pesan gagal verifikasi dari server (`errors.code`). */
    codeError: ComputedRef<string | undefined>;
    /** Pesan gagal kirim ulang dari server (`errors.resend`). */
    resendError: ComputedRef<string | undefined>;
    /** True bila kirim ulang masih terkunci cooldown/lock. */
    isResendBlocked: ComputedRef<boolean>;
    /** True bila kode lengkap, belum kedaluwarsa, dan tidak sedang dikirim. */
    canSubmit: ComputedRef<boolean>;
    submit: () => void;
    resend: () => void;
    useAnotherEmail: () => void;
}

/**
 * Aksi halaman verifikasi OTP: kirim kode, kirim ulang, dan batalkan pendaftaran tertunda.
 * State waktu tetap milik `useOtpCountdown`; hook ini hanya membaca hasilnya.
 */
export function useOtpVerification(options: IUseOtpVerificationOptions): IUseOtpVerificationState {
    const page = usePage();
    const form = useForm({ code: '' });

    const pageErrors = computed<ValidationErrors>(() => page.props.errors);

    const codeError = computed<string | undefined>(() => getFieldError(pageErrors.value, 'code'));

    const resendError = computed<string | undefined>(() => getFieldError(pageErrors.value, 'resend'));

    const isResendBlocked = computed<boolean>(() => options.resendIn.value > 0);

    const canSubmit = computed<boolean>(
        () => form.code.length === OTP_CODE_LENGTH && !form.processing && !options.isExpired.value,
    );

    function submit(): void {
        if (!canSubmit.value) return;

        form.submit(verifyOtp(), {
            preserveScroll: true,
            // Kode yang ditolak server tidak berguna lagi; kosongkan agar pengguna mengetik ulang.
            onError: () => form.reset('code'),
        });
    }

    function resend(): void {
        if (isResendBlocked.value) return;

        router.post(resendOtp.url(), {}, { preserveScroll: true });
    }

    function useAnotherEmail(): void {
        router.post(CancelPendingRegistrationController.url(), {}, { preserveScroll: true });
    }

    return { form, codeError, resendError, isResendBlocked, canSubmit, submit, resend, useAnotherEmail };
}
