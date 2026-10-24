import { computed, nextTick, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
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
    /** True bila kirim ulang masih terkunci cooldown/lock atau sedang dikirim. */
    isResendBlocked: ComputedRef<boolean>;
    /** True bila kode lengkap, belum kedaluwarsa, dan tidak sedang dikirim. */
    canSubmit: ComputedRef<boolean>;
    /** True setelah server menerima kode; dipakai kotak untuk state sukses. */
    isVerified: Ref<boolean>;
    /** Callback `ref` tombol kirim ulang; dipakai memindahkan fokus saat kode kedaluwarsa. */
    setResendButton: (element: Element | null) => void;
    /** Callback `ref` tombol "Gunakan email lain"; fokus cadangan bila kirim ulang masih terkunci. */
    setAlternateEmailButton: (element: Element | null) => void;
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
    const isResending: Ref<boolean> = ref(false);
    const isVerified: Ref<boolean> = ref(false);

    let resendButton: HTMLButtonElement | null = null;
    let alternateEmailButton: HTMLButtonElement | null = null;

    const pageErrors = computed<ValidationErrors>(() => page.props.errors);

    const codeError = computed<string | undefined>(() => getFieldError(pageErrors.value, 'code'));

    const resendError = computed<string | undefined>(() => getFieldError(pageErrors.value, 'resend'));

    const isResendBlocked = computed<boolean>(() => options.resendIn.value > 0 || isResending.value);

    const canSubmit = computed<boolean>(
        () => form.code.length === OTP_CODE_LENGTH && !form.processing && !options.isExpired.value,
    );

    /** Daftarkan tombol kirim ulang sebagai target fokus saat kode kedaluwarsa. */
    function setResendButton(element: Element | null): void {
        resendButton = element instanceof HTMLButtonElement ? element : null;
    }

    /** Daftarkan tombol "Gunakan email lain" sebagai fokus cadangan saat kirim ulang terkunci. */
    function setAlternateEmailButton(element: Element | null): void {
        alternateEmailButton = element instanceof HTMLButtonElement ? element : null;
    }

    function submit(): void {
        if (!canSubmit.value) return;

        form.submit(verifyOtp(), {
            preserveScroll: true,
            // Hijau hanya setelah server menyatakan kode diterima.
            onSuccess: () => {
                isVerified.value = true;
            },
            // Kode yang ditolak server tidak berguna lagi; kosongkan agar pengguna mengetik ulang.
            onError: () => form.reset('code'),
        });
    }

    function resend(): void {
        if (isResendBlocked.value) return;

        router.post(
            resendOtp.url(),
            {},
            {
                preserveScroll: true,
                onStart: () => {
                    isResending.value = true;
                },
                onFinish: () => {
                    isResending.value = false;
                    restoreFocusAfterResend();
                },
            },
        );
    }

    /** Fokuskan aksi yang masih bisa dipakai: kirim ulang, atau "Gunakan email lain" saat terkunci. */
    function focusActionableControl(): void {
        const target = resendButton?.disabled === false ? resendButton : alternateEmailButton;

        target?.focus();
    }

    /** Tombol kirim ulang didisable selama request; kembalikan fokus bila jatuh ke body. */
    function restoreFocusAfterResend(): void {
        void nextTick(() => {
            const active = document.activeElement;

            if (active !== null && active !== document.body) return;

            focusActionableControl();
        });
    }

    function useAnotherEmail(): void {
        router.post(CancelPendingRegistrationController.url(), {}, { preserveScroll: true });
    }

    // Auto-submit begitu 6 digit terisi (ketik/tempel); `canSubmit` menahan kirim ganda saat proses.
    watch(
        () => form.code.length === OTP_CODE_LENGTH,
        (isComplete) => {
            if (isComplete) submit();
        },
        { flush: 'post' },
    );

    // Kotak kode ikut nonaktif saat kedaluwarsa; fokus dipindah agar tidak jatuh ke body.
    // `flush: 'post'` supaya atribut disabled tombol sudah diperbarui DOM sebelum difokuskan.
    watch(
        options.isExpired,
        (expired, wasExpired) => {
            if (!expired || wasExpired) return;

            focusActionableControl();
        },
        { flush: 'post' },
    );

    return {
        form,
        codeError,
        resendError,
        isResendBlocked,
        canSubmit,
        isVerified,
        setResendButton,
        setAlternateEmailButton,
        submit,
        resend,
        useAnotherEmail,
    };
}
