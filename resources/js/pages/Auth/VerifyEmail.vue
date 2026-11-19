<script setup lang="ts">
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { CircleAlert, Clock3 } from 'lucide-vue-next';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import OtpCodeInput from '@/components/core/OtpCodeInput.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatCountdownClock } from '@/lib/format-countdown';
import { useEmailVerification } from '@/hooks/useEmailVerification';
import type { IVerifyEmailPageProps } from '@/hooks/useEmailVerification';

const props = defineProps<IVerifyEmailPageProps>();

const {
    code,
    codeError,
    submitNotice,
    resendError,
    isSubmitting,
    isVerified,
    isResendBlocked,
    canSubmit,
    hasActiveCode,
    status,
    expiresIn,
    resendIn,
    isExpired,
    isLocked,
    submit,
    resend,
} = useEmailVerification(props);

const expiresLabel: ComputedRef<string> = computed<string>(() => formatCountdownClock(expiresIn.value));

const resendLabel: ComputedRef<string> = computed<string>(() => formatCountdownClock(resendIn.value));

/** Kode yang dikirim ulang masih tertahan cooldown atau lock kirim. */
const showLockNotice: ComputedRef<boolean> = computed<boolean>(() => isLocked.value && resendIn.value > 0);

/**
 * Satu baris keterangan state halaman; hanya satu yang tampil agar tidak ada
 * dua pesan yang saling menimpa di area yang sama.
 */
const noticeText: ComputedRef<string> = computed<string>(() => {
    if (isVerified.value) return 'Email berhasil diverifikasi. Mengalihkan ke dasbor.';
    if (hasActiveCode.value && isExpired.value) return 'Kode sudah kedaluwarsa. Kirim ulang kode baru.';
    if (showLockNotice.value) return 'Batas pengiriman kode tercapai. Tunggu sampai hitung mundur selesai.';

    return '';
});

/** Id pesan error yang dibaca screen reader saat kode ditolak server. */
const codeDescriptionIds: ComputedRef<string | undefined> = computed<string | undefined>(() =>
    codeError.value === undefined ? undefined : 'verify-email-code-error'
);

const submitLabel: ComputedRef<string> = computed<string>(() => (isSubmitting.value ? 'Memverifikasi' : 'Verifikasi'));
</script>

<template>
    <AuthLayout>
        <SeoHead
            title="Verifikasi email"
            description="Masukkan kode verifikasi yang dikirim ke email Anda untuk menandai alamat email akun DForm sebagai terverifikasi."
            canonical-path="/email/verify"
            noindex
        />

        <div data-testid="verify-email-page" class="mx-auto w-full max-w-md text-center">
            <h1
                class="font-display text-foreground text-[1.875rem] leading-tight font-bold tracking-[-0.035em] sm:text-[2.125rem]"
            >
                Verifikasi email
            </h1>

            <p data-testid="verify-email-email" class="text-foreground mt-2 text-sm font-medium break-words">
                {{ email }}
            </p>

            <!-- Tanpa kode aktif: satu tombol sebagai satu-satunya tugas di layar ini. -->
            <template v-if="hasActiveCode">
                <p class="text-muted-foreground mt-2 text-sm">Masukkan kode 6 angka yang kami kirim ke alamat ini.</p>

                <div class="text-muted-foreground mt-3 flex min-h-5 items-center justify-center gap-1.5 text-xs">
                    <Clock3 class="size-3.5 shrink-0" aria-hidden="true" />
                    <span>Kode berlaku</span>
                    <span
                        class="font-medium tabular-nums"
                        :class="isExpired ? 'text-destructive' : 'text-foreground/80'"
                    >
                        {{ expiresLabel }}
                    </span>
                </div>

                <form class="mt-8" novalidate @submit.prevent="submit">
                    <OtpCodeInput
                        v-model="code"
                        data-testid="verify-email-code"
                        :invalid="codeError !== undefined"
                        :success="isVerified"
                        :disabled="isExpired"
                        :described-by="codeDescriptionIds"
                    />

                    <p
                        v-if="codeError"
                        id="verify-email-code-error"
                        role="alert"
                        class="text-destructive mt-3 flex items-start justify-center gap-1.5 text-sm font-medium"
                    >
                        <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        {{ codeError }}
                    </p>

                    <p
                        v-if="submitNotice"
                        role="alert"
                        class="text-destructive mt-3 flex items-start justify-center gap-1.5 text-sm font-medium"
                    >
                        <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        {{ submitNotice }}
                    </p>

                    <p class="text-muted-foreground mt-3 text-xs">Sisa percobaan: {{ status.attemptsRemaining }}</p>

                    <Button
                        type="submit"
                        size="lg"
                        data-testid="verify-email-submit"
                        class="mt-6 min-h-[44px] w-full disabled:cursor-not-allowed"
                        :disabled="!canSubmit"
                    >
                        <span v-if="isSubmitting" class="flex items-center gap-2">
                            <Spinner />
                            {{ submitLabel }}
                        </span>
                        <span v-else>{{ submitLabel }}</span>
                    </Button>
                </form>

                <p class="text-muted-foreground mt-6 text-sm">
                    Belum menerima kode?
                    <button
                        type="button"
                        data-testid="verify-email-resend"
                        class="text-primary focus-visible:ring-ring/40 focus-visible:ring-offset-background disabled:text-muted-foreground min-h-[44px] font-semibold underline-offset-4 transition-colors hover:underline focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:no-underline"
                        :disabled="isResendBlocked"
                        @click="resend"
                    >
                        Kirim ulang
                    </button>
                    <span v-if="isResendBlocked" class="text-muted-foreground ml-1 text-xs tabular-nums">
                        ({{ resendLabel }})
                    </span>
                </p>
            </template>

            <template v-else>
                <p class="text-muted-foreground mt-2 text-sm">
                    Kami akan mengirim kode 6 angka ke alamat email ini. Masukkan kodenya di layar berikutnya.
                </p>

                <Button
                    type="button"
                    size="lg"
                    data-testid="verify-email-resend"
                    class="mt-6 min-h-[44px] w-full"
                    :disabled="isResendBlocked"
                    @click="resend"
                >
                    <span v-if="isResendBlocked" class="flex items-center gap-2">
                        <Spinner />
                        Mengirim kode
                    </span>
                    <span v-else>Kirim kode verifikasi</span>
                </Button>
            </template>

            <p
                v-if="noticeText"
                data-testid="verify-email-notice"
                class="mt-4 text-xs font-medium"
                :class="isVerified ? 'text-success' : 'text-muted-foreground'"
            >
                {{ noticeText }}
            </p>

            <p v-if="resendError" role="alert" class="text-destructive mt-3 text-xs font-medium">
                {{ resendError }}
            </p>
        </div>
    </AuthLayout>
</template>
