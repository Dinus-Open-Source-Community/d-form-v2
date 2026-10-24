<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select'
import { formatCompactNumber, pageWindow, type TPaginationItem } from '@/lib/pagination'

interface IAppPaginationProps {
    currentPage: number
    /** `null` = cursor pagination: tanpa deret angka dan tanpa rentang total. */
    lastPage?: number | null
    total?: number | null
    perPage?: number
    /** Kirim `[]`/`null` untuk menyembunyikan selector jumlah baris. */
    perPageOptions?: number[] | null
    pageParam?: string
    perPageParam?: string
    /** Kata benda untuk label aksesibel, mis. `acara` atau `pengguna`. */
    unitLabel?: string
    /** URL prev/next dari server; wajib pada mode cursor agar navigasi benar. */
    prevUrl?: string | null
    nextUrl?: string | null
}

const props = withDefaults(defineProps<IAppPaginationProps>(), {
    lastPage: null,
    total: null,
    perPage: 10,
    perPageOptions: (): number[] => [8, 25, 50, 100],
    pageParam: 'page',
    perPageParam: 'per_page',
    unitLabel: 'data',
    prevUrl: null,
    nextUrl: null,
})

const NAVIGATION_OPTIONS = { preserveState: true, preserveScroll: true, replace: true }

const COUNT_FORMATTER = new Intl.NumberFormat('id-ID')

const isCursorMode = computed<boolean>(() => props.lastPage === null || props.total === null)

const perPageChoices = computed<number[]>(() => props.perPageOptions ?? [])

const showPerPageSelector = computed<boolean>(
    (): boolean => perPageChoices.value.length > 0 && props.perPage > 0,
)

const perPageSelectOptions = computed<SimpleSelectOption[]>(() =>
    perPageChoices.value.map(
        (value: number): SimpleSelectOption => ({ value: String(value), label: `${value} per halaman` }),
    ),
)

const selectedPerPage = computed<string>(() => String(props.perPage))

const isVisible = computed<boolean>(() => {
    if (isCursorMode.value) return props.prevUrl !== null || props.nextUrl !== null || showPerPageSelector.value
    return props.total !== null && props.total > 0
})

const rangeStart = computed<number>(() => Math.max(1, (props.currentPage - 1) * props.perPage + 1))

const rangeLabel = computed<string>(() => {
    if (isCursorMode.value || props.total === null || props.total === 0) return ''
    const rangeEnd = Math.min(props.currentPage * props.perPage, props.total)
    return `${COUNT_FORMATTER.format(rangeStart.value)}–${COUNT_FORMATTER.format(rangeEnd)} dari ${formatCompactNumber(props.total)}`
})

const pageItems = computed<TPaginationItem[]>(() =>
    props.lastPage === null || props.lastPage <= 1 ? [] : pageWindow(props.currentPage, props.lastPage),
)

const isPreviousDisabled = computed<boolean>(() =>
    isCursorMode.value ? props.prevUrl === null : props.currentPage <= 1,
)

const isNextDisabled = computed<boolean>(() =>
    isCursorMode.value
        ? props.nextUrl === null
        : props.lastPage === null || props.currentPage >= props.lastPage,
)

/** URL tujuan dari query aktif, agar filter, tab, dan pencarian tidak hilang saat pindah halaman. */
function pageUrl(page: number, perPage: number): string {
    const params = new URLSearchParams(window.location.search)
    params.set(props.pageParam, String(page))
    if (showPerPageSelector.value) params.set(props.perPageParam, String(perPage))
    params.delete('cursor')
    return `${window.location.pathname}?${params.toString()}`
}

/** Pindah ke nomor halaman tertentu, mempertahankan seluruh query filter. */
function goToPage(page: number): void {
    if (isCursorMode.value || page === props.currentPage) return
    if (props.lastPage !== null && (page < 1 || page > props.lastPage)) return
    router.get(pageUrl(page, props.perPage), {}, NAVIGATION_OPTIONS)
}

/** Ganti jumlah baris per halaman lalu kembali ke halaman pertama. */
function changePerPage(nextValue: string): void {
    const nextPerPage = Number(nextValue)
    if (!Number.isInteger(nextPerPage) || nextPerPage <= 0 || nextPerPage === props.perPage) return
    router.get(pageUrl(1, nextPerPage), {}, NAVIGATION_OPTIONS)
}

/** Mode cursor: ikuti tautan server apa adanya karena nomor halaman tidak tersedia. */
function goToCursorPage(url: string | null): void {
    if (url === null) return
    router.get(url, {}, NAVIGATION_OPTIONS)
}

/** Tombol ‹: mundur satu halaman nomor atau satu cursor. */
function goToPrevious(): void {
    if (isCursorMode.value) {
        goToCursorPage(props.prevUrl)
        return
    }
    goToPage(props.currentPage - 1)
}

/** Tombol ›: maju satu halaman nomor atau satu cursor. */
function goToNext(): void {
    if (isCursorMode.value) {
        goToCursorPage(props.nextUrl)
        return
    }
    goToPage(props.currentPage + 1)
}
</script>

<template>
    <div
        v-if="isVisible"
        class="text-muted-foreground flex flex-wrap items-center justify-between gap-3 text-xs sm:text-sm"
    >
        <div class="flex min-w-0 items-center gap-2.5">
            <SimpleSelect
                v-if="showPerPageSelector"
                :model-value="selectedPerPage"
                :options="perPageSelectOptions"
                class="h-8 w-auto text-xs"
                aria-label="Jumlah baris per halaman"
                @update:model-value="changePerPage"
            />
            <p v-if="rangeLabel" class="tabular-nums">{{ rangeLabel }}</p>
        </div>

        <nav :aria-label="`Navigasi halaman ${unitLabel}`" class="flex items-center gap-1">
            <Button
                variant="outline"
                size="icon-sm"
                radius="md"
                class="size-7"
                :disabled="isPreviousDisabled"
                aria-label="Halaman sebelumnya"
                @click="goToPrevious"
            >
                <ChevronLeft class="size-3.5" aria-hidden="true" />
            </Button>

            <div class="hidden items-center gap-1 sm:flex">
                <template v-for="(item, index) in pageItems" :key="index">
                    <span
                        v-if="item === 'ellipsis'"
                        class="text-muted-foreground w-7 text-center"
                        aria-hidden="true"
                    >
                        …
                    </span>
                    <Button
                        v-else
                        variant="ghost"
                        size="icon-sm"
                        radius="md"
                        class="h-7 min-w-7 px-1.5 text-xs font-medium tabular-nums"
                        :class="
                            item === currentPage
                                ? 'bg-primary/10 text-primary font-semibold hover:bg-primary/15'
                                : 'hover:bg-muted'
                        "
                        :aria-current="item === currentPage ? 'page' : undefined"
                        :aria-label="`Halaman ${item}`"
                        @click="goToPage(item)"
                    >
                        {{ item }}
                    </Button>
                </template>
            </div>

            <Button
                variant="outline"
                size="icon-sm"
                radius="md"
                class="size-7"
                :disabled="isNextDisabled"
                aria-label="Halaman berikutnya"
                @click="goToNext"
            >
                <ChevronRight class="size-3.5" aria-hidden="true" />
            </Button>
        </nav>
    </div>
</template>
