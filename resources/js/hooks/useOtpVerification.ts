import { computed, nextTick, ref, watch } from 'vue';
import type { ComponentPublicInstance, ComputedRef, Ref } from 'vue';
import axios from 'axios';
import { router, usePage } from '@inertiajs/vue3';
import { resend as resendOtp, verify as verifyOtp } from '@/actions/App/Http/Controllers/Auth/VerifyOtpController';
import CancelPendingRegistrationController from '@/actions/App/Http/Controllers/Auth/CancelPendingRegistrationController';
import { getFieldError } from '@/lib/error-message';
import type { ValidationErrors } from '@/lib/error-message';

/** Panjang kode OTP registrasi; harus sama dengan validasi server (`digits:6`). */
export const OTP_CODE_LENGTH = 6;

/** Jeda singkat sebelum pindah ke dashboard supaya state hijau benar-benar terlihat. */
const VERIFY_REDIRECT_DELAY_MS = 600;

export interface IUseOtpVerificationOptions {
    /** Kode sudah kedaluwarsa menurut hitung mundur server. */
    isExpired: ComputedRef<boolean>;
    /** Sisa detik sebelum kirim ulang diizinkan. */
    resendIn: ComputedRef<number>;
}

export interface IUseOtpVerificationState {
    /** Kode gabungan 6 digit; dipakai `v-model` kotak OTP. */
    code: Ref<string>;
    /** Pesan penolakan kode (422); keenam kotak ikut merah. */
    codeError: Ref<string | undefined>;
    /** Pesan non-kode (429/jaringan); kotak tidak ikut merah. */
    submitNotice: Ref<string | undefined>;
    /** Pesan gagal kirim ulang dari server (`errors.resend`). */
    resendError: ComputedRef<string | undefined>;
    /** True saat verifikasi sedang dikirim. */
    isSubmitting: Ref<boolean>;
    /** True bila kirim ulang masih terkunci cooldown/lock atau sedang dikirim. */
    isResendBlocked: ComputedRef<boolean>;
    /** True bila kode lengkap, belum kedaluwarsa, dan tidak sedang dikirim. */
    canSubmit: ComputedRef<boolean>;
    /** True setelah server menerima kode; dipakai kotak untuk state sukses. */
    isVerified: Ref<boolean>;
    /** Callback `ref` tombol kirim ulang; dipakai memindahkan fokus saat kode kedaluwarsa. */
    setResendButton: (element: Element | ComponentPublicInstance | null) => void;
    /** Callback `ref` tombol "Ganti akun"; fokus cadangan bila kirim ulang masih terkunci. */
    setAlternateEmailButton: (element: Element | ComponentPublicInstance | null) => void;
    submit: () => Promise<void>;
    resend: () => void;
    useAnotherEmail: () => void;
}

interface IVerifySuccessPayload {
    redirect: string;
}

/** Ambil `message` dari body JSON error; undefined bila bentuknya tidak dikenal. */
function readMessage(payload: unknown): string | undefined {
    if (typeof payload !== 'object' || payload === null || !('message' in payload)) return undefined;

    const { message } = payload;

    return typeof message === 'string' && message.trim() !== '' ? message : undefined;
}

/** Cek body sukses verifikasi; `redirect` wajib path internal agar tidak bisa dipakai open redirect. */
function isVerifySuccessPayload(payload: unknown): payload is IVerifySuccessPayload {
    if (typeof payload !== 'object' || payload === null || !('verified' in payload) || !('redirect' in payload)) {
        return false;
    }

    return payload.verified === true && typeof payload.redirect === 'string' && payload.redirect.startsWith('/');
}

/** Sisa detik dari header `Retry-After`; undefined bila kosong atau bukan angka. */
function readRetryAfterSeconds(raw: unknown): number | undefined {
    if (typeof raw !== 'string' && typeof raw !== 'number') return undefined;

    const seconds = Number(raw);

    return Number.isFinite(seconds) && seconds > 0 ? Math.ceil(seconds) : undefined;
}

/**
 * Aksi halaman verifikasi OTP: kirim kode, kirim ulang, dan batalkan pendaftaran tertunda.
 * Verifikasi memakai jalur JSON (`Accept: application/json`) agar state hijau bisa ditampilkan
 * sebelum pindah halaman; state waktu tetap milik `useOtpCountdown`.
 */
