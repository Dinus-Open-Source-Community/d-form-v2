<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed, useTemplateRef } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetTrigger, SheetTitle } from '@/components/ui/sheet';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import useAuth from '@/utils/composables/useAuth';
import logout from '@/actions/App/Http/Controllers/Auth/LogoutController';
import { LayoutDashboard, UserRound, LogOut, ChevronsUpDown } from 'lucide-vue-next';

const page = usePage();
const user = useAuth(page.props);

const scrolled = ref(false);
const mobileOpen = ref(false);
const currentPath = computed(() => window.location.pathname);

const accountMenuOpen = ref(false);
let accountCloseTimer: ReturnType<typeof setTimeout> | null = null;

const hoverMenusEnabled = ref(false);

/** Jeda sebelum menutup: beri waktu menelusuri celah antara trigger dan panel (portal). */
const HOVER_CLOSE_MS = 320;

const accountTriggerEl = useTemplateRef<HTMLButtonElement>('accountTriggerEl');

function accountDropdownContentEl(): Element | null {
    return document.querySelector('[data-slot="dropdown-menu-content"]');
}

function isInsideAccountDropdown(target: EventTarget | null): boolean {
    if (!target || !(target instanceof Node)) {
        return false;
    }
    const panel = accountDropdownContentEl();
    return Boolean(panel?.contains(target));
}

function isInsideAccountTrigger(target: EventTarget | null): boolean {
    const el = accountTriggerEl.value;
    if (!el || !target || !(target instanceof Node)) {
        return false;
    }
    return el.contains(target);
}

function clearAccountCloseTimer() {
    if (accountCloseTimer !== null) {
        clearTimeout(accountCloseTimer);
        accountCloseTimer = null;
    }
}

function scheduleAccountMenuClose() {
    clearAccountCloseTimer();
    accountCloseTimer = setTimeout(() => {
        accountMenuOpen.value = false;
        accountCloseTimer = null;
    }, HOVER_CLOSE_MS);
}

function onAccountTriggerEnter() {
    if (!hoverMenusEnabled.value) {
        return;
    }
    clearAccountCloseTimer();
    accountMenuOpen.value = true;
}

function onAccountTriggerLeave(e: PointerEvent) {
    if (!hoverMenusEnabled.value) {
        return;
    }
    if (isInsideAccountDropdown(e.relatedTarget)) {
        return;
    }
    scheduleAccountMenuClose();
}

function onAccountContentEnter() {
    clearAccountCloseTimer();
}

function onAccountContentLeave(e: PointerEvent) {
    if (isInsideAccountTrigger(e.relatedTarget)) {
        return;
    }
    scheduleAccountMenuClose();
}

function signOut() {
    router.post(logout().url);
}

const onScroll = () => {
    scrolled.value = window.scrollY > 10;
};

onMounted(() => {
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
    hoverMenusEnabled.value = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
});

onUnmounted(() => {
    window.removeEventListener('scroll', onScroll);
    clearAccountCloseTimer();
});

const links = [
    { label: 'Beranda', href: '/' },
    { label: 'Fitur', href: '/features' },
    { label: 'Acara', href: '/events' },
    { label: 'Dokumentasi', href: '/docs' },
];
</script>

