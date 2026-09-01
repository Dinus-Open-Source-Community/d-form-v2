<script setup lang="ts">
import type { RecruitStage } from '@/types/recruitment';
import { CheckCircle2, Circle, Clock } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ stage: RecruitStage }>();

const steps: Array<{ key: RecruitStage; label: string }> = [
    { key: 'submitted', label: 'Pendaftaran dikirim' },
    { key: 'screening', label: 'Screening berkas' },
    { key: 'document_passed', label: 'Berkas lolos' },
];

const order: RecruitStage[] = ['submitted', 'screening', 'document_passed'];

const currentIndex = computed(() => {
    const idx = order.indexOf(props.stage);
    return idx === -1 ? order.length - 1 : idx;
});
</script>

<template>
    <ol class="space-y-4">
        <li v-for="(step, i) in steps" :key="step.key" class="flex items-start gap-3">
            <component
                :is="i < currentIndex ? CheckCircle2 : i === currentIndex ? Clock : Circle"
                class="mt-0.5 h-5 w-5"
                :class="i <= currentIndex ? 'text-primary' : 'text-muted-foreground/50'"
            />
            <div>
                <p class="text-sm font-medium" :class="i <= currentIndex ? '' : 'text-muted-foreground'">
                    {{ step.label }}
                </p>
                <p v-if="i === currentIndex" class="text-xs text-muted-foreground">Tahap saat ini</p>
                <p v-else-if="i < currentIndex" class="text-xs text-success">Selesai</p>
            </div>
        </li>
    </ol>
</template>
