<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Progress } from '@/components/ui/progress';
import { MapPin, CalendarDays, Clock, DollarSign, Users, Send, Mail, MailOpen } from 'lucide-vue-next';
import {
    formatDate,
    formatDateTime,
    statusColorMap,
    categoryLabelMap,
    categoryColorMap,
    sessionLabelMap,
} from '@/lib/dummyData';
import { toCategoryList } from '@/lib/eventCategories';
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue';
import TiptapRichHtml from '@/components/modules/dashboard/events/TiptapRichHtml.vue';

defineOptions({ layout: DashboardLayout });

const props = defineProps<{
    event: IEvent;
    isRegistered: boolean;
    /** Undangan tim/bundle: belum accept/reject — bukan peserta resmi sampai dikonfirmasi */
    pendingTeamInvitationUrl?: string | null;
    registrationStatus: 'pending' | 'accepted' | 'rejected' | null;
    qr_base64: string | null;
    registration_code: string | null;
}>();

const event = props.event;
const isRegistered = computed(() => props.isRegistered);
const registrationStatus = computed(() => props.registrationStatus);

const registrationStatusLabel: Record<IEvent['registration_status'], string> = {
    not_yet_open: 'Segera dibuka',
    open: 'Pendaftaran buka',
    closed: 'Ditutup',
    full: 'Penuh',
};

const myRegistrationLabel: Record<NonNullable<typeof props.registrationStatus>, string> = {
    pending: 'Menunggu kajian',
    accepted: 'Diterima',
    rejected: 'Tidak diterima',
};

const metaBlocks = computed(() => [
    {
        title: 'Jadwal',
        value: `${formatDate(event.start_date)} — ${formatDate(event.end_date)}`,
        icon: CalendarDays,
    },
    { title: 'Lokasi', value: event.location || '—', icon: MapPin },
    {
        title: 'Sesi',
        value:
            toCategoryList(event.session)
                .map((s) => sessionLabelMap[s] ?? s)
                .join(', ') || '—',
        icon: Clock,
    },
    {
        title: 'Biaya',
        value: event.price > 0 ? `Rp ${Number(event.price).toLocaleString('id-ID')}` : 'Gratis',
        icon: DollarSign,
    },
]);

const quotaPercent = computed(() => {
    if (!event.quota || event.quota <= 0) return 0;
    return Math.min(100, Math.round((event.registered_count / event.quota) * 100));
});
</script>

