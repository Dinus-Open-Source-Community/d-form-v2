<script setup lang="ts">
import { LayoutGrid, List } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import type { TFormAnswerMobileView } from '@/types/formAnswerMobileView';

defineProps<{ view: TFormAnswerMobileView }>();
const emit = defineEmits<{ change: [view: TFormAnswerMobileView] }>();
</script>

<template>
    <div
        class="bg-muted/40 border-border/70 inline-flex rounded-xl border p-1 md:hidden"
        role="group"
        aria-label="Tampilan daftar jawaban"
    >
        <Button
            type="button"
            variant="ghost"
            class="focus-visible:ring-ring h-10 min-w-10 gap-2 px-3 focus-visible:ring-2 focus-visible:ring-offset-2"
            :class="view === 'card' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'"
            :aria-pressed="view === 'card'"
            @click="emit('change', 'card')"
        >
            <LayoutGrid class="size-4" aria-hidden="true" />
            Kartu
        </Button>
        <Button
            type="button"
            variant="ghost"
            class="focus-visible:ring-ring h-10 min-w-10 gap-2 px-3 focus-visible:ring-2 focus-visible:ring-offset-2"
            :class="view === 'list' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'"
            :aria-pressed="view === 'list'"
            @click="emit('change', 'list')"
        >
            <List class="size-4" aria-hidden="true" />
            Daftar
        </Button>
    </div>
</template>
