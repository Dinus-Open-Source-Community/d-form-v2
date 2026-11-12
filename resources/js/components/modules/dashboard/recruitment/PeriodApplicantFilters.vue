<script setup lang="ts">
import { Input } from '@/components/ui/input';
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select';

const search = defineModel<string>('search', { required: true });
const divisionId = defineModel<string>('divisionId', { required: true });
const queue = defineModel<string>('queue', { required: true });
const stage = defineModel<string>('stage', { required: true });
const semester = defineModel<string>('semester', { required: true });
defineProps<{
    divisionOptions: SimpleSelectOption[];
    queueOptions: SimpleSelectOption[];
    stageOptions: SimpleSelectOption[];
    semesterOptions: SimpleSelectOption[];
}>();
</script>
<template>
    <div class="flex flex-wrap items-end gap-3">
        <Input v-model="search" placeholder="Cari nama, NIM, nomor pendaftaran..." class="max-w-xs" />
        <div class="flex min-w-0 flex-col gap-1.5">
            <SimpleSelect
                v-model="divisionId"
                :options="divisionOptions"
                id="filter-divisi"
                class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                aria-label="Filter divisi"
            />
        </div>
        <div class="flex min-w-0 flex-col gap-1.5">
            <SimpleSelect
                v-model="queue"
                :options="queueOptions"
                id="filter-antrean"
                class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                aria-label="Filter antrean"
            />
        </div>
        <div v-if="!queue" class="flex min-w-0 flex-col gap-1.5">
            <SimpleSelect
                v-model="stage"
                :options="stageOptions"
                id="filter-tahap"
                class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                aria-label="Filter tahap"
            />
        </div>
        <div class="flex min-w-0 flex-col gap-1.5">
            <SimpleSelect
                v-model="semester"
                :options="semesterOptions"
                id="filter-semester"
                class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                aria-label="Filter semester"
            />
        </div>
    </div>
</template>
