<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { screeningReasons } from '@/lib/dummyRecruitment';
import type { IApplication, RecruitScreeningDecision } from '@/types/recruitment';
import { computed, ref } from 'vue';

const props = defineProps<{ application: IApplication }>();

const emit = defineEmits<{
    decision: [decision: RecruitScreeningDecision, reason: string, notes: string];
}>();

const decision = ref<RecruitScreeningDecision | ''>('');
const reason = ref('');
const notes = ref('');

const needsReason = computed(() => decision.value === 'revision' || decision.value === 'reject');
const canSubmit = computed(() => decision.value !== '' && (!needsReason.value || reason.value !== ''));

function pick(value: RecruitScreeningDecision): void {
    decision.value = value;
    if (value === 'pass') {
        reason.value = '';
        notes.value = '';
    }
}

function submit(): void {
    if (!canSubmit.value) {
        return;
    }
    emit('decision', decision.value as RecruitScreeningDecision, reason.value, notes.value);
}
</script>

<template>
    <div class="space-y-4">
        <div>
            <h3 class="font-semibold">Keputusan Screening</h3>
            <p class="text-muted-foreground text-sm">
                {{ props.application.applicant.fullName }} · {{ props.application.registrationNumber }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                :variant="decision === 'pass' ? 'default' : 'outline'"
                :class="decision === 'pass' ? 'bg-success/15 text-success border-success/40 hover:bg-success/25' : ''"
                @click="pick('pass')"
            >
                Pass
            </Button>
            <Button
                type="button"
                :variant="decision === 'revision' ? 'default' : 'outline'"
                :class="decision === 'revision' ? 'bg-warning/15 text-warning border-warning/40 hover:bg-warning/25' : ''"
                @click="pick('revision')"
            >
                Revision
            </Button>
            <Button
                type="button"
                :variant="decision === 'reject' ? 'destructive' : 'outline'"
                @click="pick('reject')"
            >
                Reject
            </Button>
        </div>

        <div v-if="needsReason" class="space-y-3 rounded-lg border border-border p-4">
            <div class="space-y-2">
                <Label>Alasan <span class="text-destructive">*</span></Label>
                <Select v-model="reason">
                    <SelectTrigger><SelectValue placeholder="Pilih alasan" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem v-for="r in screeningReasons" :key="r" :value="r">{{ r }}</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div class="space-y-2">
                <Label for="notes">Catatan (opsional)</Label>
                <Textarea id="notes" v-model="notes" rows="2" placeholder="Catatan internal untuk tim" />
            </div>
        </div>

        <Button type="button" class="w-full" :disabled="!canSubmit" @click="submit">
            Simpan Keputusan
        </Button>
    </div>
</template>
