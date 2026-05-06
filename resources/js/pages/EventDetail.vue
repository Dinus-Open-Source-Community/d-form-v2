<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue'
import { computed, ref, onMounted } from 'vue'
import { MapPin, CalendarDays, Users, ArrowLeft, ArrowRight, Check, Shield } from 'lucide-vue-next'
import { formatDate, categoryLabelMap, categoryColorMap, sessionLabelMap } from '@/lib/dummyData'
import { toCategoryList } from '@/lib/eventCategories'

const props = defineProps<{
    event: IEvent
}>()

const event = computed(() => props.event)

const visible = ref<boolean>(false)
onMounted(() => setTimeout(() => (visible.value = true), 100))

const capacityPercent = computed<number>(() =>
    Math.round((event.value.registered_count / event.value.quota) * 100),
)

const registrationBadgeLabel = computed<string>(() => {
    const s = event.value.registration_status
    if (s === 'open') return 'Open'
    if (s === 'full') return 'Full'
    if (s === 'closed') return 'Closed'
    if (s === 'not_yet_open') return 'Coming Soon'
    return 'Registration'
})

const registrationTone = computed<string>(() =>
    event.value.registration_status === 'open'
        ? 'bg-success/10 text-success border-success/20'
        : 'bg-warning/10 text-warning border-warning/20',
)

const highlights: string[] = [
    'Expert-led sessions',
    'Hands-on exercises',
    'Certificate of completion',
    'Lifetime access to materials',
]
</script>

