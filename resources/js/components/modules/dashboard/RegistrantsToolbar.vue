<script setup lang="ts">
import { computed } from 'vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select';
import { Search } from 'lucide-vue-next';
import { REGISTRANTS_TAB_ITEMS } from '@/lib/registrantsUi';

const searchQuery = defineModel<string>('searchQuery', { required: true });
const activeStatusTab = defineModel<'all' | 'pending' | 'accepted' | 'rejected'>('activeStatusTab', {
    required: true,
});
const activeFormFilter = defineModel<string>('activeFormFilter', { required: true });

const props = defineProps<{
    statusCounts: Record<'all' | 'pending' | 'accepted' | 'rejected', number>;
    forms: { id: string; title: string }[];
}>();

const formFilterOptions = computed<SimpleSelectOption[]>(() => [
    { value: 'all', label: 'Semua formulir' },
    ...props.forms.map((f) => ({ value: f.id, label: f.title })),
]);
</script>

<template>
    <Card class="border-border/70 rounded-2xl shadow-sm ring-1 ring-black/[0.03] dark:ring-white/[0.06]">
        <CardHeader class="pb-3">
            <CardTitle class="text-base font-medium">Filter dan pencarian</CardTitle>
            <CardDescription class="text-sm">
                Pilih status review, batasi ke satu formulir, atau cari berdasarkan nama, email, kode registrasi, atau
                judul form.
            </CardDescription>
        </CardHeader>
        <CardContent class="flex flex-col gap-5 pt-0">
            <Tabs v-model="activeStatusTab" class="w-full" aria-label="Filter status pendaftar">
                <TabsList
                    class="bg-muted/50 flex h-auto min-h-10 w-full flex-wrap gap-1 rounded-xl p-1 sm:inline-flex sm:w-auto"
                >
                    <TabsTrigger
                        v-for="t in REGISTRANTS_TAB_ITEMS"
                        :key="t.value"
                        :value="t.value"
                        class="data-[state=active]:bg-card rounded-lg px-3 py-2 text-xs font-medium data-[state=active]:shadow-sm sm:px-3.5"
                    >
                        {{ t.label }}
                        <span
                            :class="[
                                'ml-1.5 rounded-full px-1.5 py-0.5 text-[10px] font-semibold tabular-nums',
                                t.value === 'pending' && 'bg-warning/20 text-warning-foreground',
                                t.value === 'accepted' && 'bg-success/15 text-success',
                                t.value === 'rejected' && 'bg-destructive/12 text-destructive',
                                t.value === 'all' && 'bg-muted text-muted-foreground',
                            ]"
                        >
                            {{ statusCounts[t.value] }}
                        </span>
                    </TabsTrigger>
                </TabsList>
            </Tabs>

            <div class="border-border/60 flex flex-col gap-4 border-t pt-4 sm:flex-row sm:items-end sm:gap-4">
                <div v-if="forms.length > 0" class="w-full shrink-0 sm:max-w-xs">
                    <Label for="registrants-form-filter" class="text-muted-foreground text-xs font-semibold">
                        Formulir
                    </Label>
                    <SimpleSelect
                        v-model="activeFormFilter"
                        :options="formFilterOptions"
                        id="registrants-form-filter"
                        class="border-border/80 bg-background/80 mt-1.5 h-10 w-full text-xs sm:text-sm"
                        aria-label="Formulir"
                    />
                </div>

                <div class="relative min-w-0 flex-1">
                    <Label for="registrants-search" class="text-muted-foreground text-xs font-semibold"> Cari </Label>
                    <div class="relative mt-1.5">
                        <Search
                            class="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                            aria-hidden="true"
                        />
                        <Input
                            id="registrants-search"
                            v-model="searchQuery"
                            type="search"
                            placeholder="Contoh: nama peserta, email, kode registrasi, judul form…"
                            class="h-10 w-full pl-9"
                            autocomplete="off"
                        />
                    </div>
                </div>
            </div>
        </CardContent>
    </Card>
</template>
