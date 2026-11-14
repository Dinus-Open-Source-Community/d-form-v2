<script setup lang="ts">
import { computed, getCurrentInstance, ref } from 'vue';
import { CircleCheck, Trash2, Upload } from 'lucide-vue-next';
import { formatFileSize, isPreviewableImage } from '@/lib/fileDisplay';
import { uploadRowPhase, uploadRowStatusLabel, type TUploadRowState } from '@/lib/fileUploadRow';
import FileUploadRow from '@/components/core/FileUploadRow.vue';

/** `error` dipertahankan sebagai nama lama state gagal; `failed` boleh dipakai pemakai baru. */
export type TFileUploadState = TUploadRowState;

const props = withDefaults(
    defineProps<{
        state: TFileUploadState;
        /** Label tombol pilih pada state idle (mis. "Pilih foto", "Unggah PDF"). */
        idleLabel: string;
        hint?: string;
        accept?: string;
        maxSizeKb?: number;
        fileName?: string;
        fileSize?: number;
        mime?: string;
        /** Persentase 0..100 dari adapter upload. */
        progress?: number;
        /** URL server untuk pratinjau gambar (bukan object URL). */
        previewUrl?: string;
        errorMessage?: string;
        removable?: boolean;
        /** Matikan interaksi selama proses (mis. saat simpan). */
        disabled?: boolean;
    }>(),
    {
        hint: '',
        accept: undefined,
        maxSizeKb: undefined,
        fileName: '',
        fileSize: 0,
        mime: '',
        progress: 0,
        previewUrl: '',
        errorMessage: '',
        removable: true,
        disabled: false,
    }
);

const emit = defineEmits<{
    pick: [file: File];
    cancel: [];
    remove: [];
    /** Mulai ulang unggahan dari 0 dengan berkas yang sama (tidak ada resume). */
    retry: [];
}>();

const inputRef = ref<HTMLInputElement | null>(null);
const instance = getCurrentInstance();
const localFileName = ref('');
const localFileSize = ref(0);
const localMime = ref('');

const phase = computed(() => uploadRowPhase(props.state));
const displayFileName = computed(() => props.fileName || localFileName.value);
const displayFileSize = computed(() => (props.fileSize > 0 ? props.fileSize : localFileSize.value));
const displayMime = computed(() => props.mime || localMime.value);
const sizeLabel = computed(() => formatFileSize(displayFileSize.value));
const completedLabel = computed(() => uploadRowStatusLabel('completed'));
const showImagePreview = computed(
    () =>
        phase.value === 'completed' &&
        props.previewUrl !== '' &&
        isPreviewableImage(displayMime.value, displayFileName.value)
);

function openPicker(): void {
    if (props.disabled) return;
    inputRef.value?.click();
}

function onPicked(event: Event): void {
    const target = event.target as HTMLInputElement | null;
    const file = target?.files?.[0];

    if (file === undefined) return;
    localFileName.value = file.name;
    localFileSize.value = file.size;
    localMime.value = file.type;
    emit('pick', file);

    // Reset agar file yang sama bisa dipilih ulang setelah gagal/dihapus.
    if (target !== null) target.value = '';
}

/**
 * Pemakai lama belum mendengarkan `retry`; tanpa listener tombol jatuh ke
 * pemilih berkas seperti perilaku "Pilih ulang" sebelumnya, bukan tombol mati.
 */
function onRetry(): void {
    const listener = instance?.vnode.props?.['onRetry'];

    if (typeof listener === 'function') {
        emit('retry');

        return;
    }

    openPicker();
}

function clearLocalFile(): void {
    localFileName.value = '';
    localFileSize.value = 0;
    localMime.value = '';
}

function onCancel(): void {
    emit('cancel');
    clearLocalFile();
}

function onRemove(): void {
    emit('remove');
    clearLocalFile();
}
</script>

<template>
    <div class="w-full">
        <input
            ref="inputRef"
            type="file"
            class="sr-only"
            :accept="accept"
            :disabled="disabled"
            tabindex="-1"
            aria-hidden="true"
            @change="onPicked"
        />

        <div v-if="phase === 'idle'" class="flex flex-col gap-1.5">
            <button
                type="button"
                class="border-border bg-background hover:border-primary/40 hover:bg-primary/5 focus-visible:ring-ring/30 flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-dashed px-4 py-3 text-sm font-medium transition-colors duration-150 outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-50"
                :disabled="disabled"
                @click="openPicker"
            >
                <Upload class="size-4 shrink-0" aria-hidden="true" />
                {{ idleLabel }}
            </button>
            <p v-if="hint" class="text-muted-foreground text-xs">{{ hint }}</p>
            <p v-if="maxSizeKb" class="text-muted-foreground text-[11px]">
                Maksimal {{ formatFileSize(maxSizeKb * 1024) }}.
            </p>
        </div>

        <figure v-else-if="showImagePreview" class="border-border bg-card overflow-hidden rounded-xl border shadow-xs">
            <img
                :src="previewUrl"
                :alt="displayFileName || 'Pratinjau gambar'"
                loading="lazy"
                decoding="async"
                class="bg-muted max-h-44 w-full object-contain"
            />
            <figcaption class="flex items-start gap-3 px-3 py-2.5">
                <div class="min-w-0 flex-1">
                    <p class="text-foreground truncate text-sm font-medium" :title="displayFileName">
                        {{ displayFileName }}
                    </p>
                    <p class="text-muted-foreground mt-0.5 flex flex-wrap items-center gap-x-1.5 text-xs">
                        <span class="tabular-nums">{{ sizeLabel }}</span>
                        <span aria-hidden="true">·</span>
                        <span class="text-success inline-flex items-center gap-1" aria-live="polite">
                            <CircleCheck class="size-3.5 shrink-0" aria-hidden="true" />
                            {{ completedLabel }}
                        </span>
                    </p>
                </div>
                <button
                    v-if="removable"
                    type="button"
                    class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive focus-visible:ring-destructive/30 grid size-8 shrink-0 place-items-center rounded-lg transition-colors outline-none focus-visible:ring-[3px] disabled:pointer-events-none disabled:opacity-50"
                    :disabled="disabled"
                    :aria-label="`Hapus ${displayFileName || 'berkas'}`"
                    @click="emit('remove')"
                >
                    <Trash2 class="size-4" aria-hidden="true" />
                </button>
            </figcaption>
        </figure>

        <FileUploadRow
            v-else
            :state="props.state"
            :file-name="displayFileName"
            :file-size="displayFileSize"
            :mime="displayMime"
            :progress="props.progress"
            :error-message="props.errorMessage"
            :removable="props.removable"
            :disabled="props.disabled"
            @retry="onRetry"
            @cancel="onCancel"
            @remove="onRemove"
        />
    </div>
</template>
