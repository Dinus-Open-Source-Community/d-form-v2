<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Check, RefreshCw, Search, X } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { canApplyCourseSelection, missingCourseUids } from '@/lib/duCourseSelection';
import type { IDuCourseOption } from '@/types/du';

const props = withDefaults(
    defineProps<{
        open: boolean;
        courses: IDuCourseOption[];
        selectedUids: string[];
        loading?: boolean;
        error?: string;
        syncing?: boolean;
        /** Waktu sinkron terakhir, mis. "26 Nov 2026, 14.30". */
        syncedAtLabel?: string;
    }>(),
    { loading: false, error: '', syncing: false, syncedAtLabel: '' }
);

const emit = defineEmits<{
    close: [];
    apply: [uids: string[]];
    sync: [];
}>();

const search = ref('');
const draft = ref<string[]>([]);

/** Setiap pembukaan mulai dari pilihan tersimpan; draf dibuang saat dialog ditutup. */
watch(
    () => props.open,
    (open) => {
        if (!open) return;

        search.value = '';
        draft.value = [...props.selectedUids];
    },
    { immediate: true }
);

const catalogUsable = computed<boolean>(() => canApplyCourseSelection(props.loading, props.error));

/** UID pada draf yang tidak ada di katalog aktif: tetap tampil agar bisa dihapus. */
const missingUids = computed<string[]>(() =>
    catalogUsable.value ? missingCourseUids(draft.value, props.courses) : []
);

const filteredCourses = computed<IDuCourseOption[]>(() => {
    const query = search.value.trim().toLowerCase();

    if (query === '') return props.courses;

    return props.courses.filter(
        (course) => course.title.toLowerCase().includes(query) || course.uid.toLowerCase().includes(query)
    );
});

const hasQuery = computed<boolean>(() => search.value.trim() !== '');

function isSelected(uid: string): boolean {
    return draft.value.includes(uid);
}

function toggleCourse(uid: string): void {
    draft.value = isSelected(uid) ? draft.value.filter((value) => value !== uid) : [...draft.value, uid];
}

/** Pilih hasil filter tanpa membuang pilihan lain yang tidak terlihat. */
function selectFilteredCourses(): void {
    draft.value = [...new Set([...draft.value, ...filteredCourses.value.map((course) => course.uid)])];
}

function clearAll(): void {
    draft.value = [];
}

function removeMissingUid(uid: string): void {
    draft.value = draft.value.filter((value) => value !== uid);
}

function submit(): void {
    if (!catalogUsable.value) return;

    emit('apply', [...draft.value]);
}
</script>

<template>
    <Dialog :open="props.open" @update:open="(value: boolean) => value || emit('close')">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="font-display text-xl font-bold tracking-[-0.02em]">Pilih kelas DU</DialogTitle>
                <DialogDescription class="text-muted-foreground text-sm">
                    Hanya kelas yang dipilih di sini yang tampil di form untuk peserta.
                </DialogDescription>
            </DialogHeader>

            <div class="flex flex-col gap-3">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <InputGroup class="h-11 flex-1">
                        <InputGroupAddon align="inline-start" class="pl-3">
                            <Search class="text-muted-foreground size-4" aria-hidden="true" />
                        </InputGroupAddon>
                        <InputGroupInput
                            type="search"
                            aria-label="Cari kelas DU"
                            placeholder="Cari kelas DU…"
                            class="h-11"
                            :model-value="search"
                            @update:model-value="(value: string | number) => (search = String(value))"
                        />
                    </InputGroup>
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11 shrink-0 gap-1.5 text-xs font-bold"
                        :disabled="props.syncing"
                        :aria-busy="props.syncing"
                        @click="emit('sync')"
                    >
                        <RefreshCw
                            class="size-3.5"
                            :class="props.syncing ? 'animate-spin motion-reduce:animate-none' : ''"
                            aria-hidden="true"
                        />
                        {{ props.syncing ? 'Menyinkronkan…' : 'Sync kelas' }}
                    </Button>
                </div>

                <p v-if="props.loading" class="text-muted-foreground text-xs" role="status">Memuat katalog kelas DU…</p>
                <p v-else-if="props.error" role="alert" class="text-destructive text-xs">{{ props.error }}</p>

                <template v-else>
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-muted-foreground text-[11px] tabular-nums" role="status">
                            {{ filteredCourses.length }} dari {{ props.courses.length }} kelas
                            <span v-if="props.syncedAtLabel">· Terakhir sinkron {{ props.syncedAtLabel }}</span>
                        </p>
                        <Button
                            v-if="hasQuery && filteredCourses.length > 0"
                            type="button"
                            variant="ghost"
                            size="sm"
                            class="text-xs font-bold"
                            @click="selectFilteredCourses"
                        >
                            Tambah semua hasil
                        </Button>
                    </div>

                    <ul
                        v-if="missingUids.length > 0"
                        class="flex flex-col gap-2"
                        aria-label="Kelas terpilih yang tidak ada di katalog aktif"
                    >
                        <li v-for="uid in missingUids" :key="uid">
                            <div
                                class="border-border bg-muted/30 flex min-h-11 items-center justify-between gap-3 rounded-xl border border-dashed px-3 py-2"
                            >
                                <span class="min-w-0">
                                    <span class="text-muted-foreground block text-sm font-medium">
                                        Kelas tidak lagi aktif
                                    </span>
                                    <span class="text-muted-foreground block text-[11px] break-words">{{ uid }}</span>
                                </span>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    class="size-11 shrink-0"
                                    :aria-label="`Hapus kelas ${uid}`"
                                    @click="removeMissingUid(uid)"
                                >
                                    <X class="size-4" aria-hidden="true" />
                                </Button>
                            </div>
                        </li>
                    </ul>

                    <ul v-if="filteredCourses.length > 0" class="flex max-h-[45vh] flex-col gap-2 overflow-y-auto pr-1">
                        <li v-for="course in filteredCourses" :key="course.uid">
                            <button
                                type="button"
                                class="focus-visible:ring-ring/30 flex min-h-11 w-full cursor-pointer items-center justify-between gap-3 rounded-xl border px-3 py-2.5 text-left transition-colors focus-visible:ring-[3px] focus-visible:outline-none"
                                :class="
                                    isSelected(course.uid)
                                        ? 'border-primary bg-primary/10'
                                        : 'border-border bg-background hover:border-primary/40'
                                "
                                :aria-pressed="isSelected(course.uid)"
                                @click="toggleCourse(course.uid)"
                            >
                                <span class="text-foreground min-w-0 text-sm font-medium break-words">
                                    {{ course.title }}
                                </span>
                                <Check
                                    v-if="isSelected(course.uid)"
                                    class="text-primary size-4 shrink-0"
                                    aria-hidden="true"
                                />
                            </button>
                        </li>
                    </ul>

                    <p v-else class="text-muted-foreground text-xs">
                        {{ hasQuery ? 'Tidak ada kelas yang cocok.' : 'Belum ada kelas DU di katalog aktif.' }}
                    </p>
                </template>
            </div>

            <DialogFooter class="gap-2 sm:justify-between">
                <div class="flex items-center gap-1">
                    <span class="text-muted-foreground text-xs tabular-nums">{{ draft.length }} dipilih</span>
                    <Button
                        v-if="draft.length > 0"
                        type="button"
                        variant="ghost"
                        size="sm"
                        class="text-xs font-bold"
                        @click="clearAll"
                    >
                        Kosongkan
                    </Button>
                </div>
                <Button type="button" class="h-11" :disabled="!catalogUsable" @click="submit">Simpan</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
