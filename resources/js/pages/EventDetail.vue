<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import { computed, ref, onMounted } from 'vue';

const props = defineProps<{ eventId: string }>();

const visible = ref(false);
onMounted(() => setTimeout(() => (visible.value = true), 100));

const allEvents = [
    {
        id: 1,
        title: 'Design Systems Workshop',
        date: 'Apr 20, 2026',
        time: '09:00 - 17:00 WIB',
        location: 'Online',
        attendees: 342,
        capacity: 500,
        category: 'Workshop',
        status: 'Open',
        color: '#0A84DC',
        image: '/images/events/design-workshop.png',
        desc: 'A hands-on workshop covering modern design systems, component libraries, and design tokens. Learn how to build scalable and consistent UIs across products. Led by industry experts with 10+ years of experience in design engineering.',
        highlights: [
            'Expert-led sessions',
            'Hands-on exercises',
            'Certificate of completion',
            'Lifetime access to materials',
        ],
    },
    {
        id: 2,
        title: 'Startup Pitch Night',
        date: 'Apr 25, 2026',
        time: '18:00 - 22:00 WIB',
        location: 'Semarang',
        attendees: 189,
        capacity: 250,
        category: 'Networking',
        status: 'Open',
        color: '#7C3AED',
        image: '/images/events/startup-networking.png',
        desc: 'Connect with founders, investors, and mentors. Watch live startup pitches and network with the entrepreneurial community in Central Java. An evening of inspiration and opportunity.',
        highlights: ['Live startup pitches', 'Investor networking', 'Mentorship sessions', 'Free refreshments'],
    },
    {
        id: 3,
        title: 'AI & Machine Learning Summit',
        date: 'May 5, 2026',
        time: '08:00 - 18:00 WIB',
        location: 'Bandung',
        attendees: 1205,
        capacity: 2000,
        category: 'Conference',
        status: 'Open',
        color: '#059669',
        image: '/images/events/ai-summit.png',
        desc: 'Explore the latest in AI research, machine learning applications, and ethical AI frameworks. Featuring keynotes from industry leaders, research presentations, and hands-on workshops.',
        highlights: ['Keynote speakers', 'Research presentations', 'Hands-on AI workshops', 'Networking lunch'],
    },
    {
        id: 4,
        title: 'UX Research Bootcamp',
        date: 'May 10, 2026',
        time: '09:00 - 16:00 WIB',
        location: 'Online',
        attendees: 567,
        capacity: 800,
        category: 'Bootcamp',
        status: 'Open',
        color: '#D97706',
        image: '/images/events/design-workshop.png',
        desc: 'Intensive 2-day bootcamp on user research methods, usability testing, and data-driven design decisions. Perfect for designers transitioning to UX research roles.',
        highlights: ['Research methodology', 'Usability testing labs', 'Portfolio project', 'Career guidance'],
    },
    {
        id: 5,
        title: 'Open Source Meetup',
        date: 'May 22, 2026',
        time: '14:00 - 18:00 WIB',
        location: 'Yogyakarta',
        attendees: 156,
        capacity: 200,
        category: 'Meetup',
        status: 'Open',
        color: '#DC2626',
        image: '/images/events/hackathon.png',
        desc: 'Monthly meetup for open source contributors. Share projects, get code reviews, and contribute to popular repositories together in a collaborative environment.',
        highlights: ['Project showcases', 'Pair programming', 'Code reviews', 'Community networking'],
    },
    {
        id: 6,
        title: 'Cloud Architecture Day',
        date: 'Jun 1, 2026',
        time: '09:00 - 17:00 WIB',
        location: 'Surabaya',
        attendees: 890,
        capacity: 1200,
        category: 'Conference',
        status: 'Coming Soon',
        color: '#0891B2',
        image: '/images/events/cloud-architecture.png',
        desc: 'Deep-dive into cloud-native architectures, serverless patterns, and distributed systems design. Hands-on labs with AWS, GCP, and Azure.',
        highlights: ['Multi-cloud labs', 'Architecture workshops', 'Expert panels', 'Certification prep'],
    },
    {
        id: 7,
        title: 'Product Management Forum',
        date: 'Jun 8, 2026',
        time: '10:00 - 16:00 WIB',
        location: 'Online',
        attendees: 423,
        capacity: 600,
        category: 'Forum',
        status: 'Coming Soon',
        color: '#DB2777',
        image: '/images/events/startup-networking.png',
        desc: 'A day-long forum discussing product strategy, roadmap prioritization, and growth frameworks with experienced product leaders.',
        highlights: ['Strategy frameworks', 'Case studies', 'PM networking', 'Interactive Q&A'],
    },
    {
        id: 8,
        title: 'Hackathon: Code for Good',
        date: 'Jun 15, 2026',
        time: '00:00 (48 hours)',
        location: 'Jakarta',
        attendees: 300,
        capacity: 500,
        category: 'Hackathon',
        status: 'Coming Soon',
        color: '#0D9488',
        image: '/images/events/hackathon.png',
        desc: '48-hour hackathon focused on building technology solutions for social impact. Teams compete for prizes and mentorship opportunities.',
        highlights: ['Cash prizes', 'Mentorship', 'Team matching', 'Social impact'],
    },
];

