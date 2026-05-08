<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { ArrowLeft } from 'lucide-vue-next'
import { Link } from '@inertiajs/vue3'

defineProps<{
    title: string
    subtitle?: string
    /** Label kecil di atas judul, mis. konteks halaman */
    eyebrow?: string
    backHref?: string
}>()
</script>

<template>
    <div
        class="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card/70 p-4 shadow-sm ring-1 ring-black/[0.03] backdrop-blur-sm sm:p-5 dark:bg-card/80 dark:ring-white/[0.06] md:flex-row md:flex-wrap md:items-start md:justify-between md:gap-6"
    >
        <div class="flex min-w-0 items-start gap-3 md:items-center">
            <Button
                v-if="backHref"
                variant="ghost"
                size="icon-sm"
                class="mt-0.5 shrink-0 rounded-lg md:mt-0"
                as-child
            >
                <Link :href="backHref" aria-label="Kembali">
                    <ArrowLeft class="size-4" aria-hidden="true" />
                </Link>
            </Button>
            <div class="min-w-0 flex-1 space-y-1.5">
                <p
                    v-if="eyebrow"
                    class="text-primary text-[11px] font-semibold uppercase tracking-[0.14em]"
                >
                    {{ eyebrow }}
                </p>
                <h1 class="font-display text-xl font-bold tracking-[-0.025em] sm:text-2xl md:text-3xl">
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="max-w-2xl text-sm leading-relaxed text-muted-foreground">
                    {{ subtitle }}
                </p>
            </div>
        </div>
        <div class="w-full min-w-0 md:w-auto md:shrink-0 md:pt-0.5">
            <slot name="actions" />
        </div>
    </div>
</template>
