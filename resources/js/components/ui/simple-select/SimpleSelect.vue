<script setup lang="ts">
import type { HTMLAttributes } from 'vue'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { cn } from '@/lib/utils'
import { Check, ChevronDown } from 'lucide-vue-next'

defineOptions({ inheritAttrs: false })

export type SimpleSelectOption = {
    value: string
    label: string
}

const props = withDefaults(
    defineProps<{
        id?: string
        modelValue: string
        options: SimpleSelectOption[]
        placeholder?: string
        disabled?: boolean
        required?: boolean
        invalid?: boolean
        class?: HTMLAttributes['class']
    }>(),
    { placeholder: 'Pilih…', disabled: false, required: false, invalid: false },
)

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const open = ref(false)
const triggerRef = ref<HTMLButtonElement | null>(null)
const contentWidthPx = ref<number | null>(null)

const triggerClass = computed(() =>
    cn(
        'flex h-10 w-full items-center justify-between gap-2 whitespace-nowrap rounded-lg border border-input bg-card px-3 py-2 text-sm font-medium text-foreground shadow-xs ring-offset-background',
        'transition-[border-color,box-shadow] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)]',
        'hover:border-primary/30',
        'focus:outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/30',
        'disabled:cursor-not-allowed disabled:opacity-50',
        '[&>span]:line-clamp-1 [&>span]:flex-1 [&>span]:text-left',
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

const listClass = 'max-h-96 w-full overflow-y-auto p-1'

const itemClass =
    'relative flex w-full cursor-default select-none items-center rounded-sm py-1.5 pl-2 pr-8 text-sm outline-none hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground data-[disabled]:pointer-events-none data-[disabled]:opacity-50'

const selectedOption = computed(() => props.options.find((o) => o.value === props.modelValue))

const displayLabel = computed(() => selectedOption.value?.label ?? '')

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

function choose(value: string) {
    emit('update:modelValue', value)
    open.value = false
}
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
                :required="required || undefined"
                :aria-required="required || undefined"
                :aria-invalid="invalid || undefined"
                aria-haspopup="listbox"
                v-bind="$attrs"
            >
                <span :class="showPlaceholder ? 'text-muted-foreground' : 'text-foreground'">
                    {{ showPlaceholder ? placeholder : displayLabel }}
                </span>
                <ChevronDown class="h-4 w-4 opacity-50" aria-hidden="true" />
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
                        v-for="opt in options"
                        :key="opt.value"
                        type="button"
                        role="option"
                        :aria-selected="modelValue === opt.value"
                        :class="cn(itemClass, modelValue === opt.value && 'bg-accent/60')"
                        @click="choose(opt.value)"
                    >
                        <span class="absolute right-2 flex h-3.5 w-3.5 items-center justify-center">
                            <Check v-if="modelValue === opt.value" class="h-4 w-4" aria-hidden="true" />
                        </span>
                        <span class="min-w-0 truncate">{{ opt.label }}</span>
                    </button>
                </template>
                <div v-else class="px-2 py-6 text-center text-sm text-muted-foreground">Tidak ada opsi</div>
            </div>
        </PopoverContent>
    </Popover>
</template>
