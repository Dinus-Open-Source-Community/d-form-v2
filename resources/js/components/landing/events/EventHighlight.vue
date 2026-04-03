<script setup lang="ts">
const highlightEvent = {
    title: 'Tech Conference 2026',
    desc: 'The biggest technology conference in Southeast Asia. Join 5,000+ developers, designers, and entrepreneurs for 3 days of talks, workshops, and networking.',
    date: 'May 15–17, 2026',
    location: 'Jakarta Convention Center',
    attendees: 4832,
    status: 'Trending',
    image: '/images/events/tech-conference.png',
};
</script>

<template>
    <section class="border-y border-[#E5E7EB] bg-[#F9FAFB] py-16">
        <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <div class="mb-8 flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#0A84DC]/[0.06]">
                    <svg class="h-4 w-4 text-[#0A84DC]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                </div>
                <h2 class="text-lg font-bold tracking-tight text-[#111827]">Highlighted Event</h2>
            </div>

            <div class="overflow-hidden rounded-xl border border-[#E5E7EB] bg-white shadow-sm">
                <div class="grid lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <img :src="highlightEvent.image" :alt="highlightEvent.title" class="h-full w-full object-cover" style="min-height: 280px;" />
                    </div>
                    <div class="flex flex-col justify-center p-8 lg:col-span-3 lg:p-10">
                        <div class="mb-4 inline-flex w-fit items-center gap-1.5 rounded-lg bg-[#0A84DC]/[0.06] px-3 py-1">
                            <span class="relative flex h-1.5 w-1.5">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#0A84DC] opacity-75"></span>
                                <span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-[#0A84DC]"></span>
                            </span>
                            <span class="text-[11px] font-semibold text-[#0A84DC]">{{ highlightEvent.status }}</span>
                        </div>
                        <h3 class="text-2xl font-extrabold tracking-tight text-[#111827] sm:text-3xl">{{ highlightEvent.title }}</h3>
                        <p class="mt-3 max-w-xl text-sm leading-relaxed text-[#6B7280]">{{ highlightEvent.desc }}</p>
                        <div class="mt-6 flex flex-wrap items-center gap-6 text-xs text-[#6B7280]">
                            <span class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-[#9CA3AF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                                {{ highlightEvent.date }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-[#9CA3AF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                                {{ highlightEvent.location }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-[#9CA3AF]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                {{ highlightEvent.attendees.toLocaleString() }} attendees
                            </span>
                        </div>
                        <a href="/auth" class="mt-8 inline-flex w-fit items-center gap-2 rounded-lg bg-[#0A84DC] px-6 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-[#0972c0] hover:shadow-md active:scale-[0.97]">
                            Register Now
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
