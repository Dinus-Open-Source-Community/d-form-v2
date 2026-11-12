<script setup lang="ts">
import { computed } from 'vue';
import { Check, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import { FORM_ANSWER_BULK_REVIEW_MAX_SELECTION } from '@/constants/formAnswerReview';
import type { TFormReviewDecision } from '@/types/forms';

const props = defineProps<{
    selectedCount: number;
    ineligibleCount: number;
    submitting: boolean;
    open: boolean;
    decision: TFormReviewDecision | null;
}>();

const emit = defineEmits<{
    review: [decision: TFormReviewDecision];
    confirm: [];
    cancel: [];
    clear: [];
    'open-change': [open: boolean];
}>();

const exceedsSelectionLimit = computed<boolean>(() => props.selectedCount > FORM_ANSWER_BULK_REVIEW_MAX_SELECTION);

const actionsBlocked = computed<boolean>(
    () => props.submitting || exceedsSelectionLimit.value || props.ineligibleCount > 0
);

const hint = computed<string | null>(() => {
    if (exceedsSelectionLimit.value) {
        return `Maksimal ${FORM_ANSWER_BULK_REVIEW_MAX_SELECTION} jawaban sekali proses.`;
    }

    if (props.ineligibleCount > 0) {
        return `${props.ineligibleCount} jawaban terpilih tidak bisa diproses massal (hanya pendaftaran tunggal yang menunggu review).`;
    }

    return null;
});

const isRejectDecision = computed<boolean>(() => props.decision === 'rejected');
const confirmTitle = computed<string>(() =>
    isRejectDecision.value ? `Tolak ${props.selectedCount} jawaban?` : `Terima ${props.selectedCount} jawaban?`
);
const confirmDescription = computed<string>(() =>
    isRejectDecision.value
        ? 'Semua jawaban terpilih akan ditandai ditolak dan setiap pengirim menerima email notifikasi.'
        : 'Semua jawaban terpilih akan ditandai diterima dan setiap pengirim menerima email notifikasi.'
);
const confirmLabel = computed<string>(() => (isRejectDecision.value ? 'Tolak semua' : 'Terima semua'));
</script>

<template>
    <div>
        <div
            v-if="selectedCount > 0"
            data-testid="form-answer-bulk-actions"
            role="region"
            aria-label="Aksi review massal"
            class="border-border/60 bg-primary/5 flex flex-col items-stretch gap-3 border-b px-4 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-4 sm:px-5"
        >
            <p class="text-foreground text-sm font-semibold" aria-live="polite">{{ selectedCount }} jawaban dipilih</p>
            <div class="grid w-full grid-cols-2 gap-2 sm:ml-auto sm:flex sm:w-auto sm:shrink-0 sm:items-center">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    class="border-success/35 text-success hover:bg-success/10 hover:text-success h-10 w-full gap-1.5 text-sm font-medium sm:w-auto sm:flex-none"
                    :disabled="actionsBlocked"
                    aria-label="Terima semua jawaban yang dipilih"
                    @click="emit('review', 'accepted')"
                >
                    <Check class="size-4" aria-hidden="true" />
                    Terima semua
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    class="border-destructive/35 text-destructive hover:bg-destructive/10 hover:text-destructive h-10 w-full gap-1.5 text-sm font-medium sm:w-auto sm:flex-none"
                    :disabled="actionsBlocked"
                    aria-label="Tolak semua jawaban yang dipilih"
                    @click="emit('review', 'rejected')"
                >
                    <X class="size-4" aria-hidden="true" />
                    Tolak semua
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    class="border-border/60 text-muted-foreground hover:bg-accent hover:text-foreground col-span-2 h-10 w-full justify-center font-medium sm:col-span-1 sm:h-10 sm:w-auto sm:flex-none"
                    :disabled="submitting"
                    @click="emit('clear')"
                >
                    Batalkan pilihan
                </Button>
            </div>
            <p v-if="hint" class="text-muted-foreground w-full text-xs leading-relaxed sm:basis-full">{{ hint }}</p>
        </div>

        <ConfirmationModal
            :open="open"
            :title="confirmTitle"
            :description="confirmDescription"
            :confirm-text="confirmLabel"
            :variant="isRejectDecision ? 'destructive' : 'default'"
            :loading="submitting"
            @confirm="emit('confirm')"
            @cancel="emit('cancel')"
            @update:open="(value: boolean) => emit('open-change', value)"
        />
    </div>
</template>
