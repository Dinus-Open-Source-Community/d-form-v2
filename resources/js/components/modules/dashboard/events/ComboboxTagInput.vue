<script setup lang="ts">
import { ref, computed, watch, nextTick } from 'vue'
import { X, ChevronDown, Plus } from 'lucide-vue-next'

export interface TagSuggestion {
    value: string
    label: string
}

const props = withDefaults(defineProps<{
    modelValue?: string
    suggestions?: TagSuggestion[]
    maxTags?: number
    /** If false, only values from suggestions can be chosen (matches enum-only APIs). */
    allowCustom?: boolean
    placeholder?: string
    disabled?: boolean
    id?: string
}>(), {
    modelValue: '',
    suggestions: () => [],
    maxTags: Infinity,
    allowCustom: true,
    placeholder: 'Type or select…',
    disabled: false,
})

const emit = defineEmits<{
    'update:modelValue': [value: string]
}>()

const query = ref('')
const open = ref(false)
const inputRef = ref<HTMLInputElement | null>(null)
const containerRef = ref<HTMLDivElement | null>(null)
const highlightIndex = ref(-1)

const tags = computed<string[]>(() => {
    const raw = props.modelValue != null && props.modelValue !== '' ? String(props.modelValue).trim() : ''
    if (!raw) return []
    if (!raw.includes(',')) return [raw]
    return raw.split(',').map(s => s.trim()).filter(Boolean)
})

const canAddMore = computed(() => tags.value.length < props.maxTags)

const filteredSuggestions = computed(() => {
    const q = query.value.toLowerCase().trim()
    const selected = new Set(tags.value)
    return props.suggestions.filter(s =>
        !selected.has(s.value) &&
        (q === '' || s.label.toLowerCase().includes(q) || s.value.toLowerCase().includes(q))
    )
})

const showCustomOption = computed(() => {
    if (!props.allowCustom) return false
    const q = query.value.trim()
    if (!q) return false
    const existing = props.suggestions.some(s =>
        s.value.toLowerCase() === q.toLowerCase() || s.label.toLowerCase() === q.toLowerCase()
    )
    const alreadyTagged = tags.value.some(t => t.toLowerCase() === q.toLowerCase())
    return !existing && !alreadyTagged
})

const totalOptions = computed(() => filteredSuggestions.value.length + (showCustomOption.value ? 1 : 0))

function syncTags(newTags: string[]) {
    if (newTags.length === 0) {
        emit('update:modelValue', '')
        return
    }
    if (props.maxTags === 1 && newTags.length === 1) {
        emit('update:modelValue', newTags[0])
        return
    }
    emit('update:modelValue', newTags.join(','))
}

function suggestionMatchingQuery(): TagSuggestion | undefined {
    const q = query.value.trim()
    if (!q) return undefined
    const lower = q.toLowerCase()
    return props.suggestions.find(
        s => s.value.toLowerCase() === lower || s.label.toLowerCase() === lower,
    )
}

function addTag(value: string) {
    const trimmed = value.trim()
    if (!trimmed || !canAddMore.value) return
    if (tags.value.some(t => t.toLowerCase() === trimmed.toLowerCase())) return
    if (!props.allowCustom) {
        const allowed = props.suggestions.some(
            s => s.value === trimmed || s.value.toLowerCase() === trimmed.toLowerCase(),
        )
        if (!allowed) return
    }
    syncTags([...tags.value, trimmed])
    query.value = ''
    highlightIndex.value = -1
    if (props.maxTags === 1) {
        open.value = false
    }
}

function removeTag(index: number) {
    const next = [...tags.value]
    next.splice(index, 1)
    syncTags(next)
    nextTick(() => inputRef.value?.focus())
}

function labelFor(value: string): string {
    const match = props.suggestions.find(s => s.value === value)
    return match?.label ?? value
}

function handleKeydown(e: KeyboardEvent) {
    if (e.key === 'Backspace') {
        if (query.value === '' && tags.value.length > 0) {
            e.preventDefault()
            removeTag(tags.value.length - 1)
        }
        return
    }

    if (e.key === ',' || e.key === 'Enter') {
        e.preventDefault()
        if (highlightIndex.value >= 0 && highlightIndex.value < totalOptions.value) {
            selectHighlighted()
        } else if (query.value.trim()) {
            if (!props.allowCustom) {
                const match = suggestionMatchingQuery()
                if (match) addTag(match.value)
            } else {
                addTag(query.value)
            }
        }
        return
    }

    if (e.key === 'ArrowDown') {
        e.preventDefault()
        open.value = true
        highlightIndex.value = Math.min(highlightIndex.value + 1, totalOptions.value - 1)
        return
    }

    if (e.key === 'ArrowUp') {
        e.preventDefault()
        highlightIndex.value = Math.max(highlightIndex.value - 1, 0)
        return
    }

    if (e.key === 'Escape') {
        open.value = false
        highlightIndex.value = -1
    }
}

