<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { FileText, ChevronRight, Lock, AlertCircle } from 'lucide-vue-next'
import type { FormAccessStatus } from '@/types/form'

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    event: IEvent
    forms: Array<{
        id: string
        title: string
        description: string | null
        fill_url: string
        access_status: FormAccessStatus
        access_message: string
        can_start: boolean
    }>
}>()

const backHref = computed(() => `/events/joined/events/${props.event.slug}`)

function statusBadgeVariant(s: FormAccessStatus): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (s === 'allowed') return 'default'
    if (s === 'already_submitted') return 'secondary'
    if (s === 'event_form_already_chosen') return 'outline'
    return 'outline'
}

function statusLabel(s: FormAccessStatus): string {
    const map: Record<FormAccessStatus, string> = {
        allowed: 'Tersedia',
        already_submitted: 'Sudah mengirim',
        event_form_already_chosen: 'Form lain dipilih',
        not_visible: 'Tidak tersedia',
        form_closed: 'Ditutup',
        registration_not_open: 'Pendaftaran tutup',
        quota_full: 'Kuota penuh',
        unsupported_registration_mode: 'Tidak didukung',
        pending_team_confirmation: 'Menunggu konfirmasi tim',
        invitation_closed: 'Undangan tidak aktif',
    }
    return map[s] ?? s
}
</script>

<template>
    <Head :title="`Pilih formulir — ${event.title}`" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-6 sm:gap-8 xl:max-w-7xl">
        <PageHeader :title="event.title" subtitle="Pilih satu formulir pendaftaran" :back-href="backHref">
            <template #actions>
                <Badge variant="outline" class="text-[10px]">1 form / peserta</Badge>
            </template>
        </PageHeader>

        <div
            class="flex w-full gap-3 rounded-xl border border-primary/20 bg-primary/5 p-4 text-sm text-foreground/90 dark:bg-primary/10 sm:p-5"
            role="status"
        >
            <AlertCircle class="mt-0.5 size-5 shrink-0 text-primary" aria-hidden="true" />
            <div class="min-w-0 flex-1 space-y-1">
                <p class="font-semibold leading-snug">Anda hanya bisa mengirim satu formulir untuk acara ini.</p>
                <p class="text-muted-foreground leading-relaxed">
                    Baca judul dan deskripsi tiap form, lalu mulai mengisi yang paling sesuai. Setelah terkirim, form
                    lain akan terkunci.
                </p>
            </div>
        </div>

        <ul class="flex w-full flex-col gap-4">
            <li v-for="form in forms" :key="form.id" class="w-full">
                <Card
                    class="w-full overflow-hidden rounded-2xl border-border/80 shadow-sm transition-shadow"
                    :class="form.can_start ? 'hover:border-primary/30 hover:shadow-md' : 'opacity-95'"
                >
                    <CardHeader class="space-y-2 pb-2 sm:space-y-3 sm:pb-3">
                        <div class="flex w-full flex-col gap-4 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
                            <div class="flex min-w-0 flex-1 gap-3">
                                <div
                                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-muted/80 text-primary"
                                >
                                    <FileText class="size-5" aria-hidden="true" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <CardTitle class="font-display text-base leading-snug sm:text-lg">{{
                                        form.title
                                    }}</CardTitle>
                                    <Badge
                                        :variant="statusBadgeVariant(form.access_status)"
                                        class="mt-2 text-[10px] font-semibold capitalize"
                                    >
                                        {{ statusLabel(form.access_status) }}
                                    </Badge>
                                </div>
                            </div>
                            <div class="flex w-full shrink-0 sm:w-auto sm:justify-end">
                                <Button v-if="form.can_start" as-child class="w-full rounded-xl sm:w-auto">
                                    <Link :href="form.fill_url" :prefetch="false" class="justify-center">
                                        Isi formulir
                                        <ChevronRight class="ml-1 size-4" />
                                    </Link>
                                </Button>
                                <Button
                                    v-else-if="form.access_status === 'already_submitted'"
                                    variant="secondary"
                                    as-child
                                    class="w-full rounded-xl sm:w-auto"
                                >
                                    <Link :href="`/events/joined/events/${event.slug}/registration`" class="justify-center">
                                        Lihat pendaftaran
                                    </Link>
                                </Button>
                                <div
                                    v-else
                                    class="text-muted-foreground flex w-full items-center justify-center gap-1.5 rounded-lg border border-dashed bg-muted/30 px-3 py-2.5 text-xs font-medium sm:w-auto sm:justify-end sm:border-0 sm:bg-transparent sm:py-0"
                                >
                                    <Lock class="size-3.5 shrink-0" aria-hidden="true" />
                                    Tidak dapat dipilih
                                </div>
                            </div>
                        </div>
                        <CardDescription
                            v-if="form.description?.trim()"
                            class="text-pretty text-sm leading-relaxed"
                        >
                            {{ form.description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent v-if="!form.can_start && form.access_message" class="border-t bg-muted/20 pt-4 pb-4 sm:px-6">
                        <p class="text-muted-foreground text-xs leading-relaxed sm:text-sm">
                            {{ form.access_message }}
                        </p>
                    </CardContent>
                </Card>
            </li>
        </ul>
    </div>
</template>
