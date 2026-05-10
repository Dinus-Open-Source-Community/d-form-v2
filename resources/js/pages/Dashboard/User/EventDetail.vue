<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Progress } from '@/components/ui/progress'
import {
    MapPin,
    CalendarDays,
    Clock,
    DollarSign,
    Users,
    Send,
    Mail,
    Server,
    ArrowRight,
    Sparkles,
} from 'lucide-vue-next'
import {
    formatDate,
    formatDateTime,
    statusColorMap,
    categoryLabelMap,
    categoryColorMap,
    sessionLabelMap,
} from '@/lib/dummyData'
import { toCategoryList } from '@/lib/eventCategories'
import EventBannerImage from '@/components/modules/dashboard/EventBannerImage.vue'
import TiptapRichHtml from '@/components/modules/dashboard/events/TiptapRichHtml.vue'

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    event: IEvent
    isRegistered: boolean
    registrationStatus: 'pending' | 'accepted' | 'rejected' | null
    qr_base64: string | null
    registration_code: string | null
}>()

const event = props.event
const isRegistered = computed(() => props.isRegistered)
const registrationStatus = computed(() => props.registrationStatus)

const registrationStatusLabel: Record<IEvent['registration_status'], string> = {
    not_yet_open: 'Segera dibuka',
    open: 'Pendaftaran buka',
    closed: 'Ditutup',
    full: 'Penuh',
}

const myRegistrationLabel: Record<NonNullable<typeof props.registrationStatus>, string> = {
    pending: 'Menunggu kajian',
    accepted: 'Diterima',
    rejected: 'Tidak diterima',
}

const metaBlocks = computed(() => [
    {
        title: 'Jadwal',
        value: `${formatDate(event.start_date)} — ${formatDate(event.end_date)}`,
        icon: CalendarDays,
    },
    { title: 'Lokasi', value: event.location || '—', icon: MapPin },
    {
        title: 'Sesi',
        value: toCategoryList(event.session).map((s) => sessionLabelMap[s] ?? s).join(', ') || '—',
        icon: Clock,
    },
    {
        title: 'Biaya',
        value: event.price > 0 ? `Rp ${Number(event.price).toLocaleString('id-ID')}` : 'Gratis',
        icon: DollarSign,
    },
])

const quotaPercent = computed(() => {
    if (!event.quota || event.quota <= 0) return 0
    return Math.min(100, Math.round((event.registered_count / event.quota) * 100))
})
</script>

