<script setup lang="ts">
import { computed } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Separator } from '@/components/ui/separator';
import { Button } from '@/components/ui/button';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { User as UserIcon, LogOut, Settings, ChevronsUpDown, ArrowLeft } from 'lucide-vue-next';
import { useAuth } from '@/hooks/useAuth';
import logout from '@/actions/App/Http/Controllers/Auth/LogoutController';
import { resolveNavbarFallbackBackHref, routes } from '@/lib/routes';
import { goBack as navigateBack } from '@/adapters/browser/navigation';

const page = usePage();
const user = useAuth(page.props);

const greeting = computed(() => {
    const hour = new Date().getHours();
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
});

const fallbackBackHref = computed(() => resolveNavbarFallbackBackHref(page.url));

function goBack(): void {
    if (navigateBack()) return;
    router.visit(fallbackBackHref.value);
}
</script>

<template>
    <header
        class="border-border/60 bg-background/95 sticky top-0 z-20 flex h-14 shrink-0 items-center gap-2 border-b px-4 backdrop-blur-md lg:px-6"
    >
        <SidebarTrigger class="-ml-1" />
        <Separator orientation="vertical" class="mx-1 h-4!" />

        <Button
            variant="ghost"
            size="sm"
            class="text-muted-foreground hover:text-foreground h-9 gap-2 px-2.5"
            type="button"
            @click="goBack"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />
            <span class="hidden sm:inline">Kembali</span>
        </Button>

        <div class="ml-auto flex items-center gap-1.5">
            <p class="text-muted-foreground mr-1.5 hidden text-sm lg:block">
                {{ greeting }}, <span class="text-foreground font-medium">{{ user?.name }}</span>
            </p>

            <Separator orientation="vertical" class="mx-1 hidden h-4! lg:block" />

            <Tooltip>
                <TooltipTrigger as-child>
                    <Button radius="icon" variant="ghost" size="icon-sm" as-child aria-label="Profile">
                        <Link :href="routes.dashboard.profile">
                            <Settings class="size-4" />
                        </Link>
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom">Profile</TooltipContent>
            </Tooltip>

            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        radius="icon"
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
                    <button
                        class="hover:bg-accent focus-visible:ring-ring flex items-center gap-2 p-1 transition-colors focus-visible:ring-2 focus-visible:outline-none"
                    >
                        <UserAvatarFallback
                            :src="user?.avatar ?? null"
                            :seed="userAvatarSeed(user)"
                            avatar-class="size-7 rounded-lg border border-border"
                            fallback-round-class="rounded-lg"
                        />
                        <span class="hidden max-w-[120px] truncate text-sm font-medium md:inline">{{
                            user?.name
                        }}</span>
                        <ChevronsUpDown class="text-muted-foreground hidden size-3.5 md:block" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" class="w-56 rounded-lg" :side-offset="6">
                    <DropdownMenuLabel class="font-normal">
                        <div class="flex flex-col gap-0.5">
                            <p class="text-sm leading-none font-medium">{{ user?.name }}</p>
                            <p class="text-muted-foreground text-xs leading-none">{{ user?.email }}</p>
                        </div>
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem as-child>
                        <Link :href="routes.dashboard.profile" class="flex w-full items-center">
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
