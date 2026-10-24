<script lang="ts" setup>
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { CircleAlert, Clock3, MailCheck } from 'lucide-vue-next';
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
    form,
    codeError,
    resendError,
    isResendBlocked,
    canSubmit,
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

/** Id bantuan yang dibaca screen reader: hint kuota, ditambah pesan error saat ada. */
const codeDescriptionIds: ComputedRef<string> = computed<string>(() =>
    codeError.value === undefined ? 'otp-code-hint' : 'otp-code-hint otp-code-error',
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

        <div class="w-full">
            <p class="flex items-center gap-2 text-[11px] font-semibold tracking-[0.18em] text-muted-foreground uppercase">
                <MailCheck class="size-3.5 text-primary" aria-hidden="true" />
                Verifikasi email
            </p>

            <h1 class="mt-3 font-display text-[2rem] leading-tight font-bold tracking-[-0.035em] text-foreground sm:text-4xl">
                Check your messages
            </h1>

            <p class="mt-2.5 text-sm text-muted-foreground">Enter the code sent to</p>
            <p data-testid="otp-email" class="mt-0.5 font-semibold break-words text-foreground">{{ email }}</p>

            <!-- Hitung mundur tepat di bawah subjudul; angkanya memakai jam server. -->
            <div class="mt-4 flex min-h-6 items-center gap-2 text-sm">
                <span
                    v-if="isExpired"
                    data-testid="otp-expired"
                    role="status"
                    aria-live="polite"
                    class="inline-flex items-center gap-1.5 rounded-full bg-destructive/10 px-2.5 py-1 text-xs font-semibold text-destructive"
                >
                    <CircleAlert class="size-3.5" aria-hidden="true" />
                    Kode kedaluwarsa
                </span>

                <template v-else>
                    <Clock3 class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <span class="text-muted-foreground">Kode berlaku</span>
                    <span
                        data-testid="otp-countdown"
                        class="font-display text-base font-semibold tabular-nums text-foreground"
                    >
                        {{ expiresLabel }}
                    </span>
                </template>
            </div>

            <form class="mt-7" novalidate @submit.prevent="submit">
                <div class="rounded-2xl border border-border bg-card p-4 shadow-[var(--shadow-sm)] sm:p-6">
                    <OtpCodeInput
                        v-model="form.code"
                        :invalid="codeError !== undefined"
                        :disabled="isExpired"
                        :described-by="codeDescriptionIds"
                    />

                    <p
                        v-if="codeError"
                        id="otp-code-error"
                        role="alert"
                        class="mt-3 flex items-start gap-1.5 text-sm font-medium text-destructive motion-safe:animate-shake"
                    >
                        <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        {{ codeError }}
                    </p>

                    <div
                        id="otp-code-hint"
                        class="mt-4 flex items-center justify-between gap-3 border-t border-border/70 pt-3 text-xs text-muted-foreground"
                    >
                        <span data-testid="otp-sends-remaining">
                            Sisa kirim
                            <strong class="font-semibold tabular-nums text-foreground">{{ sendsRemaining }}</strong>
                        </span>
                        <span data-testid="otp-attempts-remaining">
                            <strong class="font-semibold tabular-nums text-foreground">{{ attemptsRemaining }}</strong>
                            percobaan tersisa
                        </span>
                    </div>
                </div>

                <Button
                    type="submit"
                    size="lg"
                    class="mt-4 w-full disabled:cursor-not-allowed disabled:bg-primary/10 disabled:text-primary disabled:opacity-100"
                    :disabled="!canSubmit"
                >
                    <span v-if="form.processing" class="flex items-center gap-2">
                        <Spinner />
                        Memverifikasi
                    </span>
                    <span v-else>Verifikasi</span>
                </Button>
            </form>

            <div class="mt-6 space-y-2.5 text-center">
                <p class="text-sm text-muted-foreground">
                    Tidak mendapat OTP?
                    <button
                        type="button"
                        :ref="setResendButton"
                        class="font-semibold text-primary underline-offset-4 transition-colors hover:underline focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none disabled:cursor-not-allowed disabled:text-muted-foreground disabled:no-underline"
                        :disabled="isResendBlocked"
                        @click="resend"
                    >
                        Kirim ulang
                    </button>
                    <span v-if="isResendBlocked" class="ml-1 text-xs text-muted-foreground">
                        (<span data-testid="otp-resend-countdown" class="font-display font-semibold tabular-nums">{{ resendLabel }}</span>)
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
                    class="mx-auto flex items-center gap-1.5 text-xs font-medium text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline focus-visible:ring-2 focus-visible:ring-ring/40 focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-none"
                    @click="useAnotherEmail"
                >
                    Gunakan email lain
                </button>
            </div>
        </div>
    </AuthLayout>
</template>
