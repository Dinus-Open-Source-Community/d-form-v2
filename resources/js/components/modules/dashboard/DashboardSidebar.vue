<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { onClickOutside, useEventListener } from '@vueuse/core';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarRail,
    SidebarSeparator,
    useSidebar,
} from '@/components/ui/sidebar';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    LayoutDashboard,
    CalendarDays,
    Users,
    LogOut,
    ChevronsUpDown,
    Settings,
    PieChart,
} from 'lucide-vue-next';
import logout from '@/actions/App/Http/Controllers/Auth/LogoutController';
import useAuth from '@/utils/composables/useAuth';

const page = usePage();
const user = useAuth(page.props);
const { isMobile, setOpenMobile, state } = useSidebar();

const accountMenuOpen = ref(false);
const accountMenuRootRef = ref<HTMLElement | null>(null);

const canManageEvents = computed(() => user.value?.can_manage_events === true);

const roleLabel = computed(() => (canManageEvents.value ? 'Penyelenggara' : 'Pengguna'));

const currentPath = computed(() => page.url);

/** Beranda penyelenggara vs portal peserta — URL terpisah, sama-sama “Beranda” di UI. */
const mainNavItems = computed(() =>
    canManageEvents.value
        ? [{ label: 'Beranda', href: '/admin/dashboard', icon: LayoutDashboard }]
        : [{ label: 'Beranda', href: '/user/dashboard', icon: LayoutDashboard }],
);

const managementItems = computed(() =>
    canManageEvents.value
        ? [
              { label: 'Acara', href: '/admin/dashboard/events', icon: CalendarDays },
              { label: 'Laporan', href: '/admin/dashboard/reports', icon: PieChart },
              { label: 'Rekrutmen', href: '/admin/dashboard/recruitment', icon: Users },
          ]
        : [{ label: 'Acara saya', href: '/user/dashboard', icon: CalendarDays }],
);

/** Kelas awal/akhir animasi mengikuti arah pembukaan panel. */
const accountPanelEnterFromClass = computed(() => {
    if (isMobile.value) return 'opacity-0 scale-[0.96] translate-y-2';
    if (state.value === 'collapsed') return 'opacity-0 scale-[0.96] -translate-x-2';
    return 'opacity-0 scale-[0.96] translate-y-2';
});

const accountPanelEnterToClass = 'opacity-100 scale-100 translate-x-0 translate-y-0';

/** Panel menu akun: di area sempit (ikon) buka ke kanan; selain itu ke atas. */
const accountPanelPositionClass = computed(() => {
    if (isMobile.value) {
        return 'bottom-full left-0 right-0 mb-2 max-h-[min(70vh,24rem)]';
    }
    if (state.value === 'collapsed') {
        return 'left-full bottom-0 z-[200] ml-2 w-64 max-h-[min(70vh,24rem)]';
    }
    return 'bottom-full left-0 right-0 mb-2 max-h-[min(70vh,24rem)]';
});

function isActive(href: string): boolean {
    const p = currentPath.value.split('?')[0] ?? '';
    if (href === '/admin/dashboard') {
        return p === '/admin/dashboard';
    }
    if (href === '/user/dashboard') {
        return p === '/user/dashboard' || p.startsWith('/user/dashboard/');
    }
    return p.startsWith(href);
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
    accountMenuOpen.value = false;
    setOpenMobile(false);
    router.post(logout().url);
}

function closeMobileIfNeeded() {
    if (isMobile.value) setOpenMobile(false);
}

function goToProfile() {
    accountMenuOpen.value = false;
    closeMobileIfNeeded();
    void nextTick(() => {
        router.visit('/user/dashboard/profile');
    });
}

function toggleAccountMenu() {
    accountMenuOpen.value = !accountMenuOpen.value;
}

onClickOutside(accountMenuRootRef, () => {
    accountMenuOpen.value = false;
});

useEventListener('keydown', (e) => {
    if (e.key === 'Escape') accountMenuOpen.value = false;
});

