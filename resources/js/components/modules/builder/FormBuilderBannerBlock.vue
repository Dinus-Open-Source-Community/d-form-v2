<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { ImageUp, Loader2, RefreshCw, X } from 'lucide-vue-next';
import type { FormBannerState } from './formBanner';
import { normalizeBannerSrc } from './formBanner';
import {
    createLocalImagePreviewUrl,
    revokeLocalImagePreviewUrl,
    useBuilderImageUpload,
} from '@/utils/composables/useBuilderImageUpload';

const banner = defineModel<FormBannerState>('banner', { required: true });

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
    },
);

const isDragging = ref(false);
const bannerUploadError = ref('');
const fileInput = ref<HTMLInputElement | null>(null);
/** Object URL sementara untuk preview instan; direvoke setelah upload selesai. */
const localPreviewUrl = ref('');

const { uploading: bannerUploading, uploadImage } = useBuilderImageUpload({
    uploadUrl: () => props.uploadUrl,
    onError: (message) => {
        bannerUploadError.value = message;
    },
});

const previewSrc = computed(
    () => localPreviewUrl.value || props.bannerPreviewSrc || normalizeBannerSrc(banner.value.bannerUrl),
);
const hasImage = computed(() => previewSrc.value !== '');
const isUploading = computed(() => bannerUploading.value);

function patch(partial: Partial<FormBannerState>): void {
    Object.assign(banner.value, partial);
}

async function applyFile(file: File | null | undefined): Promise<void> {
    bannerUploadError.value = '';
    if (!file) return;

    const ok = ['image/png', 'image/jpeg', 'image/gif'];
    if (!ok.includes(file.type)) {
        bannerUploadError.value = 'Gunakan PNG, JPG, JPEG, atau GIF.';
        return;
    }

    const previousUrl = banner.value.bannerUrl;
    const previousName = banner.value.bannerFileName;

    // Preview instan (object URL) — tidak pernah base64, tidak pernah ke DB apa adanya.
    revokeLocalImagePreviewUrl(localPreviewUrl.value);
    const localUrl = createLocalImagePreviewUrl(file);
    localPreviewUrl.value = localUrl;
    patch({ bannerUrl: localUrl, bannerFileName: file.name });

    const result = await uploadImage(file);
    if (result) {
        localPreviewUrl.value = '';
        revokeLocalImagePreviewUrl(localUrl);
        patch({ bannerUrl: result.url, bannerFileName: file.name });
        return;
    }

    if (props.uploadUrl.trim() !== '') {
        // Upload gagal → kembalikan state semula agar snapshot tetap konsisten.
        localPreviewUrl.value = '';
        revokeLocalImagePreviewUrl(localUrl);
        patch({ bannerUrl: previousUrl, bannerFileName: previousName });
        if (!bannerUploadError.value) bannerUploadError.value = 'Gagal mengunggah gambar. Coba lagi.';
    }
    // Tanpa uploadUrl: pertahankan preview lokal (sanitizer membuangnya sebelum save).
}

function openPicker(): void {
    fileInput.value?.click();
}

function onBannerFile(ev: Event): void {
    const input = ev.target as HTMLInputElement;
    void applyFile(input.files?.[0]);
    input.value = '';
}

function onDrop(e: DragEvent): void {
    isDragging.value = false;
    void applyFile(e.dataTransfer?.files?.[0]);
}

function clearImage(): void {
    revokeLocalImagePreviewUrl(localPreviewUrl.value);
    localPreviewUrl.value = '';
    patch({ bannerUrl: '', bannerFileName: '' });
}

onUnmounted(() => {
    revokeLocalImagePreviewUrl(localPreviewUrl.value);
});
</script>

