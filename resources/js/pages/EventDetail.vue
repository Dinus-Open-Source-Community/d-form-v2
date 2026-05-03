<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import { computed, ref, onMounted } from 'vue';
import { MapPin, CalendarDays, Users, ArrowLeft, ArrowRight, Check, Shield } from 'lucide-vue-next';
import { formatDate, categoryLabelMap, categoryColorMap, sessionLabelMap } from '@/lib/dummyData';
import { toCategoryList } from '@/lib/eventCategories';

const props = defineProps<{
    event: IEvent;
}>();

const event = computed(() => props.event);

const visible = ref(false);
onMounted(() => setTimeout(() => (visible.value = true), 100));

const capacityPercent = computed<number>(() =>
    Math.round((event.value.registered_count / event.value.quota) * 100),
);

const registrationBadgeLabel = computed(() => {
    const s = event.value.registration_status;
    if (s === 'open') return 'Open';
    if (s === 'full') return 'Full';
    if (s === 'closed') return 'Closed';
    if (s === 'not_yet_open') return 'Coming Soon';
    return 'Registration';
});

const highlights = [
    'Expert-led sessions',
    'Hands-on exercises',
    'Certificate of completion',
    'Lifetime access to materials',
];
</script>

<template>
    <LandingLayout>
        <section class="relative pt-20">
                <div class="bg-muted relative h-[280px] w-full overflow-hidden lg:h-[360px]">
                    <img :src="event.banner_url ?? ''" :alt="event.title" class="h-full w-full object-cover" />
                    <div class="absolute inset-0 bg-[#111827]/50" />
                </div>
            </section>

            <section class="bg-background relative">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
                    <div
                        :class="[
                            'brutal-card relative z-10 -mt-16 mb-10 bg-white p-6 transition-all duration-700 sm:p-8',
                            visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                        ]"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="mb-3 flex flex-wrap items-center gap-2">
                                    <span
                                        v-for="cat in toCategoryList(event.category)"
                                        :key="cat"
                                        class="rounded-lg border-2 border-[#101014] px-3 py-1 text-[11px] font-extrabold text-white shadow-[2px_2px_0_#101014]"
                                        :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                                    >
                                        {{ categoryLabelMap[cat] ?? cat }}
                                    </span>
                                    <span
                                        :class="[
                                            'rounded-lg border-2 border-[#101014] px-3 py-1 text-[11px] font-extrabold shadow-[2px_2px_0_#101014]',
                                            event.registration_status === 'open'
                                                ? 'bg-[#059669]/8 text-[#059669]'
                                                : 'bg-[#D97706]/8 text-[#D97706]',
                                        ]"
                                    >
                                        {{ registrationBadgeLabel }}
                                    </span>
                                </div>
                                <h1
                                    class="font-display text-3xl font-extrabold tracking-[-0.04em] text-[#101014] sm:text-5xl"
                                >
                                    {{ event.title }}
                                </h1>
                            </div>
                            <a
                                href="/auth"
                                class="inline-flex items-center gap-2 rounded-2xl border-2 border-[#101014] bg-[#0A84DC] px-6 py-3 text-sm font-extrabold text-white shadow-[5px_5px_0_#101014] transition-all duration-200 hover:-translate-x-1 hover:-translate-y-1 hover:bg-[#FFD84D] hover:text-[#101014] active:translate-x-1 active:translate-y-1"
                            >
                                Register Now
                                <ArrowRight class="size-4" />
                            </a>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-6 border-t-2 border-[#101014] pt-5">
                            <div class="flex items-center gap-2 text-sm text-[#6B7280]">
                                <div
                                    class="flex size-9 items-center justify-center rounded-xl border-2 border-[#101014] bg-[#FFD84D] shadow-[2px_2px_0_#101014]"
                                >
                                    <CalendarDays class="size-4 text-[#9CA3AF]" />
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-[#111827]">
                                        {{ formatDate(event.start_date) }}
                                    </p>
                                    <p class="text-[11px] text-[#9CA3AF]">
                                        {{
                                            toCategoryList(event.session)
                                                .map((s) => sessionLabelMap[s] ?? s)
                                                .join(', ')
                                        }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-[#6B7280]">
                                <div
                                    class="flex size-9 items-center justify-center rounded-xl border-2 border-[#101014] bg-[#41F0B4] shadow-[2px_2px_0_#101014]"
                                >
                                    <MapPin class="size-4 text-[#9CA3AF]" />
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-[#111827]">{{ event.location }}</p>
                                    <p class="text-[11px] text-[#9CA3AF]">Venue</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-[#6B7280]">
                                <div
                                    class="flex size-9 items-center justify-center rounded-xl border-2 border-[#101014] bg-[#FF6BB5] shadow-[2px_2px_0_#101014]"
                                >
                                    <Users class="size-4 text-[#9CA3AF]" />
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-[#111827]">
                                        {{ event.registered_count.toLocaleString() }} /
                                        {{ event.quota.toLocaleString() }}
                                    </p>
                                    <p class="text-[11px] text-[#9CA3AF]">Registered</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-10 pb-20 lg:grid-cols-3">
                        <div
                            :class="[
                                'transition-all duration-700 lg:col-span-2',
                                visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                            ]"
                            style="transition-delay: 200ms"
                        >
                            <h2 class="font-display text-3xl font-extrabold text-[#101014]">About this event</h2>
                            <div
                                class="prose prose-sm mt-4 max-w-none font-semibold text-[#34343B]"
                                v-html="event.description"
                            />

                            <div class="mt-10">
                                <h3 class="font-display mb-4 text-2xl font-extrabold text-[#101014]">
                                    What you'll get
                                </h3>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div
                                        v-for="item in highlights"
                                        :key="item"
                                        class="flex items-center gap-3 rounded-2xl border-2 border-[#101014] bg-white p-4 shadow-[4px_4px_0_#101014] transition-all duration-300 hover:-translate-x-1 hover:-translate-y-1 hover:bg-[#FFD84D]"
                                    >
                                        <div class="flex size-7 items-center justify-center rounded-lg bg-[#059669]/8">
                                            <Check class="size-3.5 text-[#059669]" />
                                        </div>
                                        <span class="text-sm font-medium text-[#111827]">{{ item }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            :class="[
                                'transition-all duration-700',
                                visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                            ]"
                            style="transition-delay: 300ms"
                        >
                            <div class="sticky top-28 flex flex-col gap-6">
                                <div class="brutal-card bg-white p-6">
                                    <h3 class="font-display mb-4 text-xl font-extrabold text-[#101014]">
                                        Registration
                                    </h3>
                                    <div class="mb-2 flex items-end justify-between">
                                        <span class="text-2xl font-extrabold text-[#0A84DC]"
                                            >{{ capacityPercent }}%</span
                                        >
                                        <span class="text-xs text-[#9CA3AF]"
                                            >{{ event.registered_count.toLocaleString() }} /
                                            {{ event.quota.toLocaleString() }}</span
                                        >
                                    </div>
                                    <div
                                        class="h-4 w-full overflow-hidden rounded-full border-2 border-[#101014] bg-white shadow-[2px_2px_0_#101014]"
                                    >
                                        <div
                                            class="h-full rounded-full bg-[#0A84DC] transition-all duration-1000"
                                            :style="{ width: capacityPercent + '%' }"
                                        />
                                    </div>
                                    <a
                                        href="/auth"
                                        class="mt-6 flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-[#101014] bg-[#0A84DC] px-6 py-3.5 text-sm font-extrabold text-white shadow-[5px_5px_0_#101014] transition-all duration-200 hover:-translate-x-1 hover:-translate-y-1 hover:bg-[#FFD84D] hover:text-[#101014]"
                                    >
                                        Register Now
                                        <ArrowRight class="size-4" />
                                    </a>
                                </div>

                                <div class="brutal-card bg-white p-6">
                                    <h3 class="font-display mb-5 text-xl font-extrabold text-[#101014]">
                                        Event Details
                                    </h3>
                                    <div class="flex flex-col gap-4">
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#F9FAFB]"
                                            >
                                                <CalendarDays class="size-4 text-[#9CA3AF]" />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-[11px] font-semibold tracking-wider text-[#9CA3AF] uppercase"
                                                >
                                                    Date & Time
                                                </p>
                                                <p class="text-sm font-medium text-[#111827]">
                                                    {{ formatDate(event.start_date) }}
                                                </p>
                                                <p class="text-xs text-[#6B7280]">
                                                    {{
                                                        toCategoryList(event.session)
                                                            .map((s) => sessionLabelMap[s] ?? s)
                                                            .join(', ')
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <div
                                                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#F9FAFB]"
                                            >
                                                <MapPin class="size-4 text-[#9CA3AF]" />
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
                                                class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#F9FAFB]"
                                            >
                                                <Shield class="size-4 text-[#9CA3AF]" />
                                            </div>
                                            <div>
                                                <p
                                                    class="text-[11px] font-semibold tracking-wider text-[#9CA3AF] uppercase"
                                                >
                                                    Category
                                                </p>
                                                <p class="flex flex-wrap gap-1 text-sm font-semibold">
                                                    <span
                                                        v-for="(cat, idx) in toCategoryList(event.category)"
                                                        :key="cat"
                                                        :style="{ color: categoryColorMap[cat] ?? '#6B7280' }"
                                                        >{{ categoryLabelMap[cat] ?? cat
                                                        }}<span v-if="idx < toCategoryList(event.category).length - 1"
                                                            >,</span
                                                        ></span
                                                    >
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t-2 border-[#101014] py-8">
                        <a
                            href="/events"
                            class="inline-flex items-center gap-2 rounded-xl border-2 border-[#101014] bg-white px-4 py-2 text-sm font-extrabold text-[#0A84DC] shadow-[3px_3px_0_#101014] transition-all hover:-translate-x-0.5 hover:-translate-y-0.5 hover:bg-[#FFD84D] hover:text-[#101014] active:translate-x-1 active:translate-y-1 active:shadow-[1px_1px_0_#101014]"
                        >
                            <ArrowLeft class="size-4" />
                            Back to All Events
                        </a>
                    </div>
                </div>
            </section>
    </LandingLayout>
</template>
