<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import EmptyState from '@/components/modules/dashboard/EmptyState.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Progress } from '@/components/ui/progress';
import { Search, MapPin, CalendarDays, Users } from 'lucide-vue-next';
import { formatDate, categoryLabelMap, categoryColorMap } from '@/lib/dummyData';
import { toCategoryList } from '@/lib/eventCategories';
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue';

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

const isBrowse = computed(() => props.listMode === 'browse');

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
    return list;
});

const emptyTitle = computed(() => (isBrowse.value ? 'Tidak ada acara ditemukan' : 'Belum ada acara yang diikuti'));
const emptyDescription = computed(() =>
    isBrowse.value
        ? 'Sesuaikan pencarian atau filter kategori.'
        : 'Telusuri acara terbuka dan daftar untuk melihatnya di sini.'
);
</script>

<template>
    <Head :title="headTitle" />

    <div class="flex flex-col gap-6">
        <PageHeader :title="pageTitle" :subtitle="pageSubtitle" />

        <div class="border-border/60 bg-muted/30 flex flex-wrap items-center gap-3 rounded-xl border p-3 shadow-xs">
            <div class="relative w-full max-w-xs">
                <Search class="text-muted-foreground absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                <Input v-model="searchQuery" placeholder="Cari acara…" class="pl-9" />
            </div>
        </div>

        <div v-if="filteredEvents.length > 0" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="event in filteredEvents"
                :key="event.id"
                :href="`/events/joined/events/${event.slug}`"
                class="group focus-visible:ring-ring block rounded-xl focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
            >
                <Card
                    class="hover:border-primary/25 gap-0 overflow-hidden rounded-xl border p-0 shadow-xs transition-colors duration-150 hover:shadow-sm"
                >
                    <div class="bg-muted relative aspect-video w-full overflow-hidden sm:aspect-[4/3]">
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
                href="/events/joined/events/browse"
                class="text-primary text-sm font-medium underline-offset-4 hover:underline"
            >
                Jelajah semua acara
            </Link>
        </EmptyState>
    </div>
</template>
