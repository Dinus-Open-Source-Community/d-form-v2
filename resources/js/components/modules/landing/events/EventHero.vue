<script setup lang="ts">
import { ref, onMounted } from 'vue';
import LocalLottie from '@/components/core/LocalLottie.vue';
import { Button } from '@/components/ui/button';
import { CalendarSearch, ArrowDown } from 'lucide-vue-next';

const show = ref(false);
onMounted(() => requestAnimationFrame(() => (show.value = true)));
</script>

<template>
    <section class="relative isolate overflow-hidden pt-32 pb-16 md:pt-44 md:pb-24">
        <div
            class="bg-primary/[0.06] pointer-events-none absolute -top-40 left-1/2 -z-10 size-[800px] -translate-x-1/2 rounded-full blur-[140px]"
            aria-hidden="true"
        />
        <div class="app-noise pointer-events-none absolute inset-0 -z-10 opacity-30" aria-hidden="true" />

        <div class="relative mx-auto max-w-7xl px-6 lg:px-10">
            <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
                <div
                    :class="[
                        'transition-all duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        show ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
                    ]"
                >
                    <span
                        class="border-primary/15 bg-primary/[0.06] text-primary inline-flex items-center gap-2 rounded-full border px-4 py-1.5 text-xs font-semibold tracking-wide"
                    >
                        <CalendarSearch class="size-3.5" />
                        Acara Terbuka
                    </span>

                    <h1
                        class="font-display text-foreground mt-7 text-3xl leading-[1.1] font-bold tracking-tight text-balance sm:text-4xl lg:text-5xl"
                    >
                        Temukan acara dan
                        <span class="from-primary to-primary/70 bg-gradient-to-r bg-clip-text text-transparent">
                            daftarkan dirimu.
                        </span>
                    </h1>

                    <p
                        class="text-muted-foreground mt-6 max-w-lg text-base leading-relaxed text-pretty md:text-lg md:leading-relaxed"
                    >
                        Jelajahi daftar acara yang sedang membuka pendaftaran dari berbagai organisasi. Pilih yang
                        menarik, isi formulir, dan langsung terdaftar.
                    </p>

                    <div class="mt-8">
                        <Button
                            as-child
                            variant="outline"
                            size="lg"
                            class="border-border/60 h-11 px-6 text-sm font-medium"
                        >
                            <a href="#event-highlight" class="inline-flex items-center gap-2">
                                Lihat Acara
                                <ArrowDown class="size-4" />
                            </a>
                        </Button>
                    </div>
                </div>

                <div
                    :class="[
                        'transition-all delay-150 duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        show ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                    ]"
                >
                    <div
                        class="border-border/30 from-card to-muted/20 overflow-hidden rounded-2xl border bg-gradient-to-b p-6 shadow-sm sm:p-8"
                    >
                        <LocalLottie name="eventsHero" :height="300" width="100%" :lazy="false" :speed="0.85" />
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
