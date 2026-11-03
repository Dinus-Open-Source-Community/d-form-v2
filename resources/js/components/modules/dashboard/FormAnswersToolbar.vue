<script setup lang="ts">
import FormAnswersViewToggle from '@/components/modules/dashboard/FormAnswersViewToggle.vue';
import type { IFormAnswersViewOption, TFormAnswersReviewFilter, TFormAnswersView } from '@/types/formAnswersView';

const props = defineProps<{
    query: string;
    status: TFormAnswersReviewFilter;
    resultCount: number;
    view: TFormAnswersView;
    viewOptions: IFormAnswersViewOption[];
}>();

const emit = defineEmits<{
    'update:query': [value: string];
    'update:status': [value: TFormAnswersReviewFilter];
    'change:view': [value: TFormAnswersView];
}>();

const statusOptions: { value: TFormAnswersReviewFilter; label: string }[] = [
    { value: 'all', label: 'Semua' },
    { value: 'pending', label: 'Menunggu' },
    { value: 'accepted', label: 'Diterima' },
    { value: 'rejected', label: 'Ditolak' },
];

function onQueryInput(event: Event): void {
    if (!(event.target instanceof HTMLInputElement)) return;
    emit('update:query', event.target.value);
}
</script>

<template>
    <div
        class="border-border/60 flex flex-col gap-3 border-b px-4 py-3 sm:px-5 lg:flex-row lg:items-center lg:justify-between"
    >
        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-center">
            <label
                class="border-input bg-background flex min-w-0 flex-1 items-center gap-2 rounded-lg border px-3 py-2 sm:min-w-[250px]"
            >
                <span class="text-muted-foreground" aria-hidden="true">⌕</span>
                <span class="sr-only">Cari nama atau email</span>
                <input
                    type="search"
                    :value="props.query"
                    placeholder="Cari nama atau email"
                    class="placeholder:text-muted-foreground min-w-0 flex-1 bg-transparent text-sm outline-none"
                    @input="onQueryInput"
                />
            </label>
            <div class="flex flex-wrap items-center gap-1" role="group" aria-label="Filter status">
                <button
                    v-for="option in statusOptions"
                    :key="option.value"
                    type="button"
                    class="focus-visible:ring-ring rounded-lg px-3 py-2 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-offset-2"
                    :class="
                        props.status === option.value
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-muted'
                    "
                    :aria-pressed="props.status === option.value"
                    @click="emit('update:status', option.value)"
                >
                    {{ option.label }}
                </button>
            </div>
        </div>
        <div class="flex items-center justify-between gap-3 sm:justify-end">
            <span class="text-muted-foreground text-xs">{{ props.resultCount }} jawaban</span>
            <FormAnswersViewToggle
                :view="props.view"
                :options="props.viewOptions"
                @change="emit('change:view', $event)"
            />
        </div>
    </div>
</template>
