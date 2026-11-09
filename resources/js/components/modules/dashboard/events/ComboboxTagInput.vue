<script setup lang="ts">
import { ref, computed, watch, nextTick, useId, onUnmounted } from 'vue';
import { X, ChevronDown, Plus } from 'lucide-vue-next';
import { onDocumentEvent } from '@/adapters/browser/domEvents';

export interface ITagSuggestion {
    value: string;
    label: string;
}

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        suggestions?: ITagSuggestion[];
        maxTags?: number;
        allowCustom?: boolean;
        placeholder?: string;
        disabled?: boolean;
        id?: string;
        /** True saat field punya error validasi: border/ring destructive + bg tint. */
        error?: boolean;
    }>(),
    {
        modelValue: '',
        suggestions: () => [],
        maxTags: Infinity,
        allowCustom: true,
        placeholder: 'Cari atau ketik…',
        disabled: false,
        error: false,
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const query = ref('');
const open = ref(false);
const inputRef = ref<HTMLInputElement | null>(null);
const containerRef = ref<HTMLDivElement | null>(null);
const highlightIndex = ref(-1);
const listboxId = useId();

const tags = computed<string[]>(() => {
    const raw = props.modelValue != null && props.modelValue !== '' ? String(props.modelValue).trim() : '';
    if (!raw) return [];
    if (!raw.includes(',')) return [raw];
    return raw
        .split(',')
        .map((s) => s.trim())
        .filter(Boolean);
});

const canAddMore = computed(() => tags.value.length < props.maxTags);

const filteredSuggestions = computed(() => {
    const q = query.value.toLowerCase().trim();
    const selected = new Set(tags.value);
    return props.suggestions.filter(
        (s) =>
            !selected.has(s.value) &&
            (q === '' || s.label.toLowerCase().includes(q) || s.value.toLowerCase().includes(q))
    );
});

const showCustomOption = computed(() => {
    if (!props.allowCustom) return false;
    const q = query.value.trim();
    if (!q) return false;
    const existing = props.suggestions.some(
        (s) => s.value.toLowerCase() === q.toLowerCase() || s.label.toLowerCase() === q.toLowerCase()
    );
    const alreadyTagged = tags.value.some((t) => t.toLowerCase() === q.toLowerCase());
    return !existing && !alreadyTagged;
});

const totalOptions = computed(() => filteredSuggestions.value.length + (showCustomOption.value ? 1 : 0));

const panelHint = computed(() => {
    if (totalOptions.value > 0) return null;
    const q = query.value.trim();
    if (props.suggestions.length === 0) {
        if (!props.allowCustom) return 'Tidak ada opsi.';
        return q ? null : 'Ketik nilai lalu Enter.';
    }
    if (q && !props.allowCustom) return 'Tidak ada yang cocok.';
    if (q && props.allowCustom) return null;
    if (!props.allowCustom) return 'Semua sudah dipilih.';
    return 'Semua dari daftar dipilih. Ketik untuk menambah.';
});

function syncTags(newTags: string[]) {
    if (newTags.length === 0) {
        emit('update:modelValue', '');
        return;
    }
    if (props.maxTags === 1 && newTags.length === 1) {
        emit('update:modelValue', newTags[0]);
        return;
    }
    emit('update:modelValue', newTags.join(','));
}

function suggestionMatchingQuery(): ITagSuggestion | undefined {
    const q = query.value.trim();
    if (!q) return undefined;
    const lower = q.toLowerCase();
    return props.suggestions.find((s) => s.value.toLowerCase() === lower || s.label.toLowerCase() === lower);
}

function addTag(value: string) {
    const trimmed = value.trim();
    if (!trimmed || !canAddMore.value) return;
    if (tags.value.some((t) => t.toLowerCase() === trimmed.toLowerCase())) return;
    if (!props.allowCustom) {
        const allowed = props.suggestions.some(
            (s) => s.value === trimmed || s.value.toLowerCase() === trimmed.toLowerCase()
        );
        if (!allowed) return;
    }
    syncTags([...tags.value, trimmed]);
    query.value = '';
    highlightIndex.value = -1;
    if (props.maxTags === 1) {
        open.value = false;
    }
}

function removeTag(index: number) {
    const next = [...tags.value];
    next.splice(index, 1);
    syncTags(next);
    nextTick(() => inputRef.value?.focus());
}

function labelFor(value: string): string {
    const match = props.suggestions.find((s) => s.value === value);
    return match?.label ?? value;
}

function toggleDropdown() {
    if (props.disabled) return;
    open.value = !open.value;
    if (open.value) {
        nextTick(() => inputRef.value?.focus());
    } else {
        highlightIndex.value = -1;
    }
}

function selectHighlighted() {
    const idx = highlightIndex.value;
    if (idx < filteredSuggestions.value.length) {
        addTag(filteredSuggestions.value[idx].value);
    } else if (showCustomOption.value) {
        addTag(query.value);
    }
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Backspace') {
        if (query.value === '' && tags.value.length > 0) {
            e.preventDefault();
            removeTag(tags.value.length - 1);
        }
        return;
    }

    if (e.key === ',' || e.key === 'Enter') {
        e.preventDefault();
        if (highlightIndex.value >= 0 && highlightIndex.value < totalOptions.value) {
            selectHighlighted();
        } else if (query.value.trim()) {
            if (!props.allowCustom) {
                const match = suggestionMatchingQuery();
                if (match) addTag(match.value);
            } else {
                addTag(query.value);
            }
        }
        return;
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault();
        open.value = true;
        if (totalOptions.value === 0) return;
        highlightIndex.value = Math.min(highlightIndex.value + 1, totalOptions.value - 1);
        return;
    }

    if (e.key === 'ArrowUp') {
        e.preventDefault();
        highlightIndex.value = Math.max(highlightIndex.value - 1, 0);
        return;
    }

    if (e.key === 'Escape') {
        open.value = false;
        highlightIndex.value = -1;
    }
}

