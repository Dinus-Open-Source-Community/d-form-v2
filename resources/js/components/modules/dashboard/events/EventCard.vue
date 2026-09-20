<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { CalendarDays, MapPin, Users, MoreVertical, SquarePen, Download, FileStack, Trash2 } from 'lucide-vue-next';
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue';
import { formatDate, categoryLabelMap, categoryColorMap } from '@/lib/dummyData';
import { routes } from '@/lib/routes';

const props = withDefaults(
    defineProps<{
        event: IEvent;
        href: string;
        canManage?: boolean;
        showPrice?: boolean;
        alertBadge?: string | null;
    }>(),
    { canManage: false, showPrice: true, alertBadge: null },
);

const emit = defineEmits<{ delete: [event: IEvent] }>();

function eventTokenList(v: unknown): string[] {
    if (Array.isArray(v)) return v.map((s) => String(s).trim()).filter(Boolean);
    if (typeof v === 'string')
        return v
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean);
    return [];
}

function registrationUi(ev: IEvent): { label: string; badgeClass: string } {
    switch (ev.registration_status) {
        case 'open':
            return {
                label: 'Buka',
                badgeClass: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
            };
        case 'full':
            return {
                label: 'Penuh',
                badgeClass: 'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-400',
            };
        case 'closed':
            return { label: 'Tutup', badgeClass: 'border-border bg-muted/60 text-muted-foreground' };
        default:
            return {
                label: 'Segera',
                badgeClass: 'border-amber-500/25 bg-amber-500/10 text-amber-800 dark:text-amber-400',
            };
    }
}

function formatPriceIdr(price: number): string {
    if (!price) return 'Gratis';
    try {
        return new Intl.NumberFormat('id-ID', {
            style: 'currency',
            currency: 'IDR',
            maximumFractionDigits: 0,
        }).format(price);
    } catch {
        return String(price);
    }
}

const categoryTokens = computed(() => eventTokenList(props.event.category));

// ── Kebab native (role-aware, hanya saat canManage) ───────────────────
const openMenuId = ref<string | null>(null);
const menuRef = ref<HTMLElement | null>(null);
const triggerRef = ref<HTMLElement | null>(null);

function toggleMenu(): void {
    openMenuId.value = openMenuId.value === props.event.id ? null : props.event.id;
}

function closeMenu(): void {
    openMenuId.value = null;
}

function menuAction(action: () => void): void {
    closeMenu();
    action();
}

function openEdit(): void {
    router.visit(routes.admin.events.edit(props.event.id));
}

function openExport(): void {
    router.visit(routes.admin.events.exports.registrations(props.event.id));
}

function openForms(): void {
    router.visit(routes.admin.events.show(props.event.id));
}

function requestDelete(): void {
    emit('delete', props.event);
}

function closeIfOutside(target: EventTarget | null): void {
    if (openMenuId.value === null) return;
    const node = target as Node | null;
    if (!node) return;
    if (triggerRef.value?.contains(node) || menuRef.value?.contains(node)) return;
    closeMenu();
}

useEventListener('pointerdown', (e) => closeIfOutside(e.target));
useEventListener('click', (e) => closeIfOutside(e.target));
useEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeMenu();
});
</script>

