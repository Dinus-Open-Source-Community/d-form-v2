<script setup lang="ts">
import type { Component } from 'vue'
import { computed, ref, watch } from 'vue'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { FileSpreadsheet, FileText } from 'lucide-vue-next'

export type ScanExportTarget = {
    id: string
    label: string
    kind: 'event' | 'oprec'
}

export type ScanExportFormat = 'csv' | 'xlsx'

const props = defineProps<{
    open: boolean
    options: ScanExportTarget[]
    format: ScanExportFormat
}>()

const emit = defineEmits<{
    'update:open': [value: boolean]
    confirm: [targets: ScanExportTarget[]]
}>()

const query = ref('')
const selectedIds = ref<string[]>([])

const filteredOptions = computed<ScanExportTarget[]>(() => {
    const needle = query.value.trim().toLowerCase()
    if (needle.length === 0) {
        return props.options
    }

    return props.options.filter((option) => `${option.label} ${option.kind}`.toLowerCase().includes(needle))
})

const selectedCount = computed<number>(() => selectedIds.value.length)

const allVisibleSelected = computed<boolean>(
    () => filteredOptions.value.length > 0 && filteredOptions.value.every((option) => selectedIds.value.includes(option.id)),
)

const formatLabel = computed<string>(() => (props.format === 'xlsx' ? 'Excel (XLSX)' : 'CSV'))

const formatIcon = computed<Component>(() => (props.format === 'xlsx' ? FileSpreadsheet : FileText))

const confirmLabel = computed<string>(() => {
    const count = selectedCount.value
    if (count === 0) {
        return `Unduh ${formatLabel.value}`
    }

    return count === 1
        ? `Unduh 1 file ${props.format.toUpperCase()}`
        : `Unduh ${count} file ${props.format.toUpperCase()}`
})

watch(
    () => props.open,
    (open) => {
        if (open) {
            query.value = ''
            selectedIds.value = []
        }
    },
)

function isSelected(id: string): boolean {
    return selectedIds.value.includes(id)
}

function toggle(id: string, checked: boolean): void {
    if (checked) {
        if (!selectedIds.value.includes(id)) {
            selectedIds.value = [...selectedIds.value, id]
        }

        return
    }

    selectedIds.value = selectedIds.value.filter((value) => value !== id)
}

function toggleAllVisible(checked: boolean): void {
    const visibleIds = filteredOptions.value.map((option) => option.id)

    if (checked) {
        selectedIds.value = Array.from(new Set([...selectedIds.value, ...visibleIds]))

        return
    }

    selectedIds.value = selectedIds.value.filter((id) => !visibleIds.includes(id))
}

function onConfirm(): void {
    const targets = props.options.filter((option) => selectedIds.value.includes(option.id))
    if (targets.length === 0) {
        return
    }

    emit('confirm', targets)
    emit('update:open', false)
}
</script>

<template>
    <Dialog :open="open" @update:open="(value: boolean) => emit('update:open', value)">
        <DialogContent class="rounded-2xl sm:max-w-lg">
            <DialogHeader>
                <DialogTitle class="font-display text-lg">Export {{ formatLabel }}</DialogTitle>
                <DialogDescription>
                    Filter sedang di <span class="font-medium">Semua acara</span>. Pilih satu atau beberapa acara — satu
                    file akan diunduh untuk setiap acara yang dipilih.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-3">
                <Input v-model="query" placeholder="Cari acara…" aria-label="Cari acara" />

                <div class="flex items-center justify-between gap-2">
                    <button
                        type="button"
                        class="text-primary text-xs font-medium hover:underline"
                        @click="toggleAllVisible(!allVisibleSelected)"
                    >
                        {{ allVisibleSelected ? 'Batalkan pilih semua' : 'Pilih semua' }}
                    </button>
                    <span class="text-muted-foreground text-xs">{{ selectedCount }} acara dipilih</span>
                </div>

                <div
                    class="border-border/70 max-h-64 space-y-1 overflow-y-auto rounded-xl border p-1.5"
                    role="group"
                    aria-label="Daftar acara"
                >
                    <div
                        v-for="option in filteredOptions"
                        :key="option.id"
                        role="checkbox"
                        :aria-checked="isSelected(option.id)"
                        tabindex="0"
                        class="hover:bg-muted/50 focus-visible:ring-ring/30 flex cursor-pointer items-center gap-2.5 rounded-lg px-2 py-2 outline-none focus-visible:ring-[3px]"
                        @click="toggle(option.id, !isSelected(option.id))"
                        @keydown.space.prevent="toggle(option.id, !isSelected(option.id))"
                        @keydown.enter.prevent="toggle(option.id, !isSelected(option.id))"
                    >
                        <Checkbox :model-value="isSelected(option.id)" class="pointer-events-none" tabindex="-1" />
                        <Badge
                            variant="outline"
                            :class="
                                option.kind === 'oprec'
                                    ? 'border-violet-500/40 text-violet-600'
                                    : 'border-sky-500/40 text-sky-600'
                            "
                            class="shrink-0 text-[10px]"
                        >
                            {{ option.kind === 'oprec' ? 'OPREC' : 'EVENT' }}
                        </Badge>
                        <span class="min-w-0 flex-1 truncate text-sm" :title="option.label">{{ option.label }}</span>
                    </div>

                    <p v-if="filteredOptions.length === 0" class="text-muted-foreground px-2 py-6 text-center text-sm">
                        Tidak ada acara yang cocok.
                    </p>
                </div>
            </div>

            <DialogFooter class="gap-2">
                <Button variant="outline" @click="emit('update:open', false)">Batal</Button>
                <Button :disabled="selectedCount === 0" @click="onConfirm">
                    <component :is="formatIcon" data-icon="inline-start" />
                    {{ confirmLabel }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
