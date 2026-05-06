<script setup lang="ts">
import { ref } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Button } from '@/components/ui/button'
import type { FormBannerState } from '@/components/modules/builder/formBanner'
import { normalizeBannerSrc } from '@/components/modules/builder/formBanner'

const props = defineProps<{ modelValue: FormBannerState }>()
const emit = defineEmits<{ 'update:modelValue': [value: FormBannerState] }>()

const bannerUploadError = ref('')

function patch(partial: Partial<FormBannerState>) {
    emit('update:modelValue', { ...props.modelValue, ...partial })
}

function onBannerFile(ev: Event) {
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

    const reader = new FileReader()
    reader.onload = () => {
        if (typeof reader.result !== 'string') return
        patch({
            bannerUrl: reader.result,
            bannerFileName: file.name,
        })
    }
    reader.onerror = () => {
        bannerUploadError.value = 'Gagal membaca file.'
    }
    reader.readAsDataURL(file)
}

function clearImage() {
    patch({ bannerUrl: '', bannerFileName: '' })
}

const previewSrc = () => normalizeBannerSrc(props.modelValue.bannerUrl)
</script>

<template>
    <div class="flex flex-col gap-3 rounded-xl border border-border bg-muted/30 p-3.5">
        <div>
            <p class="text-xs font-semibold text-foreground">Banner form</p>
            <p class="mt-0.5 text-[10px] leading-relaxed text-muted-foreground">
                Tampil di paling atas (sebelum judul). Hanya pembuat form yang mengisinya — bukan kolom pengisian peserta.
            </p>
        </div>

        <div class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Unggah gambar atau GIF</Label>
            <Input
                type="file"
                accept="image/png,image/jpeg,image/gif"
                class="text-xs"
                @change="onBannerFile"
            />
            <p class="text-[10px] text-muted-foreground">PNG, JPG, JPEG, GIF. Disimpan sebagai data URL bersama formulir Anda.</p>
            <p v-if="bannerUploadError" class="text-[10px] font-semibold text-destructive">{{ bannerUploadError }}</p>
        </div>

        <div class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Atau URL gambar / GIF</Label>
            <Input
                :model-value="modelValue.bannerUrl.startsWith('data:') ? '' : modelValue.bannerUrl"
                placeholder="https://… atau jalur ke /storage/…"
                class="text-xs"
                @update:model-value="
                    (v) =>
                        patch({
                            bannerUrl: String(v ?? ''),
                            bannerFileName: '',
                        })
                "
            />
            <p class="text-[10px] text-muted-foreground">Jika pakai upload di atas, isian URL bisa dikosongkan.</p>
        </div>

        <div class="flex flex-col gap-1.5">
            <Label class="text-xs font-semibold">Keterangan (opsional)</Label>
            <Textarea
                :model-value="modelValue.caption"
                placeholder="Teks singkat di bawah banner"
                rows="2"
                class="text-xs"
                @update:model-value="(v) => patch({ caption: String(v ?? '') })"
            />
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
            v-if="previewSrc() !== ''"
            class="overflow-hidden rounded-xl border border-border bg-card"
        >
            <img :src="previewSrc()" alt="Banner form" class="aspect-[3/1] w-full object-cover" />
            <p
                v-if="modelValue.bannerFileName && modelValue.bannerUrl.startsWith('data:')"
                class="border-t border-border px-3 py-1.5 text-[10px] text-muted-foreground"
            >
                {{ modelValue.bannerFileName }}
            </p>
        </div>
    </div>
</template>
