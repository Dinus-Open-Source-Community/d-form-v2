<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import RecruitScreeningPanel from '@/components/modules/recruitment/RecruitScreeningPanel.vue';
import RecruitStatusBadge from '@/components/modules/recruitment/RecruitStatusBadge.vue';
import { recruitmentStore } from '@/lib/recruitmentStore';
import { divisionLabels } from '@/lib/dummyRecruitment';
import type { IApplication, RecruitScreeningDecision } from '@/types/recruitment';
import { computed, onMounted } from 'vue';
import { setTopbar } from '@/utils/composables/useDashboardTopbar';

defineOptions({ layout: DashboardLayout });

const props = defineProps<{ applicationId: string }>();

const app = computed<IApplication | undefined>(() =>
    recruitmentStore.getApplicationById(props.applicationId),
);

onMounted(() => {
    setTopbar({
        title: app.value?.applicant.fullName ?? 'Applicant',
        subtitle: 'Detail data & screening',
    });
});

function handleDecision(decision: RecruitScreeningDecision, reason: string, notes: string): void {
    if (!app.value) {
        return;
    }
    recruitmentStore.screeningDecision(app.value.id, decision, reason, notes);
}
</script>

<template>
    <Head title="Detail Applicant — Recruitment" />

    <div class="flex flex-col gap-6">
        <div v-if="app" class="grid gap-6 lg:grid-cols-3">
            <Card class="rounded-2xl lg:col-span-2">
                <CardContent class="p-6">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="text-xl font-semibold">{{ app.applicant.fullName }}</h2>
                            <p class="text-muted-foreground font-mono text-xs">{{ app.registrationNumber }}</p>
                        </div>
                        <RecruitStatusBadge :stage="app.stage" />
                    </div>

                    <dl class="mt-6 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted-foreground text-xs uppercase tracking-wide">NIM</dt>
                            <dd class="mt-0.5 font-medium">{{ app.applicant.nim }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs uppercase tracking-wide">Semester</dt>
                            <dd class="mt-0.5 font-medium">{{ app.applicant.semester }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs uppercase tracking-wide">Email Pribadi</dt>
                            <dd class="mt-0.5">{{ app.applicant.personalEmail }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs uppercase tracking-wide">Email Mahasiswa</dt>
                            <dd class="mt-0.5">{{ app.applicant.studentEmail }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs uppercase tracking-wide">No. WhatsApp</dt>
                            <dd class="mt-0.5">{{ app.applicant.phone }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs uppercase tracking-wide">Instagram</dt>
                            <dd class="mt-0.5">@{{ app.applicant.instagram }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs uppercase tracking-wide">Divisi Utama</dt>
                            <dd class="mt-0.5 font-medium">{{ divisionLabels[app.applicant.primaryDivision] }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs uppercase tracking-wide">Divisi Kedua</dt>
                            <dd class="mt-0.5">{{ app.applicant.secondaryDivision ? divisionLabels[app.applicant.secondaryDivision] : '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-muted-foreground text-xs uppercase tracking-wide">Motivasi</dt>
                            <dd class="mt-1 leading-relaxed">{{ app.applicant.motivation }}</dd>
                        </div>
                        <div v-if="app.applicant.organizationExperience" class="sm:col-span-2">
                            <dt class="text-muted-foreground text-xs uppercase tracking-wide">Pengalaman Organisasi</dt>
                            <dd class="mt-1">{{ app.applicant.organizationExperience }}</dd>
                        </div>
                        <div v-if="app.applicant.skills" class="sm:col-span-2">
                            <dt class="text-muted-foreground text-xs uppercase tracking-wide">Keahlian</dt>
                            <dd class="mt-1">{{ app.applicant.skills }}</dd>
                        </div>
                    </dl>

                    <div v-if="app.screening" class="mt-6 rounded-xl border p-4 text-sm">
                        <p class="font-medium">Keputusan Screening</p>
                        <p class="text-muted-foreground mt-1">
                            {{ app.screening.decision }} · {{ app.screening.reason }}
                            <span v-if="app.screening.notes"> — {{ app.screening.notes }}</span>
                        </p>
                        <p class="text-muted-foreground mt-1 text-xs">
                            oleh {{ app.screening.decidedBy }} · {{ new Date(app.screening.decidedAt).toLocaleString('id-ID') }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <Card class="h-fit rounded-2xl">
                <CardContent class="p-5">
                    <RecruitScreeningPanel :application="app" @decision="handleDecision" />
                </CardContent>
            </Card>
        </div>

        <div v-else class="rounded-xl border border-destructive/50 bg-destructive/5 p-4 text-sm text-destructive">
            Applicant tidak ditemukan.
        </div>
    </div>
</template>
