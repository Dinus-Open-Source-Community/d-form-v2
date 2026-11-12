<script setup lang="ts">
import { computed } from 'vue';
import FileUploadCard from '@/components/core/FileUploadCard.vue';
import { Button } from '@/components/ui/button';
import { builderPendingUploads } from '@/hooks/useBuilderImageUpload';
import { useUploadedFileState, type IUploadedFileResult } from '@/hooks/useUploadedFileState';
import { uploadBuilderImage } from '@/adapters/http/forms';
import { ACCEPTED_IMAGE_EXTENSIONS, MAX_UPLOAD_BYTES, validateUploadFile } from '@/lib/uploadValidation';

/**
 * Kartu unggah satu gambar builder (blok image atau gambar opsi).
 *
 * Endpoint tetap `POST {uploadUrl}` (multipart `file`) lewat `uploadBuilderImage`,
 * sehingga persen dan Batalkan berasal dari adapter. Pratinjau hasil unggah selalu
 * URL server yang dikembalikan endpoint — tidak ada object URL/base64.
 * `builderPendingUploads` dinaikkan selama request agar autosave builder menunggu.
 */
const props = withDefaults(
    defineProps<{
        /** Endpoint `POST {form}/images`; kosong = unggah belum tersedia (mis. demo builder). */
        uploadUrl: string;
        /** URL server gambar yang sudah tersimpan pada field/opsi. */
        previewUrl?: string;
        idleLabel?: string;
        alt?: string;
        disabled?: boolean;
    }>(),
    { previewUrl: '', idleLabel: 'Unggah gambar', alt: '', disabled: false }
);

const emit = defineEmits<{
    uploaded: [url: string];
    cleared: [];
}>();

const MAX_SIZE_KB = MAX_UPLOAD_BYTES / 1024;

const hasUploadEndpoint = computed<boolean>(() => props.uploadUrl.trim() !== '');
const seededPreview = computed<string>(() => props.previewUrl.trim());
/** Pratinjau lama tetap tampil sampai unggahan baru selesai (kartu yang menampilkan hasilnya). */
const showSeededPreview = computed<boolean>(() => seededPreview.value !== '' && fileState.phase.value !== 'completed');

/** Upload satu file: validasi tipe/isi dulu (tanpa membaca penuh), lalu kirim multipart. */
async function uploadBuilderImageFile(
    file: File,
    options: { signal: AbortSignal; onProgress: (percent: number) => void }
): Promise<IUploadedFileResult> {
    const target = props.uploadUrl.trim();
    if (target === '') throw new Error('Unggah gambar belum tersedia untuk field ini.');

    const validationError = await validateUploadFile(file, ACCEPTED_IMAGE_EXTENSIONS);
    if (validationError !== null) throw new Error(validationError);

    builderPendingUploads.value += 1;

    try {
        const result = await uploadBuilderImage(target, file, 'file', options);
        const storedUrl = result.url.trim() !== '' ? result.url : result.path;

        if (storedUrl.trim() === '') throw new Error('Respons upload tidak membawa path/url gambar.');

        return {
            fileName: file.name,
            fileSize: file.size,
            mime: file.type,
            previewUrl: storedUrl,
            storedValue: result.path,
        };
    } finally {
        builderPendingUploads.value = Math.max(0, builderPendingUploads.value - 1);
    }
}

const fileState = useUploadedFileState({
    upload: uploadBuilderImageFile,
    onCompleted: (result: IUploadedFileResult): void => emit('uploaded', result.previewUrl ?? ''),
});

function clearImage(): void {
    fileState.reset();
    emit('cleared');
}
</script>

<template>
    <div class="flex flex-col gap-2">
        <div v-if="showSeededPreview" class="border-border bg-card overflow-hidden rounded-xl border">
            <img
                :src="seededPreview"
                :alt="props.alt"
                loading="lazy"
                decoding="async"
                class="bg-muted max-h-44 w-full object-contain"
            />
        </div>

        <div class="flex flex-wrap items-start gap-1.5">
            <FileUploadCard
                class="min-w-0 flex-1"
                :state="fileState.phase.value"
                :idle-label="showSeededPreview ? 'Ganti gambar' : props.idleLabel"
                :hint="hasUploadEndpoint ? '' : 'Unggah tersedia setelah form disimpan.'"
                accept="image/png,image/jpeg,image/webp,image/gif"
                :max-size-kb="MAX_SIZE_KB"
                :file-name="fileState.fileName.value"
                :file-size="fileState.fileSize.value"
                :mime="fileState.mime.value"
                :progress="fileState.progress.value"
                :preview-url="fileState.previewUrl.value"
                :error-message="fileState.errorMessage.value"
                :disabled="props.disabled || !hasUploadEndpoint"
                @pick="fileState.start"
                @cancel="fileState.cancel"
                @remove="clearImage"
            />
            <Button
                v-if="showSeededPreview"
                variant="outline"
                size="sm"
                type="button"
                class="text-xs"
                :disabled="props.disabled"
                @click="clearImage"
            >
                Hapus
            </Button>
        </div>
    </div>
</template>
