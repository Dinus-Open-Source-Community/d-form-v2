<script setup lang="ts">
import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { CircleAlert } from 'lucide-vue-next';
import AuthLayout from '@/layouts/AuthLayout.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import OtpCodeInput from '@/components/core/OtpCodeInput.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { useTwoFactorChallenge } from '@/hooks/useTwoFactorChallenge';

/**
 * Pemulihan PIN dua langkah: buktikan kendali email lewat OTP, lalu buat PIN baru.
 * Halaman ini dirender sebagai langkah lanjutan dari layar tantangan (tidak ada route
 * GET tersendiri), dan tetap sah bila nanti dipasang sebagai halaman Inertia sendiri.
 */
defineOptions({ layout: AuthLayout });

const emit = defineEmits<{
    /** Kembali ke layar tantangan tanpa mengubah PIN. */
    cancel: [];
}>();

const page = usePage();

const {
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
} = useTwoFactorChallenge();

const email: ComputedRef<string> = computed<string>(() => page.props.auth.user?.email ?? '');

const stepLabel: ComputedRef<string> = computed<string>(() =>
    recoveryStep.value === 'pin' ? 'Langkah 2 dari 2: buat PIN baru' : 'Langkah 1 dari 2: konfirmasi email'
);

const isRequestingCode: ComputedRef<boolean> = computed<boolean>(() => recoveryStep.value === 'request');

const isSendingStep: ComputedRef<boolean> = computed<boolean>(() =>
    recoveryStep.value === 'code' ? isConfirmingRecovery.value : isSendingRecovery.value
);

const canSubmitCodeStep: ComputedRef<boolean> = computed<boolean>(() =>
    recoveryStep.value === 'code' ? canConfirmRecovery.value : !isSendingRecovery.value
);

const codeStepLabel: ComputedRef<string> = computed<string>(() =>
    recoveryStep.value === 'code' ? 'Konfirmasi kode' : 'Kirim kode'
);

const newPinDescriptionIds: ComputedRef<string | undefined> = computed<string | undefined>(() =>
    newPinError.value === undefined ? undefined : 'two-factor-new-pin-error'
);

/** Langkah 1 meminta kode atau mengonfirmasinya; langkah 2 menyimpan PIN baru. */
async function submitCodeStep(): Promise<void> {
    if (!canSubmitCodeStep.value) return;

    if (recoveryStep.value === 'code') {
        await confirmRecovery();

        return;
    }

    await sendRecovery();
}

/** Tutup pemulihan dan kembalikan layar ke tantangan PIN. */
function cancelRecovery(): void {
    restartRecovery();
    emit('cancel');
}
</script>

<template>
    <div class="mx-auto w-full max-w-md">
        <SeoHead
            title="Pemulihan PIN"
            description="Pulihkan akses akun dengan kode email lalu buat PIN baru untuk langkah kedua saat masuk."
            canonical-path="/auth/two-factor"
            noindex
        />

        <div class="text-center">
            <h1
                class="font-display text-foreground text-[1.875rem] leading-tight font-bold tracking-[-0.035em] sm:text-[2.125rem]"
            >
                Pemulihan PIN
            </h1>
            <p class="text-muted-foreground mt-1 text-xs font-medium">{{ stepLabel }}</p>
            <p class="text-muted-foreground mt-2 text-sm break-words">
                Kode 6 angka kami kirim ke
                <span class="text-foreground font-medium">{{ email }}</span
                >.
            </p>
        </div>

        <form
            v-if="!isRequestingCode && recoveryStep !== 'pin'"
            class="mt-8"
            novalidate
            @submit.prevent="submitCodeStep"
        >
            <OtpCodeInput
                v-model="recoveryCode"
                data-testid="two-factor-recovery-code"
                label="Kode pemulihan 6 angka"
                :invalid="recoveryNotice !== undefined"
                :disabled="isConfirmingRecovery"
            />

            <p
                v-if="recoveryNotice"
                role="alert"
                class="text-destructive mt-3 flex items-start justify-center gap-1.5 text-sm font-medium"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                {{ recoveryNotice }}
            </p>

            <Button
                type="submit"
                size="lg"
                data-testid="two-factor-recovery-submit"
                class="mt-6 min-h-[44px] w-full"
                :disabled="!canSubmitCodeStep"
            >
                <span v-if="isSendingStep" class="flex items-center gap-2">
                    <Spinner />
                    Memeriksa kode
                </span>
                <span v-else>{{ codeStepLabel }}</span>
            </Button>
        </form>

        <div v-else-if="isRequestingCode" class="mt-8">
            <p class="text-muted-foreground text-sm">
                Tekan kirim, lalu masukkan kode 6 angka yang kami kirim ke email Anda.
            </p>

            <p
                v-if="recoveryNotice"
                role="alert"
                class="text-destructive mt-3 flex items-start gap-1.5 text-sm font-medium"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                {{ recoveryNotice }}
            </p>

            <Button
                type="button"
                size="lg"
                data-testid="two-factor-recovery-submit"
                class="mt-6 min-h-[44px] w-full"
                :disabled="isSendingRecovery"
                @click="sendRecovery"
            >
                <span v-if="isSendingRecovery" class="flex items-center gap-2">
                    <Spinner />
                    Mengirim kode
                </span>
                <span v-else>Kirim kode</span>
            </Button>
        </div>

        <form v-else class="mt-8" novalidate @submit.prevent="submitNewPin">
            <p class="text-muted-foreground text-sm">
                Buat PIN 6 angka yang mudah Anda ingat. PIN ini diminta setiap kali Anda masuk.
            </p>

            <div class="mt-6 grid gap-5">
                <div class="grid gap-2">
                    <p class="text-foreground text-sm font-medium">PIN baru</p>
                    <OtpCodeInput
                        v-model="newPin"
                        data-testid="two-factor-new-pin"
                        label="PIN baru 6 angka"
                        :invalid="newPinError !== undefined"
                        :described-by="newPinDescriptionIds"
                    />
                </div>

                <div class="grid gap-2">
                    <p class="text-foreground text-sm font-medium">Konfirmasi PIN baru</p>
                    <OtpCodeInput
                        v-model="newPinConfirmation"
                        data-testid="two-factor-new-pin-confirm"
                        label="Konfirmasi PIN baru 6 angka"
                        :invalid="newPinError !== undefined"
                        :described-by="newPinDescriptionIds"
                    />
                </div>
            </div>

            <p
                v-if="newPinError"
                id="two-factor-new-pin-error"
                role="alert"
                class="text-destructive mt-3 flex items-start gap-1.5 text-sm font-medium"
            >
                <CircleAlert class="mt-0.5 size-4 shrink-0" aria-hidden="true" />
                {{ newPinError }}
            </p>

            <Button
                type="submit"
                size="lg"
                data-testid="two-factor-reset-submit"
                class="mt-6 min-h-[44px] w-full"
                :disabled="!canSubmitNewPin"
            >
                <span v-if="isSavingNewPin" class="flex items-center gap-2">
                    <Spinner />
                    Menyimpan PIN
                </span>
                <span v-else>Simpan PIN baru</span>
            </Button>
        </form>

        <button
            type="button"
            class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/40 focus-visible:ring-offset-background mt-6 inline-flex min-h-[44px] w-full items-center justify-center text-sm font-medium underline-offset-4 transition-colors hover:underline focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
            @click="cancelRecovery"
        >
            Kembali ke PIN
        </button>
    </div>
</template>
