<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { Button } from '@/components/ui/button'
import LocalLottie from '@/components/core/LocalLottie.vue'
import { ArrowRight } from 'lucide-vue-next'
import { routes } from '@/lib/routes'

const show = ref(false)
onMounted(() => requestAnimationFrame(() => (show.value = true)))
</script>

<template>
    <section class="relative isolate overflow-hidden pt-36 pb-20 md:pt-48 md:pb-28">
        <div
            class="pointer-events-none absolute -top-48 left-1/2 -z-10 size-[900px] -translate-x-1/2 rounded-full bg-primary/[0.07] blur-[160px]"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute inset-0 -z-10 app-noise opacity-40"
            aria-hidden="true"
        />

        <div class="relative mx-auto max-w-7xl px-6 lg:px-10">
            <div class="grid items-center gap-14 lg:grid-cols-2 lg:gap-20">
                <!-- Left: Text content -->
                <div
                    :class="[
                        'transition-all duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        show ? 'translate-y-0 opacity-100' : 'translate-y-6 opacity-0',
                    ]"
                >

                    <h1
                        class="mt-7 font-display text-4xl font-bold leading-[1.08] tracking-tight text-foreground sm:text-5xl lg:text-[3.5rem] text-balance"
                    >
                        Satu platform untuk
                        <span class="bg-gradient-to-r from-primary to-primary/70 bg-clip-text text-transparent">
                            seluruh pendaftaran acara.
                        </span>
                    </h1>

                    <p class="mt-6 max-w-lg text-base leading-relaxed text-muted-foreground md:text-lg md:leading-relaxed text-pretty">
                        DForm menyederhanakan alur pendaftaran — dari pembuatan formulir dinamis
                        hingga pengelolaan data peserta secara real-time. Tanpa coding, tanpa ribet.
                    </p>

                    <div class="mt-10 flex flex-wrap gap-3.5">
                        <Button as-child size="lg" class="h-12 rounded-xl px-8 text-sm font-semibold shadow-sm">
                            <a :href="routes.auth.register" class="inline-flex items-center gap-2">
                                Coba Gratis
                                <ArrowRight class="size-4" />
                            </a>
                        </Button>
                        <Button
                            as-child
                            variant="outline"
                            size="lg"
                            class="h-12 rounded-xl border-border/60 px-7 text-sm font-medium"
                        >
                            <a href="#section-steps">Pelajari Lebih Lanjut</a>
                        </Button>
                    </div>

                    <div class="mt-10 flex items-center gap-6 text-sm text-muted-foreground">
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

                <!-- Right: Lottie animation -->
                <div
                    :class="[
                        'transition-all delay-150 duration-700 ease-[cubic-bezier(0.22,1,0.36,1)]',
                        show ? 'translate-y-0 opacity-100' : 'translate-y-8 opacity-0',
                    ]"
                >
                    <div class="relative">
                        <div class="overflow-hidden rounded-2xl border border-border/30 bg-gradient-to-b from-card to-muted/20 p-6 shadow-sm sm:p-8">
                            <LocalLottie name="landingHero" :height="340" width="100%" :lazy="false" :speed="0.85" />
                        </div>
                        <div
                            class="pointer-events-none absolute -top-3 -right-3 size-6 rounded-full border border-primary/20 bg-primary/10"
                            aria-hidden="true"
                        />
                        <div
                            class="pointer-events-none absolute -bottom-2 -left-2 size-4 rounded-full border border-primary/15 bg-primary/8"
                            aria-hidden="true"
                        />
                    </div>
                </div>
            </div>

            <!-- Stats strip -->
            <div
                :class="[
                    'mt-20 grid grid-cols-2 gap-6 border-t border-border/30 pt-10 sm:grid-cols-4',
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
                    <p class="text-2xl font-bold tabular-nums tracking-tight text-foreground">{{ stat.num }}</p>
                    <p class="mt-1 text-sm text-muted-foreground">{{ stat.label }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
