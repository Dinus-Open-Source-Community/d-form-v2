<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { RecruitStage } from '@/types/recruitment';
import { computed } from 'vue';

const props = defineProps<{ stage: RecruitStage }>();

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

const config: Record<RecruitStage, { label: string; variant: BadgeVariant; extra?: string }> = {
    submitted: { label: 'Diajukan', variant: 'secondary' },
    screening: { label: 'Screening', variant: 'secondary' },
    revision_required: { label: 'Perlu Revisi', variant: 'outline', extra: 'text-warning border-warning/40' },
    document_passed: { label: 'Berkas Lolos', variant: 'default', extra: 'bg-success/15 text-success border-success/30' },
    document_rejected: { label: 'Berkas Ditolak', variant: 'destructive' },
    interview_scheduled: { label: 'Interview Dijadwalkan', variant: 'secondary' },
    waiting_attendance: { label: 'Menunggu Kehadiran', variant: 'secondary' },
    queued: { label: 'Dalam Antrean', variant: 'secondary' },
    interviewing: { label: 'Interview Berlangsung', variant: 'secondary' },
    interviewed: { label: 'Sudah Interview', variant: 'secondary' },
    final_review: { label: 'Review Akhir', variant: 'secondary' },
    accepted: { label: 'Diterima', variant: 'default', extra: 'bg-success/15 text-success border-success/30' },
    rejected: { label: 'Ditolak', variant: 'destructive' },
    cancelled: { label: 'Dibatalkan', variant: 'outline' },
};

const label = computed(() => config[props.stage].label);
const variant = computed(() => config[props.stage].variant);
const extra = computed(() => config[props.stage].extra);
</script>

<template>
    <Badge :variant="variant" :class="extra">{{ label }}</Badge>
</template>
