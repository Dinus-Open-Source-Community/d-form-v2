<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import LandingLayout from '@/layouts/LandingLayout.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { routes } from '@/lib/routes'
import { ArrowUpRight, Building2, CalendarX2, Clock3, DoorOpen } from 'lucide-vue-next'

defineOptions({ layout: LandingLayout })

interface QueueIndexSession {
    id: string
    name: string
    division: string
    room: string
    time: string
    board_url: string
}

const props = defineProps<{
    sessions?: QueueIndexSession[] | null
}>()

const sessions = computed<QueueIndexSession[]>(() => (Array.isArray(props.sessions) ? props.sessions : []))

function boardHref(session: QueueIndexSession): string {
    const url = (session.board_url ?? '').trim()
    return url !== '' ? url : routes.recruitment.queue.show(session.id)
}
</script>

<template>
    <Head title="Antrean Interview" />

    <div class="relative">
        <section
            aria-label="Antrean interview"
            class="border-border/30 bg-muted/20 border-b pt-28 pb-8 sm:pt-32 sm:pb-10 lg:pb-12"
        >
            <div class="mx-auto w-full max-w-5xl px-4 text-center sm:px-6 lg:px-10">
                <p class="text-primary text-xs font-semibold tracking-[0.2em] uppercase sm:text-sm">
                    OpenRecruitment DOSCOM
                </p>
                <h1
                    class="font-display text-foreground mt-3 text-2xl font-bold tracking-tight text-balance sm:text-4xl lg:text-5xl"
                >
                    Antrean interview
                </h1>
                <p class="text-muted-foreground mx-auto mt-3 max-w-2xl text-sm leading-relaxed sm:text-base">
                    Pilih sesi untuk membuka papan antrean publik dan pantau nomor yang sedang dipanggil.
                </p>
            </div>
        </section>

        <div class="mx-auto w-full max-w-5xl px-4 pt-8 pb-16 sm:px-6 sm:pt-10 sm:pb-20 lg:px-10 lg:pb-24">
            <template v-if="sessions.length > 0">
                <p class="text-muted-foreground mb-4 text-xs sm:mb-5 sm:text-sm">
                    {{ sessions.length }} sesi antrean tersedia
                </p>

                <ul class="grid gap-4 sm:grid-cols-2 sm:gap-5" role="list">
                    <li v-for="session in sessions" :key="session.id">
                        <Link
                            :href="boardHref(session)"
                            class="group focus-visible:ring-ring/40 block h-full rounded-2xl focus-visible:ring-[3px] focus-visible:outline-none"
                        >
                            <Card
                                class="border-border/70 group-hover:border-primary/30 h-full rounded-2xl transition-all duration-200 group-hover:-translate-y-0.5 group-hover:shadow-md"
                            >
                                <CardContent class="flex h-full flex-col gap-4 p-5 sm:p-6">
                                    <div class="flex items-start gap-3">
                                        <span
                                            class="bg-primary/10 text-primary flex size-10 shrink-0 items-center justify-center rounded-xl"
                                            aria-hidden="true"
                                        >
                                            <Building2 class="size-5" />
                                        </span>
                                        <h2
                                            class="font-display text-foreground min-w-0 flex-1 text-base leading-snug font-semibold tracking-tight text-balance sm:text-lg"
                                        >
                                            {{ session.name?.trim() || 'Sesi interview' }}
                                        </h2>
                                    </div>

                                    <Badge
                                        v-if="(session.division ?? '').trim() !== ''"
                                        variant="secondary"
                                        class="w-fit max-w-full"
                                    >
                                        <span class="truncate">{{ session.division }}</span>
                                    </Badge>

                                    <dl class="text-muted-foreground grid gap-2 text-xs sm:text-sm">
                                        <div v-if="(session.room ?? '').trim() !== ''" class="flex items-center gap-2">
                                            <DoorOpen class="size-4 shrink-0 opacity-70" aria-hidden="true" />
                                            <dt class="sr-only">Ruang</dt>
                                            <dd class="truncate">{{ session.room }}</dd>
                                        </div>
                                        <div v-if="(session.time ?? '').trim() !== ''" class="flex items-center gap-2">
                                            <Clock3 class="size-4 shrink-0 opacity-70" aria-hidden="true" />
                                            <dt class="sr-only">Waktu</dt>
                                            <dd class="truncate">{{ session.time }}</dd>
                                        </div>
                                    </dl>

                                    <span class="text-primary mt-auto inline-flex items-center gap-1.5 text-sm font-medium">
                                        Buka papan antrean
                                        <ArrowUpRight
                                            class="size-4 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
                                            aria-hidden="true"
                                        />
                                    </span>
                                </CardContent>
                            </Card>
                        </Link>
                    </li>
                </ul>
            </template>

            <Card v-else class="border-border/70 rounded-2xl">
                <CardContent class="flex flex-col items-center px-6 py-14 text-center sm:py-20">
                    <span
                        class="bg-muted text-muted-foreground mb-5 flex size-14 items-center justify-center rounded-full"
                        aria-hidden="true"
                    >
                        <CalendarX2 class="size-6" />
                    </span>
                    <h2 class="font-display text-foreground text-lg font-semibold tracking-tight sm:text-xl">
                        Belum ada sesi antrean aktif
                    </h2>
                    <p class="text-muted-foreground mt-2 max-w-md text-sm leading-relaxed sm:text-base">
                        Sesi interview akan tampil di sini begitu panitia membuka antrean. Silakan cek kembali beberapa saat lagi.
                    </p>
                    <Button as-child variant="outline" class="mt-6">
                        <Link :href="routes.recruitment.landing">Lihat informasi OpenRecruitment</Link>
                    </Button>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
