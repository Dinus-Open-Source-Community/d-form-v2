<script setup lang="ts">
import { useId } from 'vue';
import FileUploadRow from '@/components/core/FileUploadRow.vue';
import { Trash2 } from 'lucide-vue-next';
import type { TUploadRowState } from '@/lib/fileUploadRow';
import { formatFileSize } from '@/lib/fileDisplay';
import { DEFAULT_MULTIPLE_UPLOAD_MAX_FILES, DEFAULT_MULTIPLE_UPLOAD_MAX_TOTAL_SIZE_KB } from '@/lib/uploadValidation';

const props = withDefaults(
    defineProps<{
        files: readonly File[];
        previewUrls: readonly string[];
        image: boolean;
        accept?: string;
        hint?: string;
        rowState: TUploadRowState;
        fieldError?: string;
        rowStates?: readonly TUploadRowState[];
        rowErrorMessages?: readonly string[];
        maxFiles?: number;
        maxTotalSizeKb?: number;
        progress?: number;
        disabled?: boolean;
    }>(),
    {
        accept: undefined,
        hint: '',
        fieldError: '',
        rowStates: () => [],
        rowErrorMessages: () => [],
        maxFiles: DEFAULT_MULTIPLE_UPLOAD_MAX_FILES,
        maxTotalSizeKb: DEFAULT_MULTIPLE_UPLOAD_MAX_TOTAL_SIZE_KB,
        progress: 0,
        disabled: false,
    }
);

const emit = defineEmits<{
    selectFiles: [files: File[]];
    removeFile: [index: number];
}>();

const inputId = useId();
const totalSizeLabel = formatFileSize(props.maxTotalSizeKb * 1024);

function isFailedRow(index: number): boolean {
    const state = props.rowStates[index] ?? props.rowState;

    return state === 'error' || state === 'failed';
}

function onFilesChanged(event: Event): void {
    const target = event.target;
    if (!(target instanceof HTMLInputElement)) return;

    const newlySelected = Array.from(target.files ?? []);
    if (newlySelected.length > 0) emit('selectFiles', [...props.files, ...newlySelected]);
    target.value = '';
}
</script>

<template>
    <div class="w-full min-w-0 space-y-2">
        <div v-if="files.length > 0" class="flex min-w-0 flex-col gap-2">
            <template v-if="image">
                <figure
                    v-for="(file, index) in files"
                    :key="`${file.name}-${file.lastModified}-${index}`"
                    class="border-border bg-card flex min-w-0 items-start gap-3 overflow-hidden rounded-xl border p-2 shadow-xs"
                    :class="isFailedRow(index) ? 'border-destructive/50' : ''"
                >
                    <img
                        v-if="previewUrls[index]"
                        :src="previewUrls[index]"
                        :alt="file.name"
                        :aria-describedby="isFailedRow(index) ? `${inputId}-image-error-${index}` : undefined"
                        class="bg-muted size-16 shrink-0 rounded-lg object-cover"
                        loading="lazy"
                        decoding="async"
                    />
                    <div v-else class="bg-muted size-16 shrink-0 rounded-lg" aria-hidden="true" />
                    <figcaption class="flex min-w-0 flex-1 items-start gap-2">
                        <div class="flex min-w-0 flex-1 flex-col">
                            <span class="text-foreground min-w-0 text-sm font-medium break-words">{{ file.name }}</span>
                            <p
                                v-if="isFailedRow(index)"
                                :id="`${inputId}-image-error-${index}`"
                                class="text-destructive mt-1 text-xs font-medium"
                                role="alert"
                            >
                                {{ rowErrorMessages[index] || 'Berkas gagal diunggah.' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:ring-ring/30 grid size-8 shrink-0 place-items-center rounded-lg outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-50"
                            :disabled="disabled"
                            :aria-label="`Hapus ${file.name}`"
                            @click="emit('removeFile', index)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </button>
                    </figcaption>
                </figure>
            </template>
            <FileUploadRow
                v-else
                v-for="(file, index) in files"
                :key="`${file.name}-${file.lastModified}-${index}`"
                :state="rowStates[index] ?? rowState"
                :file-name="file.name"
                :file-size="file.size"
                :mime="file.type"
                :progress="progress"
                :error-message="rowErrorMessages[index] ?? ''"
                :disabled="disabled"
                @remove="emit('removeFile', index)"
            />
        </div>

        <label
            class="border-border bg-background hover:border-primary/40 hover:bg-primary/5 focus-within:ring-ring/30 relative flex min-h-11 w-full cursor-pointer items-center justify-center rounded-xl border border-dashed px-4 py-3 text-sm font-medium transition-colors focus-within:ring-[3px]"
            :class="disabled ? 'pointer-events-none opacity-50' : ''"
        >
            <span>{{ files.length > 0 ? 'Tambah berkas' : image ? 'Pilih foto' : 'Pilih berkas' }}</span>
            <input
                :id="inputId"
                type="file"
                class="absolute inset-0 h-full w-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
                :accept="accept"
                :disabled="disabled"
                multiple
                :aria-label="files.length > 0 ? 'Tambah berkas' : image ? 'Pilih foto' : 'Pilih berkas'"
                :aria-describedby="fieldError ? `${inputId}-error` : undefined"
                @change="onFilesChanged"
            />
        </label>

        <p v-if="fieldError" :id="`${inputId}-error`" class="text-destructive text-xs font-medium" role="alert">
            {{ fieldError }}
        </p>
        <p v-if="hint" class="text-muted-foreground text-xs">{{ hint }}</p>
        <p class="text-muted-foreground text-xs">
            Pilih hingga {{ maxFiles }} berkas, maksimal {{ totalSizeLabel }} total.
        </p>
    </div>
</template>
