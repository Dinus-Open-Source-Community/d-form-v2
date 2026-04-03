<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';

const listVisible = ref(false);
onMounted(() => {
    const observer = new IntersectionObserver(
        ([entry]) => { if (entry.isIntersecting) listVisible.value = true; },
        { threshold: 0.05 }
    );
    const el = document.getElementById('event-list');
    if (el) observer.observe(el);
});

const events = [
    { id: 1, title: 'Design Systems Workshop', date: 'Apr 20, 2026', location: 'Online', attendees: 342, category: 'Workshop', status: 'Open', color: '#0A84DC', image: '/images/events/design-workshop.png' },
    { id: 2, title: 'Startup Pitch Night', date: 'Apr 25, 2026', location: 'Semarang', attendees: 189, category: 'Networking', status: 'Open', color: '#7C3AED', image: '/images/events/startup-networking.png' },
    { id: 3, title: 'AI & Machine Learning Summit', date: 'May 5, 2026', location: 'Bandung', attendees: 1205, category: 'Conference', status: 'Open', color: '#059669', image: '/images/events/ai-summit.png' },
    { id: 4, title: 'UX Research Bootcamp', date: 'May 10, 2026', location: 'Online', attendees: 567, category: 'Bootcamp', status: 'Open', color: '#D97706', image: '/images/events/design-workshop.png' },
    { id: 5, title: 'Open Source Meetup', date: 'May 22, 2026', location: 'Yogyakarta', attendees: 156, category: 'Meetup', status: 'Open', color: '#DC2626', image: '/images/events/hackathon.png' },
    { id: 6, title: 'Cloud Architecture Day', date: 'Jun 1, 2026', location: 'Surabaya', attendees: 890, category: 'Conference', status: 'Coming Soon', color: '#0891B2', image: '/images/events/cloud-architecture.png' },
    { id: 7, title: 'Product Management Forum', date: 'Jun 8, 2026', location: 'Online', attendees: 423, category: 'Forum', status: 'Coming Soon', color: '#DB2777', image: '/images/events/startup-networking.png' },
    { id: 8, title: 'Hackathon: Code for Good', date: 'Jun 15, 2026', location: 'Jakarta', attendees: 300, category: 'Hackathon', status: 'Coming Soon', color: '#0D9488', image: '/images/events/hackathon.png' },
];

const searchQuery = ref('');
const activeCategory = ref('All');

const categories = computed(() => {
    const cats = [...new Set(events.map(e => e.category))];
    return ['All', ...cats];
});

const filteredEvents = computed(() => {
    let result = events;
    if (activeCategory.value !== 'All') {
        result = result.filter(e => e.category === activeCategory.value);
    }
    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        result = result.filter(e =>
            e.title.toLowerCase().includes(q) ||
            e.location.toLowerCase().includes(q) ||
            e.category.toLowerCase().includes(q)
        );
    }
    return result;
});
</script>

<template>
    <section id="event-list" class="bg-white py-20 lg:py-28">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <!-- Header + Search -->
            <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-2xl font-extrabold tracking-tight text-[#111827]">All Events</h2>
                <div class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] bg-white px-4 py-2.5 transition-all focus-within:border-[#0A84DC]/30 focus-within:shadow-sm">
                    <svg class="h-4 w-4 text-[#9CA3AF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search events..."
                        class="w-full min-w-[200px] bg-transparent text-sm text-[#111827] placeholder-[#9CA3AF] outline-none"
                    />
                </div>
            </div>

            <!-- Category Tabs -->
            <div class="mb-10 flex flex-wrap gap-2">
                <button
                    v-for="cat in categories" :key="cat"
                    @click="activeCategory = cat"
                    :class="[
                        'rounded-lg px-4 py-2 text-xs font-semibold transition-all duration-200',
                        activeCategory === cat
                            ? 'bg-[#0A84DC] text-white shadow-sm'
                            : 'border border-[#E5E7EB] bg-white text-[#6B7280] hover:border-[#D1D5DB] hover:bg-[#F9FAFB]',
                    ]"
                >
                    {{ cat }}
                </button>
            </div>

            <!-- Event Grid -->
            <div v-if="filteredEvents.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <a
                    v-for="(event, i) in filteredEvents" :key="event.id"
                    :href="`/events/${event.id}`"
                    :class="[
                        'group flex flex-col overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md',
                        listVisible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
                    ]"
                    :style="{ transitionDelay: `${i * 60}ms` }"
                >
                    <!-- Card Image -->
                    <div class="relative aspect-[16/10] overflow-hidden">
                        <img :src="event.image" :alt="event.title" class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-[1.03]" />
                        <span :class="['absolute top-3 right-3 rounded-md px-2 py-0.5 text-[10px] font-semibold', event.status === 'Open' ? 'bg-white text-[#059669]' : 'bg-white text-[#D97706]']">
                            {{ event.status }}
                        </span>
                    </div>
                    <!-- Card Content -->
                    <div class="flex flex-1 flex-col p-5">
                        <span class="mb-3 w-fit rounded-md px-2 py-0.5 text-[10px] font-semibold" :style="{ backgroundColor: event.color + '12', color: event.color }">
                            {{ event.category }}
                        </span>
                        <h3 class="mb-2 text-sm font-semibold text-[#111827] transition-colors group-hover:text-[#0A84DC]">{{ event.title }}</h3>
                        <div class="mt-auto space-y-1.5 pt-3 text-[11px] text-[#6B7280]">
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
                                {{ event.attendees.toLocaleString() }} attendees
                            </div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Empty state -->
            <div v-else class="flex flex-col items-center justify-center py-20 text-center">
                <svg class="mb-4 h-12 w-12 text-[#E5E7EB]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <p class="text-sm font-semibold text-[#111827]">No events found</p>
                <p class="mt-1 text-xs text-[#9CA3AF]">Try adjusting your search or filter.</p>
            </div>
        </div>
    </section>
</template>
