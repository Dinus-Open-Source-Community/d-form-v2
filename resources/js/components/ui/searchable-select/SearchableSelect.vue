<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'
import { Check, ChevronDown, Plus, Search } from 'lucide-vue-next'

defineOptions({ inheritAttrs: false })

export type SearchableSelectOption = {
    value: string
    label: string
    sublabel?: string
    initials?: string
    disabled?: boolean
}

const props = withDefaults(
    defineProps<{
        id?: string
        modelValue: string
        options: SearchableSelectOption[]
        placeholder?: string
        searchPlaceholder?: string
        emptyText?: string
        disabled?: boolean
        class?: HTMLAttributes['class']
    }>(),
    {
        placeholder: 'Pilih…',
        searchPlaceholder: 'Cari…',
        emptyText: 'Tidak ada hasil',
        disabled: false,
    },
)

const emit = defineEmits<{ 'update:modelValue': [value: string]; create: [] }>()

const open = ref<boolean>(false)
const query = ref<string>('')
const highlightedValue = ref<string | null>(null)
const triggerRef = ref<HTMLButtonElement | null>(null)
const searchRef = ref<HTMLInputElement | null>(null)
const contentWidthPx = ref<number | null>(null)

const triggerClass = computed<string>(() =>
    cn(
        'flex h-10 w-full items-center justify-between gap-2 whitespace-nowrap rounded-lg border border-input bg-card px-3 py-2 text-sm font-medium text-foreground shadow-xs ring-offset-background',
        'transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]',
        'hover:border-primary/30',
        'focus:outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/30',
        'disabled:cursor-not-allowed disabled:opacity-50',
        'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
        props.class,
    ),
)

const popoverContentClass = cn(
    'relative z-50 max-h-96 min-w-32 overflow-hidden rounded-xl border border-border bg-popover p-0 text-popover-foreground shadow-sm outline-none',
    'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
    'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2',
    'data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
    'data-[side=bottom]:translate-y-1 data-[side=left]:-translate-x-1 data-[side=right]:translate-x-1 data-[side=top]:-translate-y-1',
)

function initialsFor(label: string): string {
    const words: string[] = label.trim().split(/\s+/).filter((w) => w.length > 0)
    if (words.length === 0) return '—'
    const first: string = words[0] ?? ''
    const second: string = words.length > 1 ? (words[1] ?? '') : ''
    const letters: string = `${first.charAt(0)}${second.charAt(0)}`.toUpperCase()
    return letters.trim().length > 0 ? letters : '—'
}

function optionInitials(opt: SearchableSelectOption): string {
    const custom: string | undefined = opt.initials?.trim()
    if (custom && custom.length > 0) return custom.toUpperCase().slice(0, 2)
    return initialsFor(opt.label)
}

const normalizedQuery = computed<string>(() => query.value.trim().toLowerCase())

const filteredOptions = computed<SearchableSelectOption[]>(() => {
    const q: string = normalizedQuery.value
    if (q.length === 0) return props.options
    return props.options.filter((opt) => {
        const haystack: string = `${opt.label} ${opt.sublabel ?? ''}`.toLowerCase()
        return haystack.includes(q)
    })
})

const enabledFiltered = computed<SearchableSelectOption[]>(() =>
    filteredOptions.value.filter((opt) => opt.disabled !== true),
)

const selectedOption = computed<SearchableSelectOption | undefined>(() =>
    props.options.find((o) => o.value === props.modelValue),
)

const displayLabel = computed<string>(() => selectedOption.value?.label ?? '')
const showPlaceholder = computed<boolean>(() => displayLabel.value.length === 0)

const contentStyle = computed<{ width: string; minWidth: string } | undefined>(() =>
    contentWidthPx.value !== null
        ? { width: `${contentWidthPx.value}px`, minWidth: `${contentWidthPx.value}px` }
        : undefined,
)

function syncContentWidth(): void {
    const el: HTMLButtonElement | null = triggerRef.value
    contentWidthPx.value = el ? el.offsetWidth : null
}

function focusSearch(): void {
    nextTick(() => {
        syncContentWidth()
        searchRef.value?.focus()
    })
}

watch(open, (isOpen: boolean) => {
    if (isOpen) {
        query.value = ''
        highlightedValue.value = props.modelValue.length > 0 ? props.modelValue : null
        focusSearch()
    } else {
        query.value = ''
        highlightedValue.value = null
    }
})

watch(filteredOptions, (list: SearchableSelectOption[]) => {
    const stillVisible: boolean = list.some((o) => o.value === highlightedValue.value && o.disabled !== true)
    if (!stillVisible) {
        highlightedValue.value = enabledFiltered.value[0]?.value ?? null
    }
})

onMounted(() => window.addEventListener('resize', syncContentWidth))
onBeforeUnmount(() => window.removeEventListener('resize', syncContentWidth))

function choose(opt: SearchableSelectOption): void {
    if (opt.disabled === true) return
    if (opt.value === props.modelValue) {
        open.value = false
        return
    }
    emit('update:modelValue', opt.value)
    open.value = false
}

function onActionClick(): void {
    open.value = false
    emit('create')
}

