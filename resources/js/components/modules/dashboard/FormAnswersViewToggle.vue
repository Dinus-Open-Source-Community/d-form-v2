<script setup lang="ts">
import { LayoutGrid, List } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import type { FormAnswerMobileView } from '@/hooks/useFormAnswerMobileView';

defineProps<{ view: FormAnswerMobileView }>();
const emit = defineEmits<{ change: [view: FormAnswerMobileView] }>();
</script>

<template>
    <div
        class="bg-muted/40 inline-flex rounded-xl border border-border/70 p-1 md:hidden"
        role="group"
        aria-label="Tampilan daftar jawaban"
    >
        <Button
            type="button"
            variant="ghost"
            class="h-10 min-w-10 gap-2 px-3 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
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
            class="h-10 min-w-10 gap-2 px-3 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
            :class="view === 'list' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'"
            :aria-pressed="view === 'list'"
            @click="emit('change', 'list')"
        >
            <List class="size-4" aria-hidden="true" />
            Daftar
        </Button>
    </div>
</template>
