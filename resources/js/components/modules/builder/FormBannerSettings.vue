<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Loader2 } from 'lucide-vue-next';
import type { IFormBannerState } from '@/components/modules/builder/formBanner';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import { createObjectUrl, revokeObjectUrl } from '@/adapters/browser/objectUrl';
import { useBuilderImageUpload } from '@/hooks/useBuilderImageUpload';

const props = withDefaults(
    defineProps<{
        modelValue: IFormBannerState;
        /** Endpoint `POST {form}/images`. Kosong = preview lokal saja (tidak dipersist). */
        uploadUrl?: string;
    }>(),
    { uploadUrl: '' }
);
const emit = defineEmits<{ 'update:modelValue': [value: IFormBannerState] }>();

const bannerUploadError = ref('');
const localPreviewUrl = ref('');

const { uploading: bannerUploading, uploadImage } = useBuilderImageUpload({
    uploadUrl: () => props.uploadUrl,
    onError: (message) => {
        bannerUploadError.value = message;
    },
});

function patch(partial: Partial<IFormBannerState>) {
    emit('update:modelValue', { ...props.modelValue, ...partial });
}

async function onBannerFile(ev: Event) {
    bannerUploadError.value = '';
    const input = ev.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    const ok = ['image/png', 'image/jpeg', 'image/gif'];
    if (!ok.includes(file.type)) {
        bannerUploadError.value = 'Gunakan PNG, JPG, JPEG, atau GIF.';
        input.value = '';
        return;
    }

    const previousUrl = props.modelValue.bannerUrl;
    const previousName = props.modelValue.bannerFileName;

    // Preview instan (object URL) — tidak pernah base64, tidak pernah ke DB apa adanya.
    revokeObjectUrl(localPreviewUrl.value);
    const localUrl = createObjectUrl(file);
    localPreviewUrl.value = localUrl;
    patch({ bannerUrl: localUrl, bannerFileName: file.name });
    input.value = '';

    const result = await uploadImage(file);
    if (result) {
        localPreviewUrl.value = '';
        revokeObjectUrl(localUrl);
        patch({ bannerUrl: result.url, bannerFileName: file.name });
        return;
    }

    if (props.uploadUrl.trim() !== '') {
        localPreviewUrl.value = '';
        revokeObjectUrl(localUrl);
        patch({ bannerUrl: previousUrl, bannerFileName: previousName });
        if (!bannerUploadError.value) bannerUploadError.value = 'Gagal mengunggah gambar. Coba lagi.';
    }
    // Tanpa uploadUrl: pertahankan preview lokal (sanitizer membuangnya sebelum save).
}

function clearImage() {
    revokeObjectUrl(localPreviewUrl.value);
    localPreviewUrl.value = '';
    patch({ bannerUrl: '', bannerFileName: '' });
}

const previewSrc = computed(() => localPreviewUrl.value || normalizePublicStorageUrl(props.modelValue.bannerUrl));
const isUploading = computed(() => bannerUploading.value);

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

        <div class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Unggah gambar atau GIF</Label>
            <Input type="file" accept="image/png,image/jpeg,image/gif" class="text-xs" @change="onBannerFile" />
            <p class="text-muted-foreground text-[10px]">PNG, JPG, JPEG, GIF</p>
            <p v-if="isUploading" class="text-primary flex items-center gap-1 text-[10px] font-semibold">
                <Loader2 class="size-3 animate-spin" aria-hidden="true" />
                Mengunggah…
            </p>
            <p v-if="bannerUploadError" class="text-destructive text-[10px] font-semibold">{{ bannerUploadError }}</p>
        </div>

        <Button
            v-if="modelValue.bannerUrl.trim() !== '' || modelValue.bannerFileName.trim() !== ''"
            variant="outline"
            size="sm"
            type="button"
            class="h-8 text-xs"
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
