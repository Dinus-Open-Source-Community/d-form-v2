<script setup lang="ts">
import { computed, useId } from 'vue';
import { Input } from '@/components/ui/input';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';

/**
 * Dialog konfirmasi yang mensyaratkan teks tertentu diketik ulang.
 * Presentational murni: tidak menyimpan target, tidak memanggil jaringan; teks
 * yang diketik diproksikan lewat `v-model` agar pemilik state bisa meresetnya.
 * Kerangka dialog (header/footer/open-forwarding) memakai `ConfirmationModal`.
 */
const props = defineProps<{
    open: boolean;
    title: string;
    description: string;
    expectedText: string;
    confirmText?: string;
    cancelText?: string;
    loading?: boolean;
    /** Label input; default domain-netral diturunkan dari `expectedText`. */
    inputLabel?: string;
}>();

const emit = defineEmits<{
    confirm: [];
    cancel: [];
    'update:open': [value: boolean];
}>();

/** `update:modelValue` dideklarasikan oleh `defineModel` (string, default kosong). */
const typedText = defineModel<string>({ default: '' });

const isConfirmed = computed<boolean>(() => typedText.value.trim() === props.expectedText.trim());

const inputId = useId();
const resolvedInputLabel = computed<string>(
    () => props.inputLabel ?? `Ketik "${props.expectedText}" untuk konfirmasi.`
);
</script>

<template>
    <ConfirmationModal
        :open="open"
        :title="title"
        :description="description"
        :confirm-text="confirmText"
        :cancel-text="cancelText"
        :loading="loading"
        variant="destructive"
        :confirm-disabled="!isConfirmed"
        @confirm="emit('confirm')"
        @cancel="emit('cancel')"
        @update:open="(value: boolean) => emit('update:open', value)"
    >
        <template #extra>
            <div class="space-y-2 text-left">
                <label :for="inputId" class="text-muted-foreground block text-xs">{{ resolvedInputLabel }}</label>
                <Input
                    :id="inputId"
                    v-model="typedText"
                    autocomplete="off"
                    :aria-invalid="typedText.length > 0 && !isConfirmed"
                />
            </div>
        </template>
    </ConfirmationModal>
</template>
