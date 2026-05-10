<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Card, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'

defineProps<{
    events: IEvent[]
}>()

const visible = ref(false)
onMounted(() => {
    const obs = new IntersectionObserver(
        ([e]) => { if (e?.isIntersecting) { visible.value = true; obs.disconnect() } },
        { threshold: 0.05 },
    )
    const el = document.getElementById('event-list')
    if (el) obs.observe(el)
})

const statusLabel = (s: string) => {
    if (s === 'open') return 'Dibuka'
    if (s === 'full') return 'Penuh'
    if (s === 'closed') return 'Ditutup'
    return 'Segera'
}

const statusColor = (s: string) =>
    s === 'open' ? 'bg-green-500/10 text-green-600 border-green-500/20' : 'bg-muted text-muted-foreground border-border'
</script>

<template>
    <section id="event-list" class="py-16 md:py-24">
        <div class="mx-auto max-w-5xl px-5 lg:px-8">
            <div :class="['mb-10 transition-all duration-500', visible ? 'opacity-100' : 'opacity-0']">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-primary">Semua Acara</p>
                <h2 class="mt-2 text-[1.5rem] font-semibold tracking-tight text-foreground sm:text-[1.75rem]">
                    Daftar lengkap
                </h2>
            </div>

            <div v-if="events.length" class="flex flex-col gap-3">
                <a
                    v-for="(ev, i) in events"
                    :key="ev.id"
                    :href="`/events/${ev.slug}`"
                    :class="['block transition-all duration-400', visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0']"
                    :style="{ transitionDelay: `${60 + i * 40}ms` }"
                >
                    <Card class="group border-border/40 transition-colors duration-200 hover:border-primary/20">
                        <CardContent class="flex items-center gap-4 p-4 sm:p-5">
                            <div v-if="ev.banner_url" class="hidden size-14 shrink-0 overflow-hidden rounded-lg bg-muted sm:block">
                                <img :src="ev.banner_url" :alt="ev.title" class="h-full w-full object-cover" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-[14px] font-semibold text-foreground group-hover:text-primary transition-colors">
                                    {{ ev.title }}
                                </h3>
                                <p class="mt-0.5 text-[12px] text-muted-foreground">{{ ev.location }}</p>
                            </div>
                            <Badge variant="outline" :class="['shrink-0 text-[10px]', statusColor(ev.registration_status)]">
                                {{ statusLabel(ev.registration_status) }}
                            </Badge>
                        </CardContent>
                    </Card>
                </a>
            </div>
            <div v-else class="rounded-xl border border-border/40 bg-card/50 p-10 text-center">
                <p class="text-[14px] text-muted-foreground">Belum ada acara yang tersedia saat ini.</p>
                <Button as-child variant="outline" class="mt-4 h-9 rounded-lg text-[13px]">
                    <a href="/">Kembali ke beranda</a>
                </Button>
            </div>
        </div>
    </section>
</template>
