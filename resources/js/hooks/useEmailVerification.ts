import { computed, ref, watch } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { verifyOtpCode } from '@/adapters/http/auth';
import type { IOtpVerificationResult } from '@/adapters/http/auth';
import { confirm as confirmVerificationRoute, send as sendVerificationRoute } from '@/routes/verification';
import { humanizeErrorMessage } from '@/lib/error-message';
import { useOtpCountdown } from '@/hooks/useOtpCountdown';

/** Panjang kode verifikasi email; sama dengan validasi server (`digits:6`). */
export const EMAIL_VERIFICATION_CODE_LENGTH = 6;

/** Jeda singkat sebelum pindah halaman supaya state hijau benar-benar terlihat. */
const VERIFIED_REDIRECT_DELAY_MS = 600;

/** Props halaman `Auth/VerifyEmail` dari `EmailVerificationController::notice`. */
export interface IVerifyEmailPageProps {
    email: string;
    verified: boolean;
    expiresAt: string | null;
    serverNow: string;
    attemptsRemaining: number;
    sendsRemaining: number;
    lockedUntil: string | null;
    resendAvailableAt: string | null;
}

/** Objek status server yang dipakai halaman untuk menampilkan kuota dan lock. */
export interface IEmailVerificationStatus {
    expiresAt: string | null;
    attemptsRemaining: number;
    sendsRemaining: number;
    lockedUntil: string | null;
    resendAvailableAt: string | null;
}

export interface IEmailVerificationState {
    /** Kode gabungan 6 digit; dipakai `v-model` kotak kode. */
    code: Ref<string>;
    /** Pesan penolakan kode (422); keenam kotak ikut merah. */
    codeError: Ref<string | undefined>;
    /** Pesan non-kode (429/jaringan); kotak tidak ikut merah. */
    submitNotice: Ref<string | undefined>;
    /** Pesan gagal kirim dari server (`errors.resend`). */
    resendError: ComputedRef<string | undefined>;
    isSubmitting: Ref<boolean>;
    isResending: Ref<boolean>;
    isVerified: Ref<boolean>;
    /** True bila kode dikirim ulang masih tertahan cooldown/lock atau sedang dikirim. */
    isResendBlocked: ComputedRef<boolean>;
    /** True bila kode lengkap, kode masih aktif, dan tidak sedang dikirim. */
    canSubmit: ComputedRef<boolean>;
    /** True bila server sudah memberi kode aktif yang belum kedaluwarsa. */
    hasActiveCode: ComputedRef<boolean>;
    status: ComputedRef<IEmailVerificationStatus>;
    expiresIn: ComputedRef<number>;
    resendIn: ComputedRef<number>;
    isExpired: ComputedRef<boolean>;
    isLocked: ComputedRef<boolean>;
    submit: () => Promise<void>;
    resend: () => void;
}

/**
 * Aksi halaman verifikasi email: konfirmasi kode lewat jalur JSON (agar state hijau
 * sempat tampil sebelum pindah halaman) dan kirim ulang lewat kunjungan Inertia
 * (agar props status terbaru langsung dipakai halaman). State waktu tetap milik
 * `useOtpCountdown`, sama seperti halaman OTP registrasi.
 */
