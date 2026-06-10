<script setup lang="ts">
import { reactive } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import EmptyState from '@/components/modules/dashboard/EmptyState.vue'
import RegistrantsStatCards from '@/components/modules/dashboard/RegistrantsStatCards.vue'
import RegistrantsToolbar from '@/components/modules/dashboard/RegistrantsToolbar.vue'
import RegistrantsPendingBanner from '@/components/modules/dashboard/RegistrantsPendingBanner.vue'
import RegistrantsDataTable from '@/components/modules/dashboard/RegistrantsDataTable.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { CalendarDays, FileStack, Users } from 'lucide-vue-next'
import { formatDate } from '@/lib/dummyData'
import { useEventRegistrantsPage } from '@/utils/composables/useEventRegistrantsPage'
import { routes } from '@/lib/routes'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event: IEvent
    forms: { id: string; title: string }[]
    registrants: IRegistrant[]
}>()

const p = reactive(
    useEventRegistrantsPage({
        event: props.event,
        forms: props.forms,
        registrants: props.registrants,
    }),
)

const backHref = routes.admin.events.show(props.event.id)
</script>

<template>
    <Head :title="`Pendaftar — ${props.event.title}`" />

    <div class="flex flex-col gap-8 md:gap-10">
        <PageHeader
            :title="props.event.title"
            subtitle="Semua pengiriman dari formulir pada acara ini. Filter menurut status review dan formulir, atau gunakan kotak cari."
            :back-href="backHref"
        />

        <Card class="rounded-xl border shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-base font-medium">Ringkasan cepat</CardTitle>
                <CardDescription class="text-sm">
                    Konteks acara dan jumlah data pada halaman ini.
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Tanggal mulai</Label>
                    <p class="flex min-h-10 items-center rounded-md border border-input bg-muted/30 px-3 text-sm text-foreground">
                        {{ formatDate(props.event.start_date) }}
                    </p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Lokasi</Label>
                    <p class="flex min-h-10 items-center rounded-md border border-input bg-muted/30 px-3 text-sm text-foreground">
                        {{ props.event.location }}
                    </p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Pendaftar / kuota</Label>
                    <p class="flex min-h-10 items-center gap-2 rounded-md border border-input bg-muted/30 px-3 text-sm tabular-nums text-foreground">
                        <Users class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        {{ props.event.registered_count }} / {{ props.event.quota }}
                    </p>
                </div>
                <div class="flex flex-col gap-1.5">
                    <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Jumlah formulir</Label>
                    <p class="flex min-h-10 items-center gap-2 rounded-md border border-input bg-muted/30 px-3 text-sm text-foreground">
                        <FileStack class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        {{ props.forms.length === 0 ? 'Belum ada' : props.forms.length === 1 ? '1 formulir' : `${props.forms.length} formulir` }}
                    </p>
                </div>
                <div class="flex flex-col gap-1.5 sm:col-span-2 lg:col-span-1">
                    <Label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Baris pada tabel</Label>
                    <p class="flex min-h-10 items-center gap-2 rounded-md border border-input bg-muted/30 px-3 text-sm tabular-nums text-foreground">
                        <CalendarDays class="size-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                        {{ props.registrants.length.toLocaleString('id-ID') }} pengiriman tercantum
                    </p>
                </div>
            </CardContent>
        </Card>

        <RegistrantsStatCards
            :stat-cards="p.statCards"
            :tone-styles="p.toneStyles"
            :active-status-tab="p.activeStatusTab"
            @select-stat="p.setStatTab"
        />

        <RegistrantsToolbar
            v-model:search-query="p.searchQuery"
            v-model:active-status-tab="p.activeStatusTab"
            v-model:active-form-filter="p.activeFormFilter"
            :status-counts="p.statusCounts"
            :forms="props.forms"
        />

        <RegistrantsPendingBanner :pending-count="p.pendingCount" :active-status-tab="p.activeStatusTab" />

        <RegistrantsDataTable
            v-if="p.filteredRegistrants.length > 0"
            :rows="p.filteredRegistrants"
        />

        <template v-else>
            <EmptyState
                v-if="props.forms.length === 0"
                title="Belum ada formulir"
                description="Buat formulir untuk acara ini terlebih dahulu. Setelah peserta mulai mengirim, daftar akan muncul di sini."
                animation-name="builderEmpty"
                :size="200"
            >
                <Button as-child class="mt-1">
                    <Link :href="routes.admin.events.forms.index(props.event.id)">Buka halaman formulir</Link>
                </Button>
            </EmptyState>

            <EmptyState
                v-else-if="p.registrants.length === 0"
                title="Belum ada pengiriman"
                description="Belum ada data pendaftar. Bagikan tautan formulir; daftar di bawah akan terisi otomatis setelah ada pengiriman."
                animation-name="registrantsWaiting"
                :size="220"
            />

            <EmptyState
                v-else
                title="Tidak ada hasil yang cocok"
                description="Ubah filter status, formulir, atau kosongkan pencarian untuk menampilkan lagi seluruh baris."
                animation-name="emptyData"
                :size="200"
            >
                <Button variant="outline" class="mt-1" type="button" @click="p.clearFilters">
                    Reset filter
                </Button>
            </EmptyState>
        </template>
    </div>
</template>