<template>
    <div
        class="overflow-hidden transition-[border-color,box-shadow] duration-200"
        :class="[
            variant === 'plain'
                ? 'rounded-none border-0 bg-transparent shadow-none'
                : 'border-border bg-card rounded-2xl border shadow-sm',
            isDragging ? 'border-primary/60 ring-primary/15 ring-2' : '',
        ]"
    >
        <!-- Belum ada banner → area unggah -->
        <button
            v-if="!hasImage"
            type="button"
            class="group hover:bg-muted/25 flex w-full cursor-pointer items-center gap-3 px-5 py-6 text-left transition-colors duration-200 sm:px-7"
            @click="openPicker"
            @dragover.prevent="isDragging = true"
            @dragleave="isDragging = false"
            @drop.prevent="onDrop"
        >
            <span
                class="bg-muted text-muted-foreground group-hover:border-primary/40 group-hover:text-primary border-border/70 grid size-10 shrink-0 place-items-center rounded-xl border shadow-xs transition-[color,border-color] duration-200"
            >
                <ImageUp class="size-5" aria-hidden="true" />
            </span>
            <span class="flex min-w-0 flex-1 flex-col gap-0.5">
                <span class="text-foreground text-sm font-semibold">Tambahkan banner form</span>
                <span class="text-muted-foreground text-xs leading-snug">
                    Gambar sampul di bagian atas form — rasio 3:1
                </span>
            </span>
            <span
                class="border-border bg-background text-muted-foreground/80 rounded-full border px-2.5 py-1 text-[10px] font-semibold sm:hidden"
            >
                PNG · JPG · GIF
            </span>
            <span
                class="text-muted-foreground hidden shrink-0 text-[10px] font-semibold tracking-wide uppercase sm:block"
            >
                Klik atau seret
            </span>
        </button>
        <!-- Ada banner → pratinjau + aksi -->
        <div v-else>
            <div class="relative aspect-[3/1] w-full overflow-hidden">
                <img :src="previewSrc" alt="Pratinjau banner form" class="size-full object-cover" />
                <span
                    v-if="isUploading"
                    class="bg-foreground/55 text-background absolute inset-x-0 bottom-0 flex items-center justify-center gap-1.5 py-1 text-[11px] font-semibold"
                >
                    <Loader2 class="size-3 animate-spin" aria-hidden="true" />
                    Mengunggah…
                </span>
            </div>
            <div
                class="border-border/70 flex flex-wrap items-center justify-between gap-x-3 gap-y-2 border-t px-5 py-2.5 sm:px-7"
            >
                <div class="flex min-w-0 items-center gap-2">
                    <span class="text-muted-foreground truncate text-xs font-medium">
                        {{ banner.bannerFileName || 'banner-form' }}
                    </span>
                    <span
                        v-if="isUploading"
                        class="border-primary/20 bg-primary/8 text-primary shrink-0 rounded-full border px-1.5 py-0.5 text-[10px] font-semibold"
                    >
                        mengunggah…
                    </span>
                    <span
                        v-else-if="localPreviewUrl"
                        class="border-primary/20 bg-primary/8 text-primary shrink-0 rounded-full border px-1.5 py-0.5 text-[10px] font-semibold"
                    >
                        baru
                    </span>
                </div>
                <div class="flex shrink-0 items-center gap-1.5">
                    <Button variant="outline" size="sm" type="button" class="h-8 gap-1.5 text-xs" @click="openPicker">
                        <RefreshCw class="size-3.5" aria-hidden="true" />
                        Ganti
                    </Button>
                    <Button
                        radius="icon"
                        variant="ghost"
                        size="icon-sm"
                        type="button"
                        class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                        aria-label="Hapus banner"
                        @click="clearImage"
                    >
                        <X class="size-4" aria-hidden="true" />
                    </Button>
                </div>
            </div>
        </div>

        <p
            v-if="bannerUploadError"
            class="bg-destructive/5 text-destructive border-border/70 border-t px-5 py-2 text-xs font-medium sm:px-7"
        >
            {{ bannerUploadError }}
        </p>

        <input
            ref="fileInput"
            type="file"
            accept="image/png,image/jpeg,image/gif"
            class="hidden"
            @change="onBannerFile"
        />
    </div>
</template>
