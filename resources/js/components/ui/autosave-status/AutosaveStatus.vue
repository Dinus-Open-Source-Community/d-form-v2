<script setup lang="ts">
import { computed } from 'vue';
import { Loader2 } from 'lucide-vue-next';
import type { AutosaveStatus } from '@/utils/composables/useAutosaveSync';

export type TAutosaveStatusVariant = 'inline' | 'block';

interface IAutosaveStatusProps {
    status: AutosaveStatus;
    savedText?: string;
    savingText?: string;
    uploadingText?: string;
    variant?: TAutosaveStatusVariant;
}

const props = withDefaults(defineProps<IAutosaveStatusProps>(), {
    savedText: 'Tersimpan',
    savingText: 'Menyimpan…',
    uploadingText: 'Mengunggah gambar…',
    variant: 'inline',
});

const displayText = computed((): string => {
    if (props.status === 'saving') return props.savingText;
    if (props.status === 'uploading') return props.uploadingText;
    if (props.status === 'saved') return props.savedText;
    return '';
});

const showSpinner = computed((): boolean => props.status === 'saving' || props.status === 'uploading');
</script>

<template>
    <p
        v-if="props.variant === 'block' && props.status !== 'idle'"
        aria-live="polite"
        class="text-muted-foreground flex items-center justify-center gap-1.5 text-xs"
    >
        <Loader2 v-if="showSpinner" class="size-3 animate-spin" aria-hidden="true" />
        <span v-else class="size-1.5 rounded-full bg-emerald-500" aria-hidden="true" />
        {{ displayText }}
    </p>
    <span
        v-else-if="props.variant === 'inline' && props.status !== 'idle'"
        aria-live="polite"
        class="text-muted-foreground flex shrink-0 items-center gap-1.5 text-xs"
    >
        <Loader2 v-if="showSpinner" class="size-3 animate-spin" aria-hidden="true" />
        <span v-else class="size-1.5 rounded-full bg-emerald-500" aria-hidden="true" />
        {{ displayText }}
    </span>
</template>
