<script setup lang="ts">
import type { IUserRoleOption } from '@/types/user-management';

/**
 * Pilihan role sebagai kartu radio: konsekuensi tiap role terbaca sebelum dipilih.
 * Nilai dikirim lewat `update:modelValue` sehingga komponen pemanggil tetap pemilik state.
 */
defineProps<{
    modelValue: string;
    options: IUserRoleOption[];
    /** Pesan validasi server untuk role; ditampilkan di bawah grup. */
    error?: string;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();
</script>

<template>
    <fieldset class="min-w-0" aria-required="true">
        <legend class="text-foreground text-sm font-medium">
            Role dan akses <span class="text-destructive" aria-hidden="true">*</span>
        </legend>
        <p class="text-muted-foreground mt-1 text-xs">
            Role menentukan menu dan tindakan yang bisa dilakukan akun ini.
        </p>

        <div class="mt-2 grid gap-2 sm:grid-cols-2">
            <label
                v-for="option in options"
                :key="option.value"
                data-testid="user-role-option"
                :data-role="option.value"
                class="border-border bg-card hover:border-primary/30 has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:focus-visible]:ring-ring/30 relative flex cursor-pointer flex-col gap-1 rounded-xl border p-3 transition-colors has-[:focus-visible]:ring-[3px]"
            >
                <input
                    type="radio"
                    name="user-account-role"
                    class="sr-only"
                    :value="option.value"
                    :checked="option.value === modelValue"
                    :aria-invalid="error ? true : undefined"
                    @change="emit('update:modelValue', option.value)"
                />
                <span class="text-foreground text-sm font-medium">{{ option.label }}</span>
                <span class="text-muted-foreground text-xs leading-relaxed">{{ option.description }}</span>
            </label>
        </div>

        <p v-if="error" role="alert" class="text-destructive mt-2 text-xs">{{ error }}</p>
    </fieldset>
</template>
