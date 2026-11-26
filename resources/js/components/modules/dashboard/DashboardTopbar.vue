<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { LogOut, ChevronLeft, User } from 'lucide-vue-next';
import { resolveDashboardHomeHref, routes } from '@/lib/routes';
import { buildBreadcrumbs, parentBreadcrumbHref } from '@/lib/breadcrumbs';
import Breadcrumbs from '@/components/modules/dashboard/Breadcrumbs.vue';
import logout from '@/actions/App/Http/Controllers/Auth/LogoutController';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import { useAuth } from '@/hooks/useAuth';
import { useTopbar } from '@/hooks/useDashboardTopbar';
import { readDocumentTitle } from '@/adapters/browser/documentState';
import { onDocumentCustomEvent } from '@/adapters/browser/domEvents';

const page = usePage();
const user = useAuth(page.props);
const topbar = useTopbar();

/** Tombol buka sidebar disembunyikan saat halaman memakai layout tanpa sidebar (mis. builder formulir). */
const props = withDefaults(defineProps<{ showNavigationTrigger?: boolean; pending?: boolean }>(), {
    showNavigationTrigger: true,
    pending: false,
});

const siteName = computed(() => page.props.appName || 'DForm');

function escapeRegExp(s: string): string {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** Ekstrak judul halaman dari <title> ("Judul · DForm" → "Judul"). */
function titleFromDocument(): string {
    const suffix = `\\s*·\\s*${escapeRegExp(siteName.value)}\\s*$`;
    return readDocumentTitle().replace(new RegExp(suffix), '').trim() || 'Dashboard';
}

const fallbackTitle = ref(titleFromDocument());

let titlePollId: number | null = null;
let releaseTitleEvent: () => void = () => undefined;

function syncTitle(): void {
    fallbackTitle.value = titleFromDocument();
}

onMounted(() => {
    syncTitle();
    // Event `titlechange` native tetap dipasang lewat adapter, tetapi poll 500ms
    // tetap berjalan karena adapter tidak mengekspos deteksi dukungan event.
    releaseTitleEvent = onDocumentCustomEvent('titlechange', syncTitle);
    titlePollId = setInterval(syncTitle, 500);
});

onUnmounted(() => {
    releaseTitleEvent();
    if (titlePollId !== null) {
        clearInterval(titlePollId);
        titlePollId = null;
    }
});

const primaryRole = computed<string | null>(() => user.value?.roles?.[0] ?? null);

/** "super-admin" → "Super Admin". */
function formatRole(r: string): string {
    return r
        .split('-')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

/** Judul dari halaman (eksplisit) jika ada; fallback ke parsing judul dokumen. */
const pageTitle = computed(() => topbar.title.value ?? fallbackTitle.value);

/** Selama navigasi, judul dokumen masih milik halaman lama; tampilkan skeleton, bukan judul basi. */
const showTitleSkeleton = computed<boolean>(() => props.pending && topbar.title.value === null);

/** Breadcrumb dibangun dari lib terpisah (pure function). */
const breadcrumbItems = computed(() =>
    buildBreadcrumbs(page.url, pageTitle.value, {
        segmentLabels: topbar.segmentLabels.value ?? {},
        homeHref: resolveDashboardHomeHref(user.value?.can_manage_events === true),
    })
);

/**
 * Tombol kembali memakai induk hierarki halaman (crumb link terdekat), bukan history
 * browser: hasilnya deterministik, tetap benar saat halaman dibuka dari tautan langsung,
 * dan bisa dibuka di tab baru. Halaman akar tidak menampilkan tombol.
 */
const backHref = computed<string | null>(() => parentBreadcrumbHref(breadcrumbItems.value));

const profileMenuOpen = ref(false);
const isLoggingOut = ref(false);

function closeProfileMenu(): void {
    profileMenuOpen.value = false;
}

function handleLogout(): void {
    if (isLoggingOut.value) return;

    isLoggingOut.value = true;
    profileMenuOpen.value = false;
    topbar.clearTopbar();
    router.post(
        logout().url,
        {},
        {
            onFinish: (): void => {
                isLoggingOut.value = false;
            },
        }
    );
}
</script>

<template>
    <header
        class="border-sidebar-border/50 bg-background/80 sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-4 border-b px-4 backdrop-blur-md lg:px-6"
    >
        <div class="flex min-w-0 items-center gap-1.5">
            <SidebarTrigger v-if="props.showNavigationTrigger" class="shrink-0 md:hidden" aria-label="Buka sidebar" />
            <Button v-if="backHref !== null" as-child variant="ghost" radius="icon" size="icon-sm" class="shrink-0">
                <Link :href="backHref" aria-label="Kembali">
                    <ChevronLeft class="size-4 shrink-0 stroke-[1.75]" />
                </Link>
            </Button>
            <div class="flex min-w-0 flex-col">
                <template v-if="showTitleSkeleton">
                    <Skeleton class="h-4 w-40" />
                    <Skeleton class="mt-2 h-3 w-24" />
                </template>
                <template v-else>
                    <p
                        data-testid="topbar-title"
                        class="font-display text-foreground min-w-0 truncate text-base font-semibold tracking-tight sm:text-lg"
                    >
                        {{ pageTitle }}
                    </p>
                    <div class="hidden min-w-0 sm:block">
                        <Breadcrumbs :items="breadcrumbItems" />
                    </div>
                </template>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-1.5">
            <Popover v-model:open="profileMenuOpen" :modal="false">
                <PopoverTrigger as-child>
                    <Button variant="ghost" size="icon" aria-label="Menu profil" class="rounded-full sm:hidden">
                        <UserAvatarFallback
                            :src="user?.avatar ?? null"
                            :seed="userAvatarSeed(user)"
                            avatar-class="size-8 rounded-full ring-1 ring-border"
                            fallback-round-class="rounded-full"
                        />
                    </Button>
                </PopoverTrigger>
                <PopoverContent align="end" :side-offset="8" class="w-48 rounded-xl p-1">
                    <Link
                        :href="routes.dashboard.profile"
                        class="hover:bg-accent hover:text-accent-foreground focus-visible:bg-accent focus-visible:text-accent-foreground relative flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm transition-colors outline-none select-none [&>svg]:size-4 [&>svg]:shrink-0"
                        @click="closeProfileMenu"
                    >
                        <User class="size-4" aria-hidden="true" />
                        <span>Profil</span>
                    </Link>
                    <button
                        type="button"
                        class="text-destructive hover:bg-destructive/10 hover:text-destructive focus-visible:bg-destructive/10 focus-visible:text-destructive relative flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm transition-colors outline-none select-none disabled:cursor-not-allowed disabled:opacity-60 [&>svg]:size-4 [&>svg]:shrink-0"
                        :disabled="isLoggingOut"
                        @click="handleLogout"
                    >
                        <Spinner v-if="isLoggingOut" class="size-4" />
                        <LogOut v-else class="size-4" aria-hidden="true" />
                        <span>Keluar</span>
                    </button>
                </PopoverContent>
            </Popover>

            <div class="hidden shrink-0 items-center gap-1.5 sm:flex">
                <Link
                    :href="routes.dashboard.profile"
                    class="hover:bg-accent flex items-center gap-2 rounded-xl py-1 pr-3 pl-2 transition-colors duration-150"
                >
                    <UserAvatarFallback
                        :src="user?.avatar ?? null"
                        :seed="userAvatarSeed(user)"
                        avatar-class="size-8 rounded-full ring-1 ring-border"
                        fallback-round-class="rounded-full"
                    />
                    <span class="hidden flex-col sm:flex">
                        <span class="text-foreground max-w-[140px] truncate text-sm leading-tight font-medium">
                            {{ user?.name }}
                        </span>
                        <span class="text-muted-foreground max-w-[140px] truncate text-xs leading-tight">
                            {{ primaryRole ? formatRole(primaryRole) : user?.email }}
                        </span>
                    </span>
                </Link>

                <Button
                    variant="ghost"
                    size="sm"
                    aria-label="Keluar"
                    class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive shadow-none transition-colors duration-150 hover:shadow-none"
                    :disabled="isLoggingOut"
                    @click="handleLogout"
                >
                    <Spinner v-if="isLoggingOut" class="size-4" />
                    <LogOut v-else class="size-4" />
                    <span>Keluar</span>
                </Button>
            </div>
        </div>
    </header>
</template>
