<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { routes } from '@/lib/routes'

export interface DashboardDivision {
    id: string
    code: string
    name: string
    description: string | null
    is_active: boolean
    sort_order: number
    interviewer_assignments_count: number
}

const props = defineProps<{
    open: boolean
    divisions: DashboardDivision[]
}>()

const emit = defineEmits<{ close: [] }>()

const sheetOpen = computed<boolean>({
    get: () => props.open,
    set: (value: boolean) => {
        if (!value) emit('close')
    },
})

const editingId = ref<string | null>(null)
const editName = ref<string>('')
const editIsActive = ref<boolean>(true)
const isSaving = ref<boolean>(false)

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            editingId.value = null
            isSaving.value = false
        }
    },
)

function startEdit(division: DashboardDivision): void {
    editingId.value = division.id
    editName.value = division.name
    editIsActive.value = division.is_active
}

function cancelEdit(): void {
    editingId.value = null
    isSaving.value = false
}

function saveDivision(division: DashboardDivision): void {
    if (editName.value.trim() === '') return
    isSaving.value = true
    router.put(
        routes.admin.recruitment.divisions.update(division.id),
        { name: editName.value.trim(), is_active: editIsActive.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                editingId.value = null
            },
            onFinish: () => {
                isSaving.value = false
            },
        },
    )
}
</script>

<template>
    <Sheet v-model:open="sheetOpen">
        <SheetContent
            side="right"
            overlay-class="bg-black/60 backdrop-blur-sm"
            class="inset-y-0 right-0 h-full w-full gap-0 p-0 sm:inset-y-3 sm:right-3 sm:h-[calc(100%-1.5rem)] sm:w-[28rem] sm:max-w-[calc(100vw-1.5rem)] sm:rounded-2xl sm:border sm:shadow-xl"
        >
            <SheetHeader class="border-border/70 shrink-0 space-y-1 border-b py-4 pr-12 pl-4 text-left">
                <SheetTitle class="truncate text-base">Divisi</SheetTitle>
                <SheetDescription class="text-muted-foreground truncate text-xs">
                    Daftar divisi open recruitment
                </SheetDescription>
            </SheetHeader>

            <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-4">
                <div
                    v-for="division in divisions"
                    :key="division.id"
                    class="rounded-xl border border-border/60 p-4"
                >
                    <template v-if="editingId === division.id">
                        <form class="space-y-3" @submit.prevent="saveDivision(division)">
                            <div class="space-y-1.5">
                                <Label :for="`division-name-${division.id}`">Nama divisi</Label>
                                <Input :id="`division-name-${division.id}`" v-model="editName" required />
                            </div>
                            <label class="flex items-center gap-2 text-sm">
                                <input v-model="editIsActive" type="checkbox" class="size-4 accent-current" />
                                Aktif
                            </label>
                            <div class="flex gap-2">
                                <Button size="sm" type="submit" :disabled="isSaving || editName.trim() === ''">
                                    {{ isSaving ? 'Menyimpan…' : 'Simpan' }}
                                </Button>
                                <Button size="sm" variant="ghost" type="button" @click="cancelEdit">Batal</Button>
                            </div>
                        </form>
                    </template>
                    <template v-else>
                        <p class="font-medium">{{ division.name }}</p>
                        <p class="text-muted-foreground mt-0.5 font-mono text-xs">{{ division.code }}</p>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{ division.interviewer_assignments_count }} interviewer ·
                            {{ division.is_active ? 'Aktif' : 'Nonaktif' }}
                        </p>
                        <Button size="sm" variant="outline" class="mt-3" @click="startEdit(division)">
                            Edit
                        </Button>
                    </template>
                </div>

                <p v-if="divisions.length === 0" class="text-muted-foreground px-4 py-10 text-center text-sm">
                    Belum ada data divisi.
                </p>
            </div>

            <footer class="border-border/70 shrink-0 border-t p-4">
                <Button variant="outline" class="w-full" @click="emit('close')">Tutup</Button>
            </footer>
        </SheetContent>
    </Sheet>
</template>
