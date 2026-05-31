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
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import {
    LayoutDashboard,
    CalendarDays,
    CalendarCheck2,
    Compass,
    Users,
    LogOut,
    ChevronsUpDown,
    Settings,
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
        : [{ label: 'Beranda', href: '/dashboard', icon: LayoutDashboard }]
);

const managementItems = computed(() =>
    canManageEvents.value
        ? [
              { label: 'Acara', href: '/admin/dashboard/events', icon: CalendarDays },
              { label: 'Rekrutmen', href: '/admin/dashboard/recruitment', icon: Users },
          ]
        : [
              { label: 'Acara diikuti', href: '/events/joined', icon: CalendarCheck2 },
              { label: 'Jelajah acara', href: '/events/joined/events/browse', icon: Compass },
          ]
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
    if (href === '/dashboard') {
        return p === '/dashboard';
    }
    if (href === '/events/joined/events/browse') {
        return p === '/events/joined/events/browse';
    }
    if (href === '/events/joined') {
        return p === '/events/joined';
    }
    return p.startsWith(href);
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
        router.visit('/dashboard/profile');
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

/** URL logo publik — dibentuk saat runtime agar Vite tidak mem-bundel path file PNG. */
const sidebarLogoSrc = `/${encodeURIComponent('DForm 1.png')}`;
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-sidebar-border bg-sidebar border-r">
        <SidebarHeader class="gap-0 border-b border-sidebar-border/50 p-0">
            <Link
                :href="canManageEvents ? '/admin/dashboard' : '/dashboard'"
                class="hover:bg-sidebar-accent/25 flex w-full items-center px-4 py-3.5 transition-colors"
                @click="closeMobileIfNeeded"
            >
                <img
                    :src="sidebarLogoSrc"
                    alt="DForm"
                    class="h-auto w-full max-h-9 object-contain object-center select-none group-data-[collapsible=icon]:mx-auto group-data-[collapsible=icon]:h-8 group-data-[collapsible=icon]:w-8 group-data-[collapsible=icon]:max-h-8 group-data-[collapsible=icon]:object-contain"
                    width="160"
                    height="40"
                />
            </Link>
        </SidebarHeader>

        <SidebarContent class="flex-1 px-2.5 pb-3 pt-3">
            <SidebarGroup class="p-0">
                <SidebarGroupLabel
                    class="text-sidebar-foreground/45 mb-2 px-2 text-[10px] font-semibold tracking-[0.14em] uppercase"
                >
                    Menu utama
                </SidebarGroupLabel>
                <SidebarGroupContent class="space-y-0.5">
                    <SidebarMenu class="gap-0.5">
                        <SidebarMenuItem v-for="item in mainNavItems" :key="item.href">
                            <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.label">
                                <Link :href="item.href" class="gap-3 rounded-lg" @click="closeMobileIfNeeded">
                                    <component :is="item.icon" class="size-4 shrink-0 opacity-90" />
                                    <span class="font-medium">{{ item.label }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>

            <SidebarSeparator class="bg-sidebar-border/60 my-3 opacity-80" />

            <SidebarGroup class="p-0">
                <SidebarGroupLabel
                    class="text-sidebar-foreground/45 mb-2 px-2 text-[10px] font-semibold tracking-[0.14em] uppercase"
                >
                    Kelola
                </SidebarGroupLabel>
                <SidebarGroupContent class="space-y-0.5">
                    <SidebarMenu class="gap-0.5">
                        <SidebarMenuItem v-for="item in managementItems" :key="item.href">
                            <SidebarMenuButton as-child :is-active="isActive(item.href)" :tooltip="item.label">
                                <Link :href="item.href" class="gap-3 rounded-lg" @click="closeMobileIfNeeded">
                                    <component :is="item.icon" class="size-4 shrink-0 opacity-90" />
                                    <span class="font-medium">{{ item.label }}</span>
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    </SidebarMenu>
                </SidebarGroupContent>
            </SidebarGroup>
        </SidebarContent>

        <SidebarFooter class="border-sidebar-border/60 mt-auto border-t p-2.5">
            <SidebarMenu>
                <SidebarMenuItem class="relative overflow-visible">
                    <div ref="accountMenuRootRef" class="relative w-full overflow-visible">
                        <button
                            type="button"
                            title="Menu akun"
                            :aria-expanded="accountMenuOpen"
                            aria-haspopup="menu"
                            aria-controls="dashboard-account-menu"
                            class="text-sidebar-foreground hover:bg-sidebar-accent/80 flex h-11 w-full items-center gap-2.5 overflow-hidden rounded-lg px-2 py-1.5 text-left text-sm transition-colors outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
                            :class="accountMenuOpen ? 'bg-sidebar-accent/90 ring-1 ring-primary/20' : ''"
                            @click="toggleAccountMenu"
                        >
                            <UserAvatarFallback
                                :src="user?.avatar ?? null"
                                :seed="userAvatarSeed(user)"
                                avatar-class="size-8 shrink-0 rounded-md"
                                fallback-round-class="rounded-md"
                            />
                            <div class="min-w-0 flex-1 text-left leading-tight group-data-[collapsible=icon]:hidden">
                                <span class="text-sidebar-foreground block truncate text-xs font-semibold">{{
                                    user?.name ?? 'Pengguna'
                                }}</span>
                                <span class="text-sidebar-foreground/50 mt-0.5 block truncate text-[10px]">
                                    {{ roleLabel }}
                                </span>
                            </div>
                            <ChevronsUpDown
                                class="text-sidebar-foreground/40 size-4 shrink-0 transition-transform group-data-[collapsible=icon]:hidden"
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
                                        <UserAvatarFallback
                                            :src="user?.avatar ?? null"
                                            :seed="userAvatarSeed(user)"
                                            avatar-class="size-10 shrink-0 rounded-lg"
                                            fallback-round-class="rounded-lg"
                                        />
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