<template>
    <Head :title="event.title" />
    <div class="mx-auto flex w-full flex-col gap-6 pb-6 sm:gap-8">
        <PageHeader
            title="Informasi & pendaftaran"
            subtitle="Ringkasan jadwal, lokasi, dan deskripsi lengkap dari penyelenggara."
        >
        </PageHeader>

        <section
            class="border-border/70 bg-card overflow-hidden rounded-2xl border shadow-[0_1px_0_0_rgba(0,0,0,0.04)] ring-1 ring-black/[0.04] dark:ring-white/[0.06]"
        >
            <div class="flex flex-col lg:grid lg:min-h-[min(26rem,70vh)] lg:grid-cols-2 lg:items-stretch">
                <!-- Kolom konten: permukaan solid, hierarki jelas -->
                <div
                    class="border-border/60 from-card via-card to-muted/25 order-2 flex flex-col justify-center gap-5 border-t bg-gradient-to-b px-5 py-7 sm:gap-6 sm:px-8 sm:py-9 lg:order-none lg:border-t-0 lg:border-r lg:px-10 xl:px-12"
                >
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            v-for="cat in toCategoryList(event.category)"
                            :key="cat"
                            class="border-0 text-[10px] font-semibold text-white shadow-sm"
                            :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                        >
                            {{ categoryLabelMap[cat] ?? cat }}
                        </Badge>
                        <Badge
                            v-if="pendingTeamInvitationUrl"
                            variant="secondary"
                            class="border border-amber-500/50 bg-amber-500/20 text-[10px] font-semibold text-amber-950 dark:text-amber-50"
                        >
                            Diundang · menunggu Anda
                        </Badge>
                        <Badge variant="secondary" class="text-[10px] font-semibold capitalize">
                            {{ registrationStatusLabel[event.registration_status] }}
                        </Badge>
                    </div>
                    <div class="max-w-xl space-y-4">
                        <h1
                            class="font-display text-foreground text-[1.625rem] leading-[1.2] font-bold tracking-[-0.02em] break-words sm:text-3xl lg:text-[2rem] xl:text-[2.25rem]"
                        >
                            {{ event.title }}
                        </h1>
                        <div class="flex flex-col gap-3 text-sm leading-snug sm:text-[15px]">
                            <span class="text-muted-foreground inline-flex items-center gap-2.5 [&>svg]:shrink-0">
                                <CalendarDays class="text-primary size-[1.125rem] opacity-90" aria-hidden="true" />
                                {{ metaBlocks[0].value }}
                            </span>
                            <span class="text-muted-foreground inline-flex items-start gap-2.5 [&>svg]:shrink-0">
                                <MapPin class="text-primary mt-0.5 size-[1.125rem] opacity-90" aria-hidden="true" />
                                <span class="break-words">{{ metaBlocks[1].value }}</span>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Kolom gambar: hanya visual, tanpa gradien/teks di atasnya -->
                <div
                    class="relative order-1 aspect-[16/10] min-h-[13rem] w-full sm:aspect-[2/1] sm:min-h-[15rem] lg:order-none lg:aspect-auto lg:min-h-full lg:min-w-0"
                >
                    <EventBannerImage :src="event.banner_url" :alt="event.title" />
                </div>
            </div>
        </section>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="m in metaBlocks"
                :key="m.title"
                class="group border-border/70 from-card to-muted/10 hover:border-primary/25 flex min-w-0 gap-3 rounded-2xl border bg-gradient-to-b p-4 shadow-sm transition-[border-color,box-shadow] duration-200 hover:shadow-md"
            >
                <div
                    class="bg-primary/10 text-primary ring-primary/15 flex size-10 shrink-0 items-center justify-center rounded-xl ring-1 transition-transform duration-200 group-hover:scale-[1.03] sm:size-11"
                >
                    <component :is="m.icon" class="size-5" stroke-width="2" />
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-muted-foreground text-[10px] font-bold tracking-[0.14em] uppercase">
                        {{ m.title }}
                    </p>
                    <p class="text-foreground mt-1 text-sm leading-snug font-semibold break-words">{{ m.value }}</p>
                </div>
            </div>
        </div>

        <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
            <div class="flex min-w-0 flex-col gap-8">
                <!-- Deskripsi -->
                <Card class="border-border/70 rounded-2xl shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.05]">
                    <CardHeader class="border-border/50 bg-muted/10 border-b px-5 py-4 sm:px-6">
                        <CardTitle class="font-display text-base font-bold tracking-tight sm:text-lg"
                            >Tentang acara</CardTitle
                        >
                    </CardHeader>
                    <CardContent class="px-5 py-6 sm:px-6 sm:py-8">
                        <TiptapRichHtml :html="event.description" />
                    </CardContent>
                </Card>
            </div>

            <!-- Sidebar pendaftaran -->
            <aside class="flex min-w-0 flex-col gap-4 xl:sticky xl:top-24">
                <Card
                    class="border-border/70 ring-primary/10 from-card via-card to-primary/[0.03] rounded-2xl bg-gradient-to-b shadow-md ring-1"
                >
                    <CardHeader class="space-y-1 pb-2">
                        <CardTitle class="font-display text-sm font-bold sm:text-base">Pendaftaran</CardTitle>
                        <p class="text-muted-foreground text-xs">Kuota & jadwal buka tutup</p>
                    </CardHeader>
                    <CardContent class="space-y-5 pt-0">
                        <div>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="text-muted-foreground flex items-center gap-1.5">
                                    <Users class="size-4 shrink-0" aria-hidden="true" />
                                    Terisi
                                </span>
                                <span class="text-foreground font-semibold tabular-nums">
                                    {{ event.registered_count }}/{{ event.quota }}
                                    <span class="text-muted-foreground ml-1 text-xs font-normal"
                                        >({{ quotaPercent }}%)</span
                                    >
                                </span>
                            </div>
                            <Progress
                                :model-value="event.registered_count"
                                :max="Math.max(event.quota, 1)"
                                class="h-2.5 rounded-full"
                            />
                        </div>
                        <div class="border-border/60 bg-muted/15 space-y-2 rounded-xl border px-3 py-3 text-xs">
                            <p class="flex justify-between gap-2">
                                <span class="text-muted-foreground">Buka</span>
                                <span class="text-foreground text-right font-medium">{{
                                    formatDateTime(event.registration_start)
                                }}</span>
                            </p>
                            <p class="flex justify-between gap-2">
                                <span class="text-muted-foreground">Tutup</span>
                                <span class="text-foreground text-right font-medium">{{
                                    formatDateTime(event.registration_end)
                                }}</span>
                            </p>
                        </div>

                        <div v-if="pendingTeamInvitationUrl" class="flex flex-col gap-3">
                            <p class="text-muted-foreground text-xs leading-relaxed">
                                Anda diundang sebagai anggota tim atau paket pendaftaran. Belum tercatat sebagai peserta
                                hingga Anda menyetujui undangan di tautan berikut.
                            </p>
                            <Button class="h-11 w-full rounded-xl text-[15px] font-semibold shadow-sm" as-child>
                                <Link :href="pendingTeamInvitationUrl">
                                    <MailOpen class="mr-2 size-4" aria-hidden="true" />
                                    Lihat undangan
                                </Link>
                            </Button>
                        </div>
                        <div v-else-if="!isRegistered && event.registration_status === 'open'">
                            <Button class="h-11 w-full rounded-xl text-[15px] font-semibold shadow-sm" as-child>
                                <Link :href="`/events/joined/events/${event.slug}/register`">
                                    <Send class="mr-2 size-4" aria-hidden="true" />
                                    Daftar untuk acara ini
                                </Link>
                            </Button>
                        </div>
                        <div
                            v-else-if="!isRegistered"
                            class="border-border bg-muted/20 text-muted-foreground rounded-xl border border-dashed px-3 py-4 text-center text-xs leading-relaxed"
                        >
                            Pendaftaran belum dibuka atau sudah berakhir.
                        </div>
                        <div v-else class="flex flex-col gap-4">
                            <Button class="w-full rounded-xl" variant="secondary" as-child>
                                <Link :href="`/events/joined/events/${event.slug}/registration`"
                                    >Detail pendaftaran</Link
                                >
                            </Button>
                            <div class="border-success/25 bg-success/5 rounded-xl border p-4 text-center shadow-sm">
                                <p class="text-success text-sm font-bold">Anda terdaftar</p>
                                <Badge
                                    variant="secondary"
                                    class="mt-2 text-[10px] capitalize"
                                    :style="{
                                        color:
                                            registrationStatus != null ? statusColorMap[registrationStatus] : undefined,
                                    }"
                                >
                                    {{
                                        registrationStatus != null
                                            ? myRegistrationLabel[registrationStatus]
                                            : 'Terdaftar'
                                    }}
                                </Badge>
                            </div>

                            <div
                                v-if="registrationStatus === 'accepted'"
                                class="border-border bg-muted/15 grid gap-3 rounded-xl border p-4 2xl:grid-cols-2 2xl:items-start"
                            >
                                <div class="min-w-0 space-y-2">
                                    <p
                                        class="text-muted-foreground flex items-center gap-2 text-[10px] font-black tracking-wider uppercase"
                                    >
                                        <Mail class="text-primary size-3.5 shrink-0" aria-hidden="true" />
                                        Check-in
                                    </p>
                                    <ul
                                        class="text-muted-foreground list-inside list-disc space-y-1 text-[11px] leading-relaxed font-medium"
                                    >
                                        <li>QR dan kode manual juga tampil di halaman ini.</li>
                                        <li>Email penerimaan berisi gambar QR yang sama.</li>
                                        <li>Simpan kode manual jika pemindaian gagal.</li>
                                    </ul>
                                </div>
                                <div class="min-w-0 space-y-2">
                                    <p class="text-muted-foreground text-[10px] font-black tracking-wider uppercase">
                                        Di lokasi
                                    </p>
                                    <p class="text-foreground/85 text-[11px] leading-relaxed font-medium">
                                        Tunjukkan QR di pintu masuk. Panitia dapat memasukkan kode manual.
                                    </p>
                                </div>
                            </div>

                            <div
                                v-else-if="registrationStatus === 'rejected'"
                                class="border-border bg-muted/15 rounded-xl border p-4"
                            >
                                <p class="text-muted-foreground text-[11px] leading-relaxed font-medium">
                                    Keputusan sudah dikirim lewat email. Periksa spam atau hubungi panitia.
                                </p>
                            </div>

                            <div
                                v-if="registrationStatus === 'accepted' && props.qr_base64"
                                class="border-success/30 bg-success/5 flex flex-col items-center gap-3 rounded-xl border p-4 shadow-sm"
                            >
                                <p class="text-success text-[10px] font-bold tracking-wider uppercase">QR check-in</p>
                                <img
                                    :src="`data:image/png;base64,${props.qr_base64}`"
                                    alt="Kode QR kehadiran"
                                    width="240"
                                    height="240"
                                    class="border-border h-auto w-full max-w-[240px] rounded-xl border bg-white p-2 shadow-md"
                                />
                                <div v-if="props.registration_code" class="w-full space-y-1 text-center">
                                    <p class="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">
                                        Kode manual
                                    </p>
                                    <p class="text-foreground font-mono text-lg font-bold tracking-[0.12em]">
                                        {{ props.registration_code }}
                                    </p>
                                </div>
                                <p class="text-muted-foreground max-w-[260px] text-center text-[10px] leading-snug">
                                    Sama dengan di email penerimaan. Beri kode manual jika scan gagal.
                                </p>
                            </div>
                            <div
                                v-else-if="registrationStatus === 'accepted'"
                                class="border-border bg-muted/15 text-muted-foreground rounded-xl border border-dashed p-4 text-center text-[11px]"
                            >
                                QR tidak dimuat. Buka
                                <Link
                                    :href="`/events/joined/events/${event.slug}/registration`"
                                    class="text-primary font-medium underline-offset-4 hover:underline"
                                >
                                    detail pendaftaran
                                </Link>
                                atau gunakan email penerimaan.
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </aside>
        </div>
    </div>
</template>
