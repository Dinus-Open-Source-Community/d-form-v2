<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { LogOut, Search } from 'lucide-vue-next';
import { routes } from '@/lib/routes';
import logout from '@/actions/App/Http/Controllers/Auth/LogoutController';
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import useAuth from '@/utils/composables/useAuth';
import { useTopbar } from '@/utils/composables/useDashboardTopbar';

const page = usePage();
const user = useAuth(page.props);
const topbar = useTopbar();

const siteName = computed(() => page.props.appName || 'DForm');

function escapeRegExp(s: string): string {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** "super-admin" → "Super Admin". */
function formatRole(r: string): string {
    return r
        .split('-')
        .map((w) => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ');
}

const greetingSubtitle = computed(() => {
    if (!user.value) return 'Selamat datang';
    const hour = new Date().getHours();
    const greeting = hour < 12 ? 'Selamat pagi' : hour < 17 ? 'Selamat siang' : 'Selamat malam';
    const firstName = user.value.name?.split(' ')[0] ?? '';
    return firstName ? `${greeting}, ${firstName}` : greeting;
});

/** Ekstrak judul halaman dari <title> ("Judul · DForm" → "Judul"). */
function titleFromDocument(): string {
    if (typeof document === 'undefined') return 'Dashboard';
    const suffix = `\\s*·\\s*${escapeRegExp(siteName.value)}\\s*$`;
    return document.title.replace(new RegExp(suffix), '').trim() || 'Dashboard';
}

const fallbackTitle = ref(titleFromDocument());
const search = ref('');

let titlePollId: number | null = null;

function syncTitle(): void {
    fallbackTitle.value = titleFromDocument();
}

onMounted(() => {
    syncTitle();
    // `titlechange` (Chrome 136+, 2025). Fallback: poll tiap 500ms saat navigasi Inertia.
    if (typeof document.addEventListener === 'function' && 'onTitleChange' in document) {
        document.addEventListener('titlechange', syncTitle);
    } else {
        titlePollId = window.setInterval(syncTitle, 500);
    }
});

onUnmounted(() => {
    if ('onTitleChange' in document) {
        document.removeEventListener('titlechange', syncTitle);
    }
    if (titlePollId !== null) {
        window.clearInterval(titlePollId);
    }
});

/** Judul dari halaman (eksplisit) jika ada; fallback ke parsing document.title. */
const pageTitle = computed(() => topbar.title.value ?? fallbackTitle.value);

/** Subtitle dari halaman (eksplisit) jika ada; fallback ke greeting. */
const subtitle = computed(() => topbar.subtitle.value ?? greetingSubtitle.value);
</script>

<template>
    <header
        class="border-sidebar-border/50 bg-background/80 sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-4 border-b px-4 backdrop-blur-md lg:px-6"
    >
        <!-- Kiri: judul + subtitle -->
        <div class="flex min-w-0 flex-col">
            <h1 class="font-display text-foreground min-w-0 truncate text-lg font-semibold tracking-tight">
                {{ pageTitle }}
            </h1>
            <p class="text-muted-foreground truncate text-sm">{{ subtitle }}</p>
        </div>

        <!-- Tengah: search bar -->
        <div class="relative w-full max-w-xs shrink sm:max-w-sm">
            <Search class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
            <Input
                v-model="search"
                type="search"
                placeholder="Cari..."
                class="bg-muted/40 focus-within:bg-background focus-within:border-border h-9 rounded-lg border-transparent pl-9"
                aria-label="Cari"
            />
        </div>

        <!-- Kanan: blok identitas user + logout (tanpa separator) -->
        <div class="flex shrink-0 items-center gap-1.5">
            <Link
                :href="routes.dashboard.profile"
                aria-label="Profile"
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
                        {{ user?.roles?.length ? formatRole(user.roles[0]!) : user?.email }}
                    </span>
                </span>
            </Link>

            <Button
                variant="ghost"
                size="sm"
                aria-label="Keluar"
                class="text-muted-foreground hover:bg-destructive/10 hover:text-destructive shadow-none transition-colors duration-150 hover:shadow-none"
                @click="router.post(logout().url)"
            >
                <LogOut class="size-4" />
                <span>Keluar</span>
            </Button>
        </div>
    </header>
</template>
