<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent } from '@/components/ui/card'
import LocalLottie from '@/components/core/LocalLottie.vue'
import { routes } from '@/lib/routes'

defineOptions({ layout: DashboardLayout })
</script>

<template>
    <Head title="Recruitment" />

    <div class="flex flex-col gap-8 md:gap-10">
        <PageHeader
            title="Rekrutmen"
            subtitle="Modul kampanye anggota sedang disiapkan."
            :back-href="routes.admin.index"
        />

        <Card class="rounded-2xl border-border/70 shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.06]">
            <CardContent class="flex flex-col items-center justify-center px-6 py-20 text-center">
                <LocalLottie name="comingSoon" :height="200" :width="200" class="mb-2 opacity-80" />
                <h2 class="text-xl font-semibold tracking-tight">Segera hadir</h2>
                <p class="text-muted-foreground mt-2 max-w-sm text-sm leading-relaxed">
                    Modul rekrutmen masih dalam pengembangan. Nantikan pembaruan berikutnya.
                </p>
            </CardContent>
        </Card>
    </div>
</template>
