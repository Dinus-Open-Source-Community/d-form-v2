<script setup lang="ts">
import { computed, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
import { Button } from '@/components/ui/button'
import { DatePicker } from '@/components/ui/date-picker'
import TimeAmPmInput from '@/components/ui/date-picker/TimeAmPmInput.vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import { SearchableSelect, type SearchableSelectOption } from '@/components/ui/searchable-select'
import { cn } from '@/lib/utils'
import { routes } from '@/lib/routes'
import type { InterviewDivisionChoice } from '@/components/modules/dashboard/recruitment/InterviewSessionCreateSheet.vue'

const dateErrorClass =
    'border-destructive/70 bg-red-50 focus-visible:border-destructive focus-visible:ring-destructive/20 dark:bg-red-500/10'

export interface EditableInterviewSession {
    id: string
    session_date: string
    starts_at: string
    ends_at: string
    location: string
    room: string
    notes?: string | null
    is_active: boolean
    division: { id: string; name: string; code: string } | null
}

const props = defineProps<{
    open: boolean
    session: EditableInterviewSession | null
    divisions: InterviewDivisionChoice[]
}>()

const emit = defineEmits<{ close: [] }>()

const sheetOpen = computed<boolean>({
    get: () => props.open,
    set: (value: boolean) => {
        if (!value) emit('close')
    },
})

const form = useForm({
    recruitment_division_id: '',
    session_date: '',
    starts_at: '',
    ends_at: '',
    location: '',
    room: '',
    notes: '',
    is_active: true,
})

const divisionOptions = computed<SearchableSelectOption[]>(() =>
    props.divisions.map((division) => ({
        value: division.id,
        label: division.name,
        sublabel: division.code,
        initials: division.code.slice(0, 2).toUpperCase(),
    })),
)

const canSubmit = computed<boolean>(
    () =>
        props.session !== null &&
        form.recruitment_division_id.length > 0 &&
        form.session_date.length > 0 &&
        form.starts_at.length > 0 &&
        form.ends_at.length > 0 &&
        form.location.trim().length > 0 &&
        form.room.trim().length > 0 &&
        !form.processing,
)

/** Normalisasi "HH:mm:ss" backend menjadi "HH:mm" untuk TimeAmPmInput. */
function toHourMinute(value: string): string {
    if (!value) return ''
    const parts = value.split(':')
    if (parts.length >= 2) return `${parts[0]?.padStart(2, '0')}:${parts[1]?.padStart(2, '0')}`
    return value
}

function fillFromSession(): void {
    const target = props.session
    if (!target) return
    form.clearErrors()
    form.recruitment_division_id = target.division?.id ?? ''
    form.session_date = target.session_date
    form.starts_at = toHourMinute(target.starts_at)
    form.ends_at = toHourMinute(target.ends_at)
    form.location = target.location
    form.room = target.room
    form.notes = target.notes ?? ''
    form.is_active = target.is_active
}

watch(
    () => [props.open, props.session] as const,
    ([isOpen]) => {
        if (isOpen) {
            fillFromSession()
            return
        }
        form.reset()
        form.clearErrors()
    },
    { immediate: true },
)

function updatePath(id: string): string {
    return routes.admin.recruitment.interviewSessions.update(id)
}

function submit(): void {
    if (!canSubmit.value || props.session === null) return
    form.put(updatePath(props.session.id), {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    })
}
</script>

<template>
    <Sheet v-model:open="sheetOpen">
        <SheetContent
            side="right"
            overlay-class="bg-black/60 backdrop-blur-sm"
            class="inset-y-0 right-0 h-full w-full gap-0 p-0 sm:inset-y-3 sm:right-3 sm:h-[calc(100%-1.5rem)] sm:w-[28rem] sm:max-w-[calc(100vw-1.5rem)] sm:rounded-2xl sm:border sm:shadow-xl"
        >
            <SheetHeader class="shrink-0 space-y-1 border-b border-border/70 py-4 pr-12 pl-4 text-left">
                <SheetTitle class="truncate text-base">Edit sesi interview</SheetTitle>
                <SheetDescription class="truncate text-xs text-muted-foreground">
                    Perbarui jadwal, lokasi, atau status sesi.
                </SheetDescription>
            </SheetHeader>

            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                    <div class="space-y-2">
                        <Label for="edit-session-division">Divisi</Label>
                        <SearchableSelect
                            id="edit-session-division"
                            v-model="form.recruitment_division_id"
                            :options="divisionOptions"
                            placeholder="Pilih divisi"
                            search-placeholder="Cari divisi…"
                            :aria-invalid="form.errors.recruitment_division_id ? true : undefined"
                        />
                        <p v-if="form.errors.recruitment_division_id" role="alert" class="text-xs text-destructive">
                            {{ form.errors.recruitment_division_id }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-session-date">Tanggal</Label>
                        <DatePicker
                            id="edit-session-date"
                            v-model="form.session_date"
                            :aria-invalid="!!form.errors.session_date"
                            :class="cn('bg-white text-sm', !!form.errors.session_date && dateErrorClass)"
                        />
                        <p v-if="form.errors.session_date" role="alert" class="text-xs text-destructive">
                            {{ form.errors.session_date }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-session-starts">Jam mulai</Label>
                        <TimeAmPmInput
                            id="edit-session-starts"
                            v-model="form.starts_at"
                            :aria-invalid="!!form.errors.starts_at"
                            class="w-full"
                        />
                        <p v-if="form.errors.starts_at" role="alert" class="text-xs text-destructive">
                            {{ form.errors.starts_at }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-session-ends">Jam selesai</Label>
                        <TimeAmPmInput
                            id="edit-session-ends"
                            v-model="form.ends_at"
                            :aria-invalid="!!form.errors.ends_at"
                            class="w-full"
                        />
                        <p v-if="form.errors.ends_at" role="alert" class="text-xs text-destructive">
                            {{ form.errors.ends_at }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-session-location">Lokasi</Label>
                        <Input
                            id="edit-session-location"
                            v-model="form.location"
                            type="text"
                            placeholder="Gedung / tempat"
                            :aria-invalid="form.errors.location ? true : undefined"
                        />
                        <p v-if="form.errors.location" role="alert" class="text-xs text-destructive">
                            {{ form.errors.location }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-session-room">Ruangan</Label>
                        <Input
                            id="edit-session-room"
                            v-model="form.room"
                            type="text"
                            placeholder="Ruang / kelas"
                            :aria-invalid="form.errors.room ? true : undefined"
                        />
                        <p v-if="form.errors.room" role="alert" class="text-xs text-destructive">
                            {{ form.errors.room }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="edit-session-notes">Catatan (opsional)</Label>
                        <Textarea id="edit-session-notes" v-model="form.notes" placeholder="Catatan untuk tim…" />
                        <p v-if="form.errors.notes" role="alert" class="text-xs text-destructive">
                            {{ form.errors.notes }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-3 rounded-xl border border-border/70 p-3">
                        <div>
                            <Label for="edit-session-active">Sesi aktif</Label>
                            <p class="text-xs text-muted-foreground">Sesi aktif tampil di antrean live.</p>
                        </div>
                        <Switch id="edit-session-active" v-model="form.is_active" />
                    </div>
                </div>

                <footer class="shrink-0 border-t border-border/70 p-4">
                    <div class="flex gap-2">
                        <Button variant="outline" type="button" class="flex-1" @click="emit('close')"> Batal </Button>
                        <Button type="submit" class="flex-1" :disabled="!canSubmit">
                            {{ form.processing ? 'Menyimpan…' : 'Simpan perubahan' }}
                        </Button>
                    </div>
                </footer>
            </form>
        </SheetContent>
    </Sheet>
</template>
