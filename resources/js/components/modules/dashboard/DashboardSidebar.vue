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
import { isSidebarNavActive, routes } from '@/lib/routes';
import useAuth from '@/utils/composables/useAuth';

const page = usePage();
const user = useAuth(page.props);
const { isMobile, setOpenMobile, state } = useSidebar();

const accountMenuOpen = ref(false);
const accountButtonRef = ref<HTMLElement | null>(null);
const accountMenuRef = ref<HTMLElement | null>(null);
const accountMenuStyle = ref<Record<string, string>>({});

const canManageEvents = computed(() => user.value?.can_manage_events === true);

const roleLabel = computed(() => (canManageEvents.value ? 'Penyelenggara' : 'Pengguna'));

const currentPath = computed(() => page.url);

/** Beranda penyelenggara vs portal peserta — URL terpisah, sama-sama “Beranda” di UI. */
const mainNavItems = computed(() => [
    { label: 'Beranda', href: routes.dashboard.index, icon: LayoutDashboard },
]);

const managementItems = computed(() =>
    canManageEvents.value
        ? [
              { label: 'Acara', href: routes.admin.events.index, icon: CalendarDays },
              { label: 'Rekrutmen', href: routes.admin.recruitment, icon: Users },
          ]
        : [
              { label: 'Acara diikuti', href: routes.member.joined, icon: CalendarCheck2 },
              { label: 'Jelajah acara', href: routes.member.browse, icon: Compass },
          ]
);

const accountPanelEnterFromClass = computed(() => {
    if (state.value === 'collapsed' && !isMobile.value) return 'opacity-0 scale-[0.96] -translate-x-2';
    return 'opacity-0 scale-[0.96] translate-y-2';
});

const accountPanelEnterToClass = 'opacity-100 scale-100 translate-x-0 translate-y-0';

function syncAccountMenuPosition() {
    const btn = accountButtonRef.value;
    if (!btn) return;

    const rect = btn.getBoundingClientRect();
    const panelWidth = 256;
    const gap = 8;
    const maxHeight = Math.min(window.innerHeight * 0.7, 384);

    if (isMobile.value) {
        accountMenuStyle.value = {
            position: 'fixed',
            left: `${Math.max(gap, Math.min(rect.left, window.innerWidth - panelWidth - gap))}px`,
            bottom: `${window.innerHeight - rect.top + gap}px`,
            width: `${Math.min(panelWidth, window.innerWidth - gap * 2)}px`,
            maxHeight: `${maxHeight}px`,
        };
        return;
    }

    if (state.value === 'collapsed') {
        accountMenuStyle.value = {
            position: 'fixed',
            left: `${rect.right + gap}px`,
            top: `${Math.max(gap, Math.min(rect.top, window.innerHeight - maxHeight - gap))}px`,
            width: `${panelWidth}px`,
            maxHeight: `${maxHeight}px`,
        };
        return;
    }

    accountMenuStyle.value = {
        position: 'fixed',
        left: `${rect.left}px`,
        bottom: `${window.innerHeight - rect.top + gap}px`,
        width: `${rect.width}px`,
        maxHeight: `${maxHeight}px`,
    };
}

function isActive(href: string): boolean {
    return isSidebarNavActive(href, currentPath.value);
}

function closeMobileIfNeeded() {
    if (isMobile.value) setOpenMobile(false);
}

function goToProfile() {
    accountMenuOpen.value = false;
    closeMobileIfNeeded();
    void nextTick(() => {
        router.visit(routes.dashboard.profile);
    });
}

async function toggleAccountMenu() {
    accountMenuOpen.value = !accountMenuOpen.value;
    if (accountMenuOpen.value) {
        await nextTick();
        syncAccountMenuPosition();
    }
}

onClickOutside(accountMenuRef, () => {
    accountMenuOpen.value = false;
}, { ignore: [accountButtonRef] });

useEventListener('keydown', (e) => {
    if (e.key === 'Escape') accountMenuOpen.value = false;
});

watch(currentPath, () => {
    accountMenuOpen.value = false;
});

watch(accountMenuOpen, async (open) => {
    if (open) {
        await nextTick();
        syncAccountMenuPosition();
    }
});

useEventListener('resize', () => {
    if (accountMenuOpen.value) syncAccountMenuPosition();
});

/** URL logo publik — dibentuk saat runtime agar Vite tidak mem-bundel path file PNG. */
const sidebarLogoSrc = `/${encodeURIComponent('DForm 1.png')}`;
</script>

