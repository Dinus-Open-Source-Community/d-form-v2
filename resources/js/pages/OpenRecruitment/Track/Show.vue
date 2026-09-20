<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import FormFillLayout from '@/layouts/FormFillLayout.vue';
import OpRecFeedbackForm from '@/components/modules/open-recruitment/OpRecFeedbackForm.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { routes } from '@/lib/routes';
import {
    ArrowRight,
    CalendarClock,
    CheckCircle2,
    ChevronDown,
    Circle,
    CircleDot,
    LogOut,
    MapPin,
    MessageSquare,
    Pencil,
    QrCode,
    Users,
} from 'lucide-vue-next';

defineOptions({ layout: FormFillLayout });

interface TimelineItem {
    key: string;
    label: string;
    status: 'completed' | 'current' | 'upcoming';
    note?: string | null;
}

interface NextAction {
    tone: 'info' | 'warning' | 'success' | 'neutral';
    title: string;
    description: string;
    action: string | null;
}

interface TrackingPayload {
    application: {
        registration_number: string;
        full_name: string;
        nim: string;
        semester: number;
        stage: string;
        stage_label: string;
        result: string;
        result_label: string;
        revision_required: boolean;
        primary_division: string | null;
        secondary_division: string | null;
        submitted_at: string | null;
    };
    period: { name: string | null };
    next_action: NextAction;
    timeline: TimelineItem[];
    interview: {
        scheduled_at: string;
        location: string;
        room: string;
        status: string;
        status_label: string;
    } | null;
    queue: {
        queue_number: number;
        status: string;
        status_label?: string;
    } | null;
    attendance: {
        checked_in_at: string;
        method: string;
    } | null;
    attendance_qr_base64: string | null;
    final: {
        membership_type: string | null;
        final_division: string | null;
        public_message: string | null;
        result: string;
        result_label: string;
    } | null;
    edit: {
        can_edit: boolean;
        can_request_correction: boolean;
        latest_correction: {
            id: string;
            status: string;
            status_label: string;
            request_message: string;
            review_notes: string | null;
        } | null;
    };
    feedback: {
        can_submit: boolean;
        submitted: boolean;
        submitted_at: string | null;
    };
}

const props = defineProps<{
    tracking: TrackingPayload;
    logoutUrl: string;
    editUrl: string;
    correctionUrl: string;
    feedbackStoreUrl: string;
}>();

const correctionModalOpen = ref(false);
const feedbackExpanded = ref(props.tracking.feedback.can_submit);
const interviewSectionRef = ref<HTMLElement | null>(null);

const correctionForm = useForm({
    request_message: '',
});

const heroToneClass = computed(() => {
    const tone = props.tracking.next_action.tone;
    if (tone === 'warning') return 'border-amber-200 bg-amber-50 text-amber-950';
    if (tone === 'success') return 'border-emerald-200 bg-emerald-50 text-emerald-950';
    if (tone === 'neutral') return 'border-border/70 bg-muted/40';
    return 'border-primary/20 bg-primary/5';
});

const interviewSchedule = computed(() => {
    if (!props.tracking.interview?.scheduled_at) return null;
    return new Date(props.tracking.interview.scheduled_at).toLocaleString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
});

const showInterviewSection = computed(
    () =>
        props.tracking.interview !== null ||
        props.tracking.attendance_qr_base64 !== null ||
        props.tracking.attendance !== null ||
        props.tracking.queue !== null
);

function timelineIcon(status: TimelineItem['status']) {
    if (status === 'completed') return CheckCircle2;
    if (status === 'current') return CircleDot;
    return Circle;
}