export function useOtpVerification(options: IUseOtpVerificationOptions): IUseOtpVerificationState {
    const page = usePage();
    const code: Ref<string> = ref('');
    const codeError: Ref<string | undefined> = ref(undefined);
    const submitNotice: Ref<string | undefined> = ref(undefined);
    const isSubmitting: Ref<boolean> = ref(false);
    const isResending: Ref<boolean> = ref(false);
    const isVerified: Ref<boolean> = ref(false);

    let resendButton: HTMLButtonElement | null = null;
    let alternateEmailButton: HTMLButtonElement | null = null;

    const pageErrors = computed<ValidationErrors>(() => page.props.errors);

    const resendError = computed<string | undefined>(() => getFieldError(pageErrors.value, 'resend'));

    const isResendBlocked = computed<boolean>(() => options.resendIn.value > 0 || isResending.value);

    const canSubmit = computed<boolean>(
        () =>
            code.value.length === OTP_CODE_LENGTH &&
            !isSubmitting.value &&
            !isVerified.value &&
            !options.isExpired.value,
    );

    /** Daftarkan tombol kirim ulang sebagai target fokus saat kode kedaluwarsa. */
    function setResendButton(element: Element | ComponentPublicInstance | null): void {
        resendButton = element instanceof HTMLButtonElement ? element : null;
    }

    /** Daftarkan tombol "Ganti akun" sebagai fokus cadangan saat kirim ulang terkunci. */
    function setAlternateEmailButton(element: Element | ComponentPublicInstance | null): void {
        alternateEmailButton = element instanceof HTMLButtonElement ? element : null;
    }

    /** Tandai kotak hijau, lalu pindah ke dashboard agar umpan balik sukses sempat terbaca. */
    function scheduleDashboardVisit(redirect: string): void {
        isVerified.value = true;

        window.setTimeout(() => {
            router.visit(redirect);
        }, VERIFY_REDIRECT_DELAY_MS);
    }

    /** Tangani balasan JSON verifikasi: 200 hijau, 422 merah, 429 pesan tunggu, sisanya pesan umum. */
    function handleVerifyResponse(status: number, payload: unknown, retryAfterRaw: unknown): void {
        if (status === 200 && isVerifySuccessPayload(payload)) {
            scheduleDashboardVisit(payload.redirect);

            return;
        }

        if (status === 422) {
            codeError.value = readMessage(payload) ?? 'Kode verifikasi salah. Periksa kembali atau kirim ulang kode.';
            // Kode yang ditolak tidak berguna lagi; kotak mengosongkan diri dan fokus kembali ke kotak pertama.
            code.value = '';
            // Server bisa mengosongkan kode setelah percobaan habis; segarkan props agar status ikut.
            // `reload()` selalu preserveScroll (ReloadOptions tidak menerima opsi itu di Inertia 3).
            router.reload();

            return;
        }

        if (status === 429) {
            // Pesan throttle bawaan Laravel berbahasa Inggris; pakai copy produk + sisa waktu dari header.
            const retryAfter = readRetryAfterSeconds(retryAfterRaw);

            submitNotice.value =
                retryAfter === undefined
                    ? 'Terlalu banyak percobaan. Coba lagi nanti.'
                    : `Terlalu banyak percobaan. Coba lagi dalam ${retryAfter} detik.`;

            return;
        }

        submitNotice.value = readMessage(payload) ?? 'Verifikasi gagal. Coba lagi.';
    }

    async function submit(): Promise<void> {
        if (!canSubmit.value) return;

        isSubmitting.value = true;
        codeError.value = undefined;
        submitNotice.value = undefined;

        try {
            const response = await axios.post<unknown>(
                verifyOtp.url(),
                { code: code.value },
                { headers: { Accept: 'application/json' }, validateStatus: () => true },
            );

            handleVerifyResponse(response.status, response.data, response.headers['retry-after']);
        } catch {
            submitNotice.value = 'Tidak dapat menghubungi server. Periksa koneksi lalu coba lagi.';
        } finally {
            isSubmitting.value = false;
        }
    }

    function resend(): void {
        if (isResendBlocked.value || isVerified.value) return;

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

    /** Fokuskan aksi yang masih bisa dipakai: kirim ulang, atau "Ganti akun" saat terkunci. */
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
        () => code.value.length === OTP_CODE_LENGTH,
        (isComplete) => {
            if (isComplete) void submit();
        },
        { flush: 'post' },
    );

    // Pesan merah milik kode lama tidak relevan lagi saat pengguna mulai mengetik kode baru.
    watch(code, (value) => {
        if (value !== '' && codeError.value !== undefined) codeError.value = undefined;
    });

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
        code,
        codeError,
        submitNotice,
        resendError,
        isSubmitting,
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
