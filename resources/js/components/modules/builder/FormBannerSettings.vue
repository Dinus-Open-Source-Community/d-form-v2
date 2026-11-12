<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import FileUploadCard, { type TFileUploadState } from '@/components/core/FileUploadCard.vue';
import type { IFormBannerState } from '@/components/modules/builder/formBanner';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import { createObjectUrl, revokeObjectUrl } from '@/adapters/browser/objectUrl';
import { ACCEPTED_IMAGE_EXTENSIONS, validateUploadFile } from '@/lib/uploadValidation';
import { uploadBuilderImage } from '@/adapters/http/forms';
import { describeRequestErrorMessage } from '@/adapters/http/httpError';
import { builderPendingUploads } from '@/hooks/useBuilderImageUpload';
import { useUploadedFileState, type IUploadedFileResult, type TUploader } from '@/hooks/useUploadedFileState';

const props = withDefaults(
    defineProps<{
        modelValue: IFormBannerState;
        /** Endpoint `POST {form}/images`. Kosong = preview lokal saja (tidak dipersist). */
        uploadUrl?: string;
    }>(),
    { uploadUrl: '' }
);
const emit = defineEmits<{ 'update:modelValue': [value: IFormBannerState] }>();

/** MIME yang diterima tombol pilih banner; pesan error dipertahankan seperti sebelumnya. */
const ALLOWED_BANNER_MIME_TYPES: readonly string[] = ['image/png', 'image/jpeg', 'image/gif'];
const BANNER_MAX_SIZE_KB = 5120;

const localPreviewUrl = ref('');
const hasUploadEndpoint = computed<boolean>(() => props.uploadUrl.trim() !== '');

function patch(partial: Partial<IFormBannerState>) {
    emit('update:modelValue', { ...props.modelValue, ...partial });
}

/**
 * Upload banner lewat adapter axios (`POST {form}/images`) sehingga progres byte dan
 * `AbortSignal` Batalkan benar-benar diteruskan ke request.
 * `builderPendingUploads` dinaikkan selama request agar autosave menunggu upload selesai.
 */
const uploader: TUploader = async (file, { signal, onProgress }): Promise<IUploadedFileResult> => {
    if (!ALLOWED_BANNER_MIME_TYPES.includes(file.type)) {
        throw new Error('Gunakan PNG, JPG, JPEG, atau GIF.');
    }

    const validationError = await validateUploadFile(file, ACCEPTED_IMAGE_EXTENSIONS);
    if (validationError !== null) throw new Error(validationError);

    builderPendingUploads.value += 1;

    try {
        const result = await uploadBuilderImage(props.uploadUrl.trim(), file, 'file', { signal, onProgress });
        const storedValue = result.url.trim() !== '' ? result.url : result.path;

        if (storedValue.trim() === '') {
            throw new Error('Respons upload tidak membawa path/url gambar.');
        }

        return {
            fileName: file.name,
            fileSize: file.size,
            mime: file.type,
            previewUrl: normalizePublicStorageUrl(storedValue),
            storedValue,
        };
    } catch (error) {
        // Batalkan bukan kegagalan: state machine mengembalikan kartu ke idle.
        if (error instanceof Error && error.name === 'AbortError') throw error;

        throw new Error(describeRequestErrorMessage(error, 'Gagal mengunggah gambar.'));
    } finally {
        builderPendingUploads.value = Math.max(0, builderPendingUploads.value - 1);
    }
};

const {
    phase: uploadPhase,
    progress: uploadProgress,
    fileName: uploadFileName,
    fileSize: uploadFileSize,
    mime: uploadMime,
    errorMessage: uploadErrorMessage,
    start: startUpload,
    cancel: cancelUpload,
    reset: resetUpload,
} = useUploadedFileState({
    upload: uploader,
    onCompleted: (result) => {
        patch({ bannerUrl: result.storedValue ?? '', bannerFileName: result.fileName });
    },
});

/**
 * Tanpa endpoint `{form}/images` tidak ada request: preview lokal (object URL) tetap
 * ditampilkan dan sanitizer membuangnya sebelum save.
 */
function applyDraftPreview(file: File): void {
    revokeObjectUrl(localPreviewUrl.value);
    localPreviewUrl.value = createObjectUrl(file);
    patch({ bannerUrl: localPreviewUrl.value, bannerFileName: file.name });
}

async function onBannerPick(file: File): Promise<void> {
    if (!hasUploadEndpoint.value) {
        applyDraftPreview(file);
        return;
    }

    await startUpload(file);
}

function clearImage(): void {
    resetUpload();
    revokeObjectUrl(localPreviewUrl.value);
    localPreviewUrl.value = '';
    patch({ bannerUrl: '', bannerFileName: '' });
}

const previewSrc = computed(() => localPreviewUrl.value || normalizePublicStorageUrl(props.modelValue.bannerUrl));

/** Hasil upload ditampilkan oleh pratinjau 3:1, jadi kartu kembali ke idle setelah selesai. */
const cardState = computed<TFileUploadState>(() => (uploadPhase.value === 'completed' ? 'idle' : uploadPhase.value));
const idleLabel = computed(() => (previewSrc.value !== '' ? 'Ganti gambar' : 'Unggah gambar atau GIF'));
const isUploading = computed<boolean>(() => uploadPhase.value === 'uploading');

onUnmounted(() => {
    revokeObjectUrl(localPreviewUrl.value);
});
</script>

<template>
    <div class="border-border bg-muted/30 flex flex-col gap-3 rounded-xl border p-3.5">
        <div>
            <p class="text-foreground text-xs font-semibold">Banner form</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <span
                    class="border-primary/20 bg-primary/8 text-primary rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                >
                    3:1
                </span>
                <span
                    class="border-border bg-background text-muted-foreground rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                >
                    1800 x 600
                </span>
                <span
                    class="rounded-full border border-amber-500/25 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300"
                >
                    center safe
                </span>
            </div>
        </div>

        <FileUploadCard
            :state="cardState"
            :idle-label="idleLabel"
            accept="image/png,image/jpeg,image/gif"
            hint="PNG, JPG, JPEG, GIF"
            :max-size-kb="BANNER_MAX_SIZE_KB"
            :file-name="uploadFileName"
            :file-size="uploadFileSize"
            :mime="uploadMime"
            :progress="uploadProgress"
            :error-message="uploadErrorMessage"
            @pick="onBannerPick"
            @cancel="cancelUpload"
            @remove="clearImage"
        />

        <Button
            v-if="modelValue.bannerUrl.trim() !== '' || modelValue.bannerFileName.trim() !== ''"
            variant="outline"
            size="sm"
            type="button"
            class="h-8 text-xs"
            :disabled="isUploading"
            @click="clearImage"
        >
            Hapus banner
        </Button>

        <div v-if="previewSrc !== ''" class="border-border bg-card overflow-hidden rounded-xl border">
            <div class="relative">
                <img :src="previewSrc" alt="Banner form" class="aspect-video w-full object-cover sm:aspect-[3/1]" />
                <div
                    class="pointer-events-none absolute inset-y-[18%] right-[18%] left-[18%] rounded-lg border border-white/80 shadow-[0_0_0_999px_rgba(0,0,0,0.14)]"
                    aria-hidden="true"
                />
            </div>
            <p
                v-if="modelValue.bannerFileName && (localPreviewUrl || modelValue.bannerUrl.startsWith('blob:'))"
                class="border-border text-muted-foreground border-t px-3 py-1.5 text-[10px]"
            >
                {{ modelValue.bannerFileName }}
            </p>
        </div>
    </div>
</template>
