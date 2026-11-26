<script setup lang="ts">
import { copyTextToClipboard } from '@/adapters/browser/clipboard';
import CopyFeedbackIcon from '@/components/core/CopyFeedbackIcon.vue';
import { Button } from '@/components/ui/button';
import { useCopyFeedback } from '@/hooks/useCopyFeedback';
import { useToastNotifications } from '@/hooks/useToastNotifications';

const props = withDefaults(
    defineProps<{
        code: string;
        disabled?: boolean;
    }>(),
    { disabled: false }
);

const feedback = useCopyFeedback();
const { showSuccessToast } = useToastNotifications();

/** Salin kode ke clipboard; centang dan toast hanya muncul saat salin benar-benar berhasil. */
async function copyCode(): Promise<void> {
    if (!(await copyTextToClipboard(props.code))) return;

    feedback.trigger();
    showSuccessToast('Kode referral disalin.', { id: 'form-referral-copy' });
}
</script>

<template>
    <Button
        type="button"
        variant="ghost"
        size="icon-sm"
        class="text-muted-foreground hover:text-foreground shrink-0 cursor-pointer rounded-md"
        :aria-label="`Salin kode ${props.code}`"
        :data-copy-feedback="feedback.active.value ? 'confirmed' : 'idle'"
        :disabled="props.disabled"
        @click="copyCode"
    >
        <CopyFeedbackIcon :confirmed="feedback.active.value" icon-class="size-4" />
    </Button>
</template>
