<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import { History } from 'lucide-vue-next';
import { formatDateTime } from '@/lib/dateTime';
import type { IApplicationActivityRow } from '@/types/recruitment';

defineProps<{ activityLogs: IApplicationActivityRow[] }>();

const activityActionLabels: Record<string, string> = {
    'screening.pass': 'Lolos screening',
    'screening.revision_required': 'Diminta revisi',
    'screening.reject': 'Ditolak pada tahap screening',
    'evaluation.submitted': 'Evaluasi interview dikirim',
    'evaluation.updated': 'Evaluasi interview diperbarui',
    'evaluation.staff_override': 'Evaluasi diubah staff',
    'correction.requested': 'Applicant meminta koreksi',
    'correction.approved': 'Permintaan koreksi disetujui',
    'correction.rejected': 'Permintaan koreksi ditolak',
    'interview.scheduled': 'Interview dijadwalkan',
    'interview.rescheduled': 'Jadwal interview diubah',
    'interview.reassigned': 'Interviewer diganti',
    'interview.cancelled': 'Interview dibatalkan',
    'application.verified': 'Pendaftaran diverifikasi',
    'tracking.resend': 'Informasi tracking dikirim ulang',
    'application.updated': 'Pendaftaran diperbarui applicant',
    'final.accept': 'Diterima sebagai anggota',
    'final.reject': 'Tidak lolos seleksi akhir',
    'attendance.check_in': 'Absensi interview tercatat',
    'interview.no_show': 'Tidak hadir interview',
};

function activityActionLabel(action: string): string {
    const label = activityActionLabels[action];
    if (label) return label;
    const pretty = action.replace(/[._-]+/g, ' ').trim();
    return pretty === '' ? action : pretty.charAt(0).toUpperCase() + pretty.slice(1);
}
</script>

<template>
    <Card class="border-border/70 rounded-2xl">
        <CardContent class="space-y-3 p-6">
            <div v-for="log in activityLogs" :key="log.id" class="flex gap-3 rounded-xl border p-4">
                <History class="text-muted-foreground mt-0.5 size-4 shrink-0" />
                <div class="min-w-0 flex-1">
                    <p class="font-medium">{{ activityActionLabel(log.action) }}</p>
                    <p class="text-muted-foreground text-xs">
                        {{ log.actor?.name ?? 'Sistem' }} ·
                        {{
                            formatDateTime({ value: log.created_at, variant: 'date-time-medium' }) ||
                            'Waktu tidak tercatat'
                        }}
                    </p>
                </div>
            </div>
            <p v-if="activityLogs.length === 0" class="text-muted-foreground text-sm">Belum ada aktivitas tercatat.</p>
        </CardContent>
    </Card>
</template>
