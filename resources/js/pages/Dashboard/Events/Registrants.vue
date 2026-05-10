<script setup lang="ts">
import { reactive } from 'vue';
import { Head } from '@inertiajs/vue3';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import EmptyState from '@/components/modules/dashboard/EmptyState.vue';
import RegistrantsHubHeader from '@/components/modules/dashboard/RegistrantsHubHeader.vue';
import RegistrantsStatCards from '@/components/modules/dashboard/RegistrantsStatCards.vue';
import RegistrantsToolbar from '@/components/modules/dashboard/RegistrantsToolbar.vue';
import RegistrantsPendingBanner from '@/components/modules/dashboard/RegistrantsPendingBanner.vue';
import RegistrantsDataTable from '@/components/modules/dashboard/RegistrantsDataTable.vue';
import { useEventRegistrantsPage } from '@/utils/composables/useEventRegistrantsPage';

defineOptions({ layout: DashboardFocusLayout });

const props = defineProps<{
    event: IEvent;
    registrants: IRegistrant[];
    registrationForm: { id: string; title: string } | null;
}>();

const p = reactive(
    useEventRegistrantsPage({
        event: props.event,
        registrants: props.registrants,
    })
);

const cardShadow = 'shadow-sm';
</script>

<template>
    <Head :title="`Pendaftar — ${props.event.title}`" />

    <div class="app-page flex flex-col gap-6">
        <RegistrantsHubHeader :event="props.event" :card-shadow="cardShadow" />

        <RegistrantsStatCards
            :stat-cards="p.statCards"
            :tone-styles="p.toneStyles"
            :active-status-tab="p.activeStatusTab"
            :card-shadow="cardShadow"
            @select-stat="p.setStatTab"
        />

        <RegistrantsToolbar
            v-model:search-query="p.searchQuery"
            v-model:active-status-tab="p.activeStatusTab"
            :status-counts="p.statusCounts"
        />

        <RegistrantsPendingBanner :pending-count="p.pendingCount" :active-status-tab="p.activeStatusTab" />

        <RegistrantsDataTable
            v-if="p.filteredRegistrants.length > 0"
            :rows="p.filteredRegistrants"
            :card-shadow="cardShadow"
        />

        <EmptyState
            v-else
            title="Tidak ada pendaftar di tampilan ini"
            description="Coba ubah tab status atau kosongkan pencarian. Data muncul setelah ada pengiriman formulir pendaftaran."
            animation-name="errorState"
        />
    </div>
</template>
