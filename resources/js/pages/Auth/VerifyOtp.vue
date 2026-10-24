<script lang="ts" setup>
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { CircleAlert, Clock3 } from 'lucide-vue-next';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import OtpCodeInput from '@/components/modules/auth/OtpCodeInput.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatCountdownClock } from '@/lib/format-countdown';
import { useOtpCountdown } from '@/hooks/useOtpCountdown';
import { useOtpVerification } from '@/hooks/useOtpVerification';

const props = defineProps<{
    email: string;
    expiresAt: string | null;
    serverNow: string;
    lockedUntil: string | null;
    sendsRemaining: number;
    attemptsRemaining: number;
    resendAvailableAt: string | null;
}>();

// Objek props diteruskan utuh agar expiresAt/resendAvailableAt ikut berubah setelah kirim ulang.
const { expiresIn, resendIn, isExpired, isLocked } = useOtpCountdown(props);

const {
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
} = useOtpVerification({
    isExpired,
    resendIn,
});

const expiresLabel: ComputedRef<string> = computed<string>(() => formatCountdownClock(expiresIn.value));

const resendLabel: ComputedRef<string> = computed<string>(() => formatCountdownClock(resendIn.value));

/** Banner lock hanya relevan selama hitung mundur kirim ulang masih berjalan. */
const showLockNotice: ComputedRef<boolean> = computed<boolean>(() => isLocked.value && resendIn.value > 0);

/** Teks live region: diumumkan saat kode berubah menjadi kedaluwarsa. */
const expiryAnnouncement: ComputedRef<string> = computed<string>(() =>
    isExpired.value ? 'Kode verifikasi sudah kedaluwarsa. Kirim ulang kode baru.' : '',
);

/** Id pesan error yang dibaca screen reader saat kode ditolak server (bukan saat kode habis). */
const codeDescriptionIds: ComputedRef<string | undefined> = computed<string | undefined>(() =>
    codeError.value === undefined || isExpired.value ? undefined : 'otp-code-error',
);
</script>

<template>
    <AuthLayout>
        <SeoHead
            title="Verifikasi email"
            description="Masukkan kode verifikasi yang dikirim ke email Anda untuk mengaktifkan akun DForm."
            canonical-path="/auth/verify-otp"
            noindex
        />

        <div class="mx-auto w-full max-w-md text-center">
            <h1 class="font-display text-[1.875rem] leading-tight font-bold tracking-[-0.035em] text-foreground sm:text-[2.125rem]">
                Check your messages
            </h1>

            <p data-testid="otp-email" class="mt-2 text-sm font-medium break-words text-foreground">{{ email }}</p>

            <!-- Live region selalu ter-mount (pola sama dengan form register): hanya teksnya yang berubah. -->
            <div class="sr-only" data-testid="otp-expiry-live" aria-live="polite" aria-atomic="true">
                <span>{{ expiryAnnouncement }}</span>
            </div>

            <!-- Hitung mundur kecil tepat di bawah email; angkanya memakai jam server. -->
            <div class="mt-1.5 flex min-h-5 items-center justify-center gap-1.5 text-xs text-muted-foreground">
                <Clock3 class="size-3.5 shrink-0" aria-hidden="true" />
                <span>Kode berlaku</span>
                <span
                    data-testid="otp-countdown"
                    class="font-medium tabular-nums"
                    :class="isExpired ? 'text-destructive' : 'text-foreground/80'"
                >
                    {{ expiresLabel }}
                </span>
            </div>

            <!-- Kode habis karena kedaluwarsa atau percobaan salah; server sudah mengosongkan kode. -->
            <p v-if="isExpired" data-testid="otp-expired" class="mt-2 text-xs font-medium text-destructive">
                Kode sudah kedaluwarsa. Kirim ulang OTP.
            </p>

            <form class="mt-8" novalidate @submit.prevent="submit">
                <OtpCodeInput
                    v-model="code"
                    :invalid="codeError !== undefined && !isExpired"
                    :success="isVerified"
                    :disabled="isExpired"
                    :described-by="codeDescriptionIds"
                />

                <p
                    v-if="codeError && !isExpired"
                    id="otp-code-error"
                    role="alert"
                    class="mt-3 flex items-start justify-center gap-1.5 text-sm font-medium text-destructive motion-safe:animate-shake"
                >
                    <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    {{ codeError }}
                </p>

                <!-- 429/jaringan: pesan saja, kotak tidak ikut merah. -->
                <p
                    v-if="submitNotice"
                    data-testid="otp-submit-notice"
                    role="alert"
                    class="mt-3 flex items-start justify-center gap-1.5 text-sm font-medium text-destructive"
                >
                    <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    {{ submitNotice }}
                </p>

                <Button
                    type="submit"
                    size="lg"
                    class="mt-6 w-full disabled:cursor-not-allowed disabled:bg-primary/10 disabled:text-primary disabled:opacity-100"
                    :disabled="!canSubmit"
                >
                    <span v-if="isSubmitting" class="flex items-center gap-2">
                        <Spinner />
                        Memverifikasi
                    </span>
                    <span v-else>Verifikasi</span>
                </Button>
            </form>

            <div class="mt-6 space-y-3">
                <p class="text-sm text-muted-foreground">
                    Tidak mendapatkan OTP?
                    <button
                        type="button"
                        :ref="setResendButton"
                        class="font-semibold text-primary underline-offset-4 transition-colors hover:underline focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none disabled:cursor-not-allowed disabled:text-muted-foreground disabled:no-underline"
                        :disabled="isResendBlocked"
                        @click="resend"
                    >
                        Kirim ulang OTP
                    </button>
                    <span v-if="isResendBlocked" class="ml-1 text-xs text-muted-foreground">
                        (<span data-testid="otp-resend-countdown" class="font-medium tabular-nums">{{ resendLabel }}</span>)
                    </span>
                </p>

                <p v-if="resendError" role="alert" class="text-xs font-medium text-destructive">{{ resendError }}</p>

                <p
                    v-if="showLockNotice"
                    class="mx-auto max-w-xs rounded-lg border border-warning/40 bg-warning/10 px-3 py-2 text-xs font-medium text-foreground"
                >
                    Batas pengiriman kode tercapai. Tunggu sampai hitung mundur selesai lalu kirim ulang.
                </p>

                <button
                    type="button"
                    :ref="setAlternateEmailButton"
                    class="mx-auto flex items-center gap-1.5 text-sm font-medium text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                    @click="useAnotherEmail"
                >
                    Ganti akun
                </button>
            </div>
        </div>
    </AuthLayout>
</template>