export function useEmailVerification(props: IVerifyEmailPageProps): IEmailVerificationState {
    const page = usePage();
    const code: Ref<string> = ref('');
    const codeError: Ref<string | undefined> = ref(undefined);
    const submitNotice: Ref<string | undefined> = ref(undefined);
    const isSubmitting: Ref<boolean> = ref(false);
    const isResending: Ref<boolean> = ref(false);
    const isVerified: Ref<boolean> = ref(false);

    /**
     * Deadline dibaca lewat getter, bukan disalin: Inertia mengganti props tanpa remount,
     * jadi hitung mundur harus memakai nilai baru setelah "Kirim ulang" (kode baru,
     * cooldown baru, kuota kirim baru).
     */
    const countdown = useOtpCountdown({
        get serverNow(): string {
            return props.serverNow;
        },
        get expiresAt(): string | null {
            return props.expiresAt;
        },
        get resendAvailableAt(): string | null {
            return props.resendAvailableAt;
        },
        get lockedUntil(): string | null {
            return props.lockedUntil;
        },
    });

    const status = computed<IEmailVerificationStatus>(() => ({
        expiresAt: props.expiresAt,
        attemptsRemaining: props.attemptsRemaining,
        sendsRemaining: props.sendsRemaining,
        lockedUntil: props.lockedUntil,
        resendAvailableAt: props.resendAvailableAt,
    }));

    const resendError = computed<string | undefined>(() => humanizeErrorMessage(page.props.errors.resend) || undefined);

    /** Kode dianggap ada bila server memberi batas berlaku; kedaluwarsa ditandai terpisah. */
    const hasActiveCode = computed<boolean>(() => props.expiresAt !== null);

    const isResendBlocked = computed<boolean>(() => countdown.resendIn.value > 0 || isResending.value);

    const canSubmit = computed<boolean>(
        () =>
            code.value.length === EMAIL_VERIFICATION_CODE_LENGTH &&
            hasActiveCode.value &&
            !countdown.isExpired.value &&
            !isSubmitting.value &&
            !isVerified.value
    );

    /** Tandai kotak hijau lalu kunjungi tujuan yang diberikan server. */
    function scheduleVerifiedVisit(redirect: string): void {
        isVerified.value = true;

        setTimeout(() => {
            router.visit(redirect);
        }, VERIFIED_REDIRECT_DELAY_MS);
    }

    /** Tangani hasil konfirmasi: 200 hijau, 422 merah, 429 pesan tunggu, sisanya pesan umum. */
    function handleConfirmResult(result: IOtpVerificationResult): void {
        if (result.status === 200 && result.verifiedRedirect !== null && result.verifiedRedirect.startsWith('/')) {
            scheduleVerifiedVisit(result.verifiedRedirect);

            return;
        }

        if (result.status === 422) {
            codeError.value = result.message ?? 'Kode verifikasi salah. Periksa kembali atau kirim ulang kode.';
            // Kode yang ditolak tidak berguna lagi; server menambah percobaan, jadi status ikut disegarkan.
            code.value = '';
            router.reload();

            return;
        }

        if (result.status === 429) {
            const retryAfter = result.retryAfterSeconds;

            submitNotice.value =
                retryAfter === null
                    ? 'Terlalu banyak percobaan. Coba lagi nanti.'
                    : `Terlalu banyak percobaan. Coba lagi dalam ${retryAfter} detik.`;

            return;
        }

        submitNotice.value = result.message ?? 'Verifikasi gagal. Coba lagi.';
    }

    async function submit(): Promise<void> {
        if (!canSubmit.value) return;

        isSubmitting.value = true;
        codeError.value = undefined;
        submitNotice.value = undefined;

        try {
            handleConfirmResult(await verifyOtpCode(confirmVerificationRoute.url(), code.value));
        } catch {
            submitNotice.value = 'Tidak dapat menghubungi server. Periksa koneksi lalu coba lagi.';
        } finally {
            isSubmitting.value = false;
        }
    }

    function resend(): void {
        if (isResendBlocked.value || isVerified.value) return;

        router.post(
            sendVerificationRoute.url(),
            {},
            {
                preserveScroll: true,
                onStart: () => {
                    isResending.value = true;
                },
                onFinish: () => {
                    isResending.value = false;
                },
            }
        );
    }

    // Auto-submit begitu 6 digit terisi (ketik/tempel); `canSubmit` menahan kirim ganda.
    watch(
        () => code.value.length === EMAIL_VERIFICATION_CODE_LENGTH,
        (isComplete) => {
            if (isComplete) void submit();
        },
        { flush: 'post' }
    );

    // Pesan merah milik kode lama tidak relevan lagi saat pengguna mulai mengetik kode baru.
    watch(code, (value) => {
        if (value !== '' && codeError.value !== undefined) codeError.value = undefined;
    });

    return {
        code,
        codeError,
        submitNotice,
        resendError,
        isSubmitting,
        isResending,
        isVerified,
        isResendBlocked,
        canSubmit,
        hasActiveCode,
        status,
        expiresIn: countdown.expiresIn,
        resendIn: countdown.resendIn,
        isExpired: countdown.isExpired,
        isLocked: countdown.isLocked,
        submit,
        resend,
    };
}
