<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import EmptyState from '@/components/modules/dashboard/EmptyState.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SimpleSelect } from '@/components/ui/simple-select';
import { Progress } from '@/components/ui/progress';
import { Search, MapPin, CalendarDays, Users, FilterX } from 'lucide-vue-next';
import { formatDate, categoryLabelMap, categoryColorMap, sessionLabelMap } from '@/lib/dummyData';
import { toCategoryList } from '@/lib/eventCategories';
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue';
import { eventCardBannerContainerClass } from '@/lib/eventBannerAspect';
import { routes } from '@/lib/routes';
import { setTopbar } from '@/utils/composables/useDashboardTopbar';

defineOptions({ layout: DashboardLayout });

const props = withDefaults(
    defineProps<{
        events: IEvent[];
        listMode?: 'mine' | 'browse';
    }>(),
    { listMode: 'browse' }
);

const searchQuery = ref('');
const filterCategory = ref('all');
const filterSession = ref('all');

const isBrowse = computed(() => props.listMode === 'browse');

const categoryOptions = computed(() =>
    Object.entries(categoryLabelMap).map(([value, label]) => ({ value, label }))
);

const categoryFilterOptions = computed(() => [
    { value: 'all', label: 'Semua kategori' },
    ...categoryOptions.value,
]);

const sessionOptions = computed(() => {
    const tokens = new Set<string>();
    for (const event of props.events) {
        for (const session of eventTokenList(event.session)) tokens.add(session);
    }
    return [...tokens]
        .sort((a, b) => (sessionLabelMap[a] ?? a).localeCompare(sessionLabelMap[b] ?? b))
        .map((value) => ({ value, label: sessionLabelMap[value] ?? value }));
});

const sessionFilterOptions = computed(() => [
    { value: 'all', label: 'Semua sesi' },
    ...sessionOptions.value,
]);

function eventTokenList(v: unknown): string[] {
    if (Array.isArray(v)) return v.map((s) => String(s).trim()).filter(Boolean);
    if (typeof v === 'string')
        return v
            .split(',')
            .map((s) => s.trim())
            .filter(Boolean);
    return [];
}

const hasActiveFilters = computed(
    () => searchQuery.value.trim() !== '' || filterCategory.value !== 'all' || filterSession.value !== 'all'
);

function clearFilters() {
    searchQuery.value = '';
    filterCategory.value = 'all';
    filterSession.value = 'all';
}

const pageTitle = computed(() => (isBrowse.value ? 'Jelajah acara' : 'Acara diikuti'));
const pageSubtitle = computed(() =>
    isBrowse.value
        ? 'Lihat semua acara terpublikasi dan daftar sebagai peserta.'
        : 'Acara yang Anda daftar atau ikuti (tim / undangan yang masih aktif).'
);

const headTitle = computed(() => (isBrowse.value ? 'Jelajah acara' : 'Acara diikuti'));

const filteredEvents = computed(() => {
    let list = props.events;

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        list = list.filter((e) => e.title.toLowerCase().includes(q));
    }
    if (filterCategory.value !== 'all')
        list = list.filter((e) => toCategoryList(e.category).includes(filterCategory.value));
    if (filterSession.value !== 'all')
        list = list.filter((e) => eventTokenList(e.session).includes(filterSession.value));
    return list;
});

const emptyTitle = computed(() => (isBrowse.value ? 'Tidak ada acara ditemukan' : 'Belum ada acara yang diikuti'));
const emptyDescription = computed(() =>
    isBrowse.value
        ? 'Sesuaikan pencarian atau filter kategori.'
        : 'Telusuri acara terbuka dan daftar untuk melihatnya di sini.'
);

onMounted(() => {
    setTopbar({ title: pageTitle.value, subtitle: pageSubtitle.value });
});
</script>

<template>
    <Head :title="headTitle" />

    <div class="flex flex-col gap-6">
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
                                placeholder="Cari acara…"
                                class="border-border/80 bg-background/80 h-10 w-full rounded-xl pl-9 shadow-inner"
                                autocomplete="off"
                                aria-label="Cari acara"
                            />
                        </div>
                        <div class="lg:col-span-3">
                            <SimpleSelect
                                v-model="filterCategory"
                                :options="categoryFilterOptions"
                                class="border-border/80 bg-background/80 h-10 w-full rounded-xl text-xs sm:text-sm"
                                aria-label="Filter kategori"
                            />
                        </div>
                        <div class="lg:col-span-4">
                            <SimpleSelect
                                v-model="filterSession"
                                :options="sessionFilterOptions"
                                class="border-border/80 bg-background/80 h-10 w-full rounded-xl text-xs sm:text-sm"
                                aria-label="Filter sesi"
                            />
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

        <div v-if="filteredEvents.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="event in filteredEvents"
                :key="event.id"
                :href="routes.member.event.show(event.slug)"
                class="group focus-visible:ring-ring block rounded-xl focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <Card
                    class="hover:border-primary/25 gap-0 overflow-hidden rounded-xl border p-0 shadow-xs transition-colors duration-150 hover:shadow-sm"
                >
                    <div :class="eventCardBannerContainerClass()">
                        <div class="absolute inset-0 z-0">
                            <EventBannerImage :src="event.banner_url" :alt="event.title" />
                        </div>
                        <div
                            class="pointer-events-none absolute inset-0 z-1 bg-linear-to-t from-black/35 via-transparent to-transparent"
                        />
                        <div class="absolute top-2.5 left-2.5 z-2 flex flex-wrap gap-1.5">
                            <Badge
                                v-if="!isBrowse && event.pending_team_invitation_url"
                                variant="secondary"
                                class="border border-amber-500/40 bg-amber-500/15 text-[10px] font-semibold text-amber-950 shadow-xs dark:text-amber-100"
                            >
                                Diundang
                            </Badge>
                            <Badge
                                v-for="cat in toCategoryList(event.category)"
                                :key="cat"
                                class="text-[10px] text-white shadow-xs"
                                :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                            >
                                {{ categoryLabelMap[cat] ?? cat }}
                            </Badge>
                        </div>
                    </div>
                    <CardContent class="p-4">
                        <h3 class="text-foreground group-hover:text-primary truncate text-sm font-semibold">
                            {{ event.title }}
                        </h3>
                        <div class="text-muted-foreground mt-2.5 flex flex-col gap-1.5 text-xs">
                            <div class="flex items-center gap-1.5">
                                <CalendarDays class="size-3 shrink-0" /><span>{{ formatDate(event.start_date) }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <MapPin class="size-3 shrink-0" /><span class="truncate">{{ event.location }}</span>
                            </div>
                        </div>
                        <div class="mt-3">
                            <div class="mb-1.5 flex items-center justify-between text-xs">
                                <span class="text-muted-foreground flex items-center gap-1"
                                    ><Users class="size-3" />Kuota</span
                                >
                                <span class="font-medium tabular-nums"
                                    >{{ event.registered_count }}/{{ event.quota }}</span
                                >
                            </div>
                            <Progress :model-value="event.registered_count" :max="event.quota" class="h-1.5" />
                        </div>
                    </CardContent>
                </Card>
            </Link>
        </div>

        <EmptyState
            v-else
            :title="emptyTitle"
            :description="emptyDescription"
            animation-url="https://lottie.host/4e039bf3-670e-4a0f-8a6c-1bee793bfc23/JkaGBMIxOz.json"
        >
            <Link
                v-if="!isBrowse"
                :href="routes.member.browse"
                class="text-primary text-sm font-medium underline-offset-4 hover:underline"
            >
                Jelajah semua acara
            </Link>
        </EmptyState>
    </div>
</template>
