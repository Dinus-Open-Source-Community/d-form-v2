<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { recruitmentStore } from '@/lib/recruitmentStore';
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { FileSearch, ListChecks, Users } from 'lucide-vue-next';

defineOptions({ layout: DashboardLayout });

defineProps<{
    stats: {
        total: number;
        pendingScreening: number;
        passed: number;
        revision: number;
        rejected: number;
    };
}>();

// Data live dari store (dummy) — menimpa props statis untuk akurasi demo.
const live = computed(() => {
    const apps = recruitmentStore.state.applications;
    return {
        total: apps.length,
        pendingScreening: apps.filter((a) => a.stage === 'screening' || a.stage === 'submitted').length,
        passed: apps.filter((a) => a.stage === 'document_passed').length,
        revision: apps.filter((a) => a.stage === 'revision_required').length,
        rejected: apps.filter((a) => a.stage === 'document_rejected' || a.stage === 'rejected').length,
    };
});

const kpis = computed(() => [
    { label: 'Total Applicant', value: live.value.total, icon: Users },
    { label: 'Menunggu Screening', value: live.value.pendingScreening, icon: FileSearch },
    { label: 'Berkas Lolos', value: live.value.passed, icon: ListChecks },
    { label: 'Perlu Revisi', value: live.value.revision, icon: ListChecks },
    { label: 'Ditolak', value: live.value.rejected, icon: ListChecks },
]);

function goApplicants(): void {
    router.visit('/admin/dashboard/recruitment/applicants');
}

function goCorrections(): void {
    router.visit('/admin/dashboard/recruitment/corrections');
}
</script>

<template>
    <Head title="Recruitment — Dashboard" />

    <div class="flex flex-col gap-8">
        <PageHeader
            title="Rekrutmen"
            subtitle="Kelola proses Open Recruitment DOSCOM."
        />

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <Card v-for="kpi in kpis" :key="kpi.label" class="rounded-2xl">
                <CardContent class="flex flex-col gap-2 p-5">
                    <span class="inline-flex w-fit rounded-lg bg-primary/10 p-1.5 text-primary">
                        <component :is="kpi.icon" class="h-4 w-4" />
                    </span>
                    <div>
                        <p class="text-muted-foreground text-sm">{{ kpi.label }}</p>
                        <p class="text-3xl font-bold">{{ kpi.value }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="flex flex-wrap gap-3">
            <Button class="gap-2" @click="goApplicants">
                <Users class="h-4 w-4" />
                Daftar Applicant
            </Button>
            <Button variant="outline" class="gap-2" @click="goCorrections">
                <ListChecks class="h-4 w-4" />
                Correction Requests
            </Button>
        </div>
    </div>
</template>
