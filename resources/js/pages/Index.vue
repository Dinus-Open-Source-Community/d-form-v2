<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import HomeHero from '@/components/modules/landing/home/HomeHero.vue';
import HomeSteps from '@/components/modules/landing/home/HomeSteps.vue';
import HomeFeatures from '@/components/modules/landing/home/HomeFeatures.vue';
import HomeShowcase from '@/components/modules/landing/home/HomeShowcase.vue';
import HomeFAQ from '@/components/modules/landing/home/HomeFAQ.vue';
import HomeCTA from '@/components/modules/landing/home/HomeCTA.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedSeoProps } from '@/types/seo';
import { routes } from '@/lib/routes';

const page = usePage();
const seo = computed(() => (page.props as { seo: SharedSeoProps }).seo);

const jsonLd = computed<Record<string, unknown>[]>(() => {
    const base = seo.value.siteUrl;
    const name = seo.value.siteName;
    const desc = seo.value.defaultDescription;
    return [
        {
            '@context': 'https://schema.org',
            '@type': 'WebSite',
            name,
            url: `${base}${routes.home}`,
            description: desc,
            inLanguage: 'id-ID',
        },
        {
            '@context': 'https://schema.org',
            '@type': 'Organization',
            name,
            url: `${base}${routes.home}`,
            description: desc,
        },
    ];
});
</script>

<template>
    <LandingLayout>
        <SeoHead
            title="Kelola acara & formulir pendaftaran"
            :canonical-path="routes.home"
            :json-ld="jsonLd"
        />
        <HomeHero />
        <HomeSteps />
        <HomeFeatures />
        <HomeShowcase />
        <HomeFAQ />
        <HomeCTA />
    </LandingLayout>
</template>
