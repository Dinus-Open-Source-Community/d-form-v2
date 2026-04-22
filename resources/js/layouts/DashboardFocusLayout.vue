<script setup lang="ts">
import 'vue-sonner/style.css'
import { computed } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { Toaster } from '@/components/ui/sonner'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'
import { LogOut, Settings } from 'lucide-vue-next'
import logout from '@/actions/App/Http/Controllers/Auth/LogoutController'
import useAuth from '@/utils/composables/useAuth'

const page = usePage()
const user = useAuth(page.props)

const pageProps = computed(() => page.props as Record<string, any>)

const breadcrumbs = computed<{ label: string; href?: string }[]>(() => {
    if (pageProps.value.breadcrumbs) return pageProps.value.breadcrumbs

    const pathSegments = page.url.split('?')[0].split('/').filter(Boolean)
    const crumbs: { label: string; href?: string }[] = []

    if (pathSegments[0] === 'dashboard') {
        crumbs.push({ label: 'Dashboard', href: '/dashboard' })

        if (pathSegments[1] === 'events') {
            crumbs.push({ label: 'Events', href: '/dashboard/events' })

            if (pathSegments[2] === 'create') {
                crumbs.push({ label: 'Create Event' })
            } else if (pathSegments[2]) {
                const eventTitle = pageProps.value.event?.title ?? 'Event Detail'
                const eventHref = `/dashboard/events/${pathSegments[2]}`
                const sub = pathSegments[3]

                if (sub === 'edit') {
                    crumbs.push({ label: eventTitle, href: eventHref })
                    crumbs.push({ label: 'Edit' })
                } else if (sub === 'registrants') {
                    crumbs.push({ label: eventTitle, href: eventHref })
                    crumbs.push({ label: 'Registrants' })
                } else if (sub === 'forms') {
                    crumbs.push({ label: eventTitle, href: eventHref })
                    crumbs.push({ label: 'Forms', href: `${eventHref}/forms` })
                    const formSub = pathSegments[4]
                    if (formSub === 'create') {
                        crumbs.push({ label: 'Create Form' })
                    } else if (formSub === 'edit') {
                        crumbs.push({ label: 'Edit Form' })
                    } else if (formSub && formSub !== 'index') {
                        crumbs.push({ label: 'Form Detail' })
                    }
                } else if (sub === 'scan') {
                    crumbs.push({ label: eventTitle, href: eventHref })
                    crumbs.push({ label: 'Check-in' })
                } else {
                    crumbs.push({ label: eventTitle })
                }
            }
        }
    }

    return crumbs
})

function getInitials(name: string): string {
    return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}

function handleLogout() {
    router.post(logout().url)
}
</script>

<template>
    <div class="relative flex min-h-svh flex-col bg-background">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 z-0 h-[420px] bg-[radial-gradient(120%_60%_at_50%_0%,color-mix(in_oklab,var(--primary)_10%,transparent),transparent_70%)]"
        />
        <header class="sticky top-0 z-30 border-b border-border/60 bg-background/80 backdrop-blur-lg">
            <div class="mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 px-4 lg:px-8">
                <div class="flex items-center gap-3 min-w-0">
                    <Breadcrumb class="min-w-0">
                        <BreadcrumbList>
                            <template v-for="(crumb, idx) in breadcrumbs" :key="idx">
                                <BreadcrumbSeparator v-if="idx > 0" />
                                <BreadcrumbItem>
                                    <BreadcrumbLink v-if="crumb.href" :href="crumb.href">{{ crumb.label }}</BreadcrumbLink>
                                    <BreadcrumbPage v-else>{{ crumb.label }}</BreadcrumbPage>
                                </BreadcrumbItem>
                            </template>
                        </BreadcrumbList>
                    </Breadcrumb>
                </div>

                <DropdownMenu>
                    <DropdownMenuTrigger as-child>
                        <Button variant="ghost" class="h-9 shrink-0 gap-2 rounded-full px-2">
                            <Avatar class="size-7 rounded-full">
                                <AvatarImage :src="user?.avatar ?? ''" :alt="user?.name ?? ''" />
                                <AvatarFallback class="rounded-full bg-primary/10 text-[10px] font-semibold text-primary">
                                    {{ getInitials(user?.name ?? 'U') }}
                                </AvatarFallback>
                            </Avatar>
                            <span class="hidden text-sm font-medium sm:inline">{{ user?.name }}</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="min-w-48 rounded-xl" align="end" :side-offset="8">
                        <DropdownMenuLabel class="p-0 font-normal">
                            <div class="flex items-center gap-2.5 px-3 py-2.5">
                                <Avatar class="size-8 rounded-full">
                                    <AvatarImage :src="user?.avatar ?? ''" :alt="user?.name ?? ''" />
                                    <AvatarFallback class="rounded-full bg-primary/10 text-xs font-semibold text-primary">
                                        {{ getInitials(user?.name ?? 'U') }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="grid text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ user?.name }}</span>
                                    <span class="truncate text-xs text-muted-foreground">{{ user?.email }}</span>
                                </div>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem as-child>
                            <Link href="/dashboard/profile" class="flex w-full items-center">
                                <Settings class="mr-2 size-4" />Profile
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            class="text-destructive focus:bg-destructive/10 focus:text-destructive"
                            @click="handleLogout"
                        >
                            <LogOut class="mr-2 size-4" />Log out
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </header>

        <main class="relative z-10 flex-1 px-4 pb-14 pt-6 lg:px-8">
            <div class="mx-auto max-w-7xl">
                <slot />
            </div>
        </main>
    </div>
    <Toaster position="top-right" richColors />
</template>