<template>
    <LandingLayout>
        <section class="relative pt-20">
            <div class="relative h-[280px] w-full overflow-hidden bg-muted lg:h-[360px]">
                <img :src="event.banner_url ?? ''" :alt="event.title" class="h-full w-full object-cover" />
                <div class="absolute inset-0 bg-gradient-to-b from-foreground/10 via-foreground/30 to-foreground/55" />
            </div>
        </section>

        <section class="relative bg-background">
            <div class="mx-auto max-w-7xl px-6 lg:px-8">
                <div
                    :class="[
                        'app-surface relative z-10 -mt-16 mb-10 p-6 transition-all duration-700 sm:p-8',
                        visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                    ]"
                >
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="mb-3 flex flex-wrap items-center gap-2">
                                <span
                                    v-for="cat in toCategoryList(event.category)"
                                    :key="cat"
                                    class="rounded-full border border-border/70 bg-muted/40 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em]"
                                    :style="{ color: categoryColorMap[cat] ?? 'var(--muted-foreground)' }"
                                >
                                    {{ categoryLabelMap[cat] ?? cat }}
                                </span>
                                <span
                                    :class="[
                                        'rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em]',
                                        registrationTone,
                                    ]"
                                >
                                    {{ registrationBadgeLabel }}
                                </span>
                            </div>
                            <h1 class="font-display text-balance text-3xl font-bold leading-[1.05] tracking-[-0.035em] text-foreground sm:text-5xl">
                                {{ event.title }}
                            </h1>
                        </div>
                        <a
                            href="/auth"
                            class="inline-flex items-center gap-2 rounded-xl border border-primary/15 bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition-[transform,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px hover:bg-primary/92 active:scale-[0.98]"
                        >
                            Register Now
                            <ArrowRight class="size-4" />
                        </a>
                    </div>

                    <div class="mt-6 grid grid-cols-1 gap-5 border-t border-border pt-5 sm:grid-cols-3">
                        <div class="flex items-start gap-3">
                            <div class="grid size-9 shrink-0 place-items-center rounded-xl border border-border bg-muted/40">
                                <CalendarDays class="size-4 text-muted-foreground" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">When</p>
                                <p class="mt-0.5 truncate text-sm font-semibold text-foreground">{{ formatDate(event.start_date) }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ toCategoryList(event.session).map((s) => sessionLabelMap[s] ?? s).join(', ') }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="grid size-9 shrink-0 place-items-center rounded-xl border border-border bg-muted/40">
                                <MapPin class="size-4 text-muted-foreground" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">Where</p>
                                <p class="mt-0.5 truncate text-sm font-semibold text-foreground">{{ event.location }}</p>
                                <p class="text-xs text-muted-foreground">Venue</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <div class="grid size-9 shrink-0 place-items-center rounded-xl border border-border bg-muted/40">
                                <Users class="size-4 text-muted-foreground" />
                            </div>
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-[0.12em] text-muted-foreground">Registered</p>
                                <p class="mt-0.5 text-sm font-semibold text-foreground">
                                    {{ event.registered_count.toLocaleString() }} / {{ event.quota.toLocaleString() }}
                                </p>
                                <p class="text-xs text-muted-foreground">{{ capacityPercent }}% capacity</p>
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
                        <h2 class="font-display text-3xl font-bold tracking-[-0.035em] text-foreground">About this event</h2>
                        <div class="prose prose-sm mt-4 max-w-none text-muted-foreground" v-html="event.description" />

                        <div class="mt-10">
                            <h3 class="font-display mb-4 text-2xl font-bold tracking-[-0.025em] text-foreground">What you'll get</h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    v-for="item in highlights"
                                    :key="item"
                                    class="flex items-center gap-3 rounded-2xl border border-border bg-card p-4 shadow-xs transition-[transform,border-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px hover:border-primary/30"
                                >
                                    <div class="grid size-7 place-items-center rounded-lg bg-success/10">
                                        <Check class="size-3.5 text-success" />
                                    </div>
                                    <span class="text-sm font-medium text-foreground">{{ item }}</span>
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
                            <div class="app-surface p-6">
                                <h3 class="font-display mb-4 text-xl font-bold tracking-[-0.02em] text-foreground">Registration</h3>
                                <div class="mb-2 flex items-end justify-between">
                                    <span class="font-display text-2xl font-bold text-primary">{{ capacityPercent }}%</span>
                                    <span class="text-xs text-muted-foreground">
                                        {{ event.registered_count.toLocaleString() }} / {{ event.quota.toLocaleString() }}
                                    </span>
                                </div>
                                <div class="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                                    <div
                                        class="h-full rounded-full bg-primary transition-[width] duration-1000 ease-[cubic-bezier(0.22,1,0.36,1)]"
                                        :style="{ width: capacityPercent + '%' }"
                                    />
                                </div>
                                <a
                                    href="/auth"
                                    class="mt-6 flex w-full items-center justify-center gap-2 rounded-xl border border-primary/15 bg-primary px-6 py-3 text-sm font-semibold text-primary-foreground shadow-sm transition-[transform,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px hover:bg-primary/92 active:scale-[0.98]"
                                >
                                    Register Now
                                    <ArrowRight class="size-4" />
                                </a>
                            </div>

                            <div class="app-surface p-6">
                                <h3 class="font-display mb-5 text-xl font-bold tracking-[-0.02em] text-foreground">Event Details</h3>
                                <div class="flex flex-col gap-4">
                                    <div class="flex items-start gap-3">
                                        <div class="grid size-8 shrink-0 place-items-center rounded-lg bg-muted/40">
                                            <CalendarDays class="size-4 text-muted-foreground" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Date & Time</p>
                                            <p class="text-sm font-semibold text-foreground">{{ formatDate(event.start_date) }}</p>
                                            <p class="text-xs text-muted-foreground">
                                                {{ toCategoryList(event.session).map((s) => sessionLabelMap[s] ?? s).join(', ') }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="grid size-8 shrink-0 place-items-center rounded-lg bg-muted/40">
                                            <MapPin class="size-4 text-muted-foreground" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Location</p>
                                            <p class="text-sm font-semibold text-foreground">{{ event.location }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="grid size-8 shrink-0 place-items-center rounded-lg bg-muted/40">
                                            <Shield class="size-4 text-muted-foreground" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-muted-foreground">Category</p>
                                            <p class="flex flex-wrap gap-1 text-sm font-semibold">
                                                <span
                                                    v-for="(cat, idx) in toCategoryList(event.category)"
                                                    :key="cat"
                                                    :style="{ color: categoryColorMap[cat] ?? 'var(--foreground)' }"
                                                >
                                                    {{ categoryLabelMap[cat] ?? cat
                                                    }}<span v-if="idx < toCategoryList(event.category).length - 1">,</span>
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-border py-8">
                    <a
                        href="/events"
                        class="inline-flex items-center gap-2 rounded-xl border border-border bg-card px-4 py-2 text-sm font-semibold text-foreground shadow-xs transition-[transform,border-color,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px hover:border-primary/30 hover:bg-muted/40"
                    >
                        <ArrowLeft class="size-4" />
                        Back to All Events
                    </a>
                </div>
            </div>
        </section>
    </LandingLayout>
</template>
