<script setup lang="ts">
import { computed } from 'vue'
import { MessageSquareCheck } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import DeferredRender from '@/components/core/DeferredRender.vue'
import { LazyTipTapEditor } from '@/lib/lazyRichTextEditors'

/**
 * Zona konfirmasi "Pesan setelah submit" ala Google Forms — segmen terakhir dari
 * section utama canvas. Bukan card mandiri: tanpa chrome luar, tanpa toggle.
 * Tampil saat `show` aktif (drag item palette) ATAU `successContent` berisi teks
 * bermakna (mis. form tersimpan dimuat kembali). Tombol "Hapus" mengosongkan
 * konten + menyembunyikan zona via emit `remove`.
 */
const successContent = defineModel<string>('successContent', { required: true })

const props = defineProps<{
    show: boolean
}>()

defineEmits<{
    remove: []
}>()

function hasMeaningfulContent(html: string): boolean {
    if (!html) return false
    const text = html.replace(/<[^>]*>/g, '').replace(/&nbsp;/gi, ' ').trim()
    return text !== ''
}

const visible = computed(() => props.show || hasMeaningfulContent(successContent.value))
</script>

<template>
    <div v-if="visible" class="border-border/70 border-t">
        <div class="flex items-center gap-2 border-b border-border/70 px-5 py-3.5 sm:px-7">
            <MessageSquareCheck class="text-muted-foreground size-4" aria-hidden="true" />
            <h2 class="text-foreground text-sm font-semibold tracking-[-0.01em]">Pesan setelah submit</h2>
            <div class="ml-auto">
                <Button variant="ghost" size="sm" @click="$emit('remove')">Hapus</Button>
            </div>
        </div>
        <div class="px-4 py-4 sm:px-5 sm:py-5">
            <DeferredRender min-height="14rem">
                <LazyTipTapEditor v-model="successContent" />
            </DeferredRender>
        </div>
    </div>
</template>
