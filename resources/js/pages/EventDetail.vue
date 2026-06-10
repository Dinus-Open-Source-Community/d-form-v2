<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import { computed, ref, onMounted } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { MapPin, CalendarDays, ArrowRight, Check, Shield } from 'lucide-vue-next';
import { formatDate, categoryLabelMap, categoryColorMap, sessionLabelMap } from '@/lib/dummyData';
import { toCategoryList } from '@/lib/eventCategories';
import { stripHtmlToText } from '@/utils/stripHtml';
import type { SharedSeoProps } from '@/types/seo';
import { routes } from '@/lib/routes';

const props = defineProps<{
    event: IEvent;
}>();

const page = usePage();
const seo = computed(() => (page.props as { seo: SharedSeoProps }).seo);

const event = computed(() => props.event);

const metaDescription = computed(() => {
    const plain = stripHtmlToText(event.value.description, 170);
    if (plain) {
        return plain;
    }
    return `${event.value.title} — ${formatDate(event.value.start_date)} · ${event.value.location}`;
});

const canonicalPath = computed(() => routes.landing.events.show(event.value.slug));

const eventJsonLd = computed<Record<string, unknown>[]>(() => {
    const e = event.value;
    const base = seo.value.siteUrl;
    const pageUrl = `${base}${routes.landing.events.show(e.slug)}`;
    const images = e.banner_url ? [e.banner_url] : undefined;

    let availability = 'https://schema.org/InStock';
    if (e.registration_status === 'full') {
        availability = 'https://schema.org/SoldOut';
    }
    if (e.registration_status === 'closed' || e.registration_status === 'not_yet_open') {
        availability = 'https://schema.org/PreOrder';
    }

    const eventSchema: Record<string, unknown> = {
        '@context': 'https://schema.org',
        '@type': 'Event',
        name: e.title,
        description: stripHtmlToText(e.description, 8000),
        startDate: e.start_date,
        endDate: e.end_date,
        eventAttendanceMode: 'https://schema.org/OfflineEventAttendanceMode',
        location: {
            '@type': 'Place',
            name: e.location,
            address: e.location,
        },
        url: pageUrl,
        offers: {
            '@type': 'Offer',
            price: e.price,
            priceCurrency: 'IDR',
            availability,
            url: pageUrl,
        },
    };
    if (images) {
        eventSchema.image = images;
    }

    const crumbs: Record<string, unknown> = {
        '@context': 'https://schema.org',
        '@type': 'BreadcrumbList',
        itemListElement: [
            { '@type': 'ListItem', position: 1, name: 'Beranda', item: `${base}/` },
            { '@type': 'ListItem', position: 2, name: 'Acara', item: `${base}/events` },
            { '@type': 'ListItem', position: 3, name: e.title, item: pageUrl },
        ],
    };

    return [eventSchema, crumbs];
});

const visible = ref<boolean>(false);
onMounted(() => setTimeout(() => (visible.value = true), 100));

const capacityPercent = computed<number>(() => Math.round((event.value.registered_count / event.value.quota) * 100));

const registrationBadgeLabel = computed<string>(() => {
    const s = event.value.registration_status;
    if (s === 'open') return 'Open';
    if (s === 'full') return 'Full';
    if (s === 'closed') return 'Closed';
    if (s === 'not_yet_open') return 'Coming Soon';
    return 'Registration';
});

const registrationTone = computed<string>(() =>
    event.value.registration_status === 'open'
        ? 'bg-success/10 text-success border-success/20'
        : 'bg-warning/10 text-warning border-warning/20'
);

const highlights: string[] = [
    'Expert-led sessions',
    'Hands-on exercises',
    'Certificate of completion',
    'Lifetime access to materials',
];
</script>