<template>
    <Sidebar collapsible="icon" variant="sidebar" class="border-sidebar-border bg-sidebar overflow-x-hidden border-r">
        <SidebarHeader class="gap-0 overflow-hidden border-b border-sidebar-border/50 p-0">
            <Link
                :href="routes.dashboard.index"
                class="hover:bg-sidebar-accent/25 flex w-full min-w-0 items-center overflow-hidden px-4 py-3.5 transition-colors"
                @click="closeMobileIfNeeded"
            >
                <img
                    :src="sidebarLogoSrc"
                    alt="DForm"
                    class="h-auto max-h-9 w-full max-w-full object-contain object-center select-none group-data-[collapsible=icon]:mx-auto group-data-[collapsible=icon]:h-8 group-data-[collapsible=icon]:w-8 group-data-[collapsible=icon]:max-h-8 group-data-[collapsible=icon]:max-w-8 group-data-[collapsible=icon]:object-contain"
                    width="160"
                    height="40"
                />
            </Link>
        </SidebarHeader>

        <SidebarContent class="flex-1 overflow-x-hidden px-2.5 pb-3 pt-3">
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

        <SidebarFooter class="border-sidebar-border/60 relative z-10 mt-auto shrink-0 border-t p-2.5">
            <!-- Mobile: inline account menu (rendered inside Sheet content tree) -->
            <div
                v-if="isMobile && accountMenuOpen"
                ref="accountMenuRef"
                role="menu"
                aria-label="Menu akun"
                class="border-border/80 bg-popover text-popover-foreground mb-2 flex flex-col rounded-xl border p-1.5 shadow-md"
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
                    class="hover:bg-accent hover:text-accent-foreground flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2 py-2.5 text-left outline-none"
                    @click="goToProfile"
                >
                    <Settings class="text-muted-foreground size-4 shrink-0" />
                    <span class="text-sm font-medium">Profil & pengaturan</span>
                </button>
                <div class="bg-border/80 my-0.5 h-px w-full shrink-0" role="separator" />
                <Link
                    :href="logout().url"
                    method="post"
                    as="button"
                    type="button"
                    role="menuitem"
                    class="text-destructive hover:bg-destructive/10 focus-visible:ring-destructive/30 flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-2.5 text-left text-sm font-medium outline-none focus-visible:ring-2"
                    @click="() => { accountMenuOpen = false; setOpenMobile(false); }"
                >
                    <LogOut class="size-4 shrink-0" />
                    Keluar
                </Link>
            </div>

            <SidebarMenu>
                <SidebarMenuItem class="relative">
                    <div class="relative w-full min-w-0">
                        <button
                            ref="accountButtonRef"
                            type="button"
                            title="Menu akun"
                            :aria-expanded="accountMenuOpen"
                            aria-haspopup="menu"
                            aria-controls="dashboard-account-menu"
                            class="text-sidebar-foreground hover:bg-sidebar-accent/80 flex h-11 w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left text-sm transition-colors outline-none focus-visible:ring-2 focus-visible:ring-sidebar-ring"
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

                    </div>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarFooter>

        <!-- Desktop: teleported account menu (outside Sheet context) -->
        <Teleport to="body">
            <Transition
                :enter-from-class="accountPanelEnterFromClass"
                :enter-to-class="accountPanelEnterToClass"
                enter-active-class="transform-gpu transition duration-200 ease-[cubic-bezier(0.16,1,0.3,1)] will-change-[opacity,transform]"
                :leave-from-class="accountPanelEnterToClass"
                :leave-to-class="accountPanelEnterFromClass"
                leave-active-class="transform-gpu transition duration-150 ease-[cubic-bezier(0.4,0,1,1)] will-change-[opacity,transform]"
            >
                <div
                    v-if="!isMobile && accountMenuOpen"
                    id="dashboard-account-menu"
                    ref="accountMenuRef"
                    key="dashboard-account-menu"
                    role="menu"
                    aria-label="Menu akun"
                    :style="accountMenuStyle"
                    class="border-border/80 bg-popover text-popover-foreground z-[250] flex flex-col overflow-y-auto rounded-xl border p-1.5 shadow-xl"
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
                        class="hover:bg-accent hover:text-accent-foreground flex w-full cursor-pointer items-center gap-2.5 rounded-lg px-2 py-2.5 text-left outline-none"
                        @click="goToProfile"
                    >
                        <Settings class="text-muted-foreground size-4 shrink-0" />
                        <span class="text-sm font-medium">Profil & pengaturan</span>
                    </button>
                    <div class="bg-border/80 my-0.5 h-px w-full shrink-0" role="separator" />
                    <Link
                        :href="logout().url"
                        method="post"
                        as="button"
                        type="button"
                        role="menuitem"
                        class="text-destructive hover:bg-destructive/10 focus-visible:ring-destructive/30 flex w-full cursor-pointer items-center gap-2 rounded-lg px-2 py-2.5 text-left text-sm font-medium outline-none focus-visible:ring-2"
                        @click="() => { accountMenuOpen = false; setOpenMobile(false); }"
                    >
                        <LogOut class="size-4 shrink-0" />
                        Keluar
                    </Link>
                </div>
            </Transition>
        </Teleport>

        <SidebarRail />
    </Sidebar>
</template>
