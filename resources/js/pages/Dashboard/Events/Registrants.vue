<script setup lang="ts">
import { reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import EmptyState from '@/components/modules/dashboard/EmptyState.vue'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import RegistrantsHubHeader from '@/components/modules/dashboard/RegistrantsHubHeader.vue'
import RegistrantsStatCards from '@/components/modules/dashboard/RegistrantsStatCards.vue'
import RegistrantsToolbar from '@/components/modules/dashboard/RegistrantsToolbar.vue'
import RegistrantsPendingBanner from '@/components/modules/dashboard/RegistrantsPendingBanner.vue'
import RegistrantsDataTable from '@/components/modules/dashboard/RegistrantsDataTable.vue'
import RegistrantsCardGrid from '@/components/modules/dashboard/RegistrantsCardGrid.vue'
import RegistrantDetailSheet from '@/components/modules/dashboard/RegistrantDetailSheet.vue'
import { TooltipProvider } from '@/components/ui/tooltip'
import { useEventRegistrantsPage } from '@/utils/composables/useEventRegistrantsPage'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event: IEvent
    registrants: IRegistrant[]
}>()

const p = reactive(useEventRegistrantsPage(props))

const cardShadow = 'shadow-sm'

function onApproveFromSheet(): void {
    const r = p.selectedRegistrant
    p.showDetailSheet = false
    if (r) p.startApprove(r)
}

function onRejectFromSheet(): void {
    const r = p.selectedRegistrant
    p.showDetailSheet = false
    if (r) p.startReject(r)
}

function setStatTab(key: 'all' | 'pending' | 'approved' | 'rejected'): void {
    p.activeStatusTab = key
}
</script>

<template>
    <Head :title="`Registrants — ${props.event.title}`" />
    <TooltipProvider :delay-duration="150">
        <div class="flex flex-col gap-7">
            <RegistrantsHubHeader :event="props.event" :card-shadow="cardShadow" />

            <RegistrantsStatCards
                :stat-cards="p.statCards"
                :tone-styles="p.toneStyles"
                :active-status-tab="p.activeStatusTab"
                :card-shadow="cardShadow"
                @select-stat="setStatTab"
            />

            <RegistrantsToolbar
                v-model:search-query="p.searchQuery"
                v-model:active-status-tab="p.activeStatusTab"
                v-model:view-type="p.viewType"
                :status-counts="p.statusCounts"
                @export-click="p.onExportClick"
            />

            <RegistrantsPendingBanner
                :pending-count="p.pendingCount"
                :active-status-tab="p.activeStatusTab"
                @review-pending="p.activeStatusTab = 'pending'"
            />

            <template v-if="p.filteredRegistrants.length > 0">
                <RegistrantsDataTable
                    v-if="p.viewType === 'table'"
                    :rows="p.filteredRegistrants"
                    :card-shadow="cardShadow"
                    @open-detail="p.openDetail"
                    @approve="p.startApprove"
                    @reject="p.startReject"
                />
                <RegistrantsCardGrid
                    v-else
                    :rows="p.filteredRegistrants"
                    @open-detail="p.openDetail"
                    @approve="p.startApprove"
                    @reject="p.startReject"
                />
            </template>

            <EmptyState
                v-else
                title="No registrants match your view"
                description="Try switching tabs or clearing the search. Registrants appear here the moment they submit the form."
                animation-name="errorState"
            />
        </div>
    </TooltipProvider>

    <RegistrantDetailSheet
        v-model:open="p.showDetailSheet"
        :registrant="p.selectedRegistrant"
        @approve="onApproveFromSheet"
        @reject="onRejectFromSheet"
    />

    <ConfirmationModal
        :open="p.showApproveModal"
        title="Approve this registrant?"
        :description="`We’ll mark ${p.actionTarget?.user.name} as approved and send them a confirmation email.`"
        confirm-text="Approve"
        @confirm="p.confirmApprove"
        @cancel="() => (p.showApproveModal = false)"
        @update:open="(v: boolean) => (p.showApproveModal = v)"
    />
    <ConfirmationModal
        :open="p.showRejectModal"
        title="Reject this registrant?"
        :description="`We’ll let ${p.actionTarget?.user.name} know their registration wasn’t accepted this time.`"
        confirm-text="Reject"
        variant="destructive"
        @confirm="p.confirmReject"
        @cancel="() => (p.showRejectModal = false)"
        @update:open="(v: boolean) => (p.showRejectModal = v)"
    />
</template>
