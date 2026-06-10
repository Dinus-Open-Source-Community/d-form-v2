<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { MapPin, Calendar, Users, Search, ArrowRight } from 'lucide-vue-next'
import { routes } from '@/lib/routes'

const props = defineProps<{
    events: IEvent[]
}>()

const visible = ref(false)
const query = ref('')

onMounted(() => {
    const obs = new IntersectionObserver(
        ([e]) => { if (e?.isIntersecting) { visible.value = true; obs.disconnect() } },
        { threshold: 0.05 },
    )
    const el = document.getElementById('event-list')
    if (el) obs.observe(el)
})

const filtered = computed(() => {
    const q = query.value.toLowerCase().trim()
    if (!q) return props.events
    return props.events.filter(
        (ev) =>
            ev.title.toLowerCase().includes(q) ||
            ev.location?.toLowerCase().includes(q),
    )
})

const statusLabel = (s: string) => {
    if (s === 'open') return 'Dibuka'
    if (s === 'full') return 'Penuh'
    if (s === 'closed') return 'Ditutup'
    return 'Segera'
}

const statusVariant = (s: string) =>
    s === 'open'
        ? 'border-green-500/20 bg-green-500/10 text-green-600'
        : s === 'full'
          ? 'border-amber-500/20 bg-amber-500/10 text-amber-600'
          : 'border-border bg-muted text-muted-foreground'

const formatDate = (d: string) => {
    try {
        return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
    } catch {
        return d
    }
}
</script>

<template>
    <section id="event-list" class="py-24 md:py-32">
        <div class="mx-auto max-w-7xl px-6 lg:px-10">
            <div
                :class="[
                    'mb-10 flex flex-col gap-6 sm:flex-row sm:items-end sm:justify-between transition-all duration-500',
                    visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                ]"
            >
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-primary">Semua Acara</p>
                    <h2 class="mt-3 font-display text-2xl font-bold tracking-tight text-foreground sm:text-3xl">
                        Daftar lengkap acara
                    </h2>
                </div>

                <div class="relative w-full max-w-xs">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        v-model="query"
                        placeholder="Cari acara..."
                        class="h-10 rounded-xl pl-9 text-sm"
                    />
                </div>
            </div>

            <div v-if="filtered.length" class="flex flex-col gap-3">
                <a
                    v-for="(ev, i) in filtered"
                    :key="ev.id"
                    :href="routes.landing.events.show(ev.slug)"
                    :class="[
                        'group block rounded-xl border border-border/40 bg-card transition-all duration-400 hover:border-primary/20 hover:shadow-sm',
                        visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0',
                    ]"
                    :style="{ transitionDelay: `${60 + i * 30}ms` }"
                >
                    <div class="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:p-5">
                        <div class="aspect-video w-full shrink-0 overflow-hidden rounded-xl bg-muted sm:aspect-[4/3] sm:w-24">
                            <img
                                v-if="ev.banner_url"
                                :src="ev.banner_url"
                                :alt="ev.title"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full w-full items-center justify-center">
                                <span class="text-lg font-bold text-muted-foreground/30">{{ ev.title.charAt(0) }}</span>
                            </div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start gap-3">
                                <h3 class="flex-1 truncate text-sm font-semibold text-foreground group-hover:text-primary transition-colors">
                                    {{ ev.title }}
                                </h3>
                                <Badge
                                    variant="outline"
                                    :class="['shrink-0 text-[10px]', statusVariant(ev.registration_status)]"
                                >
                                    {{ statusLabel(ev.registration_status) }}
                                </Badge>
                            </div>

                            <div class="mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <span v-if="ev.location" class="flex items-center gap-1">
                                    <MapPin class="size-3" />
                                    {{ ev.location }}
                                </span>
                                <span v-if="ev.start_date" class="flex items-center gap-1">
                                    <Calendar class="size-3" />
                                    {{ formatDate(ev.start_date) }}
                                </span>
                                <span class="flex items-center gap-1">
                                    <Users class="size-3" />
                                    {{ ev.registered_count }}/{{ ev.quota }}
                                </span>
                            </div>
                        </div>

                        <ArrowRight class="hidden size-4 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5 group-hover:text-primary sm:block" />
                    </div>
                </a>
            </div>

            <div v-else-if="query" class="rounded-2xl border border-border/40 bg-card p-10 text-center">
                <p class="text-sm text-muted-foreground">
                    Tidak ada acara yang cocok dengan "<span class="font-medium text-foreground">{{ query }}</span>".
                </p>
                <Button variant="outline" size="sm" class="mt-4 h-9 rounded-lg text-xs" @click="query = ''">
                    Reset Pencarian
                </Button>
            </div>

            <div v-else class="rounded-2xl border border-border/40 bg-card p-10 text-center">
                <p class="text-sm text-muted-foreground">Belum ada acara yang tersedia saat ini.</p>
                <Button as-child variant="outline" size="sm" class="mt-4 h-9 rounded-lg text-xs">
                    <a :href="routes.home">Kembali ke beranda</a>
                </Button>
            </div>
        </div>
    </section>
</template>
