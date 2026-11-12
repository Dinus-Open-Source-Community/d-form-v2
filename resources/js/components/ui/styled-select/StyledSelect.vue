<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'
import type { TFormFillOptionRow } from '@/types/form'
import { Check, ChevronDown } from 'lucide-vue-next'

const props = withDefaults(
    defineProps<{
        id?: string
        modelValue: string
        options: TFormFillOptionRow[]
        placeholder?: string
        disabled?: boolean
        class?: HTMLAttributes['class']
    }>(),
    { placeholder: 'Select an option', disabled: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const open = ref(false)
const triggerRef = ref<HTMLButtonElement | null>(null)
const contentWidthPx = ref<number | null>(null)

const triggerClass = computed(() =>
    cn(
        'flex h-10 min-h-10 w-full rounded-lg min-w-0 shrink-0 items-center justify-between gap-2 whitespace-nowrap border border-input bg-card px-3 py-2 text-sm font-medium text-foreground shadow-xs ring-offset-background',
        'transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]',
        'hover:border-primary/30',
        'focus:outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/30',
        'disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50',
        'aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40',
        props.class,
    ),
)

const popoverContentClass = cn(
    'border-border z-50 max-h-96 w-auto min-w-0 max-w-[calc(100vw-2rem)] overflow-hidden rounded-xl border bg-popover p-0 text-popover-foreground shadow-sm outline-none',
    'data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
    'data-[state=closed]:zoom-out-95 data-[state=open]:zoom-in-95 data-[side=bottom]:slide-in-from-top-2',
    'data-[side=left]:slide-in-from-right-2 data-[side=right]:slide-in-from-left-2 data-[side=top]:slide-in-from-bottom-2',
)

const listClass = 'max-h-96 overflow-y-auto p-1'

const displayLabel = computed(() => {
    const row = props.options.find((o) => o.label === props.modelValue)
    return row?.label ?? ''
})

const showPlaceholder = computed(() => !displayLabel.value)

function syncContentWidth() {
    const el = triggerRef.value
    contentWidthPx.value = el ? el.offsetWidth : null
}

watch(open, (isOpen) => {
    if (isOpen) {
        nextTick(() => syncContentWidth())
    }
})

onMounted(() => window.addEventListener('resize', syncContentWidth))
onBeforeUnmount(() => window.removeEventListener('resize', syncContentWidth))

const contentStyle = computed(() =>
    contentWidthPx.value
        ? { width: `${contentWidthPx.value}px`, minWidth: `${contentWidthPx.value}px` }
        : undefined,
)

function choose(label: string) {
    emit('update:modelValue', label)
    open.value = false
}

const itemClass =
    'relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-2 pr-8 text-left text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground'
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
            >
                <span
                    :class="[
                        'line-clamp-1 min-w-0 flex-1 text-left',
                        showPlaceholder ? 'text-muted-foreground' : 'text-foreground',
                    ]"
                >
                    {{ showPlaceholder ? placeholder : displayLabel }}
                </span>
                <ChevronDown class="h-4 w-4 shrink-0 opacity-50" aria-hidden="true" />
            </button>
        </PopoverTrigger>

        <PopoverContent
            align="start"
            :side-offset="4"
            :class="popoverContentClass"
            :style="contentStyle"
        >
            <div :class="listClass" role="listbox">
                <template v-if="options.length > 0">
                    <button
                        v-for="(row, idx) in options"
                        :key="`${row.label}-${idx}`"
                        type="button"
                        role="option"
                        :aria-selected="modelValue === row.label"
                        :class="cn(itemClass, modelValue === row.label && 'bg-accent/60')"
                        @click="choose(row.label)"
                    >
                        <span class="absolute right-2 flex size-3.5 items-center justify-center">
                            <Check v-if="modelValue === row.label" class="size-4 text-foreground" aria-hidden="true" />
                        </span>
                        <span class="flex min-w-0 flex-1 items-center gap-2 pr-1">
                            <span
                                v-if="row.type === 'image' && row.imageSrc"
                                class="size-7 shrink-0 overflow-hidden rounded-md border border-border"
                            >
                                <img :src="row.imageSrc" alt="" class="size-full object-cover" loading="lazy" />
                            </span>
                            <span class="min-w-0 truncate">{{ row.label }}</span>
                        </span>
                    </button>
                </template>
                <div v-else class="px-2 py-6 text-center text-sm text-muted-foreground">No options</div>
            </div>
        </PopoverContent>
    </Popover>
</template>
