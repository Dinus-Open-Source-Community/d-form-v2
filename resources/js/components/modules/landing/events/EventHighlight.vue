<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { MapPin, Users, ArrowRight } from 'lucide-vue-next'
import { routes } from '@/lib/routes'

const props = defineProps<{
    events: IEvent[]
}>()

const visible = ref(false)
onMounted(() => {
    const obs = new IntersectionObserver(
        ([e]) => { if (e?.isIntersecting) { visible.value = true; obs.disconnect() } },
        { threshold: 0.1 },
    )
    const el = document.getElementById('event-highlight')
    if (el) obs.observe(el)
})

const featured = computed(() => props.events.slice(0, 3))

const statusLabel = (s: string) => {
    if (s === 'open') return 'Pendaftaran Dibuka'
    if (s === 'full') return 'Kuota Penuh'
    if (s === 'closed') return 'Ditutup'
    return 'Segera Dibuka'
}

const statusVariant = (s: string) =>
    s === 'open'
        ? 'border-green-500/20 bg-green-500/10 text-green-600'
        : s === 'full'
          ? 'border-amber-500/20 bg-amber-500/10 text-amber-600'
          : 'border-border bg-muted text-muted-foreground'
</script>

<template>
    <section id="event-highlight" class="bg-muted/30 py-24 md:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-10">
            <div
                :class="[
                    'mx-auto mb-14 max-w-2xl text-center transition-all duration-500',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                ]"
            >
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Sorotan</p>
                <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                    Acara yang sedang berlangsung
                </h2>
                <p class="mt-3 mx-auto max-w-lg text-base leading-relaxed text-muted-foreground">
                    Beberapa acara pilihan yang saat ini membuka pendaftaran. Jangan lewatkan kesempatan untuk bergabung.
                </p>
            </div>

            <div v-if="featured.length" class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <Card
                    v-for="(ev, i) in featured"
                    :key="ev.id"
                    :class="[
                        'group overflow-hidden border-border/40 transition-all duration-500 hover:border-primary/25 hover:shadow-sm',
                        visible ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
                    ]"
                    :style="{ transitionDelay: `${100 + i * 80}ms` }"
                >
                    <div class="relative aspect-video w-full overflow-hidden bg-muted sm:aspect-[4/3]">
                        <img
                            v-if="ev.banner_url"
                            :src="ev.banner_url"
                            :alt="ev.title"
                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        />
                        <div v-else class="flex h-full items-center justify-center">
                            <span class="text-3xl font-bold text-muted-foreground/20">{{ ev.title.charAt(0) }}</span>
                        </div>
                        <Badge
                            variant="outline"
                            :class="['absolute top-3 right-3 text-[10px] backdrop-blur-sm', statusVariant(ev.registration_status)]"
                        >
                            {{ statusLabel(ev.registration_status) }}
                        </Badge>
                    </div>

                    <CardContent class="p-5">
                        <h3 class="text-base font-semibold text-foreground line-clamp-2 group-hover:text-primary transition-colors">
                            {{ ev.title }}
                        </h3>

                        <div class="mt-3 flex flex-col gap-1.5 text-xs text-muted-foreground">
                            <span v-if="ev.location" class="flex items-center gap-1.5">
                                <MapPin class="size-3.5 shrink-0" />
                                {{ ev.location }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <Users class="size-3.5 shrink-0" />
                                {{ ev.registered_count }} / {{ ev.quota }} pendaftar
                            </span>
                        </div>

                        <Button
                            as-child
                            variant="outline"
                            size="sm"
                            class="mt-4 h-9 w-full rounded-lg text-xs font-medium"
                        >
                            <a :href="routes.landing.events.show(ev.slug)" class="inline-flex items-center justify-center gap-1.5">
                                Lihat Detail
                                <ArrowRight class="size-3.5" />
                            </a>
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <div v-else class="mx-auto max-w-md rounded-2xl border border-border/40 bg-card p-10 text-center">
                <p class="text-sm text-muted-foreground">Belum ada acara yang tersedia saat ini.</p>
            </div>
        </div>
    </section>
</template>
