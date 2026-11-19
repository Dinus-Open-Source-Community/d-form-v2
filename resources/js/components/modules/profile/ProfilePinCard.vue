<script setup lang="ts">
import { computed, nextTick } from 'vue';
import type { ComputedRef } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import OtpCodeInput from '@/components/core/OtpCodeInput.vue';
import { useTwoFactorSettings } from '@/hooks/useTwoFactorSettings';
import type { TTwoFactorPinDialogMode } from '@/hooks/useTwoFactorSettings';

/**
 * Kartu PIN di bagian Keamanan profil: satu baris berisi label dan sakelar.
 * Menyalakan sakelar membuka dialog buat PIN, mematikannya membuka dialog
 * nonaktifkan; status sakelar mengikuti props halaman, bukan niat pengguna.
 */
const props = defineProps<{
    /** True bila akun punya kata sandi lokal; akun OAuth-only tidak ditanya kata sandi. */
    hasLocalPassword: boolean;
}>();

const page = usePage();

const {
    mode,
    isOpen,
    isSubmitting,
    checked,
    currentPassword,
    currentPin,
    newPin,
    newPinConfirmation,
    requiresCurrentPassword,
    requiresCurrentPin,
    requiresNewPin,
    canSubmit,
    errors,
    dialogTitle,
    submitLabel,
    requestToggle,
    close,
    submit,
} = useTwoFactorSettings({ pageProps: page.props, hasLocalPassword: props.hasLocalPassword });

const DIALOG_DESCRIPTIONS: Readonly<Record<TTwoFactorPinDialogMode, string>> = {
    enable: 'PIN 6 angka diminta setiap kali Anda masuk, setelah kata sandi atau akun Google/GitHub.',
    change: 'Masukkan PIN saat ini lalu tentukan PIN baru. PIN lama tidak berlaku setelah disimpan.',
    disable: 'Masuk tidak lagi meminta PIN. Anda bisa mengaktifkannya lagi kapan saja.',
};

const dialogDescription: ComputedRef<string> = computed<string>(() =>
    mode.value === null ? '' : DIALOG_DESCRIPTIONS[mode.value]
);

/**
 * Tutup hanya saat tidak ada permintaan berjalan; kegagalan tetap membuka dialog dengan pesan.
 *
 * `AlertDialogAction` menutup dialog (`update:open(false)`) sebelum event konfirmasi sampai
 * ke `submit()`, sehingga penutupan ditunda satu tick: submit sempat mengirim permintaan dan
 * menandai `isSubmitting`. Sesudahnya dialog hanya ditutup oleh hasil aksi, bukan oleh klik.
 */
function handleOpenChange(value: boolean): void {
    if (value) return;

    void nextTick((): void => {
        if (isSubmitting.value) return;

        close();
    });
}
</script>

<template>
    <div data-testid="profile-pin-card" class="min-w-0">
        <div class="flex min-h-[44px] items-center justify-between gap-4">
            <span class="text-foreground text-sm font-medium">PIN masuk</span>

            <div class="flex min-h-[44px] shrink-0 items-center">
                <Switch
                    id="profile-pin-toggle"
                    data-testid="profile-pin-toggle"
                    aria-label="PIN masuk"
                    :model-value="checked"
                    :disabled="isSubmitting"
                    @update:model-value="requestToggle"
                />
            </div>
        </div>

        <ConfirmationModal
            :open="isOpen"
            :title="dialogTitle"
            :description="dialogDescription"
            :confirm-text="submitLabel"
            cancel-text="Batal"
            confirm-test-id="profile-pin-submit"
            :variant="mode === 'disable' ? 'destructive' : 'default'"
            :loading="isSubmitting"
            :confirm-disabled="!canSubmit"
            @confirm="submit"
            @cancel="close"
            @update:open="handleOpenChange"
        >
            <template #extra>
                <div class="mt-4 grid gap-4 text-left">
                    <div v-if="requiresCurrentPassword" class="grid gap-2">
                        <Label for="profile-pin-password">Kata sandi saat ini</Label>
                        <Input
                            id="profile-pin-password"
                            v-model="currentPassword"
                            data-testid="profile-pin-password"
                            type="password"
                            autocomplete="current-password"
                            placeholder="Kata sandi saat ini"
                            class="h-[44px]"
                            :aria-invalid="errors.current_password !== ''"
                        />
                        <p v-if="errors.current_password" role="alert" class="text-destructive text-sm">
                            {{ errors.current_password }}
                        </p>
                    </div>

                    <div v-if="requiresCurrentPin" class="grid gap-2">
                        <p class="text-foreground text-sm font-medium">PIN saat ini</p>
                        <OtpCodeInput
                            v-model="currentPin"
                            data-testid="profile-pin-current"
                            label="PIN saat ini 6 angka"
                            :invalid="errors.current_pin !== ''"
                        />
                        <p v-if="errors.current_pin" role="alert" class="text-destructive text-sm">
                            {{ errors.current_pin }}
                        </p>
                    </div>

                    <div v-if="requiresNewPin" class="grid gap-2">
                        <p class="text-foreground text-sm font-medium">PIN baru</p>
                        <OtpCodeInput
                            v-model="newPin"
                            data-testid="profile-pin-input"
                            label="PIN baru 6 angka"
                            :invalid="errors.pin !== ''"
                        />
                    </div>

                    <div v-if="requiresNewPin" class="grid gap-2">
                        <p class="text-foreground text-sm font-medium">Konfirmasi PIN baru</p>
                        <OtpCodeInput
                            v-model="newPinConfirmation"
                            data-testid="profile-pin-confirm"
                            label="Konfirmasi PIN baru 6 angka"
                            :invalid="errors.pin !== '' || errors.pin_confirmation !== ''"
                        />
                        <p v-if="errors.pin_confirmation" role="alert" class="text-destructive text-sm">
                            {{ errors.pin_confirmation }}
                        </p>
                    </div>

                    <p v-if="errors.pin" role="alert" class="text-destructive text-sm">{{ errors.pin }}</p>
                </div>
            </template>
        </ConfirmationModal>
    </div>
</template>
