<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import LocalLottie from '@/components/core/LocalLottie.vue';
import { ArrowRight } from 'lucide-vue-next';
import { routes } from '@/lib/routes';

const show = ref(false);
onMounted(() => requestAnimationFrame(() => (show.value = true)));
</script>

<template>
    <section class="relative isolate overflow-hidden pt-36 pb-20 md:pt-48 md:pb-28">
        <div
            class="bg-primary/[0.07] pointer-events-none absolute -top-48 left-1/2 -z-10 size-[900px] -translate-x-1/2 rounded-full blur-[160px]"
            aria-hidden="true"
        />
        <div class="app-noise pointer-events-none absolute inset-0 -z-10 opacity-40" aria-hidden="true" />

        <div class="relative mx-auto max-w-7xl px-6 lg:px-10">
            <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
                <div
                    :class="[
                        'transition-all duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        show ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
                    ]"
                >
                    <h1
                        class="font-display text-foreground mt-7 text-4xl leading-[1.08] font-bold tracking-tight text-balance sm:text-5xl lg:text-[3.5rem]"
                    >
                        Satu platform untuk
                        <span class="from-primary to-primary/70 bg-gradient-to-r bg-clip-text text-transparent">
                            seluruh pendaftaran acara.
                        </span>
                    </h1>

                    <p
                        class="text-muted-foreground mt-6 max-w-lg text-base leading-relaxed text-pretty md:text-lg md:leading-relaxed"
                    >
                        DForm menyederhanakan alur pendaftaran — dari pembuatan formulir dinamis hingga pengelolaan data
                        peserta secara real-time. Tanpa coding, tanpa ribet.
                    </p>

                    <div class="mt-10 flex flex-wrap gap-3.5">
                        <Button as-child size="lg" class="h-12 px-8 text-sm font-semibold shadow-sm">
                            <a :href="routes.auth.register" class="inline-flex items-center gap-2">
                                Coba Gratis
                                <ArrowRight class="size-4" />
                            </a>
                        </Button>
                        <Button
                            as-child
                            variant="outline"
                            size="lg"
                            class="border-border/60 h-12 px-7 text-sm font-medium"
                        >
                            <a href="#section-steps">Pelajari Lebih Lanjut</a>
                        </Button>
                    </div>

                    <div class="text-muted-foreground mt-10 flex items-center gap-6 text-sm">
                        <span class="flex items-center gap-2">
                            <span class="size-1.5 rounded-full bg-green-500" />
                            Gratis selamanya
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="size-1.5 rounded-full bg-green-500" />
                            Tanpa kartu kredit
                        </span>
                    </div>
                </div>

                <div
                    :class="[
                        'transition-all delay-150 duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        show ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                    ]"
                >
                    <div class="relative">
                        <div
                            class="border-border/30 from-card to-muted/20 overflow-hidden rounded-2xl border bg-gradient-to-b p-6 shadow-sm sm:p-8"
                        >
                            <LocalLottie name="landingHero" :height="340" width="100%" :lazy="false" :speed="0.85" />
                        </div>
                        <div
                            class="border-primary/20 bg-primary/10 pointer-events-none absolute -top-3 -right-3 size-6 rounded-full border"
                            aria-hidden="true"
                        />
                        <div
                            class="border-primary/15 bg-primary/8 pointer-events-none absolute -bottom-2 -left-2 size-4 rounded-full border"
                            aria-hidden="true"
                        />
                    </div>
                </div>
            </div>

            <div
                :class="[
                    'border-border/30 mt-20 grid grid-cols-2 gap-6 border-t pt-10 sm:grid-cols-4',
                    'transition-all delay-300 duration-600 ease-[cubic-bezier(0.22,1,0.36,1)]',
                    show ? 'opacity-100' : 'opacity-0',
                ]"
            >
                <div
                    v-for="stat in [
                        { num: '500+', label: 'Acara terdaftar' },
                        { num: '10K+', label: 'Formulir terisi' },
                        { num: '50+', label: 'Organisasi aktif' },
                        { num: '99.9%', label: 'Uptime server' },
                    ]"
                    :key="stat.label"
                    class="text-center sm:text-left"
                >
                    <p class="text-foreground text-2xl font-bold tracking-tight tabular-nums">{{ stat.num }}</p>
                    <p class="text-muted-foreground mt-1 text-sm">{{ stat.label }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
