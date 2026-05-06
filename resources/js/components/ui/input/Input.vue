<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import { useVModel } from '@vueuse/core';
import { cn } from '@/lib/utils';

const props = defineProps<{
    defaultValue?: string | number;
    modelValue?: string | number;
    class?: HTMLAttributes['class'];
}>();

const emits = defineEmits<{
    (e: 'update:modelValue', payload: string | number): void;
}>();

const modelValue = useVModel(props, 'modelValue', emits, {
    passive: false,
    defaultValue: props.defaultValue,
});
</script>

<template>
    <input
        v-model="modelValue"
        data-slot="input"
        :class="
            cn(
                'file:text-foreground placeholder:text-muted-foreground selection:bg-primary/20 selection:text-foreground border-input h-10 w-full min-w-0 rounded-xl border bg-card px-3 py-1 text-sm font-medium text-foreground shadow-xs transition-[border-color,box-shadow,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 md:text-sm',
                'hover:border-primary/30 focus-visible:border-ring focus-visible:ring-ring/30 focus-visible:ring-[3px]',
                'aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive',
                props.class
            )
        "
    />
</template>
