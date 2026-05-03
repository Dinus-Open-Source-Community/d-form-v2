<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent } from '@/components/ui/card'
import LocalLottie from '@/components/core/LocalLottie.vue'

defineOptions({ layout: DashboardLayout })
</script>

<template>
    <Head title="Recruitment" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Recruitment" subtitle="Manage member recruitment campaigns." />

        <Card class="rounded-xl border shadow-xs">
            <CardContent class="flex flex-col items-center justify-center px-6 py-20 text-center">
                <LocalLottie name="comingSoon" :height="200" :width="200" class="mb-2 opacity-80" />
                <h2 class="text-xl font-semibold">Coming Soon</h2>
                <p class="mt-2 max-w-sm text-sm text-muted-foreground">
                    The recruitment module is currently under development. Stay tuned for updates!
                </p>
            </CardContent>
        </Card>
    </div>
</template>
