<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { X } from 'lucide-vue-next';
import FileUploadCard, { type TFileUploadState } from '@/components/core/FileUploadCard.vue';
import type { IFormBannerState } from './formBanner';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import { createObjectUrl, revokeObjectUrl } from '@/adapters/browser/objectUrl';
import { ACCEPTED_IMAGE_EXTENSIONS, validateUploadFile } from '@/lib/uploadValidation';
import { uploadBuilderImage } from '@/adapters/http/forms';
import { describeRequestErrorMessage } from '@/adapters/http/httpError';
import { builderPendingUploads } from '@/hooks/useBuilderImageUpload';
import { useUploadedFileState, type IUploadedFileResult, type TUploader } from '@/hooks/useUploadedFileState';

const banner = defineModel<IFormBannerState>('banner', { required: true });

/** `frame` = kartu mandiri (border + shadow sendiri); `plain` = panel di dalam section induk */
const props = withDefaults(
    defineProps<{
        variant?: 'frame' | 'plain';
        bannerPreviewSrc?: string;
        /** Endpoint `POST {form}/images`. Kosong = preview lokal saja (tidak dipersist). */
        uploadUrl?: string;
    }>(),
    {
        variant: 'frame',
        bannerPreviewSrc: '',
        uploadUrl: '',
    }
);

/** MIME yang diterima tombol pilih banner; pesan error dipertahankan seperti sebelumnya. */
const ALLOWED_BANNER_MIME_TYPES: readonly string[] = ['image/png', 'image/jpeg', 'image/gif'];
const BANNER_MAX_SIZE_KB = 5120;

/** Object URL sementara untuk preview draf lokal (tanpa endpoint upload). */
const localPreviewUrl = ref('');
const hasUploadEndpoint = computed<boolean>(() => props.uploadUrl.trim() !== '');

const previewSrc = computed(
    () => localPreviewUrl.value || props.bannerPreviewSrc || normalizePublicStorageUrl(banner.value.bannerUrl)
);
const hasImage = computed(() => previewSrc.value !== '');

function patch(partial: Partial<IFormBannerState>): void {
    Object.assign(banner.value, partial);
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
    errorMessage: uploadErrorMessage,
    start: startUpload,
    cancel: cancelUpload,
} = useUploadedFileState({
    upload: uploader,
    onCompleted: (result) => {
        patch({ bannerUrl: result.storedValue ?? '', bannerFileName: result.fileName });
    },
});

/** Tanpa endpoint `{form}/images` tidak ada request: preview lokal dipakai sampai save. */
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
    revokeObjectUrl(localPreviewUrl.value);
    localPreviewUrl.value = '';
    patch({ bannerUrl: '', bannerFileName: '' });
}

/** Hasil upload ditampilkan oleh pratinjau 3:1, jadi kartu kembali ke idle setelah selesai. */
const cardState = computed<TFileUploadState>(() => (uploadPhase.value === 'completed' ? 'idle' : uploadPhase.value));
const idleLabel = computed(() => (hasImage.value ? 'Ganti banner' : 'Tambahkan banner form'));
const isUploading = computed<boolean>(() => uploadPhase.value === 'uploading');

onUnmounted(() => {
    revokeObjectUrl(localPreviewUrl.value);
});
</script>

<template>
    <div
        class="overflow-hidden transition-[border-color,box-shadow] duration-200"
        :class="
            variant === 'plain'
                ? 'rounded-none border-0 bg-transparent shadow-none'
                : 'border-border bg-card rounded-2xl border shadow-sm'
        "
    >
        <div v-if="hasImage" class="relative aspect-[3/1] w-full overflow-hidden">
            <img :src="previewSrc" alt="Pratinjau banner form" class="size-full object-cover" />
        </div>
        <div
            v-if="hasImage"
            class="border-border/70 flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-t px-5 py-2.5 sm:px-7"
        >
            <span class="text-muted-foreground min-w-0 truncate text-xs font-medium">
                {{ banner.bannerFileName || 'banner-form' }}
            </span>
            <Button
                radius="icon"
                variant="ghost"
                size="icon-sm"
                type="button"
                class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                aria-label="Hapus banner"
                :disabled="isUploading"
                @click="clearImage"
            >
                <X class="size-4" aria-hidden="true" />
            </Button>
        </div>

        <div class="px-5 py-3 sm:px-7">
            <FileUploadCard
                :state="cardState"
                :idle-label="idleLabel"
                accept="image/png,image/jpeg,image/gif"
                hint="Gambar sampul di bagian atas form — rasio 3:1."
                :max-size-kb="BANNER_MAX_SIZE_KB"
                :progress="uploadProgress"
                :error-message="uploadErrorMessage"
                @pick="onBannerPick"
                @cancel="cancelUpload"
            />
        </div>
    </div>
</template>
