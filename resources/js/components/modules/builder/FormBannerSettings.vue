<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import { Loader2 } from 'lucide-vue-next'
import type { FormBannerState } from '@/components/modules/builder/formBanner'
import { normalizeBannerSrc } from '@/components/modules/builder/formBanner'
import {
    createLocalImagePreviewUrl,
    revokeLocalImagePreviewUrl,
    useBuilderImageUpload,
} from '@/utils/composables/useBuilderImageUpload'

const props = withDefaults(
    defineProps<{
        modelValue: FormBannerState
        /** Endpoint `POST {form}/images`. Kosong = preview lokal saja (tidak dipersist). */
        uploadUrl?: string
    }>(),
    { uploadUrl: '' },
)
const emit = defineEmits<{ 'update:modelValue': [value: FormBannerState] }>()

const bannerUploadError = ref('')
const localPreviewUrl = ref('')

const { uploading: bannerUploading, uploadImage } = useBuilderImageUpload({
    uploadUrl: () => props.uploadUrl,
    onError: (message) => {
        bannerUploadError.value = message
    },
})

function patch(partial: Partial<FormBannerState>) {
    emit('update:modelValue', { ...props.modelValue, ...partial })
}

async function onBannerFile(ev: Event) {
    bannerUploadError.value = ''
    const input = ev.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file) return

    const ok = ['image/png', 'image/jpeg', 'image/gif']
    if (!ok.includes(file.type)) {
        bannerUploadError.value = 'Gunakan PNG, JPG, JPEG, atau GIF.'
        input.value = ''
        return
    }

    const previousUrl = props.modelValue.bannerUrl
    const previousName = props.modelValue.bannerFileName

    // Preview instan (object URL) — tidak pernah base64, tidak pernah ke DB apa adanya.
    revokeLocalImagePreviewUrl(localPreviewUrl.value)
    const localUrl = createLocalImagePreviewUrl(file)
    localPreviewUrl.value = localUrl
    patch({ bannerUrl: localUrl, bannerFileName: file.name })
    input.value = ''

    const result = await uploadImage(file)
    if (result) {
        localPreviewUrl.value = ''
        revokeLocalImagePreviewUrl(localUrl)
        patch({ bannerUrl: result.url, bannerFileName: file.name })
        return
    }

    if (props.uploadUrl.trim() !== '') {
        localPreviewUrl.value = ''
        revokeLocalImagePreviewUrl(localUrl)
        patch({ bannerUrl: previousUrl, bannerFileName: previousName })
        if (!bannerUploadError.value) bannerUploadError.value = 'Gagal mengunggah gambar. Coba lagi.'
    }
    // Tanpa uploadUrl: pertahankan preview lokal (sanitizer membuangnya sebelum save).
}

function clearImage() {
    revokeLocalImagePreviewUrl(localPreviewUrl.value)
    localPreviewUrl.value = ''
    patch({ bannerUrl: '', bannerFileName: '' })
}

const previewSrc = computed(
    () => localPreviewUrl.value || normalizeBannerSrc(props.modelValue.bannerUrl),
)
const isUploading = computed(() => bannerUploading.value)

onUnmounted(() => {
    revokeLocalImagePreviewUrl(localPreviewUrl.value)
})
</script>

<template>
    <div class="flex flex-col gap-3 rounded-xl border border-border bg-muted/30 p-3.5">
        <div>
            <p class="text-xs font-semibold text-foreground">Banner form</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                <span class="rounded-full border border-primary/20 bg-primary/8 px-2 py-0.5 text-[10px] font-semibold text-primary">
                    3:1
                </span>
                <span class="rounded-full border border-border bg-background px-2 py-0.5 text-[10px] font-semibold text-muted-foreground">
                    1800 x 600
                </span>
                <span class="rounded-full border border-amber-500/25 bg-amber-500/10 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:text-amber-300">
                    center safe
                </span>
            </div>
        </div>

        <div class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Unggah gambar atau GIF</Label>
            <Input
                type="file"
                accept="image/png,image/jpeg,image/gif"
                class="text-xs"
                @change="onBannerFile"
            />
            <p class="text-[10px] text-muted-foreground">PNG, JPG, JPEG, GIF</p>
            <p v-if="isUploading" class="flex items-center gap-1 text-[10px] font-semibold text-primary">
                <Loader2 class="size-3 animate-spin" aria-hidden="true" />
                Mengunggah…
            </p>
            <p v-if="bannerUploadError" class="text-[10px] font-semibold text-destructive">{{ bannerUploadError }}</p>
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

        <div
            v-if="previewSrc !== ''"
            class="overflow-hidden rounded-xl border border-border bg-card"
        >
            <div class="relative">
                <img :src="previewSrc" alt="Banner form" class="aspect-video w-full object-cover sm:aspect-[3/1]" />
                <div
                    class="pointer-events-none absolute inset-y-[18%] left-[18%] right-[18%] rounded-lg border border-white/80 shadow-[0_0_0_999px_rgba(0,0,0,0.14)]"
                    aria-hidden="true"
                />
            </div>
            <p
                v-if="modelValue.bannerFileName && (localPreviewUrl || modelValue.bannerUrl.startsWith('blob:'))"
                class="border-t border-border px-3 py-1.5 text-[10px] text-muted-foreground"
            >
                {{ modelValue.bannerFileName }}
            </p>
        </div>
    </div>
</template>
