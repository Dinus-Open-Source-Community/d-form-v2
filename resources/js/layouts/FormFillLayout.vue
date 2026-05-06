<script setup lang="ts">
import 'vue-sonner/style.css'
import { computed } from 'vue'
import { usePage, Link } from '@inertiajs/vue3'
import { Toaster } from '@/components/ui/sonner'
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'

const page = usePage()

const breadcrumbs = computed((): { label: string; href?: string }[] => {
    // Explicitly check for event data in props or fallback to URL-based
    const event = (page.props.event as { id: string; slug?: string; title: string } | undefined)
    const form = (page.props.form as { id: string; title: string } | undefined)
    
    const crumbs: { label: string; href?: string }[] = [
        { label: 'Events', href: '/dashboard/user/events' },
    ]

    if (event) {
        crumbs.push({ label: event.title, href: `/dashboard/user/events/${event.slug ?? event.id}` })
    }

    if (form) {
        crumbs.push({ label: form.title })
    } else {
        crumbs.push({ label: 'Form' })
    }

    return crumbs
})
</script>

<template>
    <div class="relative flex min-h-svh flex-col bg-background font-sans">
        <!-- Minimal Header with only Breadcrumbs -->
        <header class="sticky top-0 z-30 border-b border-border bg-card/85 px-4 py-3 backdrop-blur-xl lg:px-8">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4">
                <Breadcrumb>
                    <BreadcrumbList>
                        <template v-for="(crumb, idx) in breadcrumbs" :key="idx">
                            <BreadcrumbSeparator v-if="idx > 0" />
                            <BreadcrumbItem>
                                <BreadcrumbLink v-if="crumb.href" :href="crumb.href" class="font-medium text-muted-foreground transition-colors hover:text-foreground">
                                    {{ crumb.label }}
                                </BreadcrumbLink>
                                <BreadcrumbPage v-else class="font-semibold text-foreground">{{ crumb.label }}</BreadcrumbPage>
                            </BreadcrumbItem>
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>

                <Link href="/" class="hidden sm:block">
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
