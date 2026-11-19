<script setup lang="ts">
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

defineProps<{
    open: boolean;
    title: string;
    description: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'default' | 'destructive';
    loading?: boolean;
    /** Nonaktifkan tombol konfirmasi di luar state loading (mis. gate ketik judul). */
    confirmDisabled?: boolean;
    /** `data-testid` tombol konfirmasi untuk alur yang diuji browser. */
    confirmTestId?: string;
}>();

const emit = defineEmits<{
    confirm: [];
    cancel: [];
    'update:open': [value: boolean];
}>();
</script>

<template>
    <AlertDialog :open="open" @update:open="(v) => emit('update:open', v)">
        <AlertDialogContent :disable-outside-pointer-events="false" class="pointer-events-auto rounded-2xl">
            <AlertDialogHeader>
                <AlertDialogTitle class="font-display text-xl font-bold tracking-[-0.02em]">{{
                    title
                }}</AlertDialogTitle>
                <AlertDialogDescription class="text-muted-foreground text-sm leading-relaxed">{{
                    description
                }}</AlertDialogDescription>
                <slot name="extra" />
            </AlertDialogHeader>
            <AlertDialogFooter class="gap-2">
                <AlertDialogCancel type="button" @click="emit('cancel')">
                    {{ cancelText ?? 'Cancel' }}
                </AlertDialogCancel>
                <AlertDialogAction
                    type="button"
                    :data-testid="confirmTestId"
                    :class="
                        variant === 'destructive'
                            ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90 border-destructive/30'
                            : ''
                    "
                    :disabled="loading === true || confirmDisabled === true"
                    @click="emit('confirm')"
                >
                    {{ confirmText ?? 'Continue' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
