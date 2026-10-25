<script setup lang="ts">
import { reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import EventShowHeroSection from '@/components/modules/dashboard/EventShowHeroSection.vue'
import EventShowRegistrationPulseCard from '@/components/modules/dashboard/EventShowRegistrationPulseCard.vue'
import EventShowAboutCard from '@/components/modules/dashboard/EventShowAboutCard.vue'
import EventShowAsideRail from '@/components/modules/dashboard/EventShowAsideRail.vue'
import { TooltipProvider } from '@/components/ui/tooltip'
import { useDashboardEventShowPage } from '@/utils/composables/useDashboardEventShowPage'
import { routes } from '@/lib/routes'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event: IEvent
    forms: { id: string; title: string }[]
}>()

const laporanHref = routes.admin.events.laporan(props.event.id)

const p = reactive(useDashboardEventShowPage(props.event, props.forms))
</script>

<template>
    <Head :title="props.event.title" />
    <TooltipProvider :delay-duration="150">
        <div class="flex min-w-0 flex-col gap-6 pb-6 sm:gap-8">
            <!-- Mobile: urutan tetap hero → aksi → pulse → tentang (wrapper `contents`). md ke atas dua kolom:
                 tablet 3/5 + 2/5 (sidebar 1024px memakan 260px, kolom 1/3 hanya ~215px), xl 2/3 + 1/3.
                 Pulse sebaris dengan hero, aksi & tentang di baris berikutnya. -->
            <div class="flex min-w-0 flex-col gap-5 sm:gap-6 md:grid md:grid-cols-5 md:gap-6 xl:grid-cols-3">
                <div class="contents md:col-span-3 md:flex md:min-w-0 md:flex-col md:gap-6 xl:col-span-2">
                    <EventShowHeroSection
                        class="max-md:order-1"
                        :event="props.event"
                        :status-pill="p.statusPill"
                        :date-range="p.dateRange"
                        :session-label="p.sessionLabel"
                        :price-label="p.priceLabel"
                        :card-shadow="p.cardShadow"
                    />
                    <EventShowAboutCard class="max-md:order-4" :event="props.event" :card-shadow="p.cardShadow" />
                </div>

                <div class="contents md:col-span-2 md:flex md:min-w-0 md:flex-col md:gap-6 xl:col-span-1">
                    <EventShowAsideRail
                        class="order-2"
                        :event="props.event"
                        :forms="p.forms"
                        :card-shadow="p.cardShadow"
                        :laporan-href="laporanHref"
                        @open-archive="p.showDeleteModal = true"
                        @open-restore="p.showRestoreModal = true"
                    />
                    <EventShowRegistrationPulseCard
                        class="max-md:order-3 md:order-1"
                        :event="props.event"
                        :fill-percent="p.fillPercent"
                        :remaining-seats="p.remainingSeats"
                        :progress-tone="p.progressTone"
                        :card-shadow="p.cardShadow"
                    />
                </div>
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
