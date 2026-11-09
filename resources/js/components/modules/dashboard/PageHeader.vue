<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { ArrowLeft } from 'lucide-vue-next'
import { router } from '@inertiajs/vue3'
import { goBack as navigateBack } from '@/adapters/browser/navigation'

defineProps<{
    title: string
    subtitle?: string
    backHref?: string
}>()

function goBack(fallbackHref?: string): void {
    if (navigateBack()) return

    if (fallbackHref) {
        router.visit(fallbackHref)
    }
}
</script>

<template>
    <div
        class="flex flex-col gap-4 rounded-2xl border border-border/70 bg-card/70 p-4 shadow-sm ring-1 ring-black/[0.03] backdrop-blur-sm sm:p-5 dark:bg-card/80 dark:ring-white/[0.06] md:flex-row md:flex-wrap md:items-start md:justify-between md:gap-6"
    >
        <div class="flex min-w-0 items-start gap-3 md:items-center">
            <Button radius="icon"
                v-if="backHref"
                variant="ghost"
                size="icon-sm"
                class="mt-0.5 shrink-0 md:mt-0"
                type="button"
                @click="goBack(backHref)"
            >
                <ArrowLeft class="size-4" aria-hidden="true" />
                <span class="sr-only">Kembali</span>
            </Button>
            <div class="min-w-0 flex-1 space-y-1.5">
                <h1 class="font-display text-[1.35rem] font-bold leading-tight tracking-tight text-balance sm:text-2xl lg:text-[1.875rem]">
                    {{ title }}
                </h1>
                <p v-if="subtitle" class="max-w-2xl text-[0.9375rem] leading-relaxed text-muted-foreground">
                    {{ subtitle }}
                </p>
            </div>
        </div>
        <div class="w-full min-w-0 md:w-auto md:shrink-0 md:pt-0.5">
            <slot name="actions" />
        </div>
    </div>
</template>