function moveHighlight(step: 1 | -1): void {
    const list: SearchableSelectOption[] = enabledFiltered.value
    if (list.length === 0) return
    const currentIndex: number = list.findIndex((o) => o.value === highlightedValue.value)
    if (currentIndex === -1) {
        highlightedValue.value = step === 1 ? (list[0]?.value ?? null) : (list[list.length - 1]?.value ?? null)
        return
    }
    const nextIndex: number = (currentIndex + step + list.length) % list.length
    highlightedValue.value = list[nextIndex]?.value ?? null
}

function onListKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown') {
        event.preventDefault()
        moveHighlight(1)
    } else if (event.key === 'ArrowUp') {
        event.preventDefault()
        moveHighlight(-1)
    } else if (event.key === 'Enter') {
        const target: SearchableSelectOption | undefined = enabledFiltered.value.find(
            (o) => o.value === highlightedValue.value,
        )
        if (target) {
            event.preventDefault()
            choose(target)
        }
    } else if (event.key === 'Escape') {
        open.value = false
    }
}

const listboxId = computed<string>(() => `${props.id ?? 'searchable-select'}-listbox`)
</script>

<template>
    <Popover v-model:open="open" :modal="false">
        <PopoverTrigger as-child>
            <button
                :id="id"
                ref="triggerRef"
                type="button"
                role="combobox"
                :disabled="disabled"
                :class="triggerClass"
                :aria-expanded="open"
                aria-haspopup="listbox"
                :aria-controls="listboxId"
                v-bind="$attrs"
            >
                <span v-if="showPlaceholder" class="line-clamp-1 min-w-0 flex-1 text-left text-muted-foreground">
                    {{ placeholder }}
                </span>
                <span v-else class="flex min-w-0 flex-1 items-center gap-2 text-left">
                    <span
                        aria-hidden="true"
                        class="flex size-6 shrink-0 items-center justify-center rounded-full bg-muted text-[10px] font-semibold tracking-wide text-muted-foreground"
                    >
                        {{ optionInitials(selectedOption as SearchableSelectOption) }}
                    </span>
                    <span class="line-clamp-1 min-w-0 flex-1 text-foreground">{{ displayLabel }}</span>
                </span>
                <ChevronDown class="size-4 shrink-0 opacity-50" aria-hidden="true" />
            </button>
        </PopoverTrigger>

        <PopoverContent align="start" :side-offset="4" :class="popoverContentClass" :style="contentStyle">
            <div class="border-b border-border/70 p-2">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground"
                        aria-hidden="true"
                    />
                    <input
                        ref="searchRef"
                        v-model="query"
                        type="text"
                        role="searchbox"
                        :placeholder="searchPlaceholder"
                        aria-label="Cari opsi"
                        class="h-9 w-full rounded-md border border-transparent bg-muted/60 pr-2 pl-8 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-ring focus:bg-background focus:ring-[3px] focus:ring-ring/30"
                        @keydown="onListKeydown"
                    />
                </div>
            </div>
            <div :id="listboxId" class="max-h-60 w-full overflow-y-auto p-1" role="listbox" @keydown="onListKeydown">
                <template v-if="filteredOptions.length > 0">
                    <button
                        v-for="opt in filteredOptions"
                        :key="opt.value"
                        type="button"
                        role="option"
                        :aria-selected="modelValue === opt.value"
                        :aria-disabled="opt.disabled === true"
                        :disabled="opt.disabled === true"
                        :data-disabled="opt.disabled === true ? '' : undefined"
                        :class="
                            cn(
                                'relative flex w-full cursor-default items-center gap-2.5 rounded-md px-2 py-2 text-left text-sm outline-none select-none',
                                'hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground',
                                'data-[disabled]:pointer-events-none data-[disabled]:opacity-50',
                                (highlightedValue === opt.value || modelValue === opt.value) && 'bg-accent/60',
                            )
                        "
                        @click="choose(opt)"
                        @mouseenter="opt.disabled === true ? undefined : (highlightedValue = opt.value)"
                    >
                        <span
                            aria-hidden="true"
                            class="flex size-7 shrink-0 items-center justify-center rounded-full bg-muted text-[11px] font-semibold tracking-wide text-muted-foreground"
                        >
                            {{ optionInitials(opt) }}
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate leading-tight font-medium">{{ opt.label }}</span>
                            <span v-if="opt.sublabel" class="block truncate text-xs leading-tight text-muted-foreground">
                                {{ opt.sublabel }}
                            </span>
                        </span>
                        <span class="flex h-4 w-4 shrink-0 items-center justify-center">
                            <Check v-if="modelValue === opt.value" class="size-4" aria-hidden="true" />
                        </span>
                    </button>
                </template>
                <div v-else class="px-2 py-6 text-center text-sm text-muted-foreground">{{ emptyText }}</div>
            </div>
            <div v-if="$slots.action" class="border-t border-border/70 p-1">
                <button
                    type="button"
                    class="flex w-full items-center justify-center gap-1.5 rounded-md border border-dashed border-border/80 px-2 py-2 text-sm font-medium text-muted-foreground transition hover:border-primary/40 hover:bg-accent hover:text-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30 focus-visible:outline-none"
                    @click="onActionClick"
                >
                    <slot name="action">
                        <Plus class="size-4" aria-hidden="true" />
                        <span>Tambah interviewer baru</span>
                    </slot>
                </button>
            </div>
        </PopoverContent>
    </Popover>
</template>
