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

type DashboardPageExtras = {
    breadcrumbs?: { label: string; href?: string }[];
    event?: { title?: string };
};
const pageProps = computed(() => page.props as DashboardPageExtras)

/** Form builder: navbar digabung editor (teleport), tanpa breadcrumb & profil. */
const isFormBuilderPage = computed(() => {
    const c = page.component
    return c === 'Dashboard/Events/Forms/Show' || c === 'Dashboard/Events/Forms/Create'
})

const breadcrumbs = computed<{ label: string; href?: string }[]>(() => {
    if (pageProps.value.breadcrumbs) return pageProps.value.breadcrumbs

    const pathSegments = page.url.split('?')[0].split('/').filter(Boolean);
    const crumbs: { label: string; href?: string }[] = [];

    if (pathSegments[0] === 'admin' && pathSegments[1] === 'dashboard') {
        crumbs.push({ label: 'Dashboard', href: '/admin/dashboard' });

        if (pathSegments[2] === 'events') {
            crumbs.push({ label: 'Events', href: '/admin/dashboard/events' });

            if (pathSegments[3] === 'create') {
                crumbs.push({ label: 'Create Event' });
            } else if (pathSegments[3]) {
                const eventTitle = pageProps.value.event?.title ?? 'Event Detail';
                const eventHref = `/admin/dashboard/events/${pathSegments[3]}`;
                const sub = pathSegments[4];

                if (sub === 'edit') {
                    crumbs.push({ label: eventTitle, href: eventHref });
                    crumbs.push({ label: 'Edit' });
                } else if (sub === 'registrants') {
                    crumbs.push({ label: eventTitle, href: eventHref });
                    crumbs.push({ label: 'Registrants' });
                } else if (sub === 'forms') {
                    crumbs.push({ label: eventTitle, href: eventHref });
                    crumbs.push({ label: 'Forms', href: `${eventHref}/forms` });
                    const formSub = pathSegments[5];
                    if (formSub === 'create') {
                        crumbs.push({ label: 'Create Form' });
                    } else if (formSub === 'edit') {
                        crumbs.push({ label: 'Edit Form' });
                    } else if (formSub && formSub !== 'index') {
                        crumbs.push({ label: 'Form Detail' });
                    }
                } else if (sub === 'scan') {
                    crumbs.push({ label: eventTitle, href: eventHref });
                    crumbs.push({ label: 'Check-in' });
                } else {
                    crumbs.push({ label: eventTitle });
                }
            }
        }
    }

    if (pathSegments[0] === 'user' && pathSegments[1] === 'dashboard') {
        crumbs.push({ label: 'Acara saya', href: '/user/dashboard' });
        if (pathSegments[2] === 'events' && pathSegments[3] && pathSegments[3] !== 'forms') {
            crumbs.push({ label: pageProps.value.event?.title ?? 'Detail acara' });
        }
    }

    return crumbs;
})

function getInitials(name: string): string {
    return name.split(' ').map((w) => w[0]).join('').toUpperCase().slice(0, 2)
}

function handleLogout(): void {
    router.post(logout().url)
}
</script>

<template>
    <div class="relative flex min-h-svh flex-col bg-background">
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 top-0 z-0 h-[360px] bg-[radial-gradient(120%_60%_at_50%_0%,color-mix(in_oklab,var(--primary)_6%,transparent),transparent_70%)]"
        />

        <header
            v-if="isFormBuilderPage"
            class="sticky top-0 z-40 border-b border-transparent bg-background/80 px-3 pt-3 pb-2 backdrop-blur-xl sm:px-5 lg:px-8"
        >
            <div
                class="border-border/70 from-card/95 to-card/80 mx-auto flex max-w-7xl flex-col gap-2.5 rounded-2xl border bg-gradient-to-b px-3 py-2.5 shadow-sm sm:flex-row sm:items-center sm:gap-3 sm:p-3 sm:shadow-md"
            >
                <div id="dashboard-fb-nav-left" class="min-w-0 w-full sm:flex-1" />
                <div
                    id="dashboard-fb-nav-right"
                    class="flex w-full shrink-0 flex-wrap items-center justify-end gap-2 sm:w-auto sm:gap-2.5"
                />
            </div>
        </header>

        <header
            v-else
            class="sticky top-0 z-30 border-b border-border bg-card/85 backdrop-blur-xl"
        >
            <div class="mx-auto flex h-14 max-w-7xl items-center justify-between gap-4 px-4 lg:px-8">
                <div class="flex min-w-0 items-center gap-3">
                    <Breadcrumb class="min-w-0 hidden sm:flex">
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
                        <Button variant="ghost" class="h-10 shrink-0 gap-2 rounded-lg px-2">
                            <Avatar class="size-7 rounded-lg border border-border">
                                <AvatarImage :src="user?.avatar ?? ''" :alt="user?.name ?? ''" />
                                <AvatarFallback class="rounded-lg bg-primary text-[10px] font-semibold text-primary-foreground">
                                    {{ getInitials(user?.name ?? 'U') }}
                                </AvatarFallback>
                            </Avatar>
                            <span class="hidden text-sm font-semibold sm:inline">{{ user?.name }}</span>
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent class="min-w-48 rounded-xl" align="end" :side-offset="8">
                        <DropdownMenuLabel class="p-0 font-normal">
                            <div class="flex items-center gap-2.5 px-3 py-2.5">
                                <Avatar class="size-8 rounded-full">
                                    <AvatarImage :src="user?.avatar ?? ''" :alt="user?.name ?? ''" />
                                    <AvatarFallback class="rounded-lg bg-primary text-xs font-bold text-primary-foreground">
                                        {{ getInitials(user?.name ?? 'U') }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="grid text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ user?.name }}</span>
                                    <span class="truncate text-xs font-medium text-muted-foreground">{{ user?.email }}</span>
                                </div>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem as-child>
                            <Link href="/user/dashboard/profile" class="flex w-full items-center">
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

        <main
            class="relative z-10 flex min-h-0 flex-1 flex-col px-4 pb-14 lg:px-8"
            :class="isFormBuilderPage ? 'pt-3 sm:pt-4 lg:pt-5' : 'pt-6'"
        >
            <div class="mx-auto flex min-h-0 w-full max-w-7xl flex-1 flex-col">
                <slot />
            </div>
        </main>
    </div>
    <Toaster position="top-right" richColors />
</template>