function selectHighlighted() {
    const idx = highlightIndex.value
    if (idx < filteredSuggestions.value.length) {
        addTag(filteredSuggestions.value[idx].value)
    } else if (showCustomOption.value) {
        addTag(query.value)
    }
}

function handleInputFocus() {
    if (!props.disabled) open.value = true
}

function handleClickOutside(e: PointerEvent) {
    if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
        open.value = false
        highlightIndex.value = -1
    }
}

watch(query, () => {
    highlightIndex.value = -1
    if (query.value && !open.value) open.value = true
})

watch(open, (val) => {
    if (val) {
        document.addEventListener('pointerdown', handleClickOutside)
    } else {
        document.removeEventListener('pointerdown', handleClickOutside)
    }
})
</script>

<template>
    <div ref="containerRef" class="relative">
        <div
            :class="[
                'flex min-h-10 flex-wrap items-center gap-1.5 rounded-xl border bg-card px-2.5 py-1.5 text-sm font-medium shadow-xs transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]',
                open ? 'border-ring ring-3 ring-ring/15' : 'border-input hover:border-primary/30',
                disabled ? 'pointer-events-none opacity-50' : 'cursor-text',
            ]"
            @click="inputRef?.focus()"
        >
            <span
                v-for="(tag, idx) in tags"
                :key="tag"
                class="inline-flex items-center gap-1 rounded-md border border-primary/20 bg-primary/10 px-1.5 py-0.5 text-xs font-semibold text-primary"
            >
                {{ labelFor(tag) }}
                <button
                    type="button"
                    tabindex="-1"
                    class="inline-flex size-3.5 items-center justify-center rounded text-primary/70 transition-colors hover:bg-primary/15 hover:text-primary focus:outline-none"
                    @click.stop="removeTag(idx)"
                >
                    <X class="size-2.5" :stroke-width="2.5" />
                </button>
            </span>

            <input
                v-if="canAddMore"
                :id="id"
                ref="inputRef"
                v-model="query"
                type="text"
                :placeholder="tags.length === 0 ? placeholder : ''"
                :disabled="disabled"
                autocomplete="off"
                class="min-w-[60px] flex-1 bg-transparent text-xs outline-none placeholder:text-muted-foreground"
                @focus="handleInputFocus"
                @keydown="handleKeydown"
            />

            <ChevronDown
                v-if="suggestions.length > 0"
                class="ml-auto size-3.5 shrink-0 text-muted-foreground/60"
            />
        </div>

        <Transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="scale-95 opacity-0"
            enter-to-class="scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="scale-100 opacity-100"
            leave-to-class="scale-95 opacity-0"
        >
            <div
                v-if="open && totalOptions > 0"
                class="absolute left-0 top-full z-50 mt-2 w-full overflow-hidden rounded-xl border border-border bg-popover text-popover-foreground shadow-sm"
            >
                <div class="max-h-48 overflow-y-auto p-1">
                    <button
                        v-for="(s, idx) in filteredSuggestions"
                        :key="s.value"
                        type="button"
                        :class="[
                            'flex w-full items-center rounded-lg px-2 py-1.5 text-xs font-medium outline-none transition-colors',
                            idx === highlightIndex
                                ? 'bg-primary/10 text-foreground'
                                : 'text-foreground/80 hover:bg-primary/8 hover:text-foreground',
                        ]"
                        @pointerdown.prevent="addTag(s.value)"
                        @pointerenter="highlightIndex = idx"
                    >
                        {{ s.label }}
                    </button>

                    <button
                        v-if="showCustomOption"
                        type="button"
                        :class="[
                            'flex w-full items-center gap-1.5 rounded-lg px-2 py-1.5 text-xs font-medium outline-none transition-colors',
                            filteredSuggestions.length === highlightIndex
                                ? 'bg-primary/10 text-foreground'
                                : 'text-foreground/80 hover:bg-primary/8 hover:text-foreground',
                        ]"
                        @pointerdown.prevent="addTag(query)"
                        @pointerenter="highlightIndex = filteredSuggestions.length"
                    >
                        <Plus class="size-3" :stroke-width="2.5" />
                        <span>Add "<span class="font-semibold">{{ query.trim() }}</span>"</span>
                    </button>
                </div>
            </div>
        </Transition>
    </div>
</template>