<template>
    <div
        class="border-border/60 bg-card hover:border-border/80 relative flex h-full min-w-0 flex-col gap-3 rounded-2xl border p-4 shadow-[0_2px_8px_-4px_rgb(0_0_0/0.06),0_1px_2px_rgb(0_0_0/0.04)] transition-colors duration-150 hover:shadow-[0_4px_16px_-6px_rgb(0_0_0/0.08)] sm:p-5"
    >
        <!-- Header row: badge kategori + alert + kebab (di luar Link) -->
        <div class="relative flex items-center justify-between gap-3">
            <div class="flex min-w-0 flex-wrap gap-1">
                <Badge
                    v-if="alertBadge"
                    variant="secondary"
                    class="border border-amber-500/40 bg-amber-500/15 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:text-amber-100"
                >
                    {{ alertBadge }}
                </Badge>
                <template v-if="categoryTokens.length > 0">
                    <Badge
                        v-for="cat in categoryTokens.slice(0, 1)"
                        :key="`${event.id}-cat-${cat}`"
                        class="border px-2.5 py-1 text-xs font-semibold shadow-sm backdrop-blur-sm"
                        :style="{
                            backgroundColor: `color-mix(in oklab, ${categoryColorMap[cat] ?? '#6B7280'} 12%, white)`,
                            borderColor: `color-mix(in oklab, ${categoryColorMap[cat] ?? '#6B7280'} 30%, transparent)`,
                            color: categoryColorMap[cat] ?? '#6B7280',
                        }"
                    >
                        {{ categoryLabelMap[cat] ?? cat }}
                    </Badge>
                    <Badge
                        v-if="categoryTokens.length > 1"
                        variant="secondary"
                        class="dark:bg-background/90 border-0 bg-white/90 px-2.5 py-1 text-xs font-semibold shadow-sm backdrop-blur-sm"
                    >
                        +{{ categoryTokens.length - 1 }}
                    </Badge>
                </template>
            </div>

            <Button radius="icon"
                v-if="canManage"
                variant="ghost"
                size="icon-sm"
                aria-label="Menu acara"
                class="border-border/60 relative size-8 shrink-0 cursor-pointer border bg-white/90 shadow-sm backdrop-blur-sm transition-colors duration-150 hover:bg-white"
                :ref="(el) => { triggerRef = el as HTMLElement | null }"
                @click.stop="toggleMenu"
            >
                <MoreVertical class="size-4 shrink-0 stroke-[1.75]" />
            </Button>

            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0 scale-[0.96] translate-y-1"
                enter-to-class="opacity-100 scale-100 translate-y-0"
                leave-active-class="transition duration-100 ease-in"
                leave-from-class="opacity-100 scale-100 translate-y-0"
                leave-to-class="opacity-0 scale-[0.96] translate-y-1"
            >
                <div
                    v-if="canManage && openMenuId === event.id"
                    :ref="(el) => { menuRef = el as HTMLElement | null }"
                    class="border-border bg-popover text-popover-foreground absolute top-10 right-0 z-[20] min-w-48 overflow-hidden rounded-xl border p-1 shadow-sm"
                >
                    <button
                        type="button"
                        class="hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground relative flex w-full cursor-pointer items-center gap-2 px-2 py-1.5 text-sm transition-colors outline-none"
                        @click="menuAction(openEdit)"
                    >
                        <SquarePen class="mr-2 size-4 shrink-0 stroke-[1.75]" />Edit acara
                    </button>
                    <button
                        type="button"
                        class="hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground relative flex w-full cursor-pointer items-center gap-2 px-2 py-1.5 text-sm transition-colors outline-none"
                        @click="menuAction(openExport)"
                    >
                        <Download class="mr-2 size-4 shrink-0 stroke-[1.75]" />Export data
                    </button>
                    <button
                        type="button"
                        class="hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground relative flex w-full cursor-pointer items-center gap-2 px-2 py-1.5 text-sm transition-colors outline-none"
                        @click="menuAction(openForms)"
                    >
                        <FileStack class="mr-2 size-4 shrink-0 stroke-[1.75]" />Kelola formulir
                    </button>
                    <div class="bg-border my-1 h-px" />
                    <button
                        type="button"
                        class="text-destructive hover:bg-destructive/10 focus:text-destructive focus:bg-destructive/15 relative flex w-full cursor-pointer items-center gap-2 px-2 py-1.5 text-sm font-medium transition-colors outline-none"
                        @click="menuAction(requestDelete)"
                    >
                        <Trash2 class="mr-2 size-4 shrink-0 stroke-[1.75]" />Hapus acara
                    </button>
                </div>
            </Transition>
        </div>

        <!-- Banner bersih (di luar Link — tidak redirect) -->
        <div class="bg-muted relative aspect-[16/7] w-full overflow-hidden rounded-xl">
            <EventBannerImage
                :src="event.banner_url"
                :alt="event.title"
                img-class="size-full object-cover"
            />
        </div>

        <!-- Konten: hanya ini yang redirect ke detail -->
        <Link
            :href="href"
            class="focus-visible:ring-ring block min-w-0 flex-1 rounded-b-xl focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
        >
            <div class="flex h-full flex-col gap-2.5">
                <div class="flex items-center gap-3">
                    <h3 class="text-foreground min-w-0 flex-1 truncate text-sm leading-snug font-semibold tracking-tight">
                        {{ event.title }}
                    </h3>
                    <Badge
                        variant="outline"
                        :class="[
                            'shrink-0 px-2.5 py-1 text-xs font-medium whitespace-nowrap',
 registrationUi(event).badgeClass,
 ]"
                    >
                        {{ registrationUi(event).label }}
                    </Badge>
                </div>

                <div class="text-muted-foreground flex items-center gap-1.5 text-xs leading-snug sm:text-[13px]">
                    <CalendarDays class="text-primary/70 mt-0.5 size-3.5 shrink-0 stroke-[1.75]" aria-hidden="true" />
                    <span class="leading-snug">
                        {{ formatDate(event.start_date) }} — {{ formatDate(event.end_date) }}
                    </span>
                </div>

                <div class="text-muted-foreground flex items-center gap-1.5 text-xs leading-snug sm:text-[13px]">
                    <MapPin class="text-primary/70 mt-0.5 size-3.5 shrink-0 stroke-[1.75]" aria-hidden="true" />
                    <span class="line-clamp-1 leading-snug">{{ event.location }}</span>
                </div>

                <div class="border-border/60 mt-auto flex items-center justify-between gap-3 border-t pt-3 text-xs">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="text-muted-foreground flex items-center gap-1.5">
                            <Users class="text-muted-foreground size-3.5 shrink-0 stroke-[1.75]" aria-hidden="true" />
                            <span class="font-medium tabular-nums">
                                {{ event.registered_count }}/{{ event.quota }}
                            </span>
                        </span>
                        <Progress
                            :model-value="Math.min(event.registered_count, Math.max(event.quota, 1))"
                            :max="Math.max(event.quota, 1)"
                            class="bg-muted/70 h-1.5 min-w-0 flex-1"
                        />
                    </div>
                    <span v-if="showPrice" class="text-foreground shrink-0 font-medium tabular-nums">
                        {{ formatPriceIdr(event.price) }}
                    </span>
                </div>
            </div>
        </Link>
    </div>
</template>
