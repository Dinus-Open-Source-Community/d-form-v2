<script setup lang="ts">
import axios from 'axios'
import { computed, ref } from 'vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Spinner } from '@/components/ui/spinner'
import { BROADCAST_BASE_PATH } from '@/lib/broadcastHub'
import { showErrorToast, showHttpErrorToast } from '@/lib/error-message'
import { Paperclip, Trash2, Upload } from 'lucide-vue-next'

const props = defineProps<{ broadcastId: string }>()

interface BroadcastAttachment {
    id: string
    path: string
    original_name: string
    mime_type: string | null
    size: number
}

const MAX_FILES = 3
const MAX_BYTES = 5 * 1024 * 1024
const ALLOWED_EXTENSIONS = ['pdf', 'jpg', 'jpeg', 'png']

const attachments = ref<BroadcastAttachment[]>([])
const fileInput = ref<HTMLInputElement | null>(null)
const uploading = ref(false)
const deletingId = ref<string | null>(null)

const countLabel = computed<string>(() => `${attachments.value.length}/${MAX_FILES}`)

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

/** Validasi sisi klien cermin backend (mimes pdf/jpg/png, ≤5MB, maks 3). */
function validateClientFile(file: File): string | null {
    const extension = file.name.split('.').pop?.()?.toLowerCase() ?? ''
    if (!ALLOWED_EXTENSIONS.includes(extension)) {
        return 'Format berkas harus pdf, jpg, atau png.'
    }
    if (file.size > MAX_BYTES) return 'Ukuran berkas maksimal 5MB.'
    if (attachments.value.length >= MAX_FILES) return 'Maksimal 3 file per broadcast.'
    return null
}

function openPicker(): void {
    fileInput.value?.click()
}

async function uploadFile(file: File): Promise<void> {
    const blocked = validateClientFile(file)
    if (blocked) {
        showErrorToast(blocked, { title: 'Lampiran tidak valid' })
        return
    }
    uploading.value = true
    try {
        const payload = new FormData()
        payload.append('file', file)
        const { data, status } = await axios.post<{ attachment: BroadcastAttachment }>(
            `${BROADCAST_BASE_PATH}/${props.broadcastId}/attachments`,
            payload,
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
        )
        if (status === 201) {
            attachments.value = [...attachments.value, data.attachment]
            toast.success(`Lampiran ${data.attachment.original_name} diunggah.`)
        }
    } catch (error) {
        if (axios.isAxiosError(error) && error.response) {
            showHttpErrorToast(error.response.status, error.response.data, {
                422: 'Lampiran ditolak — periksa format (pdf/jpg/png), ukuran ≤5MB, dan batas 3 file.',
            })
            return
        }
        showErrorToast('Gagal mengunggah lampiran. Coba lagi.')
    } finally {
        uploading.value = false
    }
}

function onFileChange(event: Event): void {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (file) void uploadFile(file)
}

async function deleteAttachment(id: string): Promise<void> {
    if (deletingId.value) return
    deletingId.value = id
    try {
        await axios.delete(`${BROADCAST_BASE_PATH}/${props.broadcastId}/attachments/${id}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        })
        attachments.value = attachments.value.filter((item) => item.id !== id)
        toast.success('Lampiran dihapus.')
    } catch (error) {
        if (axios.isAxiosError(error) && error.response) {
            showHttpErrorToast(error.response.status, error.response.data)
            return
        }
        showErrorToast('Gagal menghapus lampiran. Coba lagi.')
    } finally {
        deletingId.value = null
    }
}
</script>

<template>
    <Card class="rounded-2xl border-border/70">
        <CardHeader class="pb-2">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <CardTitle class="flex items-center gap-2 text-base">
                    <Paperclip class="size-4 text-muted-foreground" aria-hidden="true" />
                    Lampiran
                    <span class="text-xs font-normal tabular-nums text-muted-foreground">{{ countLabel }}</span>
                </CardTitle>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="uploading || attachments.length >= MAX_FILES"
                    :title="attachments.length >= MAX_FILES ? 'Batas 3 file tercapai' : undefined"
                    @click="openPicker"
                >
                    <span v-if="uploading" class="flex items-center gap-2">
                        <Spinner />
                        Mengunggah…
                    </span>
                    <span v-else class="flex items-center gap-2">
                        <Upload class="size-4" aria-hidden="true" />
                        Tambah file
                    </span>
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                pdf/jpg/png, maksimal 5MB per file, maksimal 3 file. Ikut terkirim di semua email.
            </p>
        </CardHeader>
        <CardContent>
            <ul v-if="attachments.length > 0" class="divide-y divide-border/60 rounded-xl border border-border/60">
                <li
                    v-for="item in attachments"
                    :key="item.id"
                    class="flex min-w-0 items-center justify-between gap-3 px-3 py-2.5"
                >
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium">{{ item.original_name }}</p>
                        <p class="text-xs tabular-nums text-muted-foreground">{{ formatSize(item.size) }}</p>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="shrink-0 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                        :disabled="deletingId === item.id"
                        :aria-label="`Hapus lampiran ${item.original_name}`"
                        @click="deleteAttachment(item.id)"
                    >
                        <Spinner v-if="deletingId === item.id" />
                        <Trash2 v-else class="size-4" aria-hidden="true" />
                    </Button>
                </li>
            </ul>
            <p v-else class="rounded-xl border border-dashed border-border/80 px-4 py-6 text-center text-sm text-muted-foreground">
                Belum ada lampiran. Unggah pdf/jpg/png hingga 3 file.
            </p>
            <input
                ref="fileInput"
                type="file"
                accept=".pdf,.jpg,.jpeg,.png"
                class="hidden"
                tabindex="-1"
                aria-hidden="true"
                @change="onFileChange"
            />
        </CardContent>
    </Card>
</template>
