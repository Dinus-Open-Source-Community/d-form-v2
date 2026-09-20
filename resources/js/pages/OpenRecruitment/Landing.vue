<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import LandingLayout from '@/layouts/LandingLayout.vue'
import SeoHead from '@/components/seo/SeoHead.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { CalendarRange, CheckCircle2, Users } from 'lucide-vue-next'

interface PeriodSummary {
    id: string
    name: string
    status: string
    status_label: string
    description: string | null
    registration_opens_at: string | null
    registration_closes_at: string | null
    interview_starts_at: string | null
    interview_ends_at: string | null
}

interface DivisionOption {
    id: string
    code: string
    name: string
    description: string | null
}

const props = defineProps<{
    period: PeriodSummary | null
    registration: {
        is_open: boolean
        message: string
    }
    divisions: DivisionOption[]
}>()

const timelineItems = computed(() => {
    if (!props.period) return []

    return [
        {
            label: 'Pendaftaran dibuka',
            value: formatDate(props.period.registration_opens_at),
        },
        {
            label: 'Pendaftaran ditutup',
            value: formatDate(props.period.registration_closes_at),
        },
        {
            label: 'Periode interview',
            value: formatRange(props.period.interview_starts_at, props.period.interview_ends_at),
        },
    ].filter((item) => item.value !== '—')
})

const divisionCountLabel = computed(() => {
    const count = props.divisions.length
    if (count <= 0) {
        return 'Divisi'
    }

    return `${count} Divisi`
})

const divisionSummary = computed(() => {
    const names = props.divisions.map((division) => division.name)
    if (names.length === 0) {
        return 'Pilih divisi utama dan cadangan sesuai minatmu.'
    }

    if (names.length === 1) {
        return `${names[0]} — pilih divisi utama dan cadangan.`
    }

    const head = names.slice(0, -1).join(', ')
    const last = names[names.length - 1]

    return `${head}, dan ${last} — pilih divisi utama dan cadangan.`
})

function formatDate(value: string | null): string {
    if (!value) return '—'
    return new Date(value).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

function formatRange(start: string | null, end: string | null): string {
    if (!start && !end) return '—'
    if (start && end) {
        return `${new Date(start).toLocaleDateString('id-ID')} – ${new Date(end).toLocaleDateString('id-ID')}`
    }

    return formatDate(start ?? end)
}
</script>

<template>
    <LandingLayout>
        <SeoHead
            title="OpenRecruitment DOSCOM"
            :canonical-path="routes.openRecruitment.landing"
        />
        <Head title="OpenRecruitment DOSCOM" />

        <section class="border-b border-border/60 bg-gradient-to-b from-primary/5 via-background to-background">
            <div class="mx-auto max-w-5xl px-4 py-16 md:py-24 lg:px-8">
                <p class="text-primary mb-3 text-sm font-semibold tracking-wide uppercase">
                    DOSCOM OpenRecruitment
                </p>
                <h1 class="text-foreground max-w-3xl text-4xl font-bold tracking-tight md:text-5xl">
                    Bergabung dengan DOSCOM — wujudkan ide lewat divisi impianmu.
                </h1>
                <p class="text-muted-foreground mt-4 max-w-2xl text-lg">
                    {{
                        period?.description
                            ?? 'OpenRecruitment adalah gerbang resmi untuk mahasiswa semester 1–3 yang ingin ikut organisasi DOSCOM.'
                    }}
                </p>

                <div class="mt-8 flex flex-wrap items-center gap-3">
                    <Button v-if="registration.is_open" as-child size="lg">
                        <Link :href="routes.openRecruitment.apply">Daftar sekarang</Link>
                    </Button>
                    <Button v-else disabled size="lg" variant="secondary">
                        Pendaftaran belum dibuka
                    </Button>
                    <Button as-child variant="outline" size="lg">
                        <Link :href="routes.openRecruitment.track.login">Sudah daftar? Portal tracking</Link>
                    </Button>
                    <p v-if="!registration.is_open" class="text-muted-foreground text-sm">
                        {{ registration.message }}
                    </p>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-5xl px-4 py-12 lg:px-8">
            <div class="grid gap-6 md:grid-cols-3">
                <Card class="rounded-2xl border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <Users class="size-5 text-primary" />
                            {{ divisionCountLabel }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-muted-foreground text-sm">
                        {{ divisionSummary }}
                    </CardContent>
                </Card>
                <Card class="rounded-2xl border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <CheckCircle2 class="size-5 text-primary" />
                            Tanpa login
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-muted-foreground text-sm">
                        Isi formulir sebagai guest. Nomor pendaftaran & token tracking dikirim lewat email.
                    </CardContent>
                </Card>
                <Card class="rounded-2xl border-border/70">
                    <CardHeader class="pb-2">
                        <CardTitle class="flex items-center gap-2 text-base">
                            <CalendarRange class="size-5 text-primary" />
                            {{ period?.name ?? 'Periode OpRec' }}
                        </CardTitle>
                    </CardHeader>
                    <CardContent class="text-muted-foreground text-sm">
                        Status:
                        <span class="text-foreground font-medium">
                            {{ period?.status_label ?? 'Belum ada periode' }}
                        </span>
                    </CardContent>
                </Card>
            </div>
        </section>

        <section v-if="divisions.length > 0" class="border-t border-border/60 bg-muted/20">
            <div class="mx-auto max-w-5xl px-4 py-12 lg:px-8">
                <h2 class="text-2xl font-semibold">Divisi yang dibuka</h2>
                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <Card v-for="division in divisions" :key="division.id" class="rounded-2xl border-border/70">
                        <CardContent class="p-5">
                            <p class="font-medium">{{ division.name }}</p>
                            <p v-if="division.description" class="text-muted-foreground mt-1 text-sm">
                                {{ division.description }}
                            </p>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </section>

        <section v-if="timelineItems.length > 0" class="mx-auto max-w-5xl px-4 py-12 lg:px-8">
            <h2 class="text-2xl font-semibold">Timeline</h2>
            <div class="mt-6 space-y-4">
                <div
                    v-for="item in timelineItems"
                    :key="item.label"
                    class="flex flex-col gap-1 rounded-xl border border-border/70 bg-card/60 p-4 sm:flex-row sm:items-center sm:justify-between"
                >
                    <span class="font-medium">{{ item.label }}</span>
                    <span class="text-muted-foreground text-sm">{{ item.value }}</span>
                </div>
            </div>
        </section>
    </LandingLayout>
</template>
