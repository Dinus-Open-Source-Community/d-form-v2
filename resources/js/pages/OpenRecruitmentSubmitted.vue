<script setup lang="ts">
import LandingLayout from '@/layouts/LandingLayout.vue';
import SeoHead from '@/components/seo/SeoHead.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { CheckCircle2, Copy } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    registrationNumber: string;
    confirmationMessage: string;
}>();

const copied = ref(false);

const copyText = computed(() => (copied.value ? 'Tersalin!' : 'Salin Nomor'));

async function copyRegistrationNumber(): Promise<void> {
    try {
        await navigator.clipboard.writeText(props.registrationNumber);
        copied.value = true;
        window.setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // clipboard tidak tersedia — abaikan
    }
}
</script>

<template>
    <LandingLayout>
        <SeoHead
            title="Pendaftaran Berhasil"
            description="Pendaftaran Open Recruitment DOSCOM berhasil dikirim."
            :canonical-path="'/open-recruitment/apply'"
        />

        <div class="mx-auto max-w-2xl px-6 py-16 text-center">
            <span class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-success/15">
                <CheckCircle2 class="h-10 w-10 text-success" />
            </span>

            <h1 class="mt-6 text-3xl font-bold tracking-tight">Pendaftaran Berhasil</h1>
            <p class="text-muted-foreground mt-2">{{ confirmationMessage }}</p>

            <Card class="mx-auto mt-8 max-w-md rounded-2xl">
                <CardContent class="flex flex-col items-center gap-3 p-6">
                    <p class="text-muted-foreground text-sm">Nomor Pendaftaran Anda</p>
                    <p class="font-mono text-2xl font-bold tracking-wide text-primary">
                        {{ registrationNumber }}
                    </p>
                    <Button variant="outline" size="sm" class="gap-2" @click="copyRegistrationNumber">
                        <Copy class="h-4 w-4" />
                        {{ copyText }}
                    </Button>
                </CardContent>
            </Card>

            <div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <Button class="gap-2" @click="router.visit(`/open-recruitment/tracking/${registrationNumber}`)">
                    Lacak Status
                </Button>
                <Button variant="ghost" @click="router.visit('/open-recruitment')">
                    Kembali ke Beranda
                </Button>
            </div>
        </div>
    </LandingLayout>
</template>
