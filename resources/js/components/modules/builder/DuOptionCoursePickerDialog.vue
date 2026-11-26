<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { Check, Search } from 'lucide-vue-next';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import type { IDuCourseOption } from '@/types/du';

const props = withDefaults(
    defineProps<{
        open: boolean;
        optionValue: string;
        courses: IDuCourseOption[];
        selectedUid: string;
        loading?: boolean;
        error?: string;
    }>(),
    { loading: false, error: '' }
);

const emit = defineEmits<{
    close: [];
    select: [uid: string];
}>();

const search = ref('');
const listRef = ref<HTMLElement | null>(null);

const normalizedQuery = computed<string>(() => search.value.trim().toLowerCase());

const filteredCourses = computed<IDuCourseOption[]>(() => {
    const query = normalizedQuery.value;

    if (query === '') return props.courses;

    return props.courses.filter(
        (course) => course.title.toLowerCase().includes(query) || course.uid.toLowerCase().includes(query)
    );
});

/** Setiap pembukaan mulai dari pencarian kosong dan menggulir ke course terpilih. */
watch(
    () => props.open,
    (open) => {
        if (!open) return;

        search.value = '';
        void nextTick(() => {
            listRef.value?.querySelector('[data-selected="true"]')?.scrollIntoView({ block: 'center' });
        });
    }
);

function choose(course: IDuCourseOption): void {
    emit('select', course.uid);
}
</script>

<template>
    <Dialog :open="props.open" @update:open="(value: boolean) => value || emit('close')">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="font-display text-lg font-bold tracking-[-0.02em]">Pilih course DU</DialogTitle>
                <DialogDescription class="text-muted-foreground text-sm">
                    Untuk opsi <span class="text-foreground font-medium">{{ props.optionValue }}</span
                    >.
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-3">
                <InputGroup class="h-11">
                    <InputGroupAddon align="inline-start" class="pl-3">
                        <Search class="text-muted-foreground size-4" aria-hidden="true" />
                    </InputGroupAddon>
                    <InputGroupInput
                        type="search"
                        aria-label="Cari course DU"
                        placeholder="Cari course DU…"
                        class="h-11"
                        :model-value="search"
                        @update:model-value="(value: string | number) => (search = String(value))"
                    />
                </InputGroup>

                <p v-if="props.loading" class="text-muted-foreground text-xs" role="status">Memuat katalog kelas DU…</p>
                <p v-else-if="props.error" class="text-destructive text-xs" role="alert">{{ props.error }}</p>

                <template v-else>
                    <p class="text-muted-foreground text-[11px]" role="status">
                        {{ filteredCourses.length }} dari {{ props.courses.length }} course
                    </p>

                    <ul
                        v-if="filteredCourses.length > 0"
                        ref="listRef"
                        class="border-border max-h-72 overflow-y-auto rounded-xl border"
                    >
                        <li v-for="course in filteredCourses" :key="course.uid">
                            <button
                                type="button"
                                class="focus-visible:ring-ring/30 flex w-full cursor-pointer items-center justify-between gap-3 px-3 py-2.5 text-left transition-colors outline-none focus-visible:ring-[3px]"
                                :class="course.uid === props.selectedUid ? 'bg-primary/10' : 'hover:bg-muted/60'"
                                :data-selected="course.uid === props.selectedUid ? 'true' : undefined"
                                :aria-pressed="course.uid === props.selectedUid"
                                @click="choose(course)"
                            >
                                <span class="min-w-0">
                                    <span class="text-foreground block truncate text-sm font-medium">
                                        {{ course.title }}
                                    </span>
                                </span>
                                <Check
                                    v-if="course.uid === props.selectedUid"
                                    class="text-primary size-4 shrink-0"
                                    aria-hidden="true"
                                />
                            </button>
                        </li>
                    </ul>

                    <p
                        v-else
                        class="border-border text-muted-foreground rounded-xl border px-3 py-6 text-center text-xs"
                    >
                        Course DU tidak ditemukan.
                    </p>
                </template>
            </div>
        </DialogContent>
    </Dialog>
</template>
