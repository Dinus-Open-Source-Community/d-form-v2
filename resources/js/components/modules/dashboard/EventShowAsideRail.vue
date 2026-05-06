<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import {
    Pencil, Trash2, RotateCcw, Download, Upload, QrCode, FileText, Users, FileSpreadsheet,
} from 'lucide-vue-next'
import { edit as editEvent } from '@/actions/App/Http/Controllers/Dashboard/Events/EventController'

defineProps<{
    event: IEvent
    forms: { id: string; title: string }[]
    cardShadow: string
}>()

defineEmits<{
    exportCsv: []
    exportExcel: []
    openImport: []
    openArchive: []
    openRestore: []
}>()
</script>

<template>
    <aside class="flex flex-col gap-5 lg:sticky lg:top-20 lg:self-start">
        <Card :class="['rounded-2xl border-border/60', cardShadow]">
            <CardHeader class="pb-3">
                <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">Manage event</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-2 pt-0">
                <Button class="w-full justify-start rounded-xl" as-child>
                    <Link :href="editEvent.url(event.id)"><Pencil class="mr-2 size-4" />Edit details</Link>
                </Button>
                <Button variant="outline" class="w-full justify-start rounded-xl" as-child>
                    <Link :href="`/dashboard/events/${event.id}/scan`"><QrCode class="mr-2 size-4" />Check-in scanner</Link>
                </Button>
                <Button variant="outline" class="w-full justify-start rounded-xl" as-child>
                    <Link :href="`/dashboard/events/${event.id}/registrants`"><Users class="mr-2 size-4" />Manage registrants</Link>
                </Button>
            </CardContent>
        </Card>

        <Card :class="['rounded-2xl border-border/60', cardShadow]">
            <CardHeader class="pb-3">
                <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">Data</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-2 pt-0">
                <div class="grid grid-cols-2 gap-2">
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="outline" size="sm" class="rounded-xl" @click="$emit('exportCsv')">
                                <Download class="mr-1.5 size-3.5" />CSV
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Export registrants as CSV</TooltipContent>
                    </Tooltip>
                    <Tooltip>
                        <TooltipTrigger as-child>
                            <Button variant="outline" size="sm" class="rounded-xl" @click="$emit('exportExcel')">
                                <FileSpreadsheet class="mr-1.5 size-3.5" />Excel
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>Export registrants as XLSX</TooltipContent>
                    </Tooltip>
                </div>
                <Button variant="outline" size="sm" class="w-full justify-start rounded-xl" @click="$emit('openImport')">
                    <Upload class="mr-2 size-4" />Import registrants
                </Button>
            </CardContent>
        </Card>

        <Card :class="['rounded-2xl border-border/60', cardShadow]">
            <CardHeader class="pb-3">
                <div class="flex items-center justify-between">
                    <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">Forms</CardTitle>
                    <span class="text-[11px] font-medium tabular-nums text-muted-foreground">{{ forms.length }}</span>
                </div>
            </CardHeader>
            <CardContent class="flex flex-col gap-2 pt-0">
                <Button variant="outline" size="sm" class="w-full justify-start rounded-xl" as-child>
                    <Link :href="`/dashboard/events/${event.id}/forms`"><FileText class="mr-2 size-4" />Manage forms</Link>
                </Button>
                <div v-if="forms.length > 0" class="mt-1 flex flex-col gap-1">
                    <Link
                        v-for="form in forms"
                        :key="form.id"
                        :href="`/dashboard/events/${event.id}/forms/${form.id}`"
                        class="flex items-center gap-2 rounded-xl border border-border/50 bg-muted/30 px-3 py-2 text-xs transition-colors hover:bg-muted/50"
                    >
                        <FileText class="size-3.5 shrink-0 text-muted-foreground" />
                        <span class="truncate font-medium text-foreground">{{ form.title }}</span>
                    </Link>
                </div>
            </CardContent>
        </Card>

        <Card :class="['rounded-2xl border-border/60', cardShadow]">
            <CardHeader class="pb-3">
                <CardTitle class="text-[13px] font-semibold uppercase tracking-[0.1em] text-muted-foreground">Lifecycle</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col gap-2 pt-0">
                <Button
                    v-if="!event.deleted_at"
                    variant="outline"
                    size="sm"
                    class="w-full justify-start rounded-xl border-destructive/20 text-destructive hover:bg-destructive/5 hover:text-destructive"
                    @click="$emit('openArchive')"
                >
                    <Trash2 class="mr-2 size-4" />Archive event
                </Button>
                <Button
                    v-else
                    variant="outline"
                    size="sm"
                    class="w-full justify-start rounded-xl"
                    @click="$emit('openRestore')"
                >
                    <RotateCcw class="mr-2 size-4" />Restore event
                </Button>
                <Separator class="my-1" />
                <p class="px-1 text-[11px] leading-relaxed text-muted-foreground">
                    Archiving hides this event from the public but keeps all registrant data safe. You can restore it anytime.
                </p>
            </CardContent>
        </Card>
    </aside>
</template>
