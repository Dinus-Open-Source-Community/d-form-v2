<script setup lang="ts">
import { computed } from 'vue';
import { Label } from '@/components/ui/label';
import ComboboxTagInput from '@/components/modules/dashboard/events/ComboboxTagInput.vue';

export interface IMultiValueOption {
    value: string;
    label: string;
}

const props = withDefaults(
    defineProps<{
        options: IMultiValueOption[];
        label: string;
        description?: string;
        error?: string;
        /** True saat field sedang animasi shake karena error. */
        shaking?: boolean;
        allowCustom?: boolean;
        maxTags?: number;
        placeholder?: string;
        id?: string;
        /** Tampilkan penanda wajib (*) di label. */
        required?: boolean;
    }>(),
    {
        allowCustom: true,
        placeholder: 'Cari atau ketik lalu Enter',
        required: false,
        shaking: false,
    }
);

const model = defineModel<string>({ default: '' });

const maxTagsEff = computed(() => props.maxTags ?? Number.POSITIVE_INFINITY);
</script>

<template>
    <div :class="['flex flex-col gap-2', shaking ? 'animate-shake' : '']">
        <div class="space-y-1">
            <Label :for="id" class="text-sm font-medium">
                {{ label }}<span v-if="required" class="text-destructive"> *</span>
            </Label>
            <p v-if="description" class="text-muted-foreground text-xs leading-snug">
                {{ description }}
            </p>
        </div>

        <ComboboxTagInput
            :id="id"
            v-model="model"
            :suggestions="options"
            :max-tags="maxTagsEff"
            :allow-custom="allowCustom"
            :placeholder="placeholder"
            :error="!!error"
        />

        <p v-if="error" class="text-destructive text-xs">{{ error }}</p>
    </div>
</template>
