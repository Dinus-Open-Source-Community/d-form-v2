<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import EventHero from '@/components/landing/events/EventHero.vue';
import EventHighlight from '@/components/landing/events/EventHighlight.vue';
import EventList from '@/components/landing/events/EventList.vue';
</script>

<template>
    <LandingLayout>
        <EventHero />
        <EventHighlight />
        <EventList />
    </LandingLayout>
</template>
