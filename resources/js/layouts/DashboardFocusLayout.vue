<script setup lang="ts">
import 'vue-sonner/style.css'
import { computed } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { SidebarProvider, SidebarInset, SidebarTrigger } from '@/components/ui/sidebar'
import { Toaster } from '@/components/ui/sonner'
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue'
import { userAvatarSeed } from '@/lib/userAvatarFallback'
import { Button } from '@/components/ui/button'
import DashboardSidebar from '@/components/modules/dashboard/DashboardSidebar.vue'
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { LogOut, Settings } from 'lucide-vue-next'
import logout from '@/actions/App/Http/Controllers/Auth/LogoutController'
import { routes } from '@/lib/routes'
import useAuth from '@/utils/composables/useAuth'

const page = usePage()
const user = useAuth(page.props)

/** Form builder: toolbar digabung editor lewat teleport. */
const isFormBuilderPage = computed(() => {
    const c = page.component
    return c === 'Dashboard/Events/Forms/Show' || c === 'Dashboard/Events/Forms/Create'
})

const userName = computed(() => user.value?.name)

function handleLogout(): void {
    router.post(logout().url)
}
</script>

<template>
    <SidebarProvider>
        <DashboardSidebar />
        <SidebarInset class="h-svh overflow-x-hidden bg-gradient-to-b from-background via-muted/20 to-background">
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
                    <div class="flex min-w-0 items-center gap-2 sm:flex-1">
                        <SidebarTrigger class="shrink-0" />
                        <div id="dashboard-fb-nav-left" class="min-w-0 flex-1" />
                    </div>
                    <div
                        id="dashboard-fb-nav-right"
                        class="flex w-full shrink-0 flex-wrap items-center justify-end gap-2 sm:w-auto sm:gap-2.5"
                    />
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" class="h-10 shrink-0 gap-2 rounded-lg px-2">
                                <UserAvatarFallback
                                    :src="user?.avatar ?? null"
                                    :seed="userAvatarSeed(user)"
                                    avatar-class="size-7 rounded-lg border border-border"
                                    fallback-round-class="rounded-lg"
                                />
                                <span class="hidden max-w-[8rem] truncate text-sm font-semibold sm:inline">{{ userName }}</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent class="min-w-48 rounded-xl" align="end" :side-offset="8">
                            <DropdownMenuLabel class="p-0 font-normal">
                                <div class="flex items-center gap-2.5 px-3 py-2.5">
                                    <UserAvatarFallback
                                        :src="user?.avatar ?? null"
                                        :seed="userAvatarSeed(user)"
                                        avatar-class="size-8"
                                        fallback-round-class="rounded-full"
                                    />
                                    <div class="grid text-left text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ user?.name }}</span>
                                        <span class="truncate text-xs font-medium text-muted-foreground">{{ user?.email }}</span>
                                    </div>
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link :href="routes.dashboard.profile" class="flex w-full items-center">
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
            <SidebarTrigger
                v-else
                class="fixed left-4 top-4 z-30 h-11! w-11! rounded-2xl border border-border/70 bg-card/95 shadow-lg shadow-black/5 backdrop-blur-xl md:hidden"
                aria-label="Buka sidebar"
            />

            <main
                class="relative z-10 flex min-h-0 flex-1 flex-col overflow-y-auto px-4 pb-14 md:px-6 lg:px-8"
                :class="isFormBuilderPage ? 'pt-3 sm:pt-4 lg:pt-5' : 'pt-16 md:pt-8'"
            >
                <div class="mx-auto flex min-h-0 w-full max-w-7xl flex-1 flex-col">
                    <slot />
                </div>
            </main>
        </SidebarInset>
    </SidebarProvider>
    <Toaster position="top-right" richColors />
</template>
