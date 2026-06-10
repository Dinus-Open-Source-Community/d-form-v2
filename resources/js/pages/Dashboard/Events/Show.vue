<script setup lang="ts">
import { reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import EventShowHeroSection from '@/components/modules/dashboard/EventShowHeroSection.vue'
import EventShowRegistrationPulseCard from '@/components/modules/dashboard/EventShowRegistrationPulseCard.vue'
import EventShowAboutCard from '@/components/modules/dashboard/EventShowAboutCard.vue'
import EventShowRegistrantsPreviewCard from '@/components/modules/dashboard/EventShowRegistrantsPreviewCard.vue'
import EventShowAsideRail from '@/components/modules/dashboard/EventShowAsideRail.vue'
import { TooltipProvider } from '@/components/ui/tooltip'
import { useDashboardEventShowPage } from '@/utils/composables/useDashboardEventShowPage'
import { routes } from '@/lib/routes'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event: IEvent
    forms: { id: string; title: string }[]
    exports: { registrations: string; attendance: string }
}>()

const laporanHref = routes.admin.events.laporan(props.event.id)

const p = reactive(useDashboardEventShowPage(props.event, props.forms))
</script>

<template>
    <Head :title="props.event.title" />
    <TooltipProvider :delay-duration="150">
        <div class="flex min-w-0 flex-col gap-6 pb-6 sm:gap-8">
            <EventShowHeroSection
                :event="props.event"
                :status-pill="p.statusPill"
                :meta-blocks="p.metaBlocks"
                :card-shadow="p.cardShadow"
            />

            <div class="grid min-w-0 gap-5 sm:gap-6 xl:grid-cols-3">
                <div class="order-2 flex min-w-0 flex-col gap-5 sm:gap-6 xl:order-1 xl:col-span-2">
                    <EventShowRegistrationPulseCard
                        :event="props.event"
                        :fill-percent="p.fillPercent"
                        :remaining-seats="p.remainingSeats"
                        :progress-tone="p.progressTone"
                        :card-shadow="p.cardShadow"
                        :format-date-time="p.formatDateTime"
                    />
                    <EventShowAboutCard :event="props.event" :card-shadow="p.cardShadow" :format-date="p.formatDate" />
                    <EventShowRegistrantsPreviewCard
                        :event-id="props.event.id"
                        :total-registrants="p.totalRegistrants"
                        :preview-registrants="p.previewRegistrants"
                        :card-shadow="p.cardShadow"
                    />
                </div>

                <EventShowAsideRail
                    class="order-1 xl:order-2"
                    :event="props.event"
                    :forms="p.forms"
                    :card-shadow="p.cardShadow"
                    :registrations-csv-href="props.exports.registrations"
                    :attendance-csv-href="props.exports.attendance"
                    :laporan-href="laporanHref"
                    @open-archive="p.showDeleteModal = true"
                    @open-restore="p.showRestoreModal = true"
                />
            </div>
        </div>
    </TooltipProvider>

    <ConfirmationModal
        :open="p.showDeleteModal"
        title="Archive this event?"
        description="It will be hidden from the public, but all registrant data stays intact. You can restore it whenever you’re ready."
        confirm-text="Archive"
        variant="destructive"
        :loading="p.isDeleting"
        @confirm="p.handleDelete"
        @cancel="p.showDeleteModal = false"
        @update:open="(v: boolean) => (p.showDeleteModal = v)"
    />
    <ConfirmationModal
        :open="p.showRestoreModal"
        title="Restore this event?"
        description="It will become visible again and accept registrations based on its current schedule."
        confirm-text="Restore"
        :loading="p.isRestoring"
        @confirm="p.handleRestore"
        @cancel="p.showRestoreModal = false"
        @update:open="(v: boolean) => (p.showRestoreModal = v)"
    />
</template>