const event = computed(() => allEvents.find((e) => e.id === Number(props.eventId)));
const capacityPercent = computed(() =>
    event.value ? Math.round((event.value.attendees / event.value.capacity) * 100) : 0
);
</script>

<template>
    <LandingLayout>
        <template v-if="event">
            <!-- Hero Section with image -->
            <section class="relative pt-20">
                <div class="relative h-[280px] w-full overflow-hidden bg-[#F9FAFB] lg:h-[360px]">
                    <img :src="event.image" :alt="event.title" class="h-full w-full object-cover" />
                    <div class="absolute inset-0 bg-[#111827]/50"></div>
                </div>
            </section>

            <!-- Content -->
            <section class="relative bg-white">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <!-- Floating info card -->
                    <div
                        :class="[
                            'relative z-10 -mt-16 mb-10 rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-md transition-all duration-700 sm:p-8',
                            visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                        ]"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-lg px-3 py-1 text-[11px] font-semibold text-white"
                                        :style="{ backgroundColor: event.color }"
                                    >
                                        {{ event.category }}
                                    </span>
                                    <span
                                        :class="[
                                            'rounded-lg px-3 py-1 text-[11px] font-semibold',
                                            event.status === 'Open'
                                                ? 'bg-[#059669]/8 text-[#059669]'
                                                : 'bg-[#D97706]/8 text-[#D97706]',
                                        ]"
                                    >
                                        {{ event.status }}
                                    </span>
                                </div>
                                <h1 class="text-2xl font-extrabold tracking-tight text-[#111827] sm:text-3xl">
                                    {{ event.title }}
                                </h1>
                            </div>
                            <a
                                href="/auth"
                                class="inline-flex items-center gap-2 rounded-xl bg-[#0A84DC] px-6 py-3 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-[#0972c0] hover:shadow-md active:scale-[0.97]"
                            >
                                Register Now
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                >
                                    <path d="M5 12h14" />
                                    <path d="m12 5 7 7-7 7" />
                                </svg>
                            </a>
                        </div>

                        <!-- Meta row -->
                        <div class="mt-6 flex flex-wrap items-center gap-6 border-t border-[#F3F4F6] pt-5">
                            <div class="flex items-center gap-2 text-sm text-[#6B7280]">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#F9FAFB]">
                                    <svg
                                        class="h-4 w-4 text-[#9CA3AF]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                    >
                                        <rect width="18" height="18" x="3" y="4" rx="2" />
                                        <path d="M16 2v4" />
                                        <path d="M8 2v4" />
                                        <path d="M3 10h18" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-[#111827]">{{ event.date }}</p>
                                    <p class="text-[11px] text-[#9CA3AF]">{{ event.time }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-[#6B7280]">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#F9FAFB]">
                                    <svg
                                        class="h-4 w-4 text-[#9CA3AF]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                    >
                                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" />
                                        <circle cx="12" cy="10" r="3" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-[#111827]">{{ event.location }}</p>
                                    <p class="text-[11px] text-[#9CA3AF]">Venue</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-[#6B7280]">
                                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#F9FAFB]">
                                    <svg
                                        class="h-4 w-4 text-[#9CA3AF]"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                    >
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                        <circle cx="9" cy="7" r="4" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-[#111827]">
                                        {{ event.attendees.toLocaleString() }} / {{ event.capacity.toLocaleString() }}
                                    </p>
                                    <p class="text-[11px] text-[#9CA3AF]">Registered</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main grid -->
                    <div class="grid gap-10 pb-20 lg:grid-cols-3">
                        <!-- Main content -->
                        <div
                            :class="[
                                'transition-all duration-700 lg:col-span-2',
                                visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                            ]"
                            style="transition-delay: 200ms"
                        >
                            <h2 class="text-lg font-bold text-[#111827]">About this event</h2>
                            <p class="mt-4 text-sm leading-relaxed text-[#6B7280]">{{ event.desc }}</p>

                            <div class="mt-10">
                                <h3 class="mb-4 text-base font-bold text-[#111827]">What you'll get</h3>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div
                                        v-for="item in event.highlights"
                                        :key="item"
                                        class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4 transition-all duration-300 hover:border-[#0A84DC]/15 hover:shadow-sm"
                                    >
                                        <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#059669]/8">
                                            <svg
                                                class="h-3.5 w-3.5 text-[#059669]"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2.5"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <path d="M20 6 9 17l-5-5" />
                                            </svg>
                                        </div>
                                        <span class="text-sm font-medium text-[#111827]">{{ item }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar -->
                        <div
                            :class="[
                                'transition-all duration-700',
                                visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                            ]"
                            style="transition-delay: 300ms"
                        >
                            <div class="sticky top-28 space-y-6">
                                <!-- Capacity card -->
                                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                                    <h3 class="mb-4 text-sm font-bold text-[#111827]">Registration</h3>
                                    <div class="mb-2 flex items-end justify-between">
                                        <span class="text-2xl font-extrabold text-[#0A84DC]"
                                            >{{ capacityPercent }}%</span
                                        >
                                        <span class="text-xs text-[#9CA3AF]"
                                            >{{ event.attendees.toLocaleString() }} /
                                            {{ event.capacity.toLocaleString() }}</span
                                        >
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-[#F3F4F6]">
                                        <div
                                            class="h-full rounded-full bg-[#0A84DC] transition-all duration-1000"
                                            :style="{ width: capacityPercent + '%' }"
                                        ></div>
                                    </div>
                                    <a
                                        href="/auth"
                                        class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-[#0A84DC] px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-[#0972c0] hover:shadow-md active:scale-[0.97]"
                                    >
                                        Register Now
                                        <svg
                                            class="h-4 w-4"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2.5"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >
                                            <path d="M5 12h14" />
                                            <path d="m12 5 7 7-7 7" />
                                        </svg>
                                    </a>
                                </div>

                                <!-- Details card -->
                                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                                    <h3 class="mb-5 text-sm font-bold text-[#111827]">Event Details</h3>
                                    <div class="space-y-4">
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#F9FAFB]"
                                            >
                                                <svg
                                                    class="h-4 w-4 text-[#9CA3AF]"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                >
                                                    <rect width="18" height="18" x="3" y="4" rx="2" />
                                                    <path d="M16 2v4" />
                                                    <path d="M8 2v4" />
                                                    <path d="M3 10h18" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p
                                                    class="text-[11px] font-semibold tracking-wider text-[#9CA3AF] uppercase"
                                                >
                                                    Date & Time
                                                </p>
                                                <p class="text-sm font-medium text-[#111827]">{{ event.date }}</p>
                                                <p class="text-xs text-[#6B7280]">{{ event.time }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#F9FAFB]"
                                            >
                                                <svg
                                                    class="h-4 w-4 text-[#9CA3AF]"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                >
                                                    <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z" />
                                                    <circle cx="12" cy="10" r="3" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p
                                                    class="text-[11px] font-semibold tracking-wider text-[#9CA3AF] uppercase"
                                                >
                                                    Location
                                                </p>
                                                <p class="text-sm font-medium text-[#111827]">{{ event.location }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#F9FAFB]"
                                            >
                                                <svg
                                                    class="h-4 w-4 text-[#9CA3AF]"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="2"
                                                    stroke-linecap="round"
                                                >
                                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10" />
                                                </svg>
                                            </div>
                                            <div>
                                                <p
                                                    class="text-[11px] font-semibold tracking-wider text-[#9CA3AF] uppercase"
                                                >
                                                    Category
                                                </p>
                                                <p class="text-sm font-semibold" :style="{ color: event.color }">
                                                    {{ event.category }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Back link -->
                    <div class="border-t border-[#E5E7EB] py-8">
                        <a
                            href="/events"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-[#0A84DC] transition-colors hover:text-[#0972c0]"
                        >
                            <svg
                                class="h-4 w-4"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.5"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            >
                                <path d="M19 12H5" />
                                <path d="m12 19-7-7 7-7" />
                            </svg>
                            Back to All Events
                        </a>
                    </div>
                </div>
            </section>
        </template>

        <!-- Not found -->
        <template v-else>
            <section class="flex min-h-[60vh] flex-col items-center justify-center bg-white px-6 py-24 pt-32">
                <div
                    class="mb-6 flex h-16 w-16 items-center justify-center rounded-2xl border border-[#E5E7EB] bg-[#F9FAFB]"
                >
                    <svg
                        class="h-7 w-7 text-[#9CA3AF]"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.5"
                        stroke-linecap="round"
                    >
                        <circle cx="11" cy="11" r="8" />
                        <path d="m21 21-4.3-4.3" />
                    </svg>
                </div>
                <h2 class="text-xl font-extrabold text-[#111827]">Event Not Found</h2>
                <p class="mt-2 text-sm text-[#6B7280]">The event you're looking for doesn't exist.</p>
                <a
                    href="/events"
                    class="mt-6 inline-flex items-center gap-2 rounded-xl bg-[#0A84DC] px-6 py-3 text-sm font-semibold text-white shadow-sm transition-all hover:bg-[#0972c0] hover:shadow-md"
                >
                    Browse Events
                </a>
            </section>
        </template>
    </LandingLayout>
</template>
