<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useEventListener } from '@vueuse/core';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import EmptyState from '@/components/modules/dashboard/EmptyState.vue';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { SimpleSelect } from '@/components/ui/simple-select';
import { Progress } from '@/components/ui/progress';
import {
    Plus,
    MapPin,
    CalendarDays,
    Users,
    ChevronsLeft,
    ChevronsRight,
    MoreVertical,
    SquarePen,
    Download,
    FileStack,
    QrCode,
    Trash2,
} from 'lucide-vue-next';
import {
    index as eventsIndex,
    destroy as destroyEvent,
} from '@/actions/App/Http/Controllers/Dashboard/Events/EventController';
import { formatDate, categoryLabelMap, categoryColorMap } from '@/lib/dummyData';
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue';
import { routes } from '@/lib/routes';
import { setTopbar } from '@/utils/composables/useDashboardTopbar';

defineOptions({ layout: DashboardLayout });

onMounted(() => {
    setTopbar({ title: 'Acara', subtitle: 'Kelola acara & pendaftaran' });
});

interface Paginator {
    data: IEvent[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
}

const props = defineProps<{
    events: Paginator;
    filterOptions: {
        categories: { value: string; label: string }[];
        sessions: { value: string; label: string }[];
        statuses: { value: string; label: string }[];
    };
    query: {
        search?: string;
        filter?: {
            categories?: string[];
            sessions?: string[];
            statuses?: string[];
            showTrashed?: boolean;
            timeline?: string;
        };
        sort?: { by: string; order: string };
        per_page?: number;
    };
}>();

const searchQuery = ref('');
const filterCategory = ref('all');
const filterSession = ref('all');

/** Mencegah permintaan ganda saat state disamakan ulang dari URL/Inertia. */
let suppressFilterApply = false;

const categoryOptions = computed(() => props.filterOptions.categories);
const sessionOptions = computed(() => props.filterOptions.sessions);

const categoryFilterOptions = computed(() => [{ value: 'all', label: 'Semua kategori' }, ...categoryOptions.value]);

const sessionFilterOptions = computed(() => [{ value: 'all', label: 'Semua sesi' }, ...sessionOptions.value]);

function readQueryFromProps() {
    suppressFilterApply = true;
    const q = props.query;
    searchQuery.value = (q?.search as string) ?? '';
    filterCategory.value = q?.filter?.categories?.[0] ?? 'all';
    filterSession.value = q?.filter?.sessions?.[0] ?? 'all';
    void nextTick(() => {
        suppressFilterApply = false;
    });
}

readQueryFromProps();
watch(
    () => props.query,
    () => readQueryFromProps(),
    { deep: true }
);

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

function buildQueryParams(page?: number) {
    const params: Record<string, unknown> = {};
    if (searchQuery.value.trim()) params.search = searchQuery.value.trim();
    if (page && page > 1) params.page = page;

    const filter: Record<string, unknown> = {};
    if (filterCategory.value !== 'all') filter.categories = [filterCategory.value];
    if (filterSession.value !== 'all') filter.sessions = [filterSession.value];

    if (Object.keys(filter).length > 0) params.filter = filter;

    return params;
}

function applyFilters() {
    if (suppressFilterApply) return;
    router.get(eventsIndex().url, buildQueryParams() as never, {
        preserveState: true,
        preserveScroll: true,
        only: ['events', 'query'],
    });
}

watch([filterCategory, filterSession], applyFilters);

function goToPage(page: number) {
    router.get(eventsIndex().url, buildQueryParams(page) as never, {
        preserveState: true,
        preserveScroll: true,
        only: ['events', 'query'],
    });
}

const eventsList = computed(() => props.events.data);

const currentPage = computed(() => props.events.current_page);
const lastPage = computed(() => props.events.last_page);
const totalEvents = computed(() => props.events.total);

/** Navigasi dropdown tanpa animasi scale/translate (aturan clean). */
function openEdit(eventId: string | number): void {
    router.visit(routes.admin.events.edit(eventId));
}

function openExport(event: IEvent): void {
    router.visit(routes.admin.events.exports.registrations(event.id));
}

function openForms(eventId: string | number): void {
    router.visit(routes.admin.events.forms.index(eventId));
}

function openScan(eventId: string | number): void {
    router.visit(routes.admin.events.scan(eventId));
}

function confirmDelete(event: IEvent): void {
    deleteTarget.value = event;
}

/** Modal konfirmasi hapus acara. */
const deleteTarget = ref<IEvent | null>(null);

function handleDeleteConfirm(): void {
    if (!deleteTarget.value) return;
    router.delete(destroyEvent({ event: deleteTarget.value.id }).url);
    deleteTarget.value = null;
}

/** Menu kebab native (tanpa reka-ui): satu menu terbuka per waktu, key = event id. */
const openMenuId = ref<string | number | null>(null);
const menuRefs = ref<Record<string, HTMLElement | null>>({});
const triggerRefs = ref<Record<string, HTMLElement | null>>({});

function toggleMenu(id: string | number): void {
    openMenuId.value = openMenuId.value === id ? null : id;
}

function closeMenu(): void {
    openMenuId.value = null;
}

function menuAction(action: () => void): void {
    closeMenu();
    action();
}

function setMenuRef(id: string | number, el: unknown): void {
    menuRefs.value[String(id)] = el as HTMLElement | null;
}

function setTriggerRef(id: string | number, el: unknown): void {
    triggerRefs.value[String(id)] = el as HTMLElement | null;
}

/** Tutup menu jika klik terjadi di luar trigger & menu yang sedang terbuka. */
function closeIfOutside(target: EventTarget | null): void {
    const id = openMenuId.value;
    if (id === null) return;
    const btn = triggerRefs.value[String(id)];
    const menu = menuRefs.value[String(id)];
    const node = target as Node | null;
    if (!node) return;
    if (btn?.contains(node) || menu?.contains(node)) return;
    closeMenu();
}

// pointerdown = klik cepat & touch; click = fallback mouse lama.
useEventListener('pointerdown', (e) => closeIfOutside(e.target));
useEventListener('click', (e) => closeIfOutside(e.target));

useEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeMenu();
});
</script>

