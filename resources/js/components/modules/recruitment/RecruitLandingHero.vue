<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { router } from '@inertiajs/vue3';
import { ArrowRight, Sparkles } from 'lucide-vue-next';

const props = defineProps<{
    periodName: string;
    isOpen: boolean;
    registrationUrl: string;
}>();

function goToForm(): void {
    router.visit(props.registrationUrl);
}
</script>

<template>
    <section class="relative overflow-hidden">
        <div class="pointer-events-none absolute inset-0 -z-10">
            <div class="absolute -top-24 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-primary/10 blur-3xl" />
            <div class="absolute top-20 right-0 h-56 w-56 rounded-full bg-info/10 blur-3xl" />
        </div>

        <div class="mx-auto max-w-3xl px-6 py-20 text-center md:py-28">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-primary">
                <Sparkles class="h-3.5 w-3.5" />
                {{ periodName }}
            </span>

            <h1 class="mt-5 text-4xl font-bold tracking-tight md:text-5xl">
                Bergabunglah dengan <span class="text-primary">DOSCOM</span>
            </h1>

            <p class="text-muted-foreground mx-auto mt-4 max-w-2xl text-lg leading-relaxed">
                Ikuti Open Recruitment DOSCOM dan kembangkan skill, jaringan, dan pengalaman
                organisasi bersama komunitas yang aktif.
            </p>

            <div class="mt-8 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                <Button size="lg" class="gap-2" :disabled="!isOpen" @click="goToForm">
                    {{ isOpen ? 'Daftar Sekarang' : 'Pendaftaran Ditutup' }}
                    <ArrowRight v-if="isOpen" class="h-4 w-4" />
                </Button>
                <Button size="lg" variant="outline" @click="router.visit('/open-recruitment/tracking')">
                    Lacak Pendaftaran
                </Button>
            </div>

            <p v-if="!isOpen" class="text-muted-foreground mt-4 text-sm">
                Pendaftaran periode ini telah ditutup.
            </p>
        </div>
    </section>
</template>
