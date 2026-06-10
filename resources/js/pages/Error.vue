<script setup lang="ts">
import { computed } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import LocalLottie from '@/components/core/LocalLottie.vue'
import { Button } from '@/components/ui/button'
import type { LottieName } from '@/lib/lotties'
import { routes } from '@/lib/routes'

const props = defineProps<{
    status: number
}>()

const lottieName = computed<LottieName>(() =>
    props.status === 404 ? 'error404Page' : 'errorState',
)

const title = computed(() => {
    switch (props.status) {
        case 404:
            return 'Halaman tidak ditemukan'
        case 403:
            return 'Akses ditolak'
        case 429:
            return 'Terlalu banyak permintaan'
        case 502:
        case 503:
            return 'Layanan tidak tersedia'
        default:
            return 'Terjadi kesalahan'
    }
})

const description = computed(() => {
    switch (props.status) {
        case 404:
            return 'Kami tidak menemukan halaman yang Anda cari. Periksa URL atau gunakan tombol di bawah untuk kembali.'
        case 403:
            return 'Anda tidak memiliki izin untuk mengakses sumber ini. Hubungi penyelenggara jika menurut Anda ini kesalahan.'
        case 429:
            return 'Permintaan Anda terlalu sering. Tunggu beberapa saat, lalu coba lagi.'
        case 502:
        case 503:
            return 'Server sedang sibuk atau dalam perawatan. Silakan coba lagi nanti.'
        default:
            return 'Terjadi kesalahan yang tidak terduga. Coba kembali atau muat ulang halaman nanti.'
    }
})

const headTitle = computed(() => `${props.status} · ${title.value}`)

function goBack(): void {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back()
        return
    }
    router.visit(routes.home)
}
</script>

<template>
    <Head :title="headTitle" />

    <main
        class="bg-background text-foreground flex min-h-svh flex-col items-center justify-center px-6 py-16"
        :aria-label="`Error ${status}`"
    >
        <div class="mx-auto flex w-full max-w-lg flex-col items-center text-center">
            <div
                class="flex w-full max-w-[20rem] items-center justify-center sm:max-w-[22rem]"
                aria-hidden="true"
            >
                <LocalLottie
                    :name="lottieName"
                    :height="280"
                    width="100%"
                    :lazy="false"
                />
            </div>

            <p class="text-muted-foreground mt-8 text-xs font-medium tracking-wide tabular-nums uppercase">
                {{ status }}
            </p>
            <h1 class="font-display text-foreground mt-2 text-2xl font-semibold tracking-tight sm:text-3xl">
                {{ title }}
            </h1>
            <p class="text-muted-foreground mt-4 max-w-md text-pretty text-sm leading-relaxed sm:text-base">
                {{ description }}
            </p>

            <Button type="button" size="lg" class="mt-10 min-w-[11rem] rounded-full" @click="goBack">
                Kembali
            </Button>
        </div>
    </main>
</template>
