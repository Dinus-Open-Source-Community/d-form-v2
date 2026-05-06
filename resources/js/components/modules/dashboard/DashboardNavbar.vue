<script setup lang="ts">
import { computed } from 'vue'
import { usePage, Link, router } from '@inertiajs/vue3'
import { SidebarTrigger } from '@/components/ui/sidebar'
import { Separator } from '@/components/ui/separator'
import { Button } from '@/components/ui/button'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
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
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { User as UserIcon, LogOut, Settings, ChevronsUpDown } from 'lucide-vue-next'
import useAuth from '@/utils/composables/useAuth'
import logout from '@/actions/App/Http/Controllers/Auth/LogoutController'

defineProps<{
    breadcrumbs?: { label: string; href?: string }[]
}>()

const page = usePage()
const user = useAuth(page.props)

const greeting = computed(() => {
    const hour = new Date().getHours()
    if (hour < 12) return 'Good morning'
    if (hour < 17) return 'Good afternoon'
    return 'Good evening'
})

const firstName = computed(() => user.value?.name?.split(' ')[0] ?? '')

const initials = computed(() =>
    user.value?.name?.split(' ').map(w => w[0]).join('').toUpperCase().slice(0, 2) ?? '??',
)
</script>

<template>
    <header class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b border-border/60 bg-background/95 px-4 backdrop-blur-md lg:px-6">
        <SidebarTrigger class="-ml-1" />
        <Separator orientation="vertical" class="mx-1 h-4!" />

        <Breadcrumb v-if="breadcrumbs && breadcrumbs.length > 0" class="hidden sm:flex">
            <BreadcrumbList>
                <template v-for="(crumb, idx) in breadcrumbs" :key="idx">
                    <BreadcrumbItem>
                        <BreadcrumbLink v-if="crumb.href" :href="crumb.href">
                            {{ crumb.label }}
                        </BreadcrumbLink>
                        <BreadcrumbPage v-else>{{ crumb.label }}</BreadcrumbPage>
                    </BreadcrumbItem>
                    <BreadcrumbSeparator v-if="idx < breadcrumbs.length - 1" />
                </template>
            </BreadcrumbList>
        </Breadcrumb>

        <div class="ml-auto flex items-center gap-1.5">
            <p class="mr-1.5 hidden text-sm text-muted-foreground lg:block">
                {{ greeting }}, <span class="font-medium text-foreground">{{ firstName }}</span>
            </p>

            <Separator orientation="vertical" class="mx-1 hidden h-4! lg:block" />

            <Tooltip>
                <TooltipTrigger as-child>
                    <Button variant="ghost" size="icon-sm" as-child aria-label="Profile">
                        <Link href="/dashboard/profile">
                            <Settings class="size-4" />
                        </Link>
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom">Profile</TooltipContent>
            </Tooltip>

            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Log out"
                        class="text-muted-foreground hover:text-destructive"
                        @click="router.post(logout().url)"
                    >
                        <LogOut class="size-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom">Log out</TooltipContent>
            </Tooltip>

            <Separator orientation="vertical" class="mx-0.5 h-4!" />

            <DropdownMenu>
                <DropdownMenuTrigger as-child>
                    <button class="flex items-center gap-2 rounded-lg p-1 transition-colors hover:bg-accent focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
                        <Avatar class="size-7">
                            <AvatarImage :src="user?.avatar ?? ''" :alt="user?.name ?? ''" />
                            <AvatarFallback class="bg-primary/10 text-[10px] font-semibold text-primary">{{ initials }}</AvatarFallback>
                        </Avatar>
                        <span class="hidden max-w-[120px] truncate text-sm font-medium md:inline">{{ firstName }}</span>
                        <ChevronsUpDown class="hidden size-3.5 text-muted-foreground md:block" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56 rounded-lg" :side-offset="6">
                    <DropdownMenuLabel class="font-normal">
                        <div class="flex flex-col gap-0.5">
                            <p class="text-sm font-medium leading-none">{{ user?.name }}</p>
                            <p class="text-xs leading-none text-muted-foreground">{{ user?.email }}</p>
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link href="/dashboard/profile" class="flex w-full items-center">
                            <UserIcon class="mr-2 size-4" />Profile
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        class="text-destructive focus:text-destructive"
                        @click="router.post(logout().url)"
                    >
                        <LogOut class="mr-2 size-4" />Log out
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </header>
</template>
