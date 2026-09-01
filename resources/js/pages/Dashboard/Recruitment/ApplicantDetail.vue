<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { History, ShieldCheck } from 'lucide-vue-next';
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

/** Panel keputusan aktif hanya untuk stage yang belum final. */
const isPanelActive = computed(() => {
    const s = app.value?.stage;
    return s === 'submitted' || s === 'screening' || s === 'revision_required';
});

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

const decisionLabel: Record<string, string> = {
    pass: 'Lolos',
    revision: 'Revisi',
    reject: 'Ditolak',
};

function decisionBadgeClass(decision: string): string {
    if (decision === 'pass') return 'bg-success/15 text-success border-success/30';
    if (decision === 'reject') return 'bg-destructive/15 text-destructive border-destructive/30';
    return 'bg-warning/15 text-warning border-warning/30';
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

                    <!-- Riwayat Screening (audit trail) -->
                    <div class="mt-6 rounded-2xl border border-border/70 p-4 sm:p-5">
                        <p class="flex items-center gap-2 font-medium">
                            <History class="size-4 shrink-0 stroke-[1.75] text-muted-foreground" aria-hidden="true" />
                            Riwayat Screening
                        </p>
                        <div v-if="app.screeningHistory.length > 0" class="mt-3 space-y-3">
                            <div
                                v-for="(entry, idx) in app.screeningHistory"
                                :key="`${entry.decidedAt}-${idx}`"
                                class="rounded-xl border border-border/60 bg-muted/20 p-3 text-sm"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge
                                        variant="outline"
                                        :class="['rounded-md px-2.5 py-1 text-xs font-semibold', decisionBadgeClass(entry.decision)]"
                                    >
                                        {{ decisionLabel[entry.decision] ?? entry.decision }}
                                    </Badge>
                                    <span class="text-muted-foreground text-xs">
                                        oleh {{ entry.decidedBy }} · {{ new Date(entry.decidedAt).toLocaleString('id-ID') }}
                                    </span>
                                </div>
                                <p class="mt-2 leading-relaxed">{{ entry.reason }}</p>
                                <p v-if="entry.notes" class="text-muted-foreground mt-1 text-xs leading-relaxed">
                                    {{ entry.notes }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="text-muted-foreground mt-2 text-sm">Belum ada keputusan screening.</p>
                    </div>
                </CardContent>
            </Card>

            <Card class="h-fit rounded-2xl">
                <CardContent class="p-5">
                    <template v-if="isPanelActive">
                        <RecruitScreeningPanel :application="app" @decision="handleDecision" />
                    </template>
                    <template v-else>
                        <div class="flex items-start gap-2 rounded-xl border border-border/70 bg-muted/20 p-4 text-sm">
                            <ShieldCheck class="mt-0.5 size-4 shrink-0 stroke-[1.75] text-muted-foreground" aria-hidden="true" />
                            <div>
                                <p class="font-medium">Keputusan sudah diambil</p>
                                <p class="text-muted-foreground mt-0.5 text-xs leading-relaxed">
                                    Stage {{ app.stage }} sudah final. Untuk mengubah keputusan, ajukan correction
                                    request atau hubungi admin.
                                </p>
                            </div>
                        </div>
                    </template>
                </CardContent>
            </Card>
        </div>

        <div v-else class="rounded-xl border border-destructive/50 bg-destructive/5 p-4 text-sm text-destructive">
            Applicant tidak ditemukan.
        </div>
    </div>
</template>
