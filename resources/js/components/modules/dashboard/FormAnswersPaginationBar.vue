<script setup lang="ts">
const props = defineProps<{
    page: number;
    pageCount: number;
    pageSize: number;
    rangeLabel: string;
}>();

const emit = defineEmits<{
    'change:page': [value: number];
    'change:pageSize': [value: number];
}>();

function onPageSizeChange(event: Event): void {
    if (!(event.target instanceof HTMLSelectElement)) return;
    const next = Number.parseInt(event.target.value, 10);
    if (Number.isNaN(next)) return;
    emit('change:pageSize', next);
}
</script>

<template>
    <div
        class="border-border/60 text-muted-foreground flex flex-col gap-3 border-t px-4 py-3 text-xs sm:flex-row sm:items-center sm:justify-between sm:px-5"
    >
        <span>{{ props.rangeLabel }}</span>
        <div class="flex items-center justify-between gap-3 sm:justify-end">
            <label class="flex items-center gap-2">
                <span>Per halaman</span>
                <select
                    :value="props.pageSize"
                    aria-label="Ukuran halaman"
                    class="border-input bg-background text-foreground focus-visible:ring-ring rounded-md border px-2 py-1.5 text-xs outline-none focus-visible:ring-2"
                    @change="onPageSizeChange"
                >
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </label>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="border-input bg-background text-foreground hover:bg-muted rounded-md border px-3 py-1.5 transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="props.page <= 1"
                    @click="emit('change:page', props.page - 1)"
                >
                    Sebelumnya
                </button>
                <button
                    type="button"
                    class="border-input bg-background text-foreground hover:bg-muted rounded-md border px-3 py-1.5 transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="props.page >= props.pageCount"
                    @click="emit('change:page', props.page + 1)"
                >
                    Berikutnya
                </button>
            </div>
        </div>
    </div>
</template>
