<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Separator } from '@/components/ui/separator';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { LogOut, Search, UserRound } from 'lucide-vue-next';
import { routes } from '@/lib/routes';
import logout from '@/actions/App/Http/Controllers/Auth/LogoutController';

const page = usePage();

const siteName = computed(() => page.props.appName || 'DForm');

function escapeRegExp(s: string): string {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/** Ekstrak judul halaman dari <title> ("Judul · DForm" → "Judul"). */
function titleFromDocument(): string {
    const suffix = `\\s*·\\s*${escapeRegExp(siteName.value)}\\s*$`;
    return document.title.replace(new RegExp(suffix), '').trim() || 'Dashboard';
}

const pageTitle = ref(titleFromDocument());
const search = ref('');

let titlePollId: number | null = null;

function syncTitle(): void {
    pageTitle.value = titleFromDocument();
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
</script>

<template>
    <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center justify-between gap-4 border-b border-border/60 bg-background/95 px-4 backdrop-blur-md lg:px-6">
        <!-- Kiri: nama page -->
        <h1 class="font-display min-w-0 truncate text-lg font-bold tracking-tight text-foreground sm:text-xl">
            {{ pageTitle }}
        </h1>

        <!-- Tengah: search bar -->
        <div class="relative w-full max-w-md shrink">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                v-model="search"
                type="search"
                placeholder="Cari..."
                class="pl-9"
                aria-label="Cari"
            />
        </div>

        <!-- Kanan: profile + logout (satu group) -->
        <div class="flex shrink-0 items-center gap-1.5">
            <Tooltip>
                <TooltipTrigger as-child>
                    <Button variant="ghost" size="icon-sm" as-child aria-label="Profile">
                        <Link :href="routes.dashboard.profile">
                            <UserRound class="size-4" />
                        </Link>
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom">Profile</TooltipContent>
            </Tooltip>

            <Separator orientation="vertical" class="mx-0.5 h-4!" />

            <Tooltip>
                <TooltipTrigger as-child>
                    <Button
                        variant="ghost"
                        size="icon-sm"
                        aria-label="Keluar"
                        class="text-muted-foreground hover:text-destructive"
                        @click="router.post(logout().url)"
                    >
                        <LogOut class="size-4" />
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom">Keluar</TooltipContent>
            </Tooltip>
        </div>
    </header>
</template>
