<script setup lang="ts">
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import EmptyState from '@/components/modules/dashboard/EmptyState.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Progress } from '@/components/ui/progress';
import {
    Plus,
    Search,
    MapPin,
    CalendarDays,
    Users,
    ChevronsLeft,
    ChevronsRight,
    FilterX,
    ArrowUpRight,
} from 'lucide-vue-next';
import { index as eventsIndex } from '@/actions/App/Http/Controllers/Dashboard/Events/EventController';
import { formatDate, statusColorMap, categoryLabelMap, categoryColorMap, sessionLabelMap } from '@/lib/dummyData';
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue';
import { routes } from '@/lib/routes';

defineOptions({ layout: DashboardLayout });

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
                label: 'Pendaftaran buka',
                badgeClass: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400',
            };
        case 'full':
            return {
                label: 'Kuota penuh',
                badgeClass: 'border-rose-500/25 bg-rose-500/10 text-rose-700 dark:text-rose-400',
            };
        case 'closed':
            return { label: 'Pendaftaran tutup', badgeClass: 'border-border bg-muted/60 text-muted-foreground' };
        default:
            return {
                label: 'Belum dibuka',
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

function clearFilters() {
    if (searchTimeout) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }
    searchQuery.value = '';
    filterCategory.value = 'all';
    filterSession.value = 'all';
    router.get(eventsIndex().url, {}, { preserveState: true, preserveScroll: true, only: ['events', 'query'] });
}

let searchTimeout: ReturnType<typeof setTimeout> | null = null;
function onSearchInput() {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        searchTimeout = null;
        applyFilters();
    }, 400);
}

onBeforeUnmount(() => {
    if (searchTimeout) clearTimeout(searchTimeout);
});

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

const hasActiveFilters = computed(
    () => searchQuery.value.trim() !== '' || filterCategory.value !== 'all' || filterSession.value !== 'all'
);

function statusLabel(status: string) {
    return status === 'published' ? 'Terbit' : 'Draf';
}
</script>

