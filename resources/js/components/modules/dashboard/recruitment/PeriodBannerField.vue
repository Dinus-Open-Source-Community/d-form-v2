<script setup lang="ts">
import { computed, onUnmounted, ref, useId, watch } from 'vue'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'
import { ImagePlus, X } from 'lucide-vue-next'

const props = withDefaults(
    defineProps<{
        modelValue: File | null
        bannerUrl?: string | null
        error?: string | null
        disabled?: boolean
    }>(),
    {
        bannerUrl: null,
        error: null,
        disabled: false,
    },
)

const emit = defineEmits<{
    'update:modelValue': [value: File | null]
}>()

const inputId = `period-banner-${useId().replace(/:/g, '')}`
const bannerInput = ref<HTMLInputElement | null>(null)
const bannerObjectUrl = ref<string | null>(null)
const isDragging = ref(false)

/** Pratinjau compact: berkas baru (object URL) diutamakan, lalu banner lama dari server. */
const preview = computed<string | null>(() => bannerObjectUrl.value ?? props.bannerUrl ?? null)
const hasNewFile = computed<boolean>(() => props.modelValue !== null)
const removeLabel = computed<string>(() =>
    props.bannerUrl && hasNewFile.value ? 'Batalkan pilihan banner baru' : 'Hapus banner',
)

watch(
    () => props.modelValue,
    (file) => {
        releaseBannerObjectUrl()
        if (file) bannerObjectUrl.value = URL.createObjectURL(file)
    },
    { immediate: true },
)

onUnmounted(releaseBannerObjectUrl)

function releaseBannerObjectUrl(): void {
    if (bannerObjectUrl.value) {
        URL.revokeObjectURL(bannerObjectUrl.value)
        bannerObjectUrl.value = null
    }
}

function openBannerPicker(): void {
    if (!props.disabled) bannerInput.value?.click()
}

function handleBannerChange(event: Event): void {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (file) emit('update:modelValue', file)
    input.value = ''
}

function handleBannerDrop(event: DragEvent): void {
    isDragging.value = false
    if (props.disabled) return
    const file = event.dataTransfer?.files?.[0]
    if (file && file.type.startsWith('image/')) emit('update:modelValue', file)
}

function removeBanner(): void {
    emit('update:modelValue', null)
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <Label :for="inputId">Banner</Label>
                <p class="text-muted-foreground mt-1 text-xs">
                    Opsional — disarankan 16:9, maks 10MB.<template v-if="bannerUrl">
                        Pilih berkas baru untuk menggantikan banner saat ini.</template
                    >
                </p>
            </div>
            <div v-if="preview" class="flex items-center gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    class="h-9 text-xs"
                    :disabled="disabled"
                    @click="openBannerPicker"
                >
                    Ganti
                </Button>
                <Button
                    v-if="hasNewFile"
                    type="button"
                    radius="icon"
                    variant="ghost"
                    size="icon-sm"
                    class="text-muted-foreground hover:text-destructive hover:bg-destructive/10"
                    :aria-label="removeLabel"
                    :disabled="disabled"
                    @click="removeBanner"
                >
                    <X class="size-4" />
                </Button>
            </div>
        </div>

        <div
            :class="
                cn(
                    'aspect-video w-full max-w-xs overflow-hidden rounded-xl border transition-colors sm:max-w-sm',
                    preview
                        ? 'border-border bg-muted/25'
                        : 'border-border cursor-pointer border-dashed hover:border-primary/50 hover:bg-primary/[0.03]',
                    isDragging && 'border-primary/60 bg-primary/5',
                )
            "
        >
            <img
                v-if="preview"
                :src="preview"
                alt="Pratinjau banner"
                class="size-full object-cover"
            />
            <div
                v-else
                class="flex size-full cursor-pointer flex-col items-center justify-center gap-1.5 px-4 text-center"
                @dragover.prevent="isDragging = true"
                @dragleave="isDragging = false"
                @drop.prevent="handleBannerDrop"
                @click="openBannerPicker"
            >
                <span class="bg-muted text-muted-foreground grid size-10 place-items-center rounded-full">
                    <ImagePlus class="size-5 stroke-[1.75]" aria-hidden="true" />
                </span>
                <div>
                    <p class="text-sm font-medium">Unggah banner</p>
                    <p class="text-muted-foreground mt-0.5 text-xs">
                        Klik atau seret gambar ke sini
                    </p>
                </div>
            </div>
        </div>

        <p v-if="error" class="text-destructive text-xs">{{ error }}</p>
        <input
            :id="inputId"
            ref="bannerInput"
            type="file"
            accept="image/*"
            class="hidden"
            :disabled="disabled"
            @change="handleBannerChange"
        />
    </div>
</template>