watch(currentPath, () => {
    accountMenuOpen.value = false;
});
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-sidebar-border bg-sidebar border-r">
        <SidebarHeader class="gap-3 px-3 pt-4 pb-2">
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        as-child
                        class="border-sidebar-border/90 bg-card/90 h-auto border shadow-xs"
                    >
                        <Link :href="canManageEvents ? '/admin/dashboard' : '/user/dashboard'" class="flex items-center gap-3 py-2.5" @click="closeMobileIfNeeded">
                            <div
                                class="bg-primary text-primary-foreground grid size-9 shrink-0 place-items-center rounded-xl text-xs font-bold shadow-sm"
                            >
                                DF
                            </div>
                            <div class="min-w-0 flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                                <span class="font-display block truncate text-[15px] font-bold tracking-[-0.02em]">
                                    D<span class="text-primary">Form</span>
                                </span>
                                <span class="text-muted-foreground mt-0.5 block text-[10px] font-medium">
                                    Panel acara & pendaftaran
                                </span>
                            </div>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarSeparator class="mx-3 opacity-60" />

        <SidebarContent class="px-3 pb-3">
            <div
                class="border-sidebar-border/80 bg-card/40 rounded-2xl border p-2 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.04)] dark:shadow-none"
            >
                <SidebarGroup class="space-y-0 p-0">
                    <SidebarGroupLabel
                        class="text-sidebar-foreground/55 px-2 pt-1 pb-1.5 text-[10px] font-semibold tracking-[0.12em] uppercase"
                    >
                        Menu utama
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu class="gap-0.5">
                            <SidebarMenuItem v-for="item in mainNavItems" :key="item.href">
                                <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.label">
                                    <Link :href="item.href" class="gap-3" @click="closeMobileIfNeeded">
                                        <component :is="item.icon" class="size-4 shrink-0" />
                                        <span>{{ item.label }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>

                <SidebarSeparator class="bg-sidebar-border/70 my-2" />

                <SidebarGroup class="space-y-0 p-0">
                    <SidebarGroupLabel
                        class="text-sidebar-foreground/55 px-2 pt-1 pb-1.5 text-[10px] font-semibold tracking-[0.12em] uppercase"
                    >
                        Kelola
                    </SidebarGroupLabel>
                    <SidebarGroupContent>
                        <SidebarMenu class="gap-0.5">
                            <SidebarMenuItem v-for="item in managementItems" :key="item.href">
                                <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.label">
                                    <Link :href="item.href" class="gap-3" @click="closeMobileIfNeeded">
                                        <component :is="item.icon" class="size-4 shrink-0" />
                                        <span>{{ item.label }}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        </SidebarMenu>
                    </SidebarGroupContent>
                </SidebarGroup>
            </div>
        </SidebarContent>

        <SidebarFooter
            class="border-sidebar-border/70 bg-sidebar-accent/15 mt-auto border-t px-2 pt-2 pb-4 backdrop-blur-[2px]"
        >
            <p
                class="text-sidebar-foreground/50 mb-1.5 px-1.5 text-[10px] font-medium tracking-wide uppercase group-data-[collapsible=icon]:sr-only"
            >
                Akun
            </p>
            <SidebarMenu>
                <SidebarMenuItem class="relative overflow-visible">
                    <div ref="accountMenuRootRef" class="relative w-full overflow-visible">
                        <button
                            type="button"
                            title="Menu akun"
                            :aria-expanded="accountMenuOpen"
                            aria-haspopup="menu"
                            aria-controls="dashboard-account-menu"
                            class="border-sidebar-border bg-card/90 text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground focus-visible:ring-sidebar-ring flex h-12 w-full items-center gap-2 overflow-hidden rounded-xl border p-2 text-left text-sm font-medium shadow-xs outline-none transition-[background-color,box-shadow,border-color] focus-visible:ring-2 active:scale-[0.99] [&>svg]:size-4"
                            :class="
                                accountMenuOpen ? 'border-primary/25 bg-sidebar-accent shadow-sm ring-1 ring-primary/15' : ''
                            "
                            @click="toggleAccountMenu"
                        >
                            <Avatar class="size-8 shrink-0 rounded-lg">
                                <AvatarImage :src="user?.avatar ?? ''" :alt="user?.name ?? ''" />
                                <AvatarFallback
                                    class="bg-primary text-primary-foreground rounded-lg text-[11px] font-bold"
                                >
                                    {{ getInitials(user?.name ?? 'U') }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="min-w-0 flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                                <span class="text-sidebar-foreground block truncate text-xs font-semibold">{{
                                    user?.name ?? 'Pengguna'
                                }}</span>
                                <span class="text-sidebar-foreground/55 mt-0.5 block truncate text-[10px]">
                                    {{ roleLabel }} · Menu akun
                                </span>
                            </div>
                            <ChevronsUpDown
                                class="text-sidebar-foreground/35 size-4 shrink-0 transition-transform group-data-[collapsible=icon]:hidden"
                                :class="accountMenuOpen ? 'rotate-180' : ''"
                            />
                        </button>

                        <Transition
                            :enter-from-class="accountPanelEnterFromClass"
                            :enter-to-class="accountPanelEnterToClass"
                            enter-active-class="transform-gpu transition duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] will-change-[opacity,transform]"
                            :leave-from-class="accountPanelEnterToClass"
                            :leave-to-class="accountPanelEnterFromClass"
                            leave-active-class="transform-gpu transition duration-150 ease-[cubic-bezier(0.4,0,1,1)] will-change-[opacity,transform]"
                        >
                            <div
                                v-if="accountMenuOpen"
                                id="dashboard-account-menu"
                                key="dashboard-account-menu"
                                role="menu"
                                aria-label="Menu akun"
                                :class="[
                                    accountPanelPositionClass,
                                    'border-border/80 bg-popover text-popover-foreground absolute z-[200] flex max-w-[calc(100vw-1rem)] flex-col overflow-y-auto rounded-xl border p-1.5 shadow-xl',
                                ]"
                            >
                            <div role="none" class="px-2 py-2">
                                <div class="flex gap-3">
                                    <Avatar class="size-10 shrink-0 rounded-lg">
                                        <AvatarImage :src="user?.avatar ?? ''" :alt="user?.name ?? ''" />
                                        <AvatarFallback
                                            class="bg-primary text-primary-foreground rounded-lg text-xs font-bold"
                                        >
                                            {{ getInitials(user?.name ?? 'U') }}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div class="min-w-0 flex-1 space-y-0.5">
                                        <p class="truncate text-sm leading-tight font-semibold">{{ user?.name }}</p>
                                        <p class="text-muted-foreground truncate text-xs">{{ user?.email }}</p>
                                        <p class="text-muted-foreground text-[10px] font-medium">{{ roleLabel }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-border/80 my-0.5 h-px w-full shrink-0" role="separator" />
                            <button
                                type="button"
                                role="menuitem"
                                class="hover:bg-accent hover:text-accent-foreground flex w-full cursor-pointer items-start gap-2.5 rounded-lg px-2 py-2.5 text-left outline-none"
                                @click="goToProfile"
                            >
                                <Settings class="text-muted-foreground mt-0.5 size-4 shrink-0" />
                                <span class="flex min-w-0 flex-col gap-0.5">
                                    <span class="text-sm font-medium">Profil & pengaturan</span>
                                    <span class="text-muted-foreground text-[11px]">Data diri & preferensi</span>
                                </span>
                            </button>
                            <div class="bg-border/80 my-0.5 h-px w-full shrink-0" role="separator" />
                            <button
                                type="button"
                                role="menuitem"
                                class="text-destructive hover:bg-destructive/10 focus-visible:ring-destructive/30 flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-2.5 text-left text-sm font-medium outline-none focus-visible:ring-2"
                                @click="handleLogout"
                            >
                                <LogOut class="size-4 shrink-0" />
                                Keluar
                            </button>
                            </div>
                        </Transition>
                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>

        <SidebarRail />
    </Sidebar>
</template>