<template>
    <LandingLayout>
        <SeoHead
            :title="event.title"
            :description="metaDescription"
            :canonical-path="canonicalPath"
            :og-image="event.banner_url"
            og-type="website"
            :json-ld="eventJsonLd"
        />
        <section class="relative bg-background pt-20 sm:pt-24 lg:pt-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-[2rem] bg-muted/40 p-1.5 ring-1 ring-border/70 sm:p-2 lg:rounded-[2.25rem]">
                    <article
                        :class="[
                            'overflow-hidden rounded-[calc(2rem-0.375rem)] border border-border/70 bg-card shadow-sm transition-all duration-700 lg:grid lg:grid-cols-[minmax(0,1.15fr)_minmax(23rem,0.85fr)] lg:rounded-[calc(2.25rem-0.5rem)]',
                            visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                        ]"
                    >
                        <div class="relative aspect-video overflow-hidden bg-muted sm:aspect-[3/1]">
                            <img :src="event.banner_url ?? ''" :alt="event.title" class="h-full w-full object-cover" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-black/10 to-transparent lg:from-black/35" />
                            <div class="absolute bottom-4 left-4 right-4 flex flex-wrap gap-2 lg:hidden">
                                <span
                                    v-for="cat in toCategoryList(event.category)"
                                    :key="cat"
                                    class="rounded-full bg-white/92 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.1em] text-foreground shadow-sm"
                                >
                                    {{ categoryLabelMap[cat] ?? cat }}
                                </span>
                            </div>
                        </div>

                        <div class="flex min-w-0 flex-col gap-5 p-5 sm:p-7 lg:justify-between lg:p-9">
                            <div class="min-w-0">
                                <div class="mb-4 hidden flex-wrap items-center gap-2 lg:flex">
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

                                <span
                                    :class="[
                                        'mb-3 inline-flex w-fit rounded-full border px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.12em] lg:hidden',
                                        registrationTone,
                                    ]"
                                >
                                    {{ registrationBadgeLabel }}
                                </span>

                                <h1
                                    class="font-display text-foreground text-[1.8rem] font-bold leading-[1.07] tracking-tight text-balance break-words sm:text-[2.35rem] lg:text-5xl lg:leading-[1.04]"
                                >
                                    {{ event.title }}
                                </h1>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                                <div class="flex items-start gap-3 rounded-2xl border border-border/70 bg-muted/20 p-3.5">
                                    <div class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                        <CalendarDays class="size-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Tanggal</p>
                                        <p class="mt-0.5 text-[0.95rem] font-semibold leading-snug text-foreground">{{ formatDate(event.start_date) }}</p>
                                        <p class="text-[0.8rem] leading-relaxed text-muted-foreground">
                                            {{ toCategoryList(event.session).map((s) => sessionLabelMap[s] ?? s).join(', ') }}
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-start gap-3 rounded-2xl border border-border/70 bg-muted/20 p-3.5">
                                    <div class="grid size-9 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                                        <MapPin class="size-4" />
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Lokasi</p>
                                        <p class="mt-0.5 break-words text-[0.95rem] font-semibold leading-snug text-foreground">{{ event.location }}</p>
                                        <p class="text-[0.8rem] leading-relaxed text-muted-foreground">Venue acara</p>
                                    </div>
                                </div>
                            </div>

                            <div class="rounded-2xl border border-primary/15 bg-primary/[0.04] p-4">
                                <div class="mb-2 flex items-end justify-between gap-4">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted-foreground">Kapasitas</p>
                                        <p class="mt-1 text-sm font-semibold text-foreground">
                                            {{ event.registered_count.toLocaleString() }} / {{ event.quota.toLocaleString() }} terdaftar
                                        </p>
                                    </div>
                                    <span class="font-display text-2xl font-bold tabular-nums text-primary">{{ capacityPercent }}%</span>
                                </div>
                                <div class="h-2.5 w-full overflow-hidden rounded-full bg-background">
                                    <div
                                        class="h-full rounded-full bg-primary transition-[width] duration-1000 ease-[cubic-bezier(0.22,1,0.36,1)]"
                                        :style="{ width: capacityPercent + '%' }"
                                    />
                                </div>
                            </div>

                            <Link
                                :href="page.props.auth?.user ? routes.member.event.show(event.slug) : routes.auth.registerWithIntended(routes.member.event.show(event.slug))"
                                class="group inline-flex w-full items-center justify-center gap-3 rounded-2xl border border-primary/15 bg-primary px-5 py-3.5 text-sm font-semibold text-primary-foreground shadow-sm transition-[transform,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px hover:bg-primary/92 active:scale-[0.98]"
                            >
                                {{ page.props.auth?.user ? 'View Details' : 'Register Now' }}
                                <span class="grid size-7 place-items-center rounded-full bg-white/15 transition-transform duration-200 group-hover:translate-x-0.5">
                                    <ArrowRight class="size-4" />
                                </span>
                            </Link>
                        </div>
                    </article>
                </div>

                <div class="mt-8 grid gap-6 pb-14 sm:gap-8 sm:pb-20 lg:grid-cols-3 lg:gap-10">
                    <div
                        :class="[
                            'order-2 transition-all duration-700 lg:order-1 lg:col-span-2',
                            visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                        ]"
                        style="transition-delay: 200ms"
                    >
                        <div class="rounded-[1.5rem] border border-border bg-card p-5 shadow-sm sm:p-7">
                            <h2 class="font-display text-foreground text-[1.45rem] font-bold leading-tight tracking-tight sm:text-3xl">
                                About this event
                            </h2>
                            <div class="prose prose-sm text-muted-foreground mt-4 max-w-none" v-html="event.description" />
                        </div>

                        <div class="mt-6 rounded-[1.5rem] border border-border bg-card p-5 shadow-sm sm:p-7">
                            <h3 class="font-display text-foreground mb-4 text-[1.25rem] font-bold leading-tight tracking-tight sm:text-2xl">
                                What you'll get
                            </h3>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    v-for="item in highlights"
                                    :key="item"
                                    class="border-border bg-muted/15 hover:border-primary/30 flex items-center gap-3 rounded-2xl border p-4 transition-[transform,border-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px"
                                >
                                    <div class="bg-success/10 grid size-7 place-items-center rounded-lg">
                                        <Check class="text-success size-3.5" />
                                    </div>
                                    <span class="text-foreground text-sm font-medium">{{ item }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        :class="[
                            'hidden transition-all duration-700 lg:order-2 lg:block',
                            visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                        ]"
                        style="transition-delay: 300ms"
                    >
                        <div class="flex flex-col gap-6 lg:sticky lg:top-28">
                            <div class="app-surface hidden p-6 lg:block">
                                <h3 class="font-display text-foreground mb-4 text-[1.2rem] font-bold leading-tight tracking-tight">
                                    Registration
                                </h3>
                                <div class="mb-2 flex items-end justify-between">
                                    <span class="font-display text-primary text-2xl font-bold"
                                        >{{ capacityPercent }}%</span
                                    >
                                    <span class="text-muted-foreground text-xs">
                                        {{ event.registered_count.toLocaleString() }} /
                                        {{ event.quota.toLocaleString() }}
                                    </span>
                                </div>
                                <div class="bg-muted h-2.5 w-full overflow-hidden rounded-full">
                                    <div
                                        class="bg-primary h-full rounded-full transition-[width] duration-1000 ease-[cubic-bezier(0.22,1,0.36,1)]"
                                        :style="{ width: capacityPercent + '%' }"
                                    />
                                </div>
                                <Link
                                    :href="page.props.auth?.user ? routes.member.event.show(event.slug) : routes.auth.registerWithIntended(routes.member.event.show(event.slug))"
                                    class="border-primary/15 bg-primary text-primary-foreground hover:bg-primary/92 mt-6 flex w-full items-center justify-center gap-2 rounded-xl border px-6 py-3 text-sm font-semibold shadow-sm transition-[transform,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:-translate-y-px active:scale-[0.98]"
                                >
                                    {{ page.props.auth?.user ? 'View Details' : 'Register Now' }}
                                    <ArrowRight class="size-4" />
                                </Link>
                            </div>

                            <div class="app-surface p-6">
                                <h3 class="font-display text-foreground mb-5 text-[1.2rem] font-bold leading-tight tracking-tight">
                                    Event Details
                                </h3>
                                <div class="flex flex-col gap-4">
                                    <div class="flex items-start gap-3">
                                        <div class="bg-muted/40 grid size-8 shrink-0 place-items-center rounded-lg">
                                            <CalendarDays class="text-muted-foreground size-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="text-muted-foreground text-[11px] font-semibold tracking-[0.14em] uppercase"
                                            >
                                                Date & Time
                                            </p>
                                            <p class="text-foreground text-sm font-semibold">
                                                {{ formatDate(event.start_date) }}
                                            </p>
                                            <p class="text-muted-foreground text-xs">
                                                {{
                                                    toCategoryList(event.session)
                                                        .map((s) => sessionLabelMap[s] ?? s)
                                                        .join(', ')
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="bg-muted/40 grid size-8 shrink-0 place-items-center rounded-lg">
                                            <MapPin class="text-muted-foreground size-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="text-muted-foreground text-[11px] font-semibold tracking-[0.14em] uppercase"
                                            >
                                                Location
                                            </p>
                                            <p class="text-foreground text-sm font-semibold">{{ event.location }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-start gap-3">
                                        <div class="bg-muted/40 grid size-8 shrink-0 place-items-center rounded-lg">
                                            <Shield class="text-muted-foreground size-4" />
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="text-muted-foreground text-[11px] font-semibold tracking-[0.14em] uppercase"
                                            >
                                                Category
                                            </p>
                                            <p class="flex flex-wrap gap-1 text-sm font-semibold">
                                                <span
                                                    v-for="(cat, idx) in toCategoryList(event.category)"
                                                    :key="cat"
                                                    :style="{ color: categoryColorMap[cat] ?? 'var(--foreground)' }"
                                                >
                                                    {{ categoryLabelMap[cat] ?? cat
                                                    }}<span v-if="idx < toCategoryList(event.category).length - 1"
                                                        >,</span
                                                    >
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </LandingLayout>
</template>