<template>
    <Head title="Daftar acara" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-end gap-3">
                <div class="flex min-w-0 flex-col gap-1.5">
                    <SimpleSelect
                        v-model="filterCategory"
                        :options="categoryFilterOptions"
                        id="filter-kategori"
                        class="border-border/80 bg-background/80 h-10 w-full rounded-xl text-xs sm:text-sm"
                        aria-label="Filter kategori"
                    />
                </div>
                <div class="flex min-w-0 flex-col gap-1.5">
                    <SimpleSelect
                        v-model="filterSession"
                        :options="sessionFilterOptions"
                        id="filter-sesi"
                        class="border-border/80 bg-background/80 h-10 w-full rounded-xl text-xs sm:text-sm"
                        aria-label="Filter sesi"
                    />
                </div>
            </div>
            <Button as-child class="w-full rounded-xl shadow-sm sm:w-auto">
                <Link :href="routes.admin.events.create" class="inline-flex items-center gap-2">
                    <Plus class="size-4" />
                    Buat acara
                </Link>
            </Button>
        </div>

        <div
            v-if="eventsList.length > 0"
            class="grid min-w-0 gap-4 sm:grid-cols-2 sm:gap-6 xl:grid-cols-3 2xl:grid-cols-4"
        >
            <Card
                v-for="event in eventsList"
                :key="event.id"
                class="border-border/60 bg-card hover:border-border/80 relative h-full min-w-0 gap-0 rounded-2xl border p-0 shadow-[0_2px_8px_-4px_rgb(0_0_0/0.06),0_1px_2px_rgb(0_0_0/0.04)] transition-colors duration-150 hover:shadow-[0_4px_16px_-6px_rgb(0_0_0/0.08)]"
            >
                <div class="flex flex-col gap-3 px-4 py-4 sm:px-5 sm:py-5">
                    <!-- Header row: badge kategori kiri + kebab kanan (di luar Link) -->
                    <div class="relative flex items-center justify-between gap-3">
                        <div class="flex min-w-0 flex-wrap gap-1">
                            <template v-if="eventTokenList(event.category).length > 0">
                                <Badge
                                    v-for="cat in eventTokenList(event.category).slice(0, 1)"
                                    :key="`${event.id}-cat-${cat}`"
                                    class="border px-2.5 py-1 text-xs font-semibold shadow-sm backdrop-blur-sm rounded-md"
                                    :style="{
                                        backgroundColor: `color-mix(in oklab, ${categoryColorMap[cat] ?? '#6B7280'} 12%, white)`,
                                        borderColor: `color-mix(in oklab, ${categoryColorMap[cat] ?? '#6B7280'} 30%, transparent)`,
                                        color: categoryColorMap[cat] ?? '#6B7280',
                                    }"
                                >
                                    {{ categoryLabelMap[cat] ?? cat }}
                                </Badge>
                                <Badge
                                    v-if="eventTokenList(event.category).length > 1"
                                    variant="secondary"
                                    class="dark:bg-background/90 border-0 bg-white/90 px-2.5 py-1 text-xs font-semibold shadow-sm backdrop-blur-sm rounded-md"
                                >
                                    +{{ eventTokenList(event.category).length - 1 }}
                                </Badge>
                            </template>
                        </div>

                        <Button
                            variant="ghost"
                            size="icon-sm"
                            aria-label="Menu acara"
                            class="border-border/60 relative size-8 shrink-0 cursor-pointer rounded-lg border bg-white/90 shadow-sm backdrop-blur-sm transition-colors duration-150 hover:bg-white"
                            :ref="(el) => setTriggerRef(event.id, el)"
                            @click="toggleMenu(event.id)"
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
                                v-if="openMenuId === event.id"
                                :ref="(el) => setMenuRef(event.id, el)"
                                class="border-border bg-popover text-popover-foreground absolute top-10 right-0 z-[20] min-w-48 overflow-hidden rounded-xl border p-1 shadow-sm"
                            >
                                <button
                                    type="button"
                                    class="hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground relative flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm transition-colors outline-none"
                                    @click="menuAction(() => openEdit(event.id))"
                                >
                                    <SquarePen class="mr-2 size-4 shrink-0 stroke-[1.75]" />Edit acara
                                </button>
                                <button
                                    type="button"
                                    class="hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground relative flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm transition-colors outline-none"
                                    @click="menuAction(() => openExport(event))"
                                >
                                    <Download class="mr-2 size-4 shrink-0 stroke-[1.75]" />Export data
                                </button>
                                <button
                                    type="button"
                                    class="hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground relative flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm transition-colors outline-none"
                                    @click="menuAction(() => openForms(event.id))"
                                >
                                    <FileStack class="mr-2 size-4 shrink-0 stroke-[1.75]" />Kelola formulir
                                </button>
                                <button
                                    type="button"
                                    class="hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground relative flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm transition-colors outline-none"
                                    @click="menuAction(() => openScan(event.id))"
                                >
                                    <QrCode class="mr-2 size-4 shrink-0 stroke-[1.75]" />Check in
                                </button>
                                <div class="bg-border my-1 h-px" />
                                <button
                                    type="button"
                                    class="text-destructive hover:bg-destructive/10 focus:text-destructive focus:bg-destructive/15 relative flex w-full cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm font-medium transition-colors outline-none"
                                    @click="menuAction(() => confirmDelete(event))"
                                >
                                    <Trash2 class="mr-2 size-4 shrink-0 stroke-[1.75]" />Hapus acara
                                </button>
                            </div>
                        </Transition>
                    </div>

                    <!-- Banner bersih tanpa overlay badge/kebab -->
                    <div class="bg-muted relative aspect-[16/7] w-full overflow-hidden rounded-xl">
                        <EventBannerImage
                            :src="event.banner_url"
                            :alt="event.title"
                            img-class="size-full object-cover"
                        />
                    </div>

                    <!-- Konten: hanya ini yang redirect ke detail -->
                    <Link
                        :href="routes.admin.events.show(event.id)"
                        class="focus-visible:ring-ring block rounded-b-xl focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                    >
                        <div class="flex flex-col gap-2.5">
                            <div class="flex items-center gap-3">
                                <h3
                                    class="text-foreground min-w-0 flex-1 truncate text-sm leading-snug font-semibold tracking-tight"
                                >
                                    {{ event.title }}
                                </h3>
                                <Badge
                                    variant="outline"
                                    :class="[
                                        'shrink-0 rounded-md px-2.5 py-1 text-xs font-medium whitespace-nowrap',
                                        registrationUi(event).badgeClass,
                                    ]"
                                >
                                    {{ registrationUi(event).label }}
                                </Badge>
                            </div>

                            <div class="text-muted-foreground flex items-center gap-1.5 text-xs leading-snug sm:text-[13px]">
                                <CalendarDays
                                    class="text-primary/70 mt-0.5 size-3.5 shrink-0 stroke-[1.75]"
                                    aria-hidden="true"
                                />
                                <span class="leading-snug">
                                    {{ formatDate(event.start_date) }} — {{ formatDate(event.end_date) }}
                                </span>
                            </div>

                            <div class="text-muted-foreground flex items-center gap-1.5 text-xs leading-snug sm:text-[13px]">
                                <MapPin
                                    class="text-primary/70 mt-0.5 size-3.5 shrink-0 stroke-[1.75]"
                                    aria-hidden="true"
                                />
                                <span class="line-clamp-1 leading-snug">{{ event.location }}</span>
                            </div>

                            <div class="border-border/60 flex items-center justify-between gap-3 border-t pt-3 text-xs">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="text-muted-foreground flex items-center gap-1.5">
                                        <Users
                                            class="text-muted-foreground size-3.5 shrink-0 stroke-[1.75]"
                                            aria-hidden="true"
                                        />
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
                                <span class="text-foreground shrink-0 font-medium tabular-nums">
                                    {{ formatPriceIdr(event.price) }}
                                </span>
                            </div>
                        </div>
                    </Link>
                </div>
            </Card>
        </div>

        <ConfirmationModal
            :open="deleteTarget !== null"
            :title="`Hapus acara ${deleteTarget?.title ?? ''}?`"
            description="Tindakan ini tidak dapat dibatalkan. Data acara dan pendaftaran terkait akan terhapus."
            confirm-text="Hapus"
            cancel-text="Batal"
            variant="destructive"
            @confirm="handleDeleteConfirm"
            @cancel="deleteTarget = null"
            @update:open="(v) => { if (!v) deleteTarget = null }"
        />

        <EmptyState
            v-if="eventsList.length === 0"
            title="Tidak ada acara"
            description="Sesuaikan pencarian, kategori, atau sesi — atau buat acara baru."
            animation-name="errorState"
        />

        <Card
            v-if="lastPage > 1"
            class="border-border/70 flex flex-col gap-3 rounded-2xl border px-4 py-4 shadow-sm sm:flex-row sm:items-center sm:justify-between sm:px-5"
        >
            <p class="text-muted-foreground text-center text-sm sm:text-left">
                Halaman
                <span class="text-foreground font-medium tabular-nums">{{ currentPage }}</span>
                /
                <span class="tabular-nums">{{ lastPage }}</span>
                — total
                <span class="text-foreground font-medium tabular-nums">{{ totalEvents }}</span>
                acara
            </p>
            <div class="flex flex-wrap items-center justify-center gap-2">
                <Button
                    variant="outline"
                    size="icon"
                    class="size-9 rounded-xl"
                    :disabled="currentPage <= 1"
                    @click="goToPage(1)"
                >
                    <ChevronsLeft class="size-4" />
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 rounded-xl px-4"
                    :disabled="currentPage <= 1"
                    @click="goToPage(currentPage - 1)"
                >
                    Sebelumnya
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    class="h-9 rounded-xl px-4"
                    :disabled="currentPage >= lastPage"
                    @click="goToPage(currentPage + 1)"
                >
                    Berikutnya
                </Button>
                <Button
                    variant="outline"
                    size="icon"
                    class="size-9 rounded-xl"
                    :disabled="currentPage >= lastPage"
                    @click="goToPage(lastPage)"
                >
                    <ChevronsRight class="size-4" />
                </Button>
            </div>
        </Card>
    </div>
</template>
