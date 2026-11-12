<script setup lang="ts">
import { computed, reactive } from 'vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import ExportDownloadStatus from '@/components/modules/dashboard/ExportDownloadStatus.vue';
import { routes } from '@/lib/routes';
import { useExportDownload } from '@/hooks/useExportDownload';
import { Download, Funnel, GraduationCap, Users } from 'lucide-vue-next';

interface ReportPayload {
    period: { id: string; name: string } | null;
    funnel: { stage: string; label: string; count: number }[];
    by_division: { division: string; count: number }[];
    by_semester: { semester: number; count: number }[];
    interview_stats: Record<string, number>;
    feedback: { count: number; averages: Record<string, number | null> };
}

const props = defineProps<{
    periodId: string;
    report: ReportPayload | null;
}>();

const maxFunnel = computed(() => Math.max(1, ...(props.report?.funnel.map((row) => row.count) ?? [1])));

/** Satu state unduhan per tombol; keduanya GET download terpisah. */
const funnelExport = reactive(useExportDownload());
const applicantsExport = reactive(useExportDownload());

function downloadFunnel(): void {
    void funnelExport.start([routes.admin.recruitment.reports.exportFunnel(props.periodId)]);
}

function downloadApplicants(): void {
    void applicantsExport.start([routes.admin.recruitment.reports.exportApplicants(props.periodId)]);
}
</script>

<template>
    <div v-if="report" class="flex flex-col gap-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-muted-foreground text-sm font-semibold tracking-wide uppercase">Laporan periode ini</h2>
            <div class="flex flex-col items-end gap-2">
                <div class="flex flex-wrap gap-2">
                    <Button size="sm" variant="outline" :disabled="funnelExport.isRunning" @click="downloadFunnel">
                        <Download class="mr-2 size-4" aria-hidden="true" />
                        Export funnel CSV
                    </Button>
                    <Button size="sm" :disabled="applicantsExport.isRunning" @click="downloadApplicants">
                        <Download class="mr-2 size-4" aria-hidden="true" />
                        Export applicant CSV
                    </Button>
                </div>
                <ExportDownloadStatus
                    :phase="funnelExport.phase"
                    label="Funnel CSV"
                    :progress="funnelExport.progress"
                    :indeterminate="funnelExport.indeterminate"
                    :loaded-bytes="funnelExport.loadedBytes"
                    :direct-count="funnelExport.directCount"
                    :error-message="funnelExport.errorMessage"
                    @cancel="funnelExport.cancel()"
                />
                <ExportDownloadStatus
                    :phase="applicantsExport.phase"
                    label="Applicant CSV"
                    :progress="applicantsExport.progress"
                    :indeterminate="applicantsExport.indeterminate"
                    :loaded-bytes="applicantsExport.loadedBytes"
                    :direct-count="applicantsExport.directCount"
                    :error-message="applicantsExport.errorMessage"
                    @cancel="applicantsExport.cancel()"
                />
            </div>
        </div>

        <Card class="border-border/70 rounded-2xl">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                    <Funnel class="text-muted-foreground size-4" aria-hidden="true" />
                    Funnel recruitment
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-3">
                <div v-if="report.funnel[0]" class="bg-muted/30 rounded-xl border px-4 py-3">
                    <p class="text-muted-foreground text-xs uppercase">{{ report.funnel[0].label }}</p>
                    <p class="mt-0.5 text-2xl font-semibold tabular-nums">{{ report.funnel[0].count }}</p>
                </div>
                <div v-for="row in report.funnel.slice(1)" :key="row.stage" class="space-y-1.5">
                    <div class="flex items-center justify-between gap-3 text-sm">
                        <span>{{ row.label }}</span>
                        <span class="font-medium tabular-nums" :class="row.count === 0 ? 'text-muted-foreground' : ''">
                            {{ row.count }}
                        </span>
                    </div>
                    <div class="bg-muted h-2 rounded-full">
                        <div
                            class="bg-primary h-2 rounded-full transition-all"
                            :style="{ width: `${(row.count / maxFunnel) * 100}%` }"
                        />
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-5 lg:grid-cols-2">
            <Card class="border-border/70 rounded-2xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                        <Users class="text-muted-foreground size-4" aria-hidden="true" />
                        Per divisi utama
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-sm">
                    <div v-if="report.by_division.length > 0" class="divide-border/60 divide-y">
                        <div
                            v-for="row in report.by_division"
                            :key="row.division"
                            class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                        >
                            <span>{{ row.division }}</span>
                            <span class="font-medium tabular-nums">{{ row.count }}</span>
                        </div>
                    </div>
                    <p v-else class="text-muted-foreground">Belum ada data.</p>
                </CardContent>
            </Card>

            <Card class="border-border/70 rounded-2xl">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-sm font-semibold">
                        <GraduationCap class="text-muted-foreground size-4" aria-hidden="true" />
                        Per semester
                    </CardTitle>
                </CardHeader>
                <CardContent class="text-sm">
                    <div v-if="report.by_semester.length > 0" class="divide-border/60 divide-y">
                        <div
                            v-for="row in report.by_semester"
                            :key="row.semester"
                            class="flex items-center justify-between gap-3 py-2.5 first:pt-0 last:pb-0"
                        >
                            <span>Semester {{ row.semester }}</span>
                            <span class="font-medium tabular-nums">{{ row.count }}</span>
                        </div>
                    </div>
                    <p v-else class="text-muted-foreground">Belum ada data.</p>
                </CardContent>
            </Card>
        </div>
    </div>
    <p v-else class="text-muted-foreground text-sm">Data laporan tidak tersedia untuk tab ini.</p>
</template>
