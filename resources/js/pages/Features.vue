<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import type { SharedSeoProps } from '@/types/seo';
import FeaturesHero from '@/components/modules/landing/features/FeaturesHero.vue';
import FeaturesGrid from '@/components/modules/landing/features/FeaturesGrid.vue';
import FeaturesHowItWorks from '@/components/modules/landing/features/FeaturesHowItWorks.vue';
import FeaturesIntegrations from '@/components/modules/landing/features/FeaturesIntegrations.vue';
import FeaturesComparison from '@/components/modules/landing/features/FeaturesComparison.vue';
import HomeCTA from '@/components/modules/landing/home/HomeCTA.vue';
import { routes } from '@/lib/routes';

const page = usePage();
const seo = computed(() => (page.props as { seo: SharedSeoProps }).seo);

const featuresDescription = computed(
    () =>
        `${seo.value.siteName}: fitur formulir pendaftaran, manajemen peserta, absensi QR, ekspor data, dan laporan acara.`,
);

const featuresJsonLd = computed<Record<string, unknown>>(() => ({
    '@context': 'https://schema.org',
    '@type': 'WebPage',
    name: 'Fitur',
    description: featuresDescription.value,
    url: `${seo.value.siteUrl}${routes.landing.features}`,
    isPartOf: {
        '@type': 'WebSite',
        name: seo.value.siteName,
        url: `${seo.value.siteUrl}${routes.home}`,
    },
}));
</script>

<template>
    <LandingLayout>
        <SeoHead title="Fitur" :description="featuresDescription" :canonical-path="routes.landing.features" :json-ld="featuresJsonLd" />
        <FeaturesHero />
        <FeaturesGrid />
        <FeaturesHowItWorks />
        <FeaturesIntegrations />
        <FeaturesComparison />
        <HomeCTA />
    </LandingLayout>
</template>
