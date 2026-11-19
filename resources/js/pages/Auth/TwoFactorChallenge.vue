<script setup lang="ts">
import { computed, ref } from 'vue';
import type { ComputedRef } from 'vue';
import { CircleAlert } from 'lucide-vue-next';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import OtpCodeInput from '@/components/core/OtpCodeInput.vue';
import TwoFactorRecovery from '@/pages/Auth/TwoFactorRecovery.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/dateTime';
import { useTwoFactorChallenge } from '@/hooks/useTwoFactorChallenge';
import type { ITwoFactorChallengeStatus } from '@/hooks/useTwoFactorChallenge';

const props = defineProps<{
    status: ITwoFactorChallengeStatus;
}>();

const { pin, pinError, notice, isSubmitting, canSubmit, attemptsRemaining, lockedUntil, isLocked, submit } =
    useTwoFactorChallenge({ status: () => props.status });

/** Pemulihan menggantikan layar PIN; tidak ada navigasi ke halaman lain di tengah alur. */
const isRecoveryOpen = ref(false);

const lockLabel: ComputedRef<string> = computed<string>(() =>
    lockedUntil.value === null ? '' : formatDateTime({ value: lockedUntil.value, variant: 'time-short' })
);

const pinDescriptionIds: ComputedRef<string | undefined> = computed<string | undefined>(() =>
    pinError.value === undefined ? undefined : 'two-factor-pin-error'
);
</script>

<template>
    <AuthLayout>
        <SeoHead
            title="Verifikasi PIN"
            description="Masukkan PIN 6 angka untuk menyelesaikan langkah kedua saat masuk ke akun DForm."
            canonical-path="/auth/two-factor"
            noindex
        />

        <div data-testid="two-factor-page" class="mx-auto w-full max-w-md">
            <TwoFactorRecovery v-if="isRecoveryOpen" @cancel="isRecoveryOpen = false" />

            <template v-else>
                <div class="text-center">
                    <h1
                        class="font-display text-foreground text-[1.875rem] leading-tight font-bold tracking-[-0.035em] sm:text-[2.125rem]"
                    >
                        Masukkan PIN
                    </h1>
                    <p class="text-muted-foreground mt-2 text-sm">
                        Enam angka yang Anda buat di halaman profil. PIN menjadi langkah kedua setiap kali masuk.
                    </p>
                </div>

                <form class="mt-8" novalidate @submit.prevent="submit">
                    <OtpCodeInput
                        v-model="pin"
                        data-testid="two-factor-pin"
                        label="PIN 6 angka"
                        :invalid="pinError !== undefined"
                        :disabled="isLocked"
                        :described-by="pinDescriptionIds"
                    />

                    <p
                        v-if="pinError"
                        id="two-factor-pin-error"
                        role="alert"
                        class="text-destructive mt-3 flex items-start justify-center gap-1.5 text-sm font-medium"
                    >
                        <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        {{ pinError }}
                    </p>

                    <p
                        v-if="notice"
                        role="alert"
                        class="text-destructive mt-3 flex items-start justify-center gap-1.5 text-sm font-medium"
                    >
                        <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        {{ notice }}
                    </p>

                    <div
                        v-if="isLocked"
                        data-testid="two-factor-lock"
                        class="border-warning/40 bg-warning/10 mt-4 rounded-lg border px-3 py-2 text-left"
                    >
                        <p class="text-foreground text-xs font-medium">
                            PIN terkunci sementara karena percobaan salah berulang.
                        </p>
                        <p v-if="lockLabel !== ''" class="text-foreground mt-1 text-xs">
                            Coba lagi setelah {{ lockLabel }}, atau pulihkan akses lewat email.
                        </p>
                    </div>

                    <p v-else class="text-muted-foreground mt-3 text-center text-xs">
                        Sisa percobaan: {{ attemptsRemaining }}
                    </p>

                    <Button
                        type="submit"
                        size="lg"
                        data-testid="two-factor-submit"
                        class="mt-6 min-h-[44px] w-full"
                        :disabled="!canSubmit"
                    >
                        <span v-if="isSubmitting" class="flex items-center gap-2">
                            <Spinner />
                            Memeriksa PIN
                        </span>
                        <span v-else>Masuk</span>
                    </Button>
                </form>

                <div class="mt-6 text-center">
                    <button
                        type="button"
                        data-testid="two-factor-recovery-trigger"
                        class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/40 focus-visible:ring-offset-background inline-flex min-h-[44px] items-center justify-center text-sm font-medium underline-offset-4 transition-colors hover:underline focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                        @click="isRecoveryOpen = true"
                    >
                        Lupa PIN?
                    </button>
                </div>
            </template>
        </div>
    </AuthLayout>
</template>
