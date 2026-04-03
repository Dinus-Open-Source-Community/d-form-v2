<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import HomeHero from '@/components/landing/home/HomeHero.vue';
import HomeStats from '@/components/landing/home/HomeStats.vue';
import HomePreview from '@/components/landing/home/HomePreview.vue';
import HomeSteps from '@/components/landing/home/HomeSteps.vue';
import HomeCTA from '@/components/landing/home/HomeCTA.vue';
</script>

<template>
    <LandingLayout>
        <HomeHero />
        <HomeStats />
        <HomePreview />
        <HomeSteps />
        <HomeCTA />
    </LandingLayout>
</template>
