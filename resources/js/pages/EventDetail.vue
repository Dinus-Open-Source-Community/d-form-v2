<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue'
import { computed, ref, onMounted } from 'vue'
import { MapPin, CalendarDays, Users, ArrowLeft, ArrowRight, Check, Shield } from 'lucide-vue-next'
import {
    dummyEvents, formatDate, formatDateTime,
    categoryLabelMap, categoryColorMap, sessionLabelMap, statusColorMap,
} from '@/lib/dummyData'
import { toCategoryList } from '@/lib/eventCategories'

const props = defineProps<{ eventId: string }>()

const visible = ref(false)
onMounted(() => setTimeout(() => (visible.value = true), 100))

const event = computed(() => dummyEvents.find(e => e.id === props.eventId))

const capacityPercent = computed(() =>
    event.value ? Math.round((event.value.registered_count / event.value.quota) * 100) : 0,
)

const highlights = [
    'Expert-led sessions',
    'Hands-on exercises',
    'Certificate of completion',
    'Lifetime access to materials',
]
</script>

<template>
    <LandingLayout>
        <template v-if="event">
            <section class="relative pt-20">
                <div class="relative h-[280px] w-full overflow-hidden bg-muted lg:h-[360px]">
                    <img :src="event.banner_url ?? ''" :alt="event.title" class="h-full w-full object-cover" />
                    <div class="absolute inset-0 bg-[#111827]/50" />
                </div>
            </section>

            <section class="relative bg-white">
                <div class="mx-auto max-w-7xl px-6 lg:px-8">
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
                                        v-for="cat in toCategoryList(event.category)"
                                        :key="cat"
                                        class="rounded-lg px-3 py-1 text-[11px] font-semibold text-white"
                                        :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                                    >
                                        {{ categoryLabelMap[cat] ?? cat }}
                                    </span>
                                    <span
                                        :class="[
                                            'rounded-lg px-3 py-1 text-[11px] font-semibold',
                                            event.registration_status === 'open'
                                                ? 'bg-[#059669]/8 text-[#059669]'
                                                : 'bg-[#D97706]/8 text-[#D97706]',
                                        ]"
                                    >
                                        {{ event.registration_status === 'open' ? 'Open' : 'Coming Soon' }}
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
                                <ArrowRight class="size-4" />
                            </a>
                        </div>

                        <div class="mt-6 flex flex-wrap items-center gap-6 border-t border-[#F3F4F6] pt-5">
                            <div class="flex items-center gap-2 text-sm text-[#6B7280]">
                                <div class="flex size-8 items-center justify-center rounded-lg bg-[#F9FAFB]">
                                    <CalendarDays class="size-4 text-[#9CA3AF]" />
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-[#111827]">{{ formatDate(event.start_date) }}</p>
                                    <p class="text-[11px] text-[#9CA3AF]">{{ toCategoryList(event.session).map((s) => sessionLabelMap[s] ?? s).join(', ') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-[#6B7280]">
                                <div class="flex size-8 items-center justify-center rounded-lg bg-[#F9FAFB]">
                                    <MapPin class="size-4 text-[#9CA3AF]" />
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-[#111827]">{{ event.location }}</p>
                                    <p class="text-[11px] text-[#9CA3AF]">Venue</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-[#6B7280]">
                                <div class="flex size-8 items-center justify-center rounded-lg bg-[#F9FAFB]">
                                    <Users class="size-4 text-[#9CA3AF]" />
                                </div>
                                <div>
                                    <p class="text-xs font-semibold text-[#111827]">
                                        {{ event.registered_count.toLocaleString() }} / {{ event.quota.toLocaleString() }}
                                    </p>
                                    <p class="text-[11px] text-[#9CA3AF]">Registered</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-10 pb-20 lg:grid-cols-3">
                        <div
                            :class="['transition-all duration-700 lg:col-span-2', visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0']"
                            style="transition-delay: 200ms"
                        >
                            <h2 class="text-lg font-bold text-[#111827]">About this event</h2>
                            <div class="prose prose-sm mt-4 max-w-none text-[#6B7280]" v-html="event.description" />

                            <div class="mt-10">
                                <h3 class="mb-4 text-base font-bold text-[#111827]">What you'll get</h3>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <div
                                        v-for="item in highlights"
                                        :key="item"
                                        class="flex items-center gap-3 rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] p-4 transition-all duration-300 hover:border-[#0A84DC]/15 hover:shadow-sm"
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
                            :class="['transition-all duration-700', visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0']"
                            style="transition-delay: 300ms"
                        >
                            <div class="sticky top-28 flex flex-col gap-6">
                                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                                    <h3 class="mb-4 text-sm font-bold text-[#111827]">Registration</h3>
                                    <div class="mb-2 flex items-end justify-between">
                                        <span class="text-2xl font-extrabold text-[#0A84DC]">{{ capacityPercent }}%</span>
                                        <span class="text-xs text-[#9CA3AF]">{{ event.registered_count.toLocaleString() }} / {{ event.quota.toLocaleString() }}</span>
                                    </div>
                                    <div class="h-2 w-full overflow-hidden rounded-full bg-[#F3F4F6]">
                                        <div class="h-full rounded-full bg-[#0A84DC] transition-all duration-1000" :style="{ width: capacityPercent + '%' }" />
                                    </div>
                                    <a
                                        href="/auth"
                                        class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl bg-[#0A84DC] px-6 py-3.5 text-sm font-semibold text-white shadow-sm transition-all duration-200 hover:bg-[#0972c0] hover:shadow-md active:scale-[0.97]"
                                    >
                                        Register Now
                                        <ArrowRight class="size-4" />
                                    </a>
                                </div>

                                <div class="rounded-2xl border border-[#E5E7EB] bg-white p-6 shadow-sm">
                                    <h3 class="mb-5 text-sm font-bold text-[#111827]">Event Details</h3>
                                    <div class="flex flex-col gap-4">
                                        <div class="flex items-start gap-3">
                                            <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#F9FAFB]">
                                                <CalendarDays class="size-4 text-[#9CA3AF]" />
                                            </div>
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-wider text-[#9CA3AF]">Date & Time</p>
                                                <p class="text-sm font-medium text-[#111827]">{{ formatDate(event.start_date) }}</p>
                                                <p class="text-xs text-[#6B7280]">{{ toCategoryList(event.session).map((s) => sessionLabelMap[s] ?? s).join(', ') }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#F9FAFB]">
                                                <MapPin class="size-4 text-[#9CA3AF]" />
                                            </div>
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-wider text-[#9CA3AF]">Location</p>
                                                <p class="text-sm font-medium text-[#111827]">{{ event.location }}</p>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <div class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#F9FAFB]">
                                                <Shield class="size-4 text-[#9CA3AF]" />
                                            </div>
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-wider text-[#9CA3AF]">Category</p>
                                                <p class="flex flex-wrap gap-1 text-sm font-semibold">
                                                    <span
                                                        v-for="(cat, idx) in toCategoryList(event.category)"
                                                        :key="cat"
                                                        :style="{ color: categoryColorMap[cat] ?? '#6B7280' }"
                                                    >{{ categoryLabelMap[cat] ?? cat }}<span v-if="idx < toCategoryList(event.category).length - 1">,</span></span>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-[#E5E7EB] py-8">
                        <a
                            href="/events"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-[#0A84DC] transition-colors hover:text-[#0972c0]"
                        >
                            <ArrowLeft class="size-4" />
                            Back to All Events
                        </a>
                    </div>
                </div>
            </section>
        </template>

        <template v-else>
            <section class="flex min-h-[60vh] flex-col items-center justify-center bg-white px-6 py-24 pt-32">
                <div class="mb-6 flex size-16 items-center justify-center rounded-2xl border border-[#E5E7EB] bg-[#F9FAFB]">
                    <Users class="size-7 text-[#9CA3AF]" />
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
