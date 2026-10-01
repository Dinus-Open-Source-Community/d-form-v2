<script setup lang="ts">
import axios from 'axios'
import { onMounted, ref } from 'vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { BROADCAST_BASE_PATH } from '@/lib/broadcastHub'
import { showHttpErrorToast } from '@/lib/error-message'
import { Eye } from 'lucide-vue-next'

const props = defineProps<{ broadcastId: string }>()

/** Ditampilkan via v-text agar kurung kurawal tak diparse sebagai interpolasi. */
const placeholderSample = '{{nama}}'

const html = ref<string | null>(null)
const loading = ref(true)
const failed = ref(false)

async function loadPreview(): Promise<void> {
    loading.value = true
    failed.value = false
    try {
        const { data } = await axios.get<string>(`${BROADCAST_BASE_PATH}/${props.broadcastId}/preview`, {
            headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
            responseType: 'text',
        })
        html.value = data
    } catch (error) {
        html.value = null
        failed.value = true
        if (axios.isAxiosError(error) && error.response) {
            showHttpErrorToast(error.response.status, error.response.data, {
                403: 'Pratinjau hanya tersedia untuk draft/terjadwal.',
            })
        } else {
            showHttpErrorToast(0, undefined, { 0: 'Gagal memuat pratinjau. Coba lagi.' })
        }
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    void loadPreview()
})
</script>

<template>
    <Card class="rounded-2xl border-border/70">
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center gap-2 text-base">
                <Eye class="size-4 text-muted-foreground" aria-hidden="true" />
                Pratinjau email
            </CardTitle>
            <p class="text-xs text-muted-foreground">
                Render HTML asli dari server — <code v-text="placeholderSample" /> diisi nama
                penerima pertama snapshot.
            </p>
        </CardHeader>
        <CardContent>
            <div v-if="loading" class="space-y-2" aria-label="Memuat pratinjau" role="status">
                <div class="h-5 w-2/3 animate-pulse rounded-md bg-muted" />
                <div class="h-40 animate-pulse rounded-xl bg-muted/60" />
            </div>
            <div
                v-else-if="failed || html === null"
                class="flex flex-col items-center gap-2 rounded-xl border border-dashed border-border/80 px-4 py-8 text-center"
            >
                <p class="text-sm font-medium">Pratinjau gagal dimuat.</p>
                <Button type="button" variant="outline" size="sm" @click="loadPreview">
                    Coba lagi
                </Button>
            </div>
            <iframe
                v-else
                title="Pratinjau email broadcast"
                :srcdoc="html"
                class="h-96 w-full rounded-xl border border-border/60 bg-white"
                sandbox=""
            />
        </CardContent>
    </Card>
</template>
