<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import EventHero from '@/components/modules/landing/events/EventHero.vue';
import EventHighlight from '@/components/modules/landing/events/EventHighlight.vue';
import EventList from '@/components/modules/landing/events/EventList.vue';

defineProps<{
    events: IEvent[];
}>();
</script>

<template>
    <LandingLayout>
        <EventHero />
        <EventHighlight :events="events" />
        <EventList :events="events" />
    </LandingLayout>
</template>
