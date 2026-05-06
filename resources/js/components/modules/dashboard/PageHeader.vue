<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { ArrowLeft } from 'lucide-vue-next'
import { Link } from '@inertiajs/vue3'

defineProps<{
    title: string
    subtitle?: string
    backHref?: string
}>()
</script>

<template>
    <div class="app-surface flex flex-wrap items-center justify-between gap-4 p-5">
        <div class="flex items-center gap-3">
            <Button v-if="backHref" variant="ghost" size="icon-sm" class="rounded-lg" as-child>
                <Link :href="backHref"><ArrowLeft class="size-4" /></Link>
            </Button>
            <div>
                <h1 class="font-display text-2xl font-bold tracking-[-0.025em] sm:text-3xl">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm leading-relaxed text-muted-foreground">{{ subtitle }}</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <slot name="actions" />
        </div>
    </div>
</template>
