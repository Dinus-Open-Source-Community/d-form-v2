<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import EventHero from '@/components/modules/landing/events/EventHero.vue';
import EventHighlight from '@/components/modules/landing/events/EventHighlight.vue';
import EventList from '@/components/modules/landing/events/EventList.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedSeoProps } from '@/types/seo';
import { routes } from '@/lib/routes';

const props = defineProps<{
    events: IEvent[];
}>();

const page = usePage();
const seo = computed(() => (page.props as { seo: SharedSeoProps }).seo);

const listDescription = computed(
    () =>
        'Daftar acara terpublikasi: jelajahi workshop, seminar, dan kompetisi. Daftar sebagai peserta dalam beberapa langkah.',
);

const jsonLd = computed<Record<string, unknown>[]>(() => {
    const base = seo.value.siteUrl;
    const items = props.events.slice(0, 24).map((e, i) => ({
        '@type': 'ListItem',
        position: i + 1,
        item: {
            '@type': 'Event',
            name: e.title,
            url: `${base}${routes.landing.events.show(e.slug)}`,
            startDate: e.start_date,
            location: {
                '@type': 'Place',
                name: e.location,
                address: e.location,
            },
        },
    }));
    return [
        {
            '@context': 'https://schema.org',
            '@type': 'CollectionPage',
            name: 'Acara',
            description: listDescription.value,
            url: `${base}${routes.landing.events.index}`,
            isPartOf: {
                '@type': 'WebSite',
                name: seo.value.siteName,
                url: `${base}${routes.home}`,
            },
        },
        {
            '@context': 'https://schema.org',
            '@type': 'ItemList',
            itemListElement: items,
            numberOfItems: items.length,
        },
    ];
});
</script>

<template>
    <LandingLayout>
        <SeoHead
            title="Acara"
            :description="listDescription"
            :canonical-path="routes.landing.events.index"
            :json-ld="jsonLd"
        />
        <EventHero />
        <EventHighlight :events="events" />
        <EventList :events="events" />
    </LandingLayout>
</template>
