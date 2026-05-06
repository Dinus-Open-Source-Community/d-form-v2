<script setup lang="ts">
import { reactive } from 'vue'
import { Head } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import EventShowHeroSection from '@/components/modules/dashboard/EventShowHeroSection.vue'
import EventShowRegistrationPulseCard from '@/components/modules/dashboard/EventShowRegistrationPulseCard.vue'
import EventShowAboutCard from '@/components/modules/dashboard/EventShowAboutCard.vue'
import EventShowRegistrantsPreviewCard from '@/components/modules/dashboard/EventShowRegistrantsPreviewCard.vue'
import EventShowAsideRail from '@/components/modules/dashboard/EventShowAsideRail.vue'
import { TooltipProvider } from '@/components/ui/tooltip'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Download, Upload } from 'lucide-vue-next'
import { useDashboardEventShowPage } from '@/utils/composables/useDashboardEventShowPage'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event: IEvent
    forms: { id: string; title: string }[]
}>()

const p = reactive(useDashboardEventShowPage(props.event, props.forms))
</script>

<template>
    <Head :title="props.event.title" />
    <TooltipProvider :delay-duration="150">
        <div class="flex flex-col gap-8">
            <EventShowHeroSection
                :event="props.event"
                :status-pill="p.statusPill"
                :meta-blocks="p.metaBlocks"
                :card-shadow="p.cardShadow"
            />

            <div class="grid gap-6 lg:grid-cols-3">
                <div class="flex flex-col gap-6 lg:col-span-2">
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
                    :event="props.event"
                    :forms="p.forms"
                    :card-shadow="p.cardShadow"
                    @export-csv="p.handleExport('CSV')"
                    @export-excel="p.handleExport('Excel')"
                    @open-import="p.showImportModal = true"
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

    <Dialog :open="p.showImportModal" @update:open="(v: boolean) => (p.showImportModal = v)">
        <DialogContent class="max-w-md rounded-2xl">
            <DialogHeader>
                <DialogTitle>Import registrants</DialogTitle>
                <DialogDescription>Upload a CSV or Excel file to bring existing registrants into this event.</DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-4 pt-2">
                <div
                    class="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-border px-6 py-10 text-center transition-colors hover:border-primary/50 hover:bg-muted/30"
                    @click="($refs.importInput as HTMLInputElement)?.click()"
                >
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                        <Upload class="size-5" />
                    </div>
                    <p class="mt-3 text-sm font-medium">{{ p.importFile ? p.importFile.name : 'Click to select a file' }}</p>
                    <p class="mt-1 text-xs text-muted-foreground">CSV, XLSX up to 5 MB</p>
                    <input ref="importInput" type="file" accept=".csv,.xlsx,.xls" class="hidden" @change="p.handleImportFileChange" />
                </div>
                <div class="flex items-center justify-between">
                    <Button variant="link" size="sm" class="h-auto p-0 text-xs" @click="toast.info('Downloading template...')">
                        <Download class="mr-1 size-3" />Download template
                    </Button>
                    <Button class="rounded-full" :disabled="!p.importFile" @click="p.handleImport">Import</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
