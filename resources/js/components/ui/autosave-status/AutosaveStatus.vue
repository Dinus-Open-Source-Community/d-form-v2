<script setup lang="ts">
import { computed } from 'vue';
import { Loader2 } from 'lucide-vue-next';
import type { AutosaveStatus } from '@/hooks/useAutosaveSync';

export type TAutosaveStatusVariant = 'inline' | 'block';

interface IAutosaveStatusProps {
    status: AutosaveStatus;
    savedText?: string;
    savingText?: string;
    uploadingText?: string;
    errorText?: string;
    variant?: TAutosaveStatusVariant;
}

const props = withDefaults(defineProps<IAutosaveStatusProps>(), {
    savedText: 'Tersimpan',
    savingText: 'Menyimpan…',
    uploadingText: 'Mengunggah gambar…',
    errorText: 'Gagal menyimpan otomatis',
    variant: 'inline',
});

const emit = defineEmits<{ retry: [] }>();

const displayText = computed((): string => {
    if (props.status === 'saving') return props.savingText;
    if (props.status === 'uploading') return props.uploadingText;
    if (props.status === 'saved') return props.savedText;
    if (props.status === 'error') return props.errorText;
    return '';
});

const showSpinner = computed((): boolean => props.status === 'saving' || props.status === 'uploading');
const isError = computed((): boolean => props.status === 'error');

/** Teruskan aksi retry ke halaman pemilik autosave. */
function requestRetry(): void {
    emit('retry');
}
</script>

<template>
    <!-- Live region selalu ter-mount agar perubahan status diumumkan pembaca layar. -->
    <span aria-live="polite" data-testid="autosave-status">
        <span
            v-if="props.status !== 'idle'"
            :class="[
                'flex items-center gap-1.5 text-xs',
                props.variant === 'block' ? 'justify-center' : 'shrink-0',
                isError ? 'text-destructive' : 'text-muted-foreground',
            ]"
        >
            <Loader2 v-if="showSpinner" class="size-3 animate-spin" aria-hidden="true" />
            <span
                v-else
                class="size-1.5 rounded-full"
                :class="isError ? 'bg-destructive' : 'bg-emerald-500'"
                aria-hidden="true"
            />
            <span>{{ displayText }}</span>
            <!-- Tombol tetap ter-mount selama saving agar fokus keyboard tidak hilang
                 saat status berpindah error → saving. -->
            <button
                v-if="isError || props.status === 'saving'"
                type="button"
                class="focus-visible:ring-destructive/40 rounded-sm underline underline-offset-2 hover:no-underline focus-visible:ring-2 focus-visible:outline-none disabled:no-underline disabled:opacity-60"
                aria-label="Coba lagi menyimpan otomatis"
                :disabled="props.status === 'saving'"
                @click="requestRetry"
            >
                Coba lagi
            </button>
        </span>
    </span>
</template>