function handleInputFocus() {
    if (!props.disabled) open.value = true;
}

function handleClickOutside(e: PointerEvent) {
    if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
        open.value = false;
        highlightIndex.value = -1;
    }
}

watch(query, () => {
    highlightIndex.value = -1;
    if (query.value && !open.value) open.value = true;
});

let releasePointerDown: () => void = () => undefined;

watch(open, (val) => {
    releasePointerDown();
    releasePointerDown = () => undefined;
    if (val) {
        releasePointerDown = onDocumentEvent('pointerdown', handleClickOutside);
    }
});

onUnmounted(() => releasePointerDown());
</script>

<template>
    <div ref="containerRef" class="relative">
        <div
            :class="[
                'flex min-h-9 items-stretch overflow-hidden rounded-lg border bg-white text-sm transition-colors',
                open
                    ? 'border-ring ring-ring/20 ring-2'
                    : props.error
                      ? 'border-destructive/70 bg-red-50 dark:bg-red-500/10'
                      : 'border-input hover:border-muted-foreground/25',
                disabled ? 'pointer-events-none opacity-50' : '',
            ]"
        >
            <div class="flex min-w-0 flex-1 flex-wrap items-center gap-1 px-2 py-1.5" @click="inputRef?.focus()">
                <span
                    v-for="(tag, idx) in tags"
                    :key="`${tag}-${idx}`"
                    class="bg-muted text-foreground inline-flex max-w-full items-center gap-0.5 rounded-md px-1.5 py-0.5 text-xs"
                >
                    <span class="truncate">{{ labelFor(tag) }}</span>
                    <button
                        type="button"
                        tabindex="-1"
                        :aria-label="`Hapus ${labelFor(tag)}`"
                        class="text-muted-foreground hover:bg-background hover:text-foreground inline-flex size-4 shrink-0 items-center justify-center rounded"
                        @click.stop="removeTag(idx)"
                    >
                        <X class="size-3" :stroke-width="2" />
                    </button>
                </span>

                <input
                    v-if="canAddMore"
                    :id="id"
                    ref="inputRef"
                    v-model="query"
                    type="text"
                    role="combobox"
                    :aria-expanded="open"
                    :aria-controls="listboxId"
                    :aria-autocomplete="suggestions.length ? 'list' : 'none'"
                    :placeholder="tags.length === 0 ? placeholder : ''"
                    :disabled="disabled"
                    autocomplete="off"
                    class="placeholder:text-muted-foreground min-w-[5.5rem] flex-1 bg-transparent text-sm outline-none"
                    @focus="handleInputFocus"
                    @keydown="handleKeydown"
                />
            </div>

            <button
                v-if="suggestions.length > 0"
                type="button"
                class="text-muted-foreground hover:bg-muted/50 hover:text-foreground flex w-9 shrink-0 items-center justify-center"
                :aria-expanded="open"
                :aria-controls="listboxId"
                aria-haspopup="listbox"
                title="Daftar"
                @click.stop="toggleDropdown"
            >
                <ChevronDown
                    class="size-4 transition-transform duration-200"
                    :class="open ? 'rotate-180' : ''"
                    aria-hidden="true"
                />
            </button>
        </div>

        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="translate-y-px opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-px opacity-0"
        >
            <div
                v-if="open"
                :id="listboxId"
                role="listbox"
                class="bg-popover absolute top-full left-0 z-50 mt-1 w-full overflow-hidden rounded-lg border py-1 shadow-md"
            >
                <div class="max-h-48 overflow-y-auto">
                    <template v-if="totalOptions > 0">
                        <button
                            v-for="(s, idx) in filteredSuggestions"
                            :key="s.value"
                            type="button"
                            role="option"
                            :class="[
                                'flex w-full px-3 py-2 text-left text-sm outline-none',
                                idx === highlightIndex ? 'bg-accent text-accent-foreground' : 'hover:bg-muted/70',
                            ]"
                            @pointerdown.prevent="addTag(s.value)"
                            @pointerenter="highlightIndex = idx"
                        >
                            {{ s.label }}
                        </button>

                        <button
                            v-if="showCustomOption"
                            type="button"
                            role="option"
                            :class="[
                                'flex w-full items-center gap-2 px-3 py-2 text-left text-sm outline-none',
                                filteredSuggestions.length === highlightIndex
                                    ? 'bg-accent text-accent-foreground'
                                    : 'hover:bg-muted/70',
                            ]"
                            @pointerdown.prevent="addTag(query)"
                            @pointerenter="highlightIndex = filteredSuggestions.length"
                        >
                            <Plus class="size-3.5 shrink-0 opacity-70" :stroke-width="2" aria-hidden="true" />
                            <span>Tambah "{{ query.trim() }}"</span>
                        </button>
                    </template>

                    <p v-else class="text-muted-foreground px-3 py-3 text-center text-xs">
                        {{ panelHint }}
                    </p>
                </div>
            </div>
        </Transition>
    </div>
</template>