<template>
    <Head :title="event.title" />

    <div class="mx-auto flex w-full max-w-6xl flex-col gap-8 xl:max-w-7xl">
        <!-- Hero -->
        <section
            class="relative overflow-hidden rounded-2xl border border-border/70 bg-card shadow-[0_1px_0_0_rgba(0,0,0,0.04)] ring-1 ring-black/[0.04] dark:ring-white/[0.06]"
        >
            <div class="relative aspect-[21/9] min-h-[200px] w-full sm:min-h-[240px] lg:aspect-[2.4/1] lg:min-h-[280px]">
                <EventBannerImage :src="event.banner_url" :alt="event.title" />
                <div
                    class="pointer-events-none absolute inset-0 bg-gradient-to-t from-background via-background/55 to-transparent sm:via-background/35"
                    aria-hidden="true"
                />
                <div
                    class="pointer-events-none absolute inset-0 bg-gradient-to-r from-background/80 via-transparent to-transparent sm:from-background/50"
                    aria-hidden="true"
                />
                <div class="absolute inset-x-0 bottom-0 flex flex-col gap-4 p-5 sm:p-7 lg:p-8">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge
                            v-for="cat in toCategoryList(event.category)"
                            :key="cat"
                            class="border-0 text-[10px] font-semibold text-white shadow-sm"
                            :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                        >
                            {{ categoryLabelMap[cat] ?? cat }}
                        </Badge>
                        <Badge variant="secondary" class="text-[10px] font-semibold capitalize backdrop-blur-sm">
                            {{ registrationStatusLabel[event.registration_status] }}
                        </Badge>
                    </div>
                    <div class="max-w-3xl space-y-2">
                        <h1 class="font-display text-2xl font-bold tracking-[-0.03em] text-foreground sm:text-3xl lg:text-4xl">
                            {{ event.title }}
                        </h1>
                        <p class="max-w-2xl text-sm leading-relaxed text-muted-foreground sm:text-base">
                            {{ metaBlocks[0].value }}
                            <span class="mx-2 text-border">·</span>
                            {{ metaBlocks[1].value }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <PageHeader
            eyebrow="Detail acara"
            title="Informasi & pendaftaran"
            subtitle="Ringkasan jadwal, lokasi, dan deskripsi lengkap dari penyelenggara."
            back-href="/user/dashboard"
        >
            <template #actions>
                <Button v-if="!isRegistered && event.registration_status === 'open'" as-child class="rounded-xl shadow-sm">
                    <Link :href="`/user/dashboard/events/${event.slug}/register`" class="inline-flex items-center gap-2">
                        <Sparkles class="size-4" aria-hidden="true" />
                        Daftar sekarang
                    </Link>
                </Button>
                <Button v-else-if="isRegistered" variant="secondary" as-child class="rounded-xl">
                    <Link :href="`/user/dashboard/events/${event.slug}/registration`" class="inline-flex items-center gap-2">
                        Lihat pendaftaran
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Button>
            </template>
        </PageHeader>

        <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_22rem] xl:items-start">
            <div class="flex min-w-0 flex-col gap-8">
                <!-- Meta grid -->
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div
                        v-for="m in metaBlocks"
                        :key="m.title"
                        class="group flex gap-3 rounded-2xl border border-border/70 bg-gradient-to-b from-card to-muted/10 p-4 shadow-sm transition-[border-color,box-shadow] duration-200 hover:border-primary/25 hover:shadow-md"
                    >
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15 transition-transform duration-200 group-hover:scale-[1.03]"
                        >
                            <component :is="m.icon" class="size-5" stroke-width="2" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-[10px] font-bold uppercase tracking-[0.14em] text-muted-foreground">
                                {{ m.title }}
                            </p>
                            <p class="mt-1 text-sm font-semibold leading-snug text-foreground">{{ m.value }}</p>
                        </div>
                    </div>
                </div>

                <!-- Deskripsi -->
                <Card class="rounded-2xl border-border/70 shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.05]">
                    <CardHeader class="border-b border-border/50 bg-muted/10 px-5 py-4 sm:px-6">
                        <CardTitle class="font-display text-base font-bold tracking-tight sm:text-lg">Tentang acara</CardTitle>
                    </CardHeader>
                    <CardContent class="px-5 py-6 sm:px-6 sm:py-8">
                        <TiptapRichHtml :html="event.description" />
                    </CardContent>
                </Card>
            </div>

            <!-- Sidebar pendaftaran -->
            <aside class="flex flex-col gap-4 xl:sticky xl:top-24">
                <Card
                    class="rounded-2xl border-border/70 shadow-md ring-1 ring-primary/10 bg-gradient-to-b from-card via-card to-primary/[0.03]"
                >
                    <CardHeader class="space-y-1 pb-2">
                        <CardTitle class="font-display text-sm font-bold sm:text-base">Pendaftaran</CardTitle>
                        <p class="text-xs text-muted-foreground">Kuota & jadwal buka tutup</p>
                    </CardHeader>
                    <CardContent class="space-y-5 pt-0">
                        <div>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="flex items-center gap-1.5 text-muted-foreground">
                                    <Users class="size-4 shrink-0" aria-hidden="true" />
                                    Terisi
                                </span>
                                <span class="font-semibold tabular-nums text-foreground">
                                    {{ event.registered_count }}/{{ event.quota }}
                                    <span class="text-muted-foreground ml-1 text-xs font-normal">({{ quotaPercent }}%)</span>
                                </span>
                            </div>
                            <Progress :model-value="event.registered_count" :max="Math.max(event.quota, 1)" class="h-2.5 rounded-full" />
                        </div>
                        <div class="space-y-2 rounded-xl border border-border/60 bg-muted/15 px-3 py-3 text-xs">
                            <p class="flex justify-between gap-2">
                                <span class="text-muted-foreground">Buka</span>
                                <span class="text-right font-medium text-foreground">{{ formatDateTime(event.registration_start) }}</span>
                            </p>
                            <p class="flex justify-between gap-2">
                                <span class="text-muted-foreground">Tutup</span>
                                <span class="text-right font-medium text-foreground">{{ formatDateTime(event.registration_end) }}</span>
                            </p>
                        </div>

                        <div v-if="!isRegistered && event.registration_status === 'open'">
                            <Button class="h-11 w-full rounded-xl text-[15px] font-semibold shadow-sm" as-child>
                                <Link :href="`/user/dashboard/events/${event.slug}/register`">
                                    <Send class="mr-2 size-4" aria-hidden="true" />
                                    Daftar untuk acara ini
                                </Link>
                            </Button>
                        </div>
                        <div
                            v-else-if="!isRegistered"
                            class="rounded-xl border border-dashed border-border bg-muted/20 px-3 py-4 text-center text-xs leading-relaxed text-muted-foreground"
                        >
                            Pendaftaran belum dibuka atau sudah berakhir.
                        </div>
                        <div v-else class="flex flex-col gap-4">
                            <Button class="w-full rounded-xl" variant="secondary" as-child>
                                <Link :href="`/user/dashboard/events/${event.slug}/registration`">Detail pendaftaran</Link>
                            </Button>
                            <div class="rounded-xl border border-success/25 bg-success/5 p-4 text-center shadow-sm">
                                <p class="text-sm font-bold text-success">Anda terdaftar</p>
                                <Badge
                                    variant="secondary"
                                    class="mt-2 text-[10px] capitalize"
                                    :style="{
                                        color: registrationStatus != null ? statusColorMap[registrationStatus] : undefined,
                                    }"
                                >
                                    {{
                                        registrationStatus != null ? myRegistrationLabel[registrationStatus] : 'Terdaftar'
                                    }}
                                </Badge>
                            </div>

                            <div
                                v-if="registrationStatus === 'pending'"
                                class="grid gap-3 rounded-xl border border-border bg-muted/15 p-4 md:grid-cols-2 md:items-start"
                            >
                                <div class="min-w-0 space-y-2">
                                    <p
                                        class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wider text-muted-foreground"
                                    >
                                        <Mail class="size-3.5 shrink-0 text-primary" aria-hidden="true" />
                                        Email
                                    </p>
                                    <ul class="list-inside list-disc space-y-1 text-[11px] font-medium leading-relaxed text-muted-foreground">
                                        <li>Anda akan mendapat konfirmasi bahwa jawaban kami terima.</li>
                                        <li>Jika diterima, email lain berisi QR check-in dan kode manual.</li>
                                        <li class="inline-flex flex-wrap items-center gap-1.5">
                                            <Server class="inline size-3.5 shrink-0 text-foreground/70" aria-hidden="true" />
                                            Jika tidak masuk, pastikan worker antrean jalan (bukan driver <code class="rounded bg-muted px-1">sync</code>).
                                        </li>
                                    </ul>
                                </div>
                                <div class="min-w-0 space-y-2">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">Langkah</p>
                                    <p class="text-[11px] font-medium leading-relaxed text-foreground/85">
                                        Periksa inbox untuk email keputusan. QR check-in hanya setelah diterima.
                                    </p>
                                </div>
                            </div>

                            <div
                                v-else-if="registrationStatus === 'accepted'"
                                class="grid gap-3 rounded-xl border border-border bg-muted/15 p-4 md:grid-cols-2 md:items-start"
                            >
                                <div class="min-w-0 space-y-2">
                                    <p
                                        class="flex items-center gap-2 text-[10px] font-black uppercase tracking-wider text-muted-foreground"
                                    >
                                        <Mail class="size-3.5 shrink-0 text-primary" aria-hidden="true" />
                                        Check-in
                                    </p>
                                    <ul class="list-inside list-disc space-y-1 text-[11px] font-medium leading-relaxed text-muted-foreground">
                                        <li>QR dan kode manual juga tampil di halaman ini.</li>
                                        <li>Email penerimaan berisi gambar QR yang sama.</li>
                                        <li>Simpan kode manual jika pemindaian gagal.</li>
                                    </ul>
                                </div>
                                <div class="min-w-0 space-y-2">
                                    <p class="text-[10px] font-black uppercase tracking-wider text-muted-foreground">Di lokasi</p>
                                    <p class="text-[11px] font-medium leading-relaxed text-foreground/85">
                                        Tunjukkan QR di pintu masuk. Panitia dapat memasukkan kode manual.
                                    </p>
                                </div>
                            </div>

                            <div
                                v-else-if="registrationStatus === 'rejected'"
                                class="rounded-xl border border-border bg-muted/15 p-4"
                            >
                                <p class="text-[11px] font-medium leading-relaxed text-muted-foreground">
                                    Keputusan sudah dikirim lewat email. Periksa spam atau hubungi panitia.
                                </p>
                            </div>

                            <div
                                v-if="registrationStatus === 'accepted' && props.qr_base64"
                                class="flex flex-col items-center gap-3 rounded-xl border border-success/30 bg-success/5 p-4 shadow-sm"
                            >
                                <p class="text-[10px] font-bold uppercase tracking-wider text-success">QR check-in</p>
                                <img
                                    :src="`data:image/png;base64,${props.qr_base64}`"
                                    alt="Kode QR kehadiran"
                                    width="240"
                                    height="240"
                                    class="rounded-xl border border-border bg-white p-2 shadow-md"
                                />
                                <div v-if="props.registration_code" class="w-full space-y-1 text-center">
                                    <p class="text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">Kode manual</p>
                                    <p class="font-mono text-lg font-bold tracking-[0.12em] text-foreground">
                                        {{ props.registration_code }}
                                    </p>
                                </div>
                                <p class="max-w-[260px] text-center text-[10px] leading-snug text-muted-foreground">
                                    Sama dengan di email penerimaan. Beri kode manual jika scan gagal.
                                </p>
                            </div>
                            <div
                                v-else-if="registrationStatus === 'accepted'"
                                class="rounded-xl border border-dashed border-border bg-muted/15 p-4 text-center text-[11px] text-muted-foreground"
                            >
                                QR tidak dimuat. Buka
                                <Link
                                    :href="`/user/dashboard/events/${event.slug}/registration`"
                                    class="font-medium text-primary underline-offset-4 hover:underline"
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
