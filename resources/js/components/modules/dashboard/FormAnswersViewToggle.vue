<script setup lang="ts">
import { LayoutGrid, List } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import type { IFormAnswersViewOption, TFormAnswersView } from '@/types/formAnswersView';

withDefaults(
    defineProps<{
        view: TFormAnswersView;
        options: IFormAnswersViewOption[];
        label?: string;
    }>(),
    { label: 'Tampilan daftar jawaban' }
);
const emit = defineEmits<{ change: [view: TFormAnswersView] }>();

function iconFor(value: TFormAnswersView): typeof LayoutGrid {
    return value === 'table' ? List : LayoutGrid;
}
</script>

<template>
    <div class="bg-muted/40 border-border/70 inline-flex rounded-xl border p-1" role="group" :aria-label="label">
        <Button
            v-for="option in options"
            :key="option.value"
            type="button"
            variant="ghost"
            class="focus-visible:ring-ring h-10 min-w-10 gap-2 px-3 focus-visible:ring-2 focus-visible:ring-offset-2"
            :class="view === option.value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'"
            :aria-pressed="view === option.value"
            :aria-label="option.label"
            @click="emit('change', option.value)"
        >
            <component :is="iconFor(option.value)" class="size-4" aria-hidden="true" />
            {{ option.label }}
        </Button>
    </div>
</template>
