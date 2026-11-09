<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import LocalLottie from '@/components/core/LocalLottie.vue';
import { ArrowRight } from 'lucide-vue-next';
import { routes } from '@/lib/routes';
import { findElementById } from '@/adapters/browser/domQuery';

const visible = ref(false);
onMounted(() => {
    const obs = new IntersectionObserver(
        ([e]) => {
            if (e?.isIntersecting) {
                visible.value = true;
                obs.disconnect();
            }
        },
        { threshold: 0.2 }
    );
    const el = findElementById('section-cta');
    if (el !== null) obs.observe(el);
});
</script>

<template>
    <section id="section-cta" class="bg-primary relative overflow-hidden py-20 md:py-28">
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,rgba(255,255,255,0.12)_0%,transparent_60%)]"
            aria-hidden="true"
        />
        <div
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(ellipse_at_bottom_left,rgba(255,255,255,0.08)_0%,transparent_60%)]"
            aria-hidden="true"
        />

        <div
            :class="[
                'relative mx-auto max-w-2xl px-6 text-center transition-all duration-600 lg:px-10',
                visible ? 'translate-y-0 opacity-100' : 'translate-y-5 opacity-0',
            ]"
        >
            <LocalLottie name="landingCta" :height="80" :width="80" class="mx-auto mb-6" :lazy="false" />

            <h2 class="font-display text-primary-foreground text-2xl font-bold tracking-tight sm:text-3xl lg:text-4xl">
                Siap menyederhanakan pendaftaran acara?
            </h2>
            <p class="text-primary-foreground/80 mx-auto mt-4 max-w-md text-base leading-relaxed">
                Buat akun gratis sekarang dan rancang formulir acara pertama Anda. Tidak perlu kartu kredit, tidak perlu
                keahlian teknis.
            </p>

            <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row">
                <Button as-child size="lg" variant="secondary" class="h-12 px-8 text-sm font-semibold">
                    <a :href="routes.auth.register" class="inline-flex items-center gap-2">
                        Mulai Sekarang — Gratis
                        <ArrowRight class="size-4" />
                    </a>
                </Button>
                <Button
                    as-child
                    size="lg"
                    variant="ghost"
                    class="text-primary-foreground/90 hover:text-primary-foreground h-12 px-6 text-sm font-medium hover:bg-white/10"
                >
                    <a href="#section-faq">Baca FAQ</a>
                </Button>
            </div>
        </div>
    </section>
</template>
