<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import EventBannerImage from '@/components/core/EventBannerImage.vue';
import FormFieldAnswerDisplay from '@/components/modules/dashboard/FormFieldAnswerDisplay.vue';
import TiptapRichHtml from '@/components/modules/dashboard/events/TiptapRichHtml.vue';
import { eventHeroBannerContainerClass } from '@/lib/eventBannerAspect';
import { CalendarDays, MapPin } from 'lucide-vue-next';
import { statusColorMap } from '@/constants/eventDisplay';
import { formatDateTime } from '@/lib/dateTime';
import { routes } from '@/lib/routes';
import { setTopbar } from '@/hooks/useDashboardTopbar';

defineOptions({ layout: DashboardLayout });

interface BundleParticipant {
    invited_email: string;
    display_name: string;
    review_status: 'pending' | 'accepted' | 'rejected';
    registration_code: string | null;
    qr_base64: string | null;
}

const props = defineProps<{
    event: IEvent;
    form: {
        id: string;
        title: string;
        registration_mode: 'single' | 'bundle' | 'team' | null;
        success_content?: string | null;
    } | null;
    registration: {
        review_status: 'pending' | 'accepted' | 'rejected';
        submitted_at: string;
        reviewed_at: string | null;
        registration_code: string | null;
        registration_role: 'leader' | 'member' | null;
        answers_summary: Record<string, string>;
        qr_base64: string | null;
    };
    bundle_participants?: BundleParticipant[];
}>();

const bundleParticipants = computed(() => props.bundle_participants ?? []);
const eventBannerUrl = computed(() => props.event.banner_url?.trim() ?? '');

const successContent = computed(() => {
    const html = props.form?.success_content;
    if (!html || !html.trim() || html.trim() === '<p></p>') return null;
    return html;
});

const isBundleLeader = computed(
    () => props.form?.registration_mode === 'bundle' && props.registration.registration_role === 'leader'
);

const participantStatusLabels: Record<BundleParticipant['review_status'], string> = {
    pending: 'Menunggu ditinjau',
    accepted: 'Diterima',
    rejected: 'Tidak diterima',
};

const statusLabels: Record<(typeof props.registration)['review_status'], string> = {
    pending: 'Menunggu ditinjau',
    accepted: 'Diterima',
    rejected: 'Tidak diterima',
};

const participationLabel = computed(() => {
    const mode = props.form?.registration_mode;
    const role = props.registration.registration_role;
    if (mode === 'bundle') {
        if (role === 'leader') return 'Ketua / pendaftar utama (bundle)';
        if (role === 'member') return 'Peserta bundle';
        return 'Bundle';
    }
    if (mode === 'team') {
        if (role === 'leader') return 'Ketua tim';
        if (role === 'member') return 'Anggota tim';
        return 'Tim';
    }
    if (role === 'leader') return 'Pendaftar utama';
    if (role === 'member') return 'Peserta';
    return null;
});

onMounted(() => {
    setTopbar({
        title: props.form?.title ?? props.event.title,
        subtitle: `Registration — ${props.event.title}`,
    });
});
</script>

