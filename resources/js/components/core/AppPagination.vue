<script setup lang="ts">
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button'
import { paginationLabel } from '@/lib/paginationLabel'

interface PaginationLink {
    url: string | null
    label: string
    active: boolean
}

const props = withDefaults(
    defineProps<{
        links: PaginationLink[] | undefined
        /** Nomor halaman aktif; null/absen pada paginasi cursor. */
        currentPage?: number | null
        /** Jumlah halaman total; opsional untuk paginasi prev/next yang tanpa count. */
        lastPage?: number
        /** Total baris; opsional, hanya dipakai untuk baris teks ringkasan. */
        total?: number
        /** Kata setelah angka total, mis. "pengiriman" atau "rekaman". */
        totalLabel?: string
    }>(),
    { totalLabel: 'pengiriman' },
)

/** Navigasi link tampil bila ada tombol prev/next; `lastPage` 1 tetap disembunyikan. */
const showLinks = computed(
    (): boolean =>
        props.links !== undefined && props.links.length > 0 && props.lastPage !== 1,
)

/** Ringkasan halaman hanya tampil bila nomor halaman, jumlah halaman, dan total tersedia. */
const pageSummary = computed((): { page: number; lastPage: number; total: number } | null => {
    const { currentPage, lastPage, total } = props
    if (typeof currentPage !== 'number' || lastPage === undefined || total === undefined) return null
    return { page: currentPage, lastPage, total }
})

function formatInt(n: number): string {
    return new Intl.NumberFormat('id-ID').format(n)
}
</script>

<template>
    <div class="flex flex-col items-center gap-3 pt-8">
        <p v-if="pageSummary" class="text-center text-sm text-muted-foreground">
            Halaman
            <span class="font-medium text-foreground">{{ formatInt(pageSummary.page) }}</span>
            dari
            <span class="font-medium text-foreground">{{ formatInt(pageSummary.lastPage) }}</span>
            <span class="text-border"> · </span>
            <span class="font-medium text-foreground">{{ formatInt(pageSummary.total) }}</span>
            {{ totalLabel }}
        </p>
        <div v-if="showLinks" class="flex flex-wrap items-center justify-center gap-1.5">
            <Button
                v-for="link in links"
                :key="link.label"
                variant="outline"
                size="sm"
                :class="[
 'h-9 min-w-9',
 link.active ? 'border-primary bg-primary/10 text-primary' : '',
 !link.url ? 'opacity-40' : '',
 ]"
                :disabled="!link.url"
                as-child
            >
                <Link v-if="link.url" :href="link.url">{{ paginationLabel(link.label) }}</Link>
                <span v-else>{{ paginationLabel(link.label) }}</span>
            </Button>
        </div>
    </div>
</template>