function scrollToInterview() {
    interviewSectionRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function handleHeroAction() {
    const action = props.tracking.next_action.action;
    if (action === 'edit') {
        router.visit(props.editUrl);
        return;
    }
    if (action === 'feedback') {
        feedbackExpanded.value = true;
        nextTick(() => {
            document.getElementById('feedback-section')?.scrollIntoView({ behavior: 'smooth' });
        });
        return;
    }
    if (action === 'qr' || action === 'interview' || action === 'queue') {
        scrollToInterview();
    }
}

function logout() {
    router.post(props.logoutUrl);
}

function submitCorrection() {
    correctionForm.post(props.correctionUrl, {
        preserveScroll: true,
        onSuccess: () => {
            correctionModalOpen.value = false;
            correctionForm.reset();
        },
    });
}
</script>

<template>
    <Head title="Portal OpRec" />

    <div class="mx-auto max-w-2xl space-y-5 px-2 pb-10">
        <!-- Header identitas ringkas -->
        <div class="flex items-start justify-between gap-3 pt-2">
            <div class="min-w-0">
                <p class="text-primary text-xs font-semibold tracking-wide uppercase">Portal OpRec</p>
                <h1 class="truncate text-xl font-bold tracking-tight">{{ tracking.application.full_name }}</h1>
                <div class="mt-1.5 flex flex-wrap items-center gap-2">
                    <p class="text-muted-foreground font-mono text-xs">
                        {{ tracking.application.registration_number }}
                    </p>
                    <Badge variant="secondary">{{ tracking.application.stage_label }}</Badge>
                </div>
            </div>
            <Button variant="ghost" size="icon" class="shrink-0" title="Keluar" aria-label="Keluar" @click="logout">
                <LogOut class="size-4" />
            </Button>
        </div>

        <!-- Langkah selanjutnya -->
        <div class="rounded-2xl border p-5 shadow-sm" :class="heroToneClass">
            <p class="text-xs font-medium tracking-wide uppercase opacity-80">Langkah selanjutnya</p>
            <h2 class="mt-1 text-lg leading-snug font-semibold">{{ tracking.next_action.title }}</h2>
            <p class="mt-1.5 text-sm leading-relaxed opacity-90">{{ tracking.next_action.description }}</p>
            <div v-if="tracking.next_action.action" class="mt-3">
                <Button
                    size="sm"
                    :variant="tracking.next_action.tone === 'warning' ? 'default' : 'secondary'"
                    @click="handleHeroAction"
                >
                    {{
                        tracking.next_action.action === 'edit'
                            ? 'Edit pendaftaran'
                            : tracking.next_action.action === 'feedback'
                              ? 'Isi feedback'
                              : tracking.next_action.action === 'qr'
                                ? 'Lihat QR absensi'
                                : 'Lihat detail interview'
                    }}
                    <ArrowRight class="ml-1.5 size-4" />
                </Button>
            </div>
        </div>

        <!-- Aksi cepat -->
        <div v-if="tracking.edit.can_edit || tracking.edit.can_request_correction" class="flex flex-wrap gap-2">
            <Button v-if="tracking.edit.can_edit" as-child size="sm">
                <Link :href="editUrl">
                    <Pencil class="mr-1.5 size-4" />
                    Edit data
                </Link>
            </Button>
            <Button
                v-if="tracking.edit.can_request_correction"
                variant="outline"
                size="sm"
                @click="correctionModalOpen = true"
            >
                Ajukan koreksi
            </Button>
        </div>

        <!-- Koreksi pending -->
        <p
            v-if="tracking.edit.latest_correction?.status === 'pending'"
            class="text-muted-foreground rounded-lg border border-dashed px-3 py-2 text-xs"
        >
            Koreksi: {{ tracking.edit.latest_correction.status_label }} —
            {{ tracking.edit.latest_correction.request_message }}
        </p>

        <!-- Hari-H interview: satu kartu event kohesif -->
        <section v-if="showInterviewSection" ref="interviewSectionRef" id="interview-section" class="scroll-mt-24">
            <Card class="border-border/70 rounded-2xl">
                <CardHeader class="pb-3">
                    <CardTitle class="flex items-center gap-2 text-base">
                        <CalendarClock class="text-primary size-4" />
                        Hari-H interview
                    </CardTitle>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div v-if="tracking.interview" class="space-y-1.5 text-sm">
                        <p v-if="interviewSchedule" class="font-medium">{{ interviewSchedule }}</p>
                        <p class="text-muted-foreground flex items-start gap-2">
                            <MapPin class="mt-0.5 size-4 shrink-0" />
                            <span>{{ tracking.interview.location }} · Ruang {{ tracking.interview.room }}</span>
                        </p>
                        <p class="text-muted-foreground text-xs">Status: {{ tracking.interview.status_label }}</p>
                    </div>

                    <div v-if="tracking.queue">
                        <Separator v-if="tracking.interview" class="mb-4" />
                        <p class="text-muted-foreground flex items-center gap-1.5 text-xs">
                            <Users class="size-3.5" />
                            Nomor antrean
                        </p>
                        <p class="mt-0.5 text-3xl font-bold tabular-nums">#{{ tracking.queue.queue_number }}</p>
                        <p class="text-muted-foreground text-sm">
                            {{ tracking.queue.status_label ?? tracking.queue.status }}
                        </p>
                    </div>

                    <div v-if="tracking.attendance_qr_base64 && !tracking.attendance">
                        <Separator v-if="tracking.interview || tracking.queue" class="mb-4" />
                        <div class="space-y-2 text-center">
                            <p class="flex items-center justify-center gap-1.5 text-xs font-medium">
                                <QrCode class="size-3.5" />
                                QR absensi
                            </p>
                            <img
                                :src="`data:image/png;base64,${tracking.attendance_qr_base64}`"
                                alt="QR code absensi"
                                class="mx-auto size-48 rounded-xl border bg-white p-2"
                            />
                            <p class="text-muted-foreground text-xs leading-relaxed">
                                Tunjukkan ke panitia — tidak perlu check-in sendiri.
                            </p>
                        </div>
                    </div>

                    <p v-if="tracking.attendance" class="text-muted-foreground text-xs">
                        Check-in:
                        {{
                            tracking.attendance.checked_in_at
                                ? new Date(tracking.attendance.checked_in_at).toLocaleString('id-ID')
                                : '—'
                        }}
                    </p>
                </CardContent>
            </Card>
        </section>

        <!-- Alur proses: selalu terlihat -->
        <Card class="border-border/70 rounded-2xl">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Alur proses</CardTitle>
            </CardHeader>
            <CardContent>
                <ol
                    class="before:bg-border relative space-y-4 before:absolute before:top-2 before:bottom-2 before:left-[7px] before:w-px"
                >
                    <li v-for="item in tracking.timeline" :key="item.key" class="relative flex gap-2.5">
                        <component
                            :is="timelineIcon(item.status)"
                            class="bg-card relative z-10 mt-0.5 size-4 shrink-0"
                            :class="{
                                'text-primary': item.status === 'current',
                                'text-emerald-600': item.status === 'completed',
                                'text-muted-foreground/40': item.status === 'upcoming',
                            }"
                        />
                        <div class="min-w-0">
                            <p
                                class="text-sm font-medium"
                                :class="item.status === 'upcoming' ? 'text-muted-foreground' : ''"
                            >
                                {{ item.label }}
                            </p>
                            <p v-if="item.note" class="mt-0.5 text-xs text-amber-700">{{ item.note }}</p>
                        </div>
                    </li>
                </ol>
            </CardContent>
        </Card>

        <!-- Data pendaftaran -->
        <Card class="border-border/70 rounded-2xl">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Data pendaftaran</CardTitle>
            </CardHeader>
            <CardContent>
                <dl class="divide-border/60 divide-y text-sm">
                    <div class="flex items-center justify-between gap-4 py-2 first:pt-0 last:pb-0">
                        <dt class="text-muted-foreground shrink-0">Tahap</dt>
                        <dd class="text-right font-medium">{{ tracking.application.stage_label }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-2 first:pt-0 last:pb-0">
                        <dt class="text-muted-foreground shrink-0">Hasil</dt>
                        <dd class="text-right font-medium">{{ tracking.application.result_label }}</dd>
                    </div>
                    <div
                        v-if="tracking.period.name"
                        class="flex items-center justify-between gap-4 py-2 first:pt-0 last:pb-0"
                    >
                        <dt class="text-muted-foreground shrink-0">Periode</dt>
                        <dd class="text-right font-medium">{{ tracking.period.name }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-2 first:pt-0 last:pb-0">
                        <dt class="text-muted-foreground shrink-0">Divisi</dt>
                        <dd class="text-right font-medium">
                            {{ tracking.application.primary_division ?? '—' }}
                            <span
                                v-if="tracking.application.secondary_division"
                                class="text-muted-foreground font-normal"
                            >
                                · cadangan {{ tracking.application.secondary_division }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-2 first:pt-0 last:pb-0">
                        <dt class="text-muted-foreground shrink-0">NIM</dt>
                        <dd class="text-right font-medium">{{ tracking.application.nim }}</dd>
                    </div>
                </dl>
            </CardContent>
        </Card>

        <!-- Keputusan akhir -->
        <Card v-if="tracking.final" class="border-border/70 rounded-2xl">
            <CardHeader class="pb-2">
                <CardTitle class="text-base">Keputusan akhir</CardTitle>
            </CardHeader>
            <CardContent class="space-y-1.5 text-sm">
                <p class="text-lg font-semibold">{{ tracking.final.result_label }}</p>
                <p v-if="tracking.final.membership_type">Keanggotaan: {{ tracking.final.membership_type }}</p>
                <p v-if="tracking.final.final_division">Divisi: {{ tracking.final.final_division }}</p>
                <p v-if="tracking.final.public_message" class="text-muted-foreground pt-1">
                    {{ tracking.final.public_message }}
                </p>
            </CardContent>
        </Card>

        <!-- Feedback inline -->
        <Card
            v-if="tracking.feedback.can_submit || tracking.feedback.submitted"
            id="feedback-section"
            class="border-border/70 scroll-mt-24 rounded-2xl"
        >
            <CardHeader
                class="cursor-pointer pb-2"
                @click="tracking.feedback.can_submit && (feedbackExpanded = !feedbackExpanded)"
            >
                <CardTitle class="flex items-center justify-between text-base">
                    <span class="flex items-center gap-2">
                        <MessageSquare class="size-4" />
                        Feedback
                    </span>
                    <ChevronDown
                        v-if="tracking.feedback.can_submit"
                        class="size-4 transition-transform"
                        :class="feedbackExpanded ? 'rotate-180' : ''"
                    />
                </CardTitle>
            </CardHeader>
            <CardContent v-if="tracking.feedback.submitted" class="text-muted-foreground text-sm">
                Terima kasih! Feedback diterima
                {{
                    tracking.feedback.submitted_at
                        ? new Date(tracking.feedback.submitted_at).toLocaleDateString('id-ID')
                        : ''
                }}.
            </CardContent>
            <CardContent v-else-if="feedbackExpanded">
                <OpRecFeedbackForm :store-url="feedbackStoreUrl" compact />
            </CardContent>
        </Card>

        <p class="text-muted-foreground text-center text-xs">
            <Link :href="routes.recruitment.landing" class="underline-offset-2 hover:underline">
                Info OpenRecruitment
            </Link>
        </p>
    </div>

    <Dialog v-model:open="correctionModalOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Permintaan koreksi</DialogTitle>
                <DialogDescription>Jelaskan data yang perlu diperbaiki.</DialogDescription>
            </DialogHeader>
            <form class="space-y-4" @submit.prevent="submitCorrection">
                <div class="space-y-2">
                    <Label for="request_message">Pesan</Label>
                    <Textarea
                        id="request_message"
                        v-model="correctionForm.request_message"
                        rows="4"
                        required
                        minlength="10"
                        placeholder="Contoh: NIM saya salah ketik..."
                    />
                    <p v-if="correctionForm.errors.request_message" class="text-destructive text-xs">
                        {{ correctionForm.errors.request_message }}
                    </p>
                </div>
                <DialogFooter>
                    <Button type="button" variant="outline" @click="correctionModalOpen = false">Batal</Button>
                    <Button type="submit" :disabled="correctionForm.processing">Kirim</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
