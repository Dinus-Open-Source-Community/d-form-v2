<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { TFormReviewDecision } from '@/types/forms';

const props = defineProps<{
    open: boolean;
    selectedCount: number;
    decision: TFormReviewDecision | null;
    loading: boolean;
    error: string | null;
}>();

const emit = defineEmits<{
    confirm: [];
    cancel: [];
    'update:open': [open: boolean];
}>();

const isRejecting = computed<boolean>(() => props.decision === 'rejected');
const title = computed<string>(() =>
    isRejecting.value
        ? `Tolak ${props.selectedCount} jawaban terpilih?`
        : `Terima ${props.selectedCount} jawaban terpilih?`
);
const confirmLabel = computed<string>(() => (isRejecting.value ? 'Tolak jawaban' : 'Terima jawaban'));

function onOpenChange(open: boolean): void {
    emit('update:open', open);
    if (!open && !props.loading) emit('cancel');
}
</script>

<template>
    <Dialog :open="open" @update:open="onOpenChange">
        <DialogContent
            class="max-h-[calc(100dvh-2rem)] gap-5 overflow-y-auto rounded-2xl p-5 sm:max-w-md sm:p-6"
            :show-close-button="false"
            :aria-busy="loading"
        >
            <DialogHeader class="text-left">
                <DialogTitle class="font-display text-xl font-bold tracking-[-0.02em]">{{ title }}</DialogTitle>
                <DialogDescription class="text-sm leading-relaxed">
                    Hanya jawaban yang dipilih yang akan diproses.
                </DialogDescription>
            </DialogHeader>

            <p
                v-if="error"
                role="alert"
                class="text-destructive border-destructive/30 bg-destructive/5 rounded-lg border px-3 py-2 text-sm"
            >
                {{ error }}
            </p>
            <p v-if="loading" role="status" aria-live="polite" class="text-muted-foreground text-sm">
                Sedang memproses jawaban terpilih.
            </p>

            <DialogFooter class="flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-10"
                    :disabled="loading"
                    @click="onOpenChange(false)"
                >
                    Batal
                </Button>
                <Button
                    type="button"
                    class="min-h-10"
                    :variant="isRejecting ? 'destructive' : 'default'"
                    :disabled="loading"
                    @click="emit('confirm')"
                >
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
