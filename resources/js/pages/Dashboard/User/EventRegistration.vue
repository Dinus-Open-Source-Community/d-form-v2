<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue'
import { CalendarDays, MapPin } from 'lucide-vue-next'
import { formatDate, formatDateTime, statusColorMap } from '@/lib/dummyData'
import { routes } from '@/lib/routes'

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    event: IEvent
    form: { id: string; title: string; registration_mode: 'single' | 'bundle' | 'team' | null } | null
    registration: {
        review_status: 'pending' | 'accepted' | 'rejected'
        submitted_at: string
        reviewed_at: string | null
        registration_code: string | null
        registration_role: 'leader' | 'member' | null
        answers_summary: Record<string, string>
        qr_base64: string | null
    }
}>()

const statusLabels: Record<(typeof props.registration)['review_status'], string> = {
    pending: 'Awaiting review',
    accepted: 'Accepted',
    rejected: 'Not accepted',
}

const participationLabel = computed(() => {
    const mode = props.form?.registration_mode
    const role = props.registration.registration_role
    if (mode === 'bundle') {
        if (role === 'leader') return 'Ketua / pendaftar utama (bundle)'
        if (role === 'member') return 'Peserta bundle'
        return 'Bundle'
    }
    if (mode === 'team') {
        if (role === 'leader') return 'Ketua tim'
        if (role === 'member') return 'Anggota tim'
        return 'Tim'
    }
    if (role === 'leader') return 'Pendaftar utama'
    if (role === 'member') return 'Peserta'
    return null
})

function isFileLink(value: string): boolean {
    return /^https?:\/\//i.test(value) || value.startsWith('/storage/')
}

function isImageFileUrl(value: string): boolean {
    const path = (value.split('?')[0] ?? '').toLowerCase()
    return /\.(jpe?g|png|gif|webp|avif|bmp|svg)$/i.test(path)
}
</script>

<template>
    <Head :title="`Registration — ${props.event.title}`" />

    <div class="flex flex-col gap-6">
        <PageHeader
            title="Your registration"
            :subtitle="props.form?.title ? `${props.form.title} · ${props.event.title}` : props.event.title"
            :back-href="routes.member.event.show(props.event.slug)"
        />

        <div v-if="participationLabel" class="-mt-2 flex flex-wrap items-center gap-2">
            <Badge variant="outline" class="text-[11px] font-medium">{{ participationLabel }}</Badge>
        </div>

        <div class="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
            <div class="relative aspect-video w-full sm:aspect-[3/1]">
                <EventBannerImage :src="props.event.banner_url" :alt="props.event.title" />
            </div>
            <div class="border-t border-border px-4 py-4 sm:px-6">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</p>
                        <Badge
                            variant="secondary"
                            class="mt-1 text-[11px] capitalize"
                            :style="{ color: statusColorMap[props.registration.review_status] }"
                        >
                            {{ statusLabels[props.registration.review_status] }}
                        </Badge>
                    </div>
                    <div class="text-right text-xs text-muted-foreground">
                        <p>
                            Submitted
                            <span class="font-medium text-foreground">{{ formatDateTime(props.registration.submitted_at) }}</span>
                        </p>
                        <p v-if="props.registration.reviewed_at" class="mt-1">
                            Updated
                            <span class="font-medium text-foreground">{{ formatDateTime(props.registration.reviewed_at) }}</span>
                        </p>
                    </div>
                </div>
                <div class="mt-4 grid gap-2 text-sm text-muted-foreground sm:grid-cols-2">
                    <p class="flex items-center gap-2">
                        <CalendarDays class="size-4 shrink-0 text-primary" />
                        {{ formatDate(props.event.start_date) }} — {{ formatDate(props.event.end_date) }}
                    </p>
                    <p class="flex items-center gap-2">
                        <MapPin class="size-4 shrink-0 text-primary" />
                        {{ props.event.location }}
                    </p>
                </div>
            </div>
        </div>

        <Card v-if="Object.keys(props.registration.answers_summary).length > 0" class="rounded-xl border shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-sm font-medium">Your answers</CardTitle>
            </CardHeader>
            <CardContent class="space-y-3 pt-0">
                <div
                    v-for="(value, label) in props.registration.answers_summary"
                    :key="label"
                    class="rounded-lg border border-border/60 bg-muted/15 px-3 py-2"
                >
                    <p class="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">{{ label }}</p>
                    <template v-if="isFileLink(value)">
                        <div class="mt-2 space-y-2">
                            <a
                                :href="value"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                Buka / unduh berkas
                            </a>
                            <div v-if="isImageFileUrl(value)" class="pt-1">
                                <img
                                    :src="value"
                                    alt=""
                                    class="max-h-56 max-w-full rounded-md border border-border bg-background object-contain"
                                    loading="lazy"
                                />
                            </div>
                        </div>
                    </template>
                    <p v-else class="mt-1 text-sm text-foreground">{{ value }}</p>
                </div>
            </CardContent>
        </Card>

        <Card
            v-if="props.registration.review_status === 'accepted' && props.registration.qr_base64"
            class="rounded-xl border border-success/25 bg-success/5 shadow-xs"
        >
            <CardHeader class="pb-3">
                <CardTitle class="text-sm font-medium text-success">Check-in</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col items-center gap-4 pt-0 sm:flex-row sm:items-start">
                <img
                    :src="`data:image/png;base64,${props.registration.qr_base64}`"
                    alt="Attendance QR code"
                    width="240"
                    height="240"
                    class="rounded-xl border border-border bg-white p-2 shadow-sm"
                />
                <div class="w-full max-w-sm space-y-2 text-center sm:text-left">
                    <p v-if="props.registration.registration_code" class="text-sm text-muted-foreground">
                        Manual registration code
                    </p>
                    <p
                        v-if="props.registration.registration_code"
                        class="font-mono text-xl font-bold tracking-[0.12em] text-foreground"
                    >
                        {{ props.registration.registration_code }}
                    </p>
                    <p class="text-xs leading-relaxed text-muted-foreground">
                        Show the QR at the entrance. If scanning fails, give staff your manual code.
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card v-else-if="props.registration.review_status === 'pending'" class="rounded-xl border shadow-xs">
            <CardContent class="py-6 text-center text-sm text-muted-foreground">
                Your registration is being reviewed. Check-in QR and manual code appear here after acceptance.
            </CardContent>
        </Card>

        <div class="flex justify-center pb-4">
            <Link
                :href="routes.member.event.show(props.event.slug)"
                class="text-sm font-medium text-primary underline-offset-4 hover:underline"
            >
                Back to event details
            </Link>
        </div>
    </div>
</template>
