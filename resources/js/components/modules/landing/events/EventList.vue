<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { formatDate, categoryLabelMap } from '@/lib/dummyData';
import { toCategoryList } from '@/lib/eventCategories';

const props = withDefaults(
    defineProps<{
        events?: IEvent[];
    }>(),
    { events: () => [] },
);

const listVisible = ref(false);
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => {
            if (entry.isIntersecting) listVisible.value = true;
        },
        { threshold: 0.05 },
    );
    const el = document.getElementById('event-list');
    if (el) observer.observe(el);
});

type Row = {
    id: string;
    title: string;
    date: string;
    location: string;
    attendees: number;
    category: string;
    status: string;
    image: string;
};

const rows = computed<Row[]>(() =>
    props.events.map((e) => {
        const cats = toCategoryList(e.category);
        const primary = cats[0] ?? '';
        const reg = e.registration_status;
        let status = 'Coming Soon';
        if (reg === 'open') status = 'Open';
        else if (reg === 'full') status = 'Full';
        else if (reg === 'closed') status = 'Closed';

        return {
            id: e.id,
            title: e.title,
            date: formatDate(e.start_date),
            location: e.location,
            attendees: e.registered_count,
            category: primary ? categoryLabelMap[primary] ?? primary : 'Event',
            status,
            image: e.banner_url ?? '',
        };
    }),
);

const searchQuery = ref('');
const activeCategory = ref('All');

const categories = computed(() => {
    const cats = [...new Set(rows.value.map((e) => e.category))];
    return ['All', ...cats];
});

const filteredEvents = computed(() => {
    let result = rows.value;
    if (activeCategory.value !== 'All') {
        result = result.filter((e) => e.category === activeCategory.value);
    }
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(
            (e) =>
                e.title.toLowerCase().includes(q) ||
                e.location.toLowerCase().includes(q) ||
                e.category.toLowerCase().includes(q),
        );
    }
    return result;
});
</script>

<template>
    <section id="event-list" class="relative overflow-hidden bg-background py-20 lg:py-28">
        <div class="absolute right-8 top-10 h-20 w-20 rounded-full bg-[var(--brutal-mint)]/8 blur-md"></div>
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <!-- Header + Search -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="font-display text-3xl font-bold tracking-[-0.035em] text-[var(--brutal-ink)]">All Events</h2>
                <div class="flex items-center gap-3 rounded-xl border-[1.5px] border-[var(--brutal-ink)] bg-white px-4 py-2 shadow-[var(--brutal-shadow-sm)] transition-all focus-within:-translate-y-0.5">
                    <svg class="h-4 w-4 text-[#101014]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search events..."
                        class="w-full min-w-[200px] border-0 bg-transparent text-sm font-bold text-[#101014] shadow-none placeholder-[#6B7280] outline-none"
                    />
                </div>
            </div>

            <!-- Category Tabs -->
            <div class="mb-10 flex flex-wrap gap-2">
                <button
                    v-for="cat in categories"
                    :key="cat"
                    type="button"
                    class="rounded-lg border-[1.5px] border-[var(--brutal-ink)] px-3.5 py-1.5 text-xs font-bold shadow-[var(--brutal-shadow-sm)] transition-all duration-200 active:translate-y-0.5 active:shadow-none"
                    :class="
                        activeCategory === cat
                            ? 'bg-[var(--brutal-blue)] text-white'
                            : 'bg-white text-[var(--brutal-ink)] hover:bg-[var(--brutal-yellow)]/15'
                    "
                    @click="activeCategory = cat"
                >
                    {{ cat }}
                </button>
            </div>

            <!-- Event Grid -->
            <div v-if="filteredEvents.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <a
                    v-for="(event, i) in filteredEvents"
                    :key="event.id"
                    :href="`/events/${event.id}`"
                    :class="[
                        'brutal-card group flex flex-col overflow-hidden transition-all duration-300',
                        listVisible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
                    ]"
                    :style="{ transitionDelay: `${i * 60}ms` }"
                >
                    <!-- Card Image -->
                    <div class="relative aspect-[16/10] overflow-hidden bg-muted">
                        <img
                            v-if="event.image"
                            :src="event.image"
                            :alt="event.title"
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]"
                        />
                        <span
                            :class="[
                                'absolute top-3 right-3 rounded-lg border-[1.5px] border-[var(--brutal-ink)] bg-white px-2 py-0.5 text-[10px] font-bold shadow-[var(--brutal-shadow-sm)]',
                                event.status === 'Open' ? 'text-[#059669]' : 'text-[#D97706]',
                            ]"
                        >
                            {{ event.status }}
                        </span>
                    </div>
                    <!-- Card Content -->
                    <div class="flex flex-1 flex-col p-5">
                        <span class="mb-2.5 w-fit rounded-md border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-yellow)]/12 px-2 py-0.5 text-[10px] font-bold text-[var(--brutal-ink)] shadow-[var(--shadow-xs)]">
                            {{ event.category }}
                        </span>
                        <h3 class="font-display mb-2 text-base font-bold text-[var(--brutal-ink)] transition-colors group-hover:text-[var(--brutal-blue)]">{{ event.title }}</h3>
                        <div class="mt-auto space-y-1.5 pt-3 text-[11px] font-bold text-[#34343B]">
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3 w-3 text-[#9CA3AF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                                {{ event.date }}
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3 w-3 text-[#9CA3AF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                {{ event.location }}
                            </div>
                            <div class="flex items-center gap-1.5">
                                <svg class="h-3 w-3 text-[#9CA3AF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                                {{ event.attendees.toLocaleString() }} registered
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Empty state -->
            <div v-else class="flex flex-col items-center justify-center py-20 text-center">
                <svg class="mb-4 h-12 w-12 text-[#101014]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <p class="font-display text-lg font-extrabold text-[#101014]">No events found</p>
                <p class="mt-1 text-sm font-semibold text-[#34343B]">Try adjusting your search or filter.</p>
            </div>
        </div>
    </section>
</template>
