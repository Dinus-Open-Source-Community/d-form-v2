<script setup lang="ts">
import { Badge } from '@/components/ui/badge';
import type { RecruitStage } from '@/types/recruitment';
import { computed } from 'vue';

const props = defineProps<{ stage: RecruitStage }>();

type BadgeVariant = 'default' | 'secondary' | 'destructive' | 'outline';

const config: Record<RecruitStage, { label: string; variant: BadgeVariant; extra?: string }> = {
    submitted: { label: 'Diajukan', variant: 'secondary', extra: 'bg-secondary/60 text-secondary-foreground border-secondary/40' },
    screening: { label: 'Screening', variant: 'secondary', extra: 'bg-secondary/60 text-secondary-foreground border-secondary/40' },
    revision_required: { label: 'Perlu Revisi', variant: 'outline', extra: 'bg-warning/15 text-warning border-warning/30' },
    document_passed: { label: 'Berkas Lolos', variant: 'outline', extra: 'bg-success/15 text-success border-success/30' },
    document_rejected: { label: 'Berkas Ditolak', variant: 'outline', extra: 'bg-destructive/15 text-destructive border-destructive/30' },
    interview_scheduled: { label: 'Interview Dijadwalkan', variant: 'secondary', extra: 'bg-secondary/60 text-secondary-foreground border-secondary/40' },
    waiting_attendance: { label: 'Menunggu Kehadiran', variant: 'secondary', extra: 'bg-secondary/60 text-secondary-foreground border-secondary/40' },
    queued: { label: 'Dalam Antrean', variant: 'secondary', extra: 'bg-secondary/60 text-secondary-foreground border-secondary/40' },
    interviewing: { label: 'Interview Berlangsung', variant: 'secondary', extra: 'bg-secondary/60 text-secondary-foreground border-secondary/40' },
    interviewed: { label: 'Sudah Interview', variant: 'secondary', extra: 'bg-secondary/60 text-secondary-foreground border-secondary/40' },
    final_review: { label: 'Review Akhir', variant: 'secondary', extra: 'bg-secondary/60 text-secondary-foreground border-secondary/40' },
    accepted: { label: 'Diterima', variant: 'outline', extra: 'bg-success/15 text-success border-success/30' },
    rejected: { label: 'Ditolak', variant: 'outline', extra: 'bg-destructive/15 text-destructive border-destructive/30' },
    cancelled: { label: 'Dibatalkan', variant: 'outline', extra: 'bg-muted/60 text-muted-foreground border-border/60' },
};

const label = computed(() => config[props.stage].label);
const variant = computed(() => config[props.stage].variant);
const extra = computed(() => config[props.stage].extra);
</script>

<template>
    <Badge :variant="variant" :class="['rounded-md px-2.5 py-1 text-xs font-semibold', extra]">{{ label }}</Badge>
</template>