<template>
    <Head title="Daftar acara" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <PageHeader
            title="Acara"
            subtitle="Kelola acara dan pantau pendaftaran — cari judul, lalu saring per kategori atau sesi."
        >
            <template #actions>
                <Button as-child class="w-full rounded-xl shadow-sm sm:w-auto">
                    <Link :href="routes.admin.events.create" class="inline-flex items-center gap-2">
                        <Plus class="size-4" />
                        Buat acara
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <section
            class="app-surface border-border/70 overflow-hidden rounded-2xl border shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.06]"
        >
            <div class="flex flex-col gap-4 px-4 py-4 sm:px-6 sm:py-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between lg:gap-4">
                    <div class="grid min-w-0 flex-1 gap-3 sm:grid-cols-2 lg:grid-cols-12 lg:items-end">
                        <div class="relative sm:col-span-2 lg:col-span-5">
                            <Search
                                class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                aria-hidden="true"
                            />
                            <Input
                                v-model="searchQuery"
                                type="search"
                                placeholder="Cari judul acara…"
                                class="border-border/80 bg-background/80 h-10 w-full rounded-xl pl-9 shadow-inner"
                                autocomplete="off"
                                aria-label="Cari judul acara"
                                @input="onSearchInput"
                            />
                        </div>
                        <div class="lg:col-span-3">
                            <Select v-model="filterCategory">
                                <SelectTrigger
                                    class="border-border/80 bg-background/80 h-10 w-full rounded-xl text-xs sm:text-sm"
                                >
                                    <SelectValue placeholder="Kategori" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua kategori</SelectItem>
                                    <SelectItem v-for="opt in categoryOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="lg:col-span-4">
                            <Select v-model="filterSession">
                                <SelectTrigger
                                    class="border-border/80 bg-background/80 h-10 w-full rounded-xl text-xs sm:text-sm"
                                >
                                    <SelectValue placeholder="Sesi" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">Semua sesi</SelectItem>
                                    <SelectItem v-for="opt in sessionOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <Button
                        v-if="hasActiveFilters"
                        variant="outline"
                        size="sm"
                        class="h-10 w-full shrink-0 gap-2 rounded-xl border-dashed sm:w-auto lg:self-end"
                        type="button"
                        @click="clearFilters"
                    >
                        <FilterX class="size-3.5" />
                        Reset
                    </Button>
                </div>
            </div>
        </section>

        <p v-if="eventsList.length > 0" class="text-muted-foreground text-sm leading-relaxed">
            Menampilkan
            <span class="text-foreground font-medium tabular-nums">{{ props.events.from ?? 0 }}</span>
            –
            <span class="text-foreground font-medium tabular-nums">{{ props.events.to ?? 0 }}</span>
            dari
            <span class="text-foreground font-medium tabular-nums">{{ totalEvents }}</span>
            acara
        </p>

        <div
            v-if="eventsList.length > 0"
            class="grid min-w-0 gap-4 sm:grid-cols-2 sm:gap-6 xl:grid-cols-3 2xl:grid-cols-4"
        >
            <Link
                v-for="event in eventsList"
                :key="event.id"
                :href="routes.admin.events.show(event.id)"
                class="group focus-visible:ring-ring block min-w-0 rounded-2xl focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <Card
                    class="border-border/70 bg-card hover:border-primary/30 relative h-full gap-0 overflow-hidden rounded-2xl border p-0 shadow-sm ring-1 ring-black/[0.03] transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:ring-white/[0.06]"
                >
                    <div class="bg-muted relative aspect-video w-full overflow-hidden sm:aspect-[4/3]">
                        <div class="absolute inset-0 z-0">
                            <EventBannerImage
                                :src="event.banner_url"
                                :alt="event.title"
                                img-class="size-full object-cover transition-transform duration-500 group-hover:scale-[1.03]"
                            />
                        </div>
                        <div
                            class="pointer-events-none absolute inset-0 z-[1] bg-gradient-to-t from-black/60 via-black/15 to-transparent"
                        />
                        <div
                            class="absolute top-2.5 right-2.5 left-2.5 z-[2] flex flex-wrap gap-1 pr-16 sm:top-3 sm:right-auto sm:left-3 sm:max-w-[88%] sm:pr-14"
                        >
                            <Badge
                                v-for="(cat, idx) in eventTokenList(event.category)"
                                :key="`${event.id}-cat-${idx}`"
                                class="border-0 text-[10px] font-semibold text-white shadow-sm"
                                :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                            >
                                {{ categoryLabelMap[cat] ?? cat }}
                            </Badge>
                            <Badge
                                v-for="(sess, idx) in eventTokenList(event.session)"
                                :key="`${event.id}-sess-${idx}`"
                                variant="secondary"
                                class="text-foreground/90 dark:bg-background/90 border-0 bg-white/90 text-[10px] font-semibold shadow-sm backdrop-blur-sm"
                            >
                                {{ sessionLabelMap[sess] ?? sess }}
                            </Badge>
                            <Badge v-if="event.deleted_at" variant="destructive" class="text-[10px] font-semibold">
                                Terarsip
                            </Badge>
                        </div>
                        <div class="absolute top-2.5 right-2.5 z-[2] sm:top-3 sm:right-3">
                            <Badge
                                variant="secondary"
                                class="dark:bg-background/95 border-0 bg-white/95 text-[10px] font-semibold shadow-sm backdrop-blur-sm"
                                :style="{ color: statusColorMap[event.status] }"
                            >
                                {{ statusLabel(event.status) }}
                            </Badge>
                        </div>
                        <div
                            class="absolute right-0 bottom-0 left-0 z-[2] flex items-end justify-between gap-2 px-3 pt-14 pb-2.5 text-white sm:px-4 sm:pb-3"
                        >
                            <h3
                                class="line-clamp-2 min-w-0 flex-1 text-sm leading-snug font-semibold tracking-tight text-white drop-shadow sm:text-[0.95rem]"
                            >
                                {{ event.title }}
                            </h3>
                            <ArrowUpRight
                                class="size-4 shrink-0 opacity-80 transition-opacity group-hover:opacity-100"
                                aria-hidden="true"
                            />
                        </div>
                    </div>
                    <CardContent class="border-border/50 space-y-4 border-t p-3.5 sm:p-5">
                        <div
                            class="text-muted-foreground flex flex-col gap-3 text-xs sm:flex-row sm:items-start sm:justify-between sm:gap-4 sm:text-[13px]"
                        >
                            <div class="min-w-0 flex-1 space-y-2">
                                <div class="flex items-start gap-2">
                                    <CalendarDays class="text-primary/80 mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                    <span class="leading-snug">
                                        {{ formatDate(event.start_date) }} — {{ formatDate(event.end_date) }}
                                    </span>
                                </div>
                                <div class="flex items-start gap-2">
                                    <MapPin class="text-primary/80 mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                    <span class="line-clamp-2 leading-snug">{{ event.location }}</span>
                                </div>
                            </div>
                            <div class="min-w-0 shrink-0 sm:pt-0.5">
                                <Badge
                                    variant="outline"
                                    :class="[
                                        'max-w-full text-[10px] font-medium whitespace-normal sm:whitespace-nowrap',
                                        registrationUi(event).badgeClass,
                                    ]"
                                >
                                    {{ registrationUi(event).label }}
                                </Badge>
                            </div>
                        </div>

                        <div
                            class="border-border/60 bg-muted/20 flex items-center justify-between gap-3 rounded-xl border px-3 py-2 text-xs sm:text-[13px]"
                        >
                            <span class="text-muted-foreground">Biaya</span>
                            <span class="text-foreground text-right font-semibold break-words tabular-nums">
                                {{ formatPriceIdr(event.price) }}
                            </span>
                        </div>

                        <div class="border-border/60 bg-muted/25 rounded-xl border p-3">
                            <div class="mb-2 flex items-center justify-between text-xs">
                                <span class="text-foreground flex items-center gap-1.5 font-medium">
                                    <Users class="text-muted-foreground size-3.5" aria-hidden="true" />
                                    Pendaftar
                                </span>
                                <span class="text-foreground font-semibold tabular-nums">
                                    {{ event.registered_count }}/{{ event.quota }}
                                </span>
                            </div>
                            <Progress
                                :model-value="Math.min(event.registered_count, Math.max(event.quota, 1))"
                                :max="Math.max(event.quota, 1)"
                                class="bg-muted/80 h-2"
                            />
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </div>

        <EmptyState
            v-else
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
