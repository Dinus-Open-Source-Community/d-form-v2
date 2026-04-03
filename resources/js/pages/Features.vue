<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import FeaturesHero from '@/components/landing/features/FeaturesHero.vue';
import FeaturesGrid from '@/components/landing/features/FeaturesGrid.vue';
import FeaturesHowItWorks from '@/components/landing/features/FeaturesHowItWorks.vue';
import FeaturesIntegrations from '@/components/landing/features/FeaturesIntegrations.vue';
import FeaturesComparison from '@/components/landing/features/FeaturesComparison.vue';
import HomeCTA from '@/components/landing/home/HomeCTA.vue';
</script>

<template>
    <LandingLayout>
        <FeaturesHero />
        <FeaturesGrid />
        <FeaturesHowItWorks />
        <FeaturesIntegrations />
        <FeaturesComparison />
        <HomeCTA />
    </LandingLayout>
</template>
