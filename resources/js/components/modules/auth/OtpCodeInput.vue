<script setup lang="ts">
import { toRef } from 'vue';
import { useOtpCodeInput } from '@/hooks/useOtpCodeInput';

type Props = {
    /** Nilai gabungan semua kotak (6 digit). */
    modelValue: string;
    /** Banyak kotak kode; default 6 sesuai validasi server. */
    length?: number;
    /** Tandai kotak dengan gaya error (border dan ring destruktif). */
    invalid?: boolean;
    /** Nonaktifkan seluruh kotak, mis. saat kode sudah kedaluwarsa. */
    disabled?: boolean;
    /** id elemen bantuan/error untuk `aria-describedby` grup. */
    describedBy?: string;
};

const props = withDefaults(defineProps<Props>(), {
    length: 6,
    invalid: false,
    disabled: false,
    describedBy: undefined,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    complete: [value: string];
}>();

const { digits, setInputElement, handleInput, handleKeydown, handlePaste, handleFocus } = useOtpCodeInput({
    modelValue: toRef(props, 'modelValue'),
    length: props.length,
    onChange: (value) => emit('update:modelValue', value),
    onComplete: (value) => emit('complete', value),
});
</script>

<template>
    <div
        class="grid grid-cols-6 gap-2 sm:gap-2.5"
        role="group"
        :aria-label="`Kode verifikasi ${length} digit`"
        :aria-describedby="describedBy"
    >
        <input
            v-for="index in length"
            :key="index"
            :ref="setInputElement(index - 1)"
            type="text"
            :value="digits[index - 1]"
            inputmode="numeric"
            maxlength="1"
            :autocomplete="index === 1 ? 'one-time-code' : 'off'"
            :aria-label="`Digit ${index} dari ${length}`"
            :aria-invalid="invalid"
            :disabled="disabled"
            :data-filled="digits[index - 1] === '' ? undefined : 'true'"
            class="h-14 w-full rounded-xl border border-input bg-card text-center font-display text-base font-semibold tabular-nums text-foreground shadow-xs transition-[color,border-color,box-shadow] duration-200 outline-none hover:border-primary/30 focus:border-ring focus:ring-[3px] focus:ring-ring/30 aria-invalid:border-destructive aria-invalid:bg-destructive/5 aria-invalid:focus:ring-destructive/20 data-[filled=true]:border-foreground/30 disabled:cursor-not-allowed disabled:opacity-60 sm:h-16 sm:text-2xl"
            @input="handleInput(index - 1, $event)"
            @keydown="handleKeydown(index - 1, $event)"
            @paste="handlePaste(index - 1, $event)"
            @focus="handleFocus"
        />
    </div>
</template>
