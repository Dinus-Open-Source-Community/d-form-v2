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
} from '@/components/ui/alert-dialog'

defineProps<{
    open: boolean
    title: string
    description: string
    confirmText?: string
    cancelText?: string
    variant?: 'default' | 'destructive'
    loading?: boolean
}>()

const emit = defineEmits<{
    confirm: []
    cancel: []
    'update:open': [value: boolean]
}>()
</script>

<template>
    <AlertDialog :open="open" @update:open="(v) => emit('update:open', v)">
        <AlertDialogContent class="rounded-2xl">
            <AlertDialogHeader>
                <AlertDialogTitle class="font-display text-xl font-bold tracking-[-0.02em]">{{ title }}</AlertDialogTitle>
                <AlertDialogDescription class="text-sm leading-relaxed text-muted-foreground">{{ description }}</AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter class="gap-2">
                <AlertDialogCancel @click="emit('cancel')">
                    {{ cancelText ?? 'Cancel' }}
                </AlertDialogCancel>
                <AlertDialogAction
                    :class="variant === 'destructive' ? 'bg-destructive text-destructive-foreground hover:bg-destructive/90 border-destructive/30' : ''"
                    :disabled="loading"
                    @click="emit('confirm')"
                >
                    {{ confirmText ?? 'Continue' }}
                </AlertDialogAction>
            </AlertDialogFooter>
        </AlertDialogContent>
    </AlertDialog>
</template>
