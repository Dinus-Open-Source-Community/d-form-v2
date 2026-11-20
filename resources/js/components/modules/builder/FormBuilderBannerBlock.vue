<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import FileUploadCard, { type TFileUploadState } from '@/components/core/FileUploadCard.vue';
import type { IFormBannerState } from './formBanner';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import { createObjectUrl, revokeObjectUrl } from '@/adapters/browser/objectUrl';
import { ACCEPTED_IMAGE_EXTENSIONS, validateUploadFile } from '@/lib/uploadValidation';
import { formatFileSize } from '@/lib/fileDisplay';
import { uploadBuilderImage } from '@/adapters/http/forms';
import { describeRequestErrorMessage } from '@/adapters/http/httpError';
import { builderPendingUploads } from '@/hooks/useBuilderImageUpload';
import { useUploadedFileState, type IUploadedFileResult, type TUploader } from '@/hooks/useUploadedFileState';

const banner = defineModel<IFormBannerState>('banner', { required: true });

const props = withDefaults(
    defineProps<{
        bannerPreviewSrc?: string;
        /** Endpoint `POST {form}/images`. Kosong = preview lokal saja (tidak dipersist). */
        uploadUrl?: string;
    }>(),
    {
        bannerPreviewSrc: '',
        uploadUrl: '',
    }
);

/** MIME yang diterima pemilih banner; pesan error dipertahankan seperti sebelumnya. */
const ALLOWED_BANNER_MIME_TYPES: readonly string[] = ['image/png', 'image/jpeg', 'image/gif'];
const BANNER_MAX_SIZE_KB = 5120;

/** Object URL sementara untuk preview draf lokal (tanpa endpoint upload). */
const localPreviewUrl = ref('');
const pickerInput = ref<HTMLInputElement | null>(null);

const hasUploadEndpoint = computed<boolean>(() => props.uploadUrl.trim() !== '');
const previewSrc = computed(
    () => localPreviewUrl.value || props.bannerPreviewSrc || normalizePublicStorageUrl(banner.value.bannerUrl)
);
const hasImage = computed(() => previewSrc.value !== '');
const displayFileName = computed<string>(() => banner.value.bannerFileName.trim());

/** Satu baris bantuan: ukuran diambil dari konstanta agar tidak ada angka duplikat. */
const bannerHint = computed<string>(
    () => `Gambar sampul di bagian atas form. Rasio 3:1, maksimal ${formatFileSize(BANNER_MAX_SIZE_KB * 1024)}.`
);

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

function openPicker(): void {
    pickerInput.value?.click();
}

function onPicked(event: Event): void {
    const target = event.target as HTMLInputElement | null;
    const file = target?.files?.[0];

    if (file !== undefined) void onBannerPick(file);
    if (target !== null) target.value = '';
}

function clearImage(): void {
    resetUpload();
    revokeObjectUrl(localPreviewUrl.value);
    localPreviewUrl.value = '';
    patch({ bannerUrl: '', bannerFileName: '' });
}

/** Hasil upload ditampilkan oleh baris ringkas, jadi kartu kembali ke idle setelah selesai. */
const cardState = computed<TFileUploadState>(() => (uploadPhase.value === 'completed' ? 'idle' : uploadPhase.value));
const idleLabel = computed(() => (hasImage.value ? 'Ganti banner' : 'Tambahkan banner form'));
const isUploading = computed<boolean>(() => uploadPhase.value === 'uploading');
const showCompactRow = computed<boolean>(() => hasImage.value && cardState.value === 'idle');

onUnmounted(() => {
    revokeObjectUrl(localPreviewUrl.value);
});
</script>

<template>
    <div class="w-full">
        <input
            ref="pickerInput"
            type="file"
            class="sr-only"
            accept="image/png,image/jpeg,image/gif"
            tabindex="-1"
            aria-hidden="true"
            @change="onPicked"
        />

        <div v-if="showCompactRow" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
            <img
                :src="previewSrc"
                alt="Pratinjau banner form"
                data-testid="builder-banner-preview"
                width="192"
                height="64"
                decoding="async"
                class="border-border bg-muted h-24 w-full shrink-0 rounded-lg border object-cover sm:h-16 sm:w-32 md:w-48"
            />
            <div class="min-w-0 flex-1">
                <p
                    class="text-foreground truncate text-sm font-medium"
                    :title="displayFileName === '' ? 'Banner form' : displayFileName"
                >
                    {{ displayFileName === '' ? 'Banner form' : displayFileName }}
                </p>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <Button type="button" variant="outline" size="sm" class="min-h-11 sm:min-h-9" @click="openPicker">
                        Ganti banner
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-destructive hover:bg-destructive/10 hover:text-destructive min-h-11 sm:min-h-9"
                        aria-label="Hapus banner"
                        :disabled="isUploading"
                        @click="clearImage"
                    >
                        Hapus
                    </Button>
                </div>
            </div>
        </div>

        <FileUploadCard
            v-else
            :state="cardState"
            :idle-label="idleLabel"
            accept="image/png,image/jpeg,image/gif"
            :file-name="uploadFileName"
            :file-size="uploadFileSize"
            :mime="uploadMime"
            :progress="uploadProgress"
            :error-message="uploadErrorMessage"
            @pick="onBannerPick"
            @cancel="cancelUpload"
            @remove="clearImage"
        />

        <p data-testid="builder-banner-hint" class="text-muted-foreground mt-1.5 text-xs">
            {{ bannerHint }}
        </p>
    </div>
</template>
