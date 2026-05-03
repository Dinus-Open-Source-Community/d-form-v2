<script setup lang="ts">
import { computed } from 'vue';
import { formatDate } from '@/lib/dummyData';

const props = defineProps<{
    events: IEvent[];
}>();

const featured = computed(() => props.events[0] ?? null);

const highlight = computed(() => {
    const e = featured.value;
    if (!e) {
        return null;
    }
    return {
        id: e.id,
        title: e.title,
        desc: e.description?.replace(/<[^>]+>/g, '').slice(0, 220) + (e.description && e.description.length > 220 ? '…' : ''),
        date: `${formatDate(e.start_date)} — ${formatDate(e.end_date)}`,
        location: e.location,
        attendees: e.registered_count,
        status: e.registration_status === 'open' ? 'Open' : 'Featured',
        image: e.banner_url ?? '',
        categoryLabel: cat ? categoryLabelMap[cat] ?? cat : '',
        sessionLabel: session ? sessionLabelMap[session] ?? session : '',
    };
});
</script>

<template>
    <section v-if="highlight" class="border-y-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-yellow)]/10 py-14">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mb-8 flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-pink)]/15 text-[var(--brutal-ink)] shadow-[var(--brutal-shadow-sm)]">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <h2 class="font-display text-2xl font-bold tracking-[-0.035em] text-[var(--brutal-ink)]">Highlighted Event</h2>
            </div>

            <div class="brutal-card overflow-hidden bg-white">
                <div class="grid lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <img :src="highlight.image" :alt="highlight.title" class="h-full w-full object-cover" style="min-height: 280px;" />
                    </div>
                    <div class="flex flex-col justify-center p-8 lg:col-span-3 lg:p-10">
                        <div class="mb-3.5 inline-flex w-fit items-center gap-1.5 rounded-lg border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-mint)]/15 px-3 py-1 shadow-[var(--brutal-shadow-sm)]">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[var(--brutal-blue)] opacity-75"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-[var(--brutal-blue)]"></span>
                            </span>
                            <span class="text-[10px] font-bold text-[var(--brutal-ink)]">{{ highlight.status }}</span>
                        </div>
                        <h3 class="font-display text-2xl font-bold tracking-[-0.035em] text-[var(--brutal-ink)] sm:text-3xl">{{ highlight.title }}</h3>
                        <p class="mt-3 max-w-xl text-sm font-medium leading-relaxed text-[var(--brutal-ink)]/60">{{ highlight.desc }}</p>
                        <div class="mt-6 flex flex-wrap items-center gap-6 text-xs font-bold text-[#34343B]">
                            <span class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-[#9CA3AF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                                {{ highlight.date }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-[#9CA3AF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                {{ highlight.location }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-[#9CA3AF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                {{ highlight.attendees.toLocaleString() }} registered
                            </span>
                        </div>
                        <a :href="`/events/${highlight.id}`" class="mt-7 inline-flex w-fit items-center gap-2 rounded-xl border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-blue)] px-6 py-3 text-sm font-bold text-white shadow-[var(--brutal-shadow)] transition-all duration-200 hover:-translate-y-0.5 hover:shadow-[4px_4px_0_var(--brutal-ink)] active:translate-y-0 active:shadow-[1px_1px_0_var(--brutal-ink)]">
                            View event
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
