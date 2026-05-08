<script setup lang="ts">
import { Clock } from 'lucide-vue-next'

defineProps<{
    pendingCount: number
    activeStatusTab: 'all' | 'pending' | 'accepted' | 'rejected'
}>()
</script>

<template>
    <div
        v-if="pendingCount > 0 && activeStatusTab !== 'pending'"
        class="flex items-start gap-3 rounded-xl border border-warning/25 bg-warning/5 px-4 py-3"
        role="status"
    >
        <div
            class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-lg bg-warning/15 text-warning-foreground"
        >
            <Clock class="size-4" aria-hidden="true" />
        </div>
        <div class="min-w-0 flex-1 text-sm">
            <p class="font-medium text-foreground">
                {{ pendingCount === 1 ? 'Ada 1 pendaftar' : `Ada ${pendingCount} pendaftar` }} yang menunggu keputusan.
            </p>
            <p class="mt-0.5 text-xs leading-relaxed text-muted-foreground">
                Buka tab <span class="font-medium text-foreground">Menunggu</span> di atas untuk hanya menampilkan daftar
                tersebut. Tinjau pendaftar dapat dilakukan dari halaman pengiriman formulir jika tersedia.
            </p>
        </div>
    </div>
</template>
