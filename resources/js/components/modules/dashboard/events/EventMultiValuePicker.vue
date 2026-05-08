<script setup lang="ts">
import { computed } from 'vue';
import { Label } from '@/components/ui/label';
import ComboboxTagInput from '@/components/modules/dashboard/events/ComboboxTagInput.vue';

export interface MultiValueOption {
    value: string;
    label: string;
}

const props = withDefaults(
    defineProps<{
        options: MultiValueOption[];
        label: string;
        description?: string;
        error?: string;
        allowCustom?: boolean;
        maxTags?: number;
        placeholder?: string;
        id?: string;
    }>(),
    {
        allowCustom: true,
        placeholder: 'Ketik untuk mencari, pilih dari daftar, atau Enter…',
    },
);

const model = defineModel<string>({ default: '' });

const maxTagsEff = computed(() => props.maxTags ?? Number.POSITIVE_INFINITY);
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="space-y-0.5">
            <Label :for="id" class="text-sm font-medium">{{ label }}</Label>
            <p v-if="description" class="text-muted-foreground text-xs leading-relaxed">{{ description }}</p>
        </div>

        <ComboboxTagInput
            :id="id"
            v-model="model"
            :suggestions="options"
            :max-tags="maxTagsEff"
            :allow-custom="allowCustom"
            :placeholder="placeholder"
        />

        <p v-if="error" class="text-destructive text-xs">{{ error }}</p>
    </div>
</template>