<template>
    <header
        :class="[
            'fixed inset-x-0 top-0 z-50 transition-all duration-300',
            scrolled
                ? 'border-border/50 bg-background/85 border-b shadow-[0_1px_3px_rgb(0_0_0/0.04)] backdrop-blur-xl'
                : 'bg-transparent',
        ]"
    >
        <div class="mx-auto flex h-[4.5rem] max-w-7xl items-center justify-between px-6 lg:px-10">
            <a href="/" class="group flex items-center gap-2.5">
                <img
                    src="/public/DForm%201.png"
                    alt="DOSCOM"
                    class="h-8 w-auto transition-transform duration-200 group-hover:scale-105"
                />
            </a>

            <nav class="hidden items-center gap-1 md:flex" aria-label="Navigasi utama">
                <a
                    v-for="link in links"
                    :key="link.href"
                    :href="link.href"
                    :class="[
                        'rounded-lg px-3.5 py-2 text-sm font-medium transition-colors duration-150',
                        currentPath === link.href ? 'text-primary' : 'text-muted-foreground hover:text-foreground',
                    ]"
                >
                    {{ link.label }}
                </a>
            </nav>

            <div v-if="user" class="hidden items-center gap-2.5 md:flex">
                <DropdownMenu v-model:open="accountMenuOpen">
                    <DropdownMenuTrigger as-child>
                        <button
                            ref="accountTriggerEl"
                            type="button"
                            class="border-border/60 bg-background/40 hover:border-border hover:bg-muted/60 focus-visible:ring-ring flex max-w-[240px] items-center gap-2 rounded-xl border px-2.5 py-1.5 text-left transition-[box-shadow,background-color,border-color] duration-200 focus-visible:ring-2 focus-visible:outline-none"
                            :aria-label="`Menu akun ${user.name}`"
                            @pointerenter="onAccountTriggerEnter"
                            @pointerleave="onAccountTriggerLeave"
                        >
                            <UserAvatarFallback
                                :src="user.avatar ?? null"
                                :seed="userAvatarSeed(user)"
                                avatar-class="size-8 shrink-0 rounded-lg border border-border/80"
                                fallback-round-class="rounded-lg"
                            />
                            <span class="min-w-0 flex-1 truncate text-sm leading-tight font-medium">{{
                                user.name
                            }}</span>
                            <ChevronsUpDown class="text-muted-foreground size-4 shrink-0" aria-hidden="true" />
                        </button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent
                        align="end"
                        :side-offset="4"
                        class="data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2 w-56 origin-top-right rounded-xl duration-200 ease-out"
                        @pointerenter="onAccountContentEnter"
                        @pointerleave="onAccountContentLeave"
                    >
                        <!-- Jembatan hit-area di atas panel supaya hover tidak hilang saat lewat celah portal -->
                        <div class="-mt-2 mb-0 h-2 w-full shrink-0" aria-hidden="true" />
                        <DropdownMenuLabel class="font-normal">
                            <div class="flex flex-col gap-0.5">
                                <p class="text-sm leading-none font-medium">{{ user.name }}</p>
                                <p class="text-muted-foreground text-xs leading-normal">{{ user.email }}</p>
                            </div>
                        </DropdownMenuLabel>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem as-child>
                            <Link href="/dashboard" class="flex w-full cursor-pointer items-center gap-2">
                                <LayoutDashboard class="text-muted-foreground size-4" />
                                Dashboard
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem as-child>
                            <Link href="/dashboard/profile" class="flex w-full cursor-pointer items-center gap-2">
                                <UserRound class="text-muted-foreground size-4" />
                                Profile
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            class="text-destructive focus:text-destructive cursor-pointer"
                            @click="signOut"
                        >
                            <LogOut class="size-4" />
                            Logout
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>

            <div v-else class="hidden items-center gap-2.5 md:flex">
                <Button as-child variant="ghost" size="sm" class="h-9 rounded-lg px-4 text-sm">
                    <a href="/auth/login">Masuk</a>
                </Button>
                <Button as-child size="sm" class="h-9 rounded-lg px-5 text-sm font-semibold">
                    <a href="/auth/register">Daftar</a>
                </Button>
            </div>

            <Sheet v-model:open="mobileOpen">
                <SheetTrigger as-child>
                    <button
                        type="button"
                        class="border-border/70 text-foreground hover:bg-muted inline-flex size-9 items-center justify-center rounded-lg border transition-colors md:hidden"
                        aria-label="Menu navigasi"
                    >
                        <svg width="18" height="18" viewBox="0 0 16 16" fill="none" class="text-foreground">
                            <path
                                d="M2 4h12M2 8h12M2 12h12"
                                stroke="currentColor"
                                stroke-width="1.5"
                                stroke-linecap="round"
                            />
                        </svg>
                    </button>
                </SheetTrigger>
                <SheetContent side="right" class="w-72 p-0">
                    <SheetTitle class="sr-only">Navigasi</SheetTitle>
                    <div class="flex flex-col gap-1 px-6 pt-14">
                        <a
                            v-for="link in links"
                            :key="link.href"
                            :href="link.href"
                            :class="[
                                'rounded-lg px-3 py-2.5 text-sm font-medium transition-colors',
                                currentPath === link.href
                                    ? 'bg-primary/8 text-primary'
                                    : 'text-muted-foreground hover:bg-muted hover:text-foreground',
                            ]"
                            @click="mobileOpen = false"
                        >
                            {{ link.label }}
                        </a>

                        <Separator class="my-5" />

                        <template v-if="user">
                            <div
                                class="border-border/70 bg-muted/30 mb-3 flex items-center gap-3 rounded-xl border px-3 py-2.5"
                            >
                                <UserAvatarFallback
                                    :src="user.avatar ?? null"
                                    :seed="userAvatarSeed(user)"
                                    avatar-class="size-10 shrink-0 rounded-lg border border-border/80"
                                    fallback-round-class="rounded-lg"
                                />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium">{{ user.name }}</p>
                                    <p class="text-muted-foreground truncate text-xs">{{ user.email }}</p>
                                </div>
                            </div>
                            <Button as-child variant="outline" class="h-10 w-full rounded-lg text-sm">
                                <Link href="/dashboard" @click="mobileOpen = false">Dashboard</Link>
                            </Button>
                            <Button as-child variant="outline" class="mt-2 h-10 w-full rounded-lg text-sm">
                                <Link href="/dashboard/profile" @click="mobileOpen = false">Profile</Link>
                            </Button>
                            <Button
                                variant="destructive"
                                class="mt-2 h-10 w-full rounded-lg text-sm"
                                @click="
                                    mobileOpen = false;
                                    signOut();
                                "
                            >
                                Logout
                            </Button>
                        </template>
                        <template v-else>
                            <Button as-child variant="outline" class="h-10 w-full rounded-lg text-sm">
                                <a href="/auth/login" @click="mobileOpen = false">Masuk</a>
                            </Button>
                            <Button as-child class="mt-2 h-10 w-full rounded-lg text-sm font-semibold">
                                <a href="/auth/register" @click="mobileOpen = false">Daftar</a>
                            </Button>
                        </template>
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    </header>
</template>
