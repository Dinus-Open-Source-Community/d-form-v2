<script setup lang="ts">
import { computed } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { LayoutDashboard, CalendarDays, Users, LogOut, ChevronsUpDown, Settings } from 'lucide-vue-next';
import logout from '@/actions/App/Http/Controllers/Auth/LogoutController';
import useAuth from '@/utils/composables/useAuth';

const page = usePage();
const user = useAuth(page.props);

const isAdmin = computed(() => {
    const roles = user.value?.roles;
    if (!roles || roles.length === 0) return false;
    return roles.includes('admin') || roles.includes('super-admin');
});

const currentPath = computed(() => page.url);

const mainNavItems = computed(() => [{ label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard }]);

const managementItems = computed(() =>
    isAdmin.value
        ? [
              { label: 'Events', href: '/dashboard/events', icon: CalendarDays, badge: '8' },
              { label: 'Recruitment', href: '/dashboard/recruitment', icon: Users },
          ]
        : [{ label: 'Events', href: '/dashboard/user/events', icon: CalendarDays }]
);

function isActive(href: string): boolean {
    if (href === '/dashboard') return currentPath.value === '/dashboard';
    return currentPath.value.startsWith(href);
}

function getInitials(name: string): string {
    return name
        .split(' ')
        .map((w) => w[0])
        .join('')
        .toUpperCase()
        .slice(0, 2);
}

function handleLogout() {
    router.post(logout().url);
}
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-r border-sidebar-border bg-sidebar">
        <SidebarHeader class="px-3 pt-3 pb-1">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <a
                            href="/dashboard"
                            class="flex items-center gap-3 rounded-xl border border-sidebar-border bg-card p-2 shadow-xs"
                        >
                            <div class="grid size-8 place-items-center rounded-lg bg-primary text-xs font-semibold text-primary-foreground">
                                DF
                            </div>
                            <div class="grid leading-none">
                                <span class="font-display text-base font-bold tracking-[-0.02em]">
                                    D<span class="text-primary">Form</span>
                                </span>
                                <span class="text-muted-foreground text-[9px] font-semibold tracking-[0.14em] uppercase">
                                    Event Manager
                                </span>
                            </div>
                        </a>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <div class="mt-2 inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/8 px-2.5 py-1 text-[9px] font-semibold tracking-[0.14em] text-primary uppercase group-data-[collapsible=icon]:hidden">
                <span class="size-1.5 rounded-full bg-primary"></span>
                Workspace Ready
            </div>
        </SidebarHeader>

        <SidebarSeparator class="mx-3 my-2" />

        <SidebarContent class="px-2">
            <SidebarGroup class="rounded-xl border border-sidebar-border bg-card/60 p-2">
                <SidebarGroupLabel class="text-sidebar-foreground/50 text-[9px] font-semibold tracking-[0.14em] uppercase">
                    Main
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in mainNavItems" :key="item.href">
                            <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.label">
                                <Link :href="item.href">
                                    <component :is="item.icon" />
                                    <span>{{ item.label }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup class="mt-2 rounded-xl border border-sidebar-border bg-card/60 p-2">
                <SidebarGroupLabel class="text-sidebar-foreground/50 text-[9px] font-semibold tracking-[0.14em] uppercase">
                    Management
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem v-for="item in managementItems" :key="item.href">
                            <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.label">
                                <Link :href="item.href">
                                    <component :is="item.icon" />
                                    <span>{{ item.label }}</span>
                                </Link>
                            </SidebarMenuButton>
                            <SidebarMenuBadge
                                v-if="'badge' in item && item.badge"
                                class="border border-primary/20 bg-primary/10 px-1.5 text-[10px] font-semibold text-primary shadow-none"
                            >
                                {{ item.badge }}
                            </SidebarMenuBadge>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <SidebarGroup class="mt-auto rounded-xl border border-sidebar-border bg-card/60 p-2">
                <SidebarGroupLabel class="text-sidebar-foreground/50 text-[9px] font-semibold tracking-[0.14em] uppercase">
                    Account
                </SidebarGroupLabel>
                <SidebarGroupContent>
                    <SidebarMenu>
                        <SidebarMenuItem>
                            <SidebarMenuButton as-child :is-active="isActive('/dashboard/profile')" tooltip="Profile">
                                <Link href="/dashboard/profile">
                                    <Settings />
                                    <span>Profile</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                        <SidebarMenuItem>
                            <SidebarMenuButton
                                tooltip="Log out"
                                class="text-sidebar-foreground/50 hover:text-destructive hover:bg-destructive/8 active:bg-destructive/12"
                                @click="handleLogout"
                            >
                                <LogOut />
                                <span>Log out</span>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarSeparator class="mx-3 my-2" />

        <SidebarFooter class="px-3 pb-3">
            <SidebarMenu>
                <SidebarMenuItem>
                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <SidebarMenuButton
                                size="lg"
                                class="rounded-xl border border-sidebar-border bg-card/80 shadow-xs data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                            >
                                <Avatar class="size-7 rounded-md">
                                    <AvatarImage :src="user?.avatar ?? ''" :alt="user?.name ?? ''" />
                                    <AvatarFallback
                                        class="bg-primary text-primary-foreground rounded-md text-[10px] font-bold"
                                    >
                                        {{ getInitials(user?.name ?? 'U') }}
                                    </AvatarFallback>
                                </Avatar>
                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate text-xs font-semibold">{{ user?.name }}</span>
                                    <span class="text-sidebar-foreground/40 truncate text-[10px]">{{
                                        user?.email
                                    }}</span>
                                </div>
                                <ChevronsUpDown class="text-sidebar-foreground/30 ml-auto size-3.5" />
                            </SidebarMenuButton>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent
                            class="w-[--reka-dropdown-menu-trigger-width] min-w-56 rounded-lg"
                            side="top"
                            align="start"
                            :side-offset="6"
                        >
                            <DropdownMenuLabel class="p-0 font-normal">
                                <div class="flex items-center gap-2.5 px-2 py-2">
                                    <Avatar class="size-8 rounded-md">
                                        <AvatarImage :src="user?.avatar ?? ''" :alt="user?.name ?? ''" />
                                        <AvatarFallback
                                            class="bg-primary text-primary-foreground rounded-md text-xs font-bold"
                                        >
                                            {{ getInitials(user?.name ?? 'U') }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="grid text-left text-sm leading-tight">
                                        <span class="truncate font-semibold">{{ user?.name }}</span>
                                        <span class="text-muted-foreground truncate text-xs">{{ user?.email }}</span>
                                    </div>
                                </div>
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem as-child>
                                <Link href="/dashboard/profile" class="flex w-full items-center">
                                    <Settings class="mr-2 size-4" />
                                    Profile
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                class="text-destructive focus:text-destructive focus:bg-destructive/10"
                                @click="handleLogout"
                            >
                                <LogOut class="mr-2 size-4" />
                                Log out
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>

        <SidebarRail />
    </Sidebar>
</template>
