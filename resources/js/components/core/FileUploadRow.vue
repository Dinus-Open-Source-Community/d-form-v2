<script setup lang="ts">
import { computed } from 'vue';
import { CircleCheck, CircleX, LoaderCircle, Trash2, X } from 'lucide-vue-next';
import FileTypeTile from '@/components/core/FileTypeTile.vue';
import { formatFileSize } from '@/lib/fileDisplay';
import { uploadRowPercentLabel, uploadRowPhase, uploadRowStatusLabel, type TUploadRowState } from '@/lib/fileUploadRow';

const props = withDefaults(
    defineProps<{
        state: TUploadRowState;
        fileName?: string;
        fileSize?: number;
        mime?: string;
        /** Persentase 0..100 dari adapter upload. */
        progress?: number;
        errorMessage?: string;
        removable?: boolean;
        disabled?: boolean;
    }>(),
    {
        fileName: '',
        fileSize: 0,
        mime: '',
        progress: 0,
        errorMessage: '',
        removable: true,
        disabled: false,
    }
);

const emit = defineEmits<{
    retry: [];
    cancel: [];
    remove: [];
}>();

const phase = computed(() => uploadRowPhase(props.state));
const statusLabel = computed(() => uploadRowStatusLabel(phase.value));
const sizeLabel = computed(() => formatFileSize(props.fileSize));
/** Fase selesai selalu 100%; adapter boleh berhenti di 0 saat redirect selesai. */
const percent = computed(() => uploadRowPercentLabel(phase.value === 'completed' ? 100 : props.progress));
const percentValue = computed(() => Number.parseInt(percent.value, 10));
const displayName = computed(() => (props.fileName.trim() === '' ? 'Berkas' : props.fileName));

const isUploading = computed(() => phase.value === 'uploading');
const isFailed = computed(() => phase.value === 'failed');
const showProgressBar = computed(() => phase.value === 'uploading' || phase.value === 'completed');

const statusIcon = computed(() => {
    if (phase.value === 'uploading') return LoaderCircle;
    if (phase.value === 'failed') return CircleX;

    return CircleCheck;
});

/** Satu nada merah untuk status, pesan galat, dan `Coba lagi` pada baris gagal. */
const FAILED_TEXT = 'text-red-700 dark:text-red-400';

const statusTone = computed(() => {
    if (phase.value === 'uploading') return 'text-muted-foreground';
    if (phase.value === 'failed') return FAILED_TEXT;

    return 'text-success';
});

const barTone = computed(() => {
    if (phase.value === 'completed') return 'bg-success';
    if (phase.value === 'failed') return 'bg-destructive';

    return 'bg-primary';
});

const rowTone = computed(() =>
    isFailed.value ? 'border-red-300 bg-red-50 dark:border-red-800/70 dark:bg-red-950/30' : 'border-border bg-card'
);

/** Ikon kanan: batal saat mengunggah, hapus setelah selesai/gagal. */
const actionLabel = computed(() =>
    isUploading.value ? `Batalkan unggah ${displayName.value}` : `Hapus ${displayName.value}`
);

function onAction(): void {
    if (props.disabled) return;

    if (isUploading.value) {
        emit('cancel');

        return;
    }

    emit('remove');
}
</script>

<template>
    <div class="flex flex-col gap-2 rounded-xl border px-3 py-2.5 shadow-xs transition-colors" :class="rowTone">
        <div class="flex items-start gap-3">
            <FileTypeTile :mime="props.mime" :file-name="props.fileName" />

            <div class="min-w-0 flex-1">
                <p class="text-foreground truncate text-sm font-medium" :title="props.fileName">
                    {{ props.fileName }}
                </p>
                <p class="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs">
                    <span class="tabular-nums">{{ sizeLabel }}</span>
                    <span aria-hidden="true">·</span>
                    <span class="inline-flex items-center gap-1" :class="statusTone" aria-live="polite">
                        <component
                            :is="statusIcon"
                            class="size-3.5 shrink-0"
                            :class="isUploading ? 'animate-spin motion-reduce:animate-none' : ''"
                            aria-hidden="true"
                        />
                        {{ statusLabel }}
                    </span>
                </p>
                <p
                    v-if="isFailed && props.errorMessage"
                    :class="FAILED_TEXT"
                    class="mt-1 line-clamp-2 text-[11px] leading-snug"
                    :title="props.errorMessage"
                >
                    {{ props.errorMessage }}
                </p>
                <button
                    v-if="isFailed"
                    type="button"
                    :class="FAILED_TEXT"
                    class="mt-0.5 -ml-1 inline-flex min-h-7 items-center gap-1 rounded-md px-1 py-0.5 text-xs font-medium underline-offset-2 outline-none hover:underline focus-visible:ring-[3px] focus-visible:ring-red-500/30 disabled:pointer-events-none disabled:opacity-50"
                    :disabled="props.disabled"
                    :title="`Mulai unggah ulang ${displayName} dari awal`"
                    @click="emit('retry')"
                >
                    Coba lagi
                </button>
            </div>

            <button
                v-if="props.removable || isUploading"
                type="button"
                class="text-muted-foreground hover:bg-muted hover:text-foreground focus-visible:ring-ring/30 grid size-8 shrink-0 place-items-center rounded-lg transition-colors outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-50"
                :class="
                    isFailed
                        ? 'hover:bg-red-100 hover:text-red-700 dark:hover:bg-red-900/40 dark:hover:text-red-300'
                        : ''
                "
                :disabled="props.disabled"
                :aria-label="actionLabel"
                @click="onAction"
            >
                <X v-if="isUploading" class="size-4" aria-hidden="true" />
                <Trash2 v-else class="size-4" aria-hidden="true" />
            </button>
        </div>

        <div v-if="showProgressBar" class="flex items-center gap-2">
            <div
                v-if="isUploading"
                class="bg-muted h-1.5 min-w-0 flex-1 overflow-hidden rounded-full"
                role="progressbar"
                :aria-valuenow="percentValue"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="`Progres unggah ${displayName}`"
            >
                <div
                    class="h-full rounded-full transition-[width] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] motion-reduce:transition-none"
                    :class="barTone"
                    :style="{ width: `${percent}` }"
                />
            </div>
            <div v-else class="bg-muted h-1.5 min-w-0 flex-1 overflow-hidden rounded-full" aria-hidden="true">
                <div class="h-full w-full rounded-full" :class="barTone" />
            </div>
            <span class="text-muted-foreground w-9 shrink-0 text-right text-[11px] tabular-nums">{{ percent }}</span>
        </div>
    </div>
</template>
