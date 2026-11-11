<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue';
import { Label } from '@/components/ui/label';
import FileUploadCard, { type TFileUploadState } from '@/components/core/FileUploadCard.vue';
import { validateUploadFile } from '@/lib/uploadValidation';
import { createObjectUrl, revokeObjectUrl } from '@/adapters/browser/objectUrl';

const props = withDefaults(
    defineProps<{
        modelValue: File | null;
        bannerUrl?: string | null;
        error?: string | null;
        disabled?: boolean;
    }>(),
    {
        bannerUrl: null,
        error: null,
        disabled: false,
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: File | null];
}>();

/** Batas & format banner di frontend; backend tetap memvalidasi. */
const BANNER_MAX_SIZE_KB = 5120;
const BANNER_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'] as const;

/** Object URL pratinjau untuk berkas yang baru dipilih (belum diunggah). */
const previewUrl = ref<string | null>(null);
/** Error pilih berkas (format/ukuran) — ditampilkan oleh kartu unggah. */
const pickError = ref('');

const hasNewFile = computed<boolean>(() => props.modelValue !== null);
/** Banner tersimpan ditampilkan hanya saat tidak ada berkas baru yang menunggu submit. */
const serverBannerUrl = computed<string | null>(() => (hasNewFile.value ? null : props.bannerUrl));

const bannerCardState = computed<TFileUploadState>(() => {
    if (pickError.value !== '') return 'error';
    return hasNewFile.value ? 'completed' : 'idle';
});

const bannerIdleLabel = computed<string>(() => (props.bannerUrl ? 'Ganti banner' : 'Unggah banner'));

watch(
    () => props.modelValue,
    (file) => {
        revokeObjectUrl(previewUrl.value);
        previewUrl.value = file ? createObjectUrl(file) : null;
    },
    { immediate: true }
);

onUnmounted(() => {
    revokeObjectUrl(previewUrl.value);
});

/** Pilih banner: validasi format/ukuran lalu serahkan berkas ke form induk (submit Inertia). */
async function onBannerPick(file: File): Promise<void> {
    pickError.value = '';
    const validationError = await validateUploadFile(file, BANNER_EXTENSIONS);

    if (validationError !== null) {
        pickError.value = validationError;
        return;
    }

    emit('update:modelValue', file);
}

function removeBanner(): void {
    pickError.value = '';
    emit('update:modelValue', null);
}
</script>

<template>
    <div class="space-y-2">
        <div>
            <Label>Banner</Label>
            <p class="text-muted-foreground mt-1 text-xs">
                Opsional.<template v-if="bannerUrl"> Pilih berkas baru untuk menggantikan banner saat ini.</template>
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-[auto_minmax(0,1fr)] sm:items-start">
            <div class="w-full max-w-xs space-y-2 sm:max-w-sm">
                <div
                    v-if="serverBannerUrl !== null"
                    class="border-border bg-muted/25 aspect-video w-full overflow-hidden rounded-xl border"
                >
                    <img :src="serverBannerUrl" alt="Banner saat ini" class="size-full object-cover" />
                </div>

                <FileUploadCard
                    :state="bannerCardState"
                    :idle-label="bannerIdleLabel"
                    accept="image/*"
                    hint="Rasio 16:9 disarankan agar tampil penuh."
                    :max-size-kb="BANNER_MAX_SIZE_KB"
                    :file-name="modelValue?.name ?? ''"
                    :file-size="modelValue?.size ?? 0"
                    :mime="modelValue?.type ?? ''"
                    :preview-url="previewUrl ?? ''"
                    :error-message="pickError"
                    :disabled="disabled"
                    @pick="onBannerPick"
                    @remove="removeBanner"
                />
            </div>
            <div class="min-w-0 space-y-2">
                <p class="text-sm font-medium">Persyaratan berkas</p>
                <ul class="text-muted-foreground list-disc space-y-1 pl-5 text-xs">
                    <li>Format JPG, JPEG, PNG, atau WEBP.</li>
                    <li>Ukuran maksimal 5 MB.</li>
                    <li>Rasio 16:9 disarankan agar tampil penuh (tidak wajib).</li>
                </ul>
                <p class="text-muted-foreground text-xs">
                    Tips: gunakan gambar landscape agar tidak terpotong; kompres di bawah 5 MB bila unggahan gagal.
                </p>
            </div>
        </div>

        <p v-if="error" class="text-destructive text-xs">{{ error }}</p>
    </div>
</template>
