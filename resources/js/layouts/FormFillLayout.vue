<script setup lang="ts">
import 'vue-sonner/style.css'
import { computed } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { Toaster } from '@/components/ui/sonner'
import { Button } from '@/components/ui/button'
import { ArrowLeft } from 'lucide-vue-next'
import { routes } from '@/lib/routes'

const page = usePage()

const fallbackBackHref = computed((): string => {
    const event = (page.props.event as { id: string; slug?: string; title: string } | undefined)
    if (event) return routes.member.event.show(event.slug ?? event.id)
    return routes.member.joined
})

function goBack(): void {
    if (typeof window !== 'undefined' && window.history.length > 1) {
        window.history.back()
        return
    }
    router.visit(fallbackBackHref.value)
}
</script>

<template>
    <div class="relative flex min-h-svh flex-col bg-background font-sans">
        <header class="sticky top-0 z-30 border-b border-border bg-card/85 px-4 py-3 backdrop-blur-xl lg:px-8">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4">
                <Button
                    variant="ghost"
                    size="sm"
                    class="h-9 gap-2 rounded-lg px-2.5 text-muted-foreground hover:text-foreground"
                    type="button"
                    @click="goBack"
                >
                    <ArrowLeft class="size-4" aria-hidden="true" />
                    <span>Kembali</span>
                </Button>

                <Link :href="routes.home" class="hidden sm:block">
                    <span class="font-display text-lg font-bold tracking-[-0.02em]">
                        D<span class="text-primary">Form</span>
                    </span>
                </Link>
            </div>
        </header>

        <main class="relative z-10 flex-1 py-8">
            <div class="mx-auto max-w-7xl px-4 lg:px-8">
                <slot />
            </div>
        </main>
    </div>
    <Toaster position="top-right" richColors />
</template>