<template>
    <Head :title="`Registration — ${props.event.title}`" />

    <div class="mx-auto flex w-full max-w-5xl flex-col gap-5 sm:gap-6">
        <div v-if="participationLabel" class="flex flex-wrap items-center gap-2">
            <Badge variant="outline" class="text-[11px] font-medium">{{ participationLabel }}</Badge>
        </div>

        <Card v-if="successContent" class="border-primary/20 bg-primary/5 rounded-xl border shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-sm font-semibold">Informasi</CardTitle>
            </CardHeader>
            <CardContent class="pt-0">
                <TiptapRichHtml :html="successContent" />
            </CardContent>
        </Card>

        <div class="border-border bg-card overflow-hidden rounded-xl border shadow-sm">
            <div
                v-if="eventBannerUrl"
                data-testid="event-registration-banner"
                :class="eventHeroBannerContainerClass('mx-auto h-40 max-h-80 max-w-5xl sm:h-56 md:h-72')"
            >
                <EventBannerImage :src="eventBannerUrl" :alt="props.event.title" />
            </div>
            <div class="border-border border-t px-4 py-4 sm:px-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                            <h2 class="text-foreground min-w-0 text-lg leading-tight font-semibold break-words">
                                {{ props.event.title }}
                            </h2>
                            <Badge
                                variant="secondary"
                                class="shrink-0 text-[11px]"
                                :style="{ color: statusColorMap[props.registration.review_status] }"
                            >
                                {{ statusLabels[props.registration.review_status] }}
                            </Badge>
                        </div>
                        <p v-if="props.form?.title" class="text-muted-foreground mt-1 text-sm break-words">
                            {{ props.form.title }}
                        </p>
                    </div>
                    <div
                        class="text-muted-foreground flex shrink-0 flex-col gap-1 text-left text-xs sm:items-end sm:text-right"
                    >
                        <p class="leading-snug">
                            Dikirim
                            <span class="text-foreground font-medium">{{
                                formatDateTime({ value: props.registration.submitted_at, variant: 'date-time' })
                            }}</span>
                        </p>
                        <p v-if="props.registration.reviewed_at" class="leading-snug">
                            Diperbarui
                            <span class="text-foreground font-medium">{{
                                formatDateTime({ value: props.registration.reviewed_at, variant: 'date-time' })
                            }}</span>
                        </p>
                    </div>
                </div>
                <div class="border-border/60 mt-4 grid min-w-0 gap-x-6 gap-y-3 border-t pt-3 text-sm sm:grid-cols-2">
                    <div class="flex min-w-0 items-start gap-2.5">
                        <CalendarDays class="text-primary mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <div class="min-w-0">
                            <p class="text-muted-foreground text-xs">Tanggal</p>
                            <p class="text-foreground mt-0.5 break-words">
                                {{ formatDateTime({ value: props.event.start_date }) }} —
                                {{ formatDateTime({ value: props.event.end_date }) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex min-w-0 items-start gap-2.5">
                        <MapPin class="text-primary mt-0.5 size-4 shrink-0" aria-hidden="true" />
                        <div class="min-w-0">
                            <p class="text-muted-foreground text-xs">Lokasi</p>
                            <p class="text-foreground mt-0.5 break-words">{{ props.event.location }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Card v-if="Object.keys(props.registration.answers_summary).length > 0" class="rounded-xl border shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-sm font-medium">Jawaban Anda</CardTitle>
            </CardHeader>
            <CardContent class="grid min-w-0 grid-cols-1 gap-3 pt-0 lg:grid-cols-2">
                <div
                    v-for="(value, label) in props.registration.answers_summary"
                    :key="label"
                    class="border-border/60 bg-muted/15 min-w-0 rounded-lg border p-3 sm:p-4"
                >
                    <p class="text-muted-foreground mb-2 text-[11px] font-semibold tracking-wide uppercase">
                        {{ label }}
                    </p>
                    <FormFieldAnswerDisplay :field="null" :value="value" />
                </div>
            </CardContent>
        </Card>

        <Card
            v-if="props.registration.review_status === 'accepted' && props.registration.qr_base64"
            class="border-success/25 bg-success/5 rounded-xl border shadow-xs"
        >
            <CardHeader class="pb-3">
                <CardTitle class="text-success text-sm font-medium">Check-in</CardTitle>
            </CardHeader>
            <CardContent class="flex flex-col items-center gap-4 pt-0 sm:flex-row sm:items-start">
                <img
                    :src="`data:image/png;base64,${props.registration.qr_base64}`"
                    alt="Attendance QR code"
                    width="240"
                    height="240"
                    class="border-border rounded-xl border bg-white p-2 shadow-sm"
                />
                <div class="w-full max-w-sm space-y-2 text-center sm:text-left">
                    <p v-if="props.registration.registration_code" class="text-muted-foreground text-sm">
                        Manual registration code
                    </p>
                    <p
                        v-if="props.registration.registration_code"
                        class="text-foreground font-mono text-xl font-bold tracking-[0.12em]"
                    >
                        {{ props.registration.registration_code }}
                    </p>
                    <p class="text-muted-foreground text-xs leading-relaxed">
                        Show the QR at the entrance. If scanning fails, give staff your manual code.
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card v-else-if="props.registration.review_status === 'pending'" class="rounded-xl border shadow-xs">
            <CardContent class="text-muted-foreground py-6 text-center text-sm">
                Your registration is being reviewed. Check-in QR and manual code appear here after acceptance.
            </CardContent>
        </Card>

        <Card v-if="isBundleLeader && bundleParticipants.length > 0" class="rounded-xl border shadow-xs">
            <CardHeader class="pb-3">
                <CardTitle class="text-sm font-medium">Bundle participants</CardTitle>
            </CardHeader>
            <CardContent class="space-y-4 pt-0">
                <div
                    v-for="(participant, index) in bundleParticipants"
                    :key="`${participant.invited_email}-${index}`"
                    class="border-border/60 bg-muted/15 rounded-lg border px-3 py-3"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-foreground text-sm font-medium">{{ participant.display_name }}</p>
                            <p class="text-muted-foreground mt-0.5 truncate text-xs">{{ participant.invited_email }}</p>
                        </div>
                        <Badge
                            variant="secondary"
                            class="text-[11px] capitalize"
                            :style="{ color: statusColorMap[participant.review_status] }"
                        >
                            {{ participantStatusLabels[participant.review_status] }}
                        </Badge>
                    </div>

                    <div
                        v-if="participant.review_status === 'accepted' && participant.qr_base64"
                        class="border-success/30 bg-success/5 mt-4 flex flex-col items-center gap-3 rounded-xl border p-4 sm:flex-row sm:items-start"
                    >
                        <img
                            :src="`data:image/png;base64,${participant.qr_base64}`"
                            alt="Participant attendance QR code"
                            width="200"
                            height="200"
                            class="border-border rounded-xl border bg-white p-2 shadow-sm"
                        />
                        <div class="w-full max-w-sm space-y-2 text-center sm:text-left">
                            <p v-if="participant.registration_code" class="text-muted-foreground text-sm">
                                Manual registration code
                            </p>
                            <p
                                v-if="participant.registration_code"
                                class="text-foreground font-mono text-lg font-bold tracking-[0.12em]"
                            >
                                {{ participant.registration_code }}
                            </p>
                            <p class="text-muted-foreground text-xs leading-relaxed">
                                Show this QR at the entrance for this participant.
                            </p>
                        </div>
                    </div>

                    <p v-else-if="participant.review_status === 'pending'" class="text-muted-foreground mt-3 text-xs">
                        Check-in QR appears here after this participant is accepted.
                    </p>
                </div>
            </CardContent>
        </Card>

        <div class="flex justify-center pb-4">
            <Link
                :href="routes.member.event.show(props.event.slug)"
                class="text-primary text-sm font-medium underline-offset-4 hover:underline"
            >
                Back to event details
            </Link>
        </div>
    </div>
</template>
