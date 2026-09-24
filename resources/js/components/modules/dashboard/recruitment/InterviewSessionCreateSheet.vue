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

const dateErrorClass =
    'border-destructive/70 bg-red-50 focus-visible:border-destructive focus-visible:ring-destructive/20 dark:bg-red-500/10'

export interface InterviewDivisionChoice {
    id: string
    name: string
    code: string
}

const props = defineProps<{
    open: boolean
    periodId: string
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
    recruitment_period_id: '',
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
        form.recruitment_division_id.length > 0 &&
        form.session_date.length > 0 &&
        form.starts_at.length > 0 &&
        form.ends_at.length > 0 &&
        form.location.trim().length > 0 &&
        form.room.trim().length > 0 &&
        !form.processing,
)

watch(
    () => props.open,
    (isOpen: boolean) => {
        if (isOpen) {
            form.clearErrors()
            form.recruitment_period_id = props.periodId

            return
        }

        form.reset()
        form.clearErrors()
    },
)

function submit(): void {
    if (!canSubmit.value) return

    form.post(routes.admin.recruitment.interviewSessions.store, {
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
                <SheetTitle class="truncate text-base">Buat sesi interview</SheetTitle>
                <SheetDescription class="truncate text-xs text-muted-foreground">
                    Sesi dikelompokkan per divisi. Setelah dibuat, jadwalkan pelamar dari halaman detail sesi.
                </SheetDescription>
            </SheetHeader>

            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                <div class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4">
                    <div class="space-y-2">
                        <Label for="session-division">Divisi</Label>
                        <SearchableSelect
                            id="session-division"
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
                        <Label for="session-date">Tanggal</Label>
                        <DatePicker
                            id="session-date"
                            v-model="form.session_date"
                            :aria-invalid="!!form.errors.session_date"
                            :class="cn('bg-white text-sm', !!form.errors.session_date && dateErrorClass)"
                        />
                        <p v-if="form.errors.session_date" role="alert" class="text-xs text-destructive">
                            {{ form.errors.session_date }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="session-starts">Jam mulai</Label>
                        <TimeAmPmInput
                            id="session-starts"
                            v-model="form.starts_at"
                            :aria-invalid="!!form.errors.starts_at"
                            class="w-full"
                        />
                        <p v-if="form.errors.starts_at" role="alert" class="text-xs text-destructive">
                            {{ form.errors.starts_at }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="session-ends">Jam selesai</Label>
                        <TimeAmPmInput
                            id="session-ends"
                            v-model="form.ends_at"
                            :aria-invalid="!!form.errors.ends_at"
                            class="w-full"
                        />
                        <p v-if="form.errors.ends_at" role="alert" class="text-xs text-destructive">
                            {{ form.errors.ends_at }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="session-location">Lokasi</Label>
                        <Input
                            id="session-location"
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
                        <Label for="session-room">Ruangan</Label>
                        <Input
                            id="session-room"
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
                        <Label for="session-notes">Catatan (opsional)</Label>
                        <Textarea id="session-notes" v-model="form.notes" placeholder="Catatan untuk tim…" />
                        <p v-if="form.errors.notes" role="alert" class="text-xs text-destructive">
                            {{ form.errors.notes }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between gap-3 rounded-xl border border-border/70 p-3">
                        <div>
                            <Label for="session-active">Sesi aktif</Label>
                            <p class="text-xs text-muted-foreground">Sesi aktif tampil di antrean live.</p>
                        </div>
                        <Switch id="session-active" v-model="form.is_active" />
                    </div>
                </div>

                <footer class="shrink-0 border-t border-border/70 p-4">
                    <div class="flex gap-2">
                        <Button variant="outline" type="button" class="flex-1" @click="emit('close')">
                            Batal
                        </Button>
                        <Button type="submit" class="flex-1" :disabled="!canSubmit">
                            {{ form.processing ? 'Menyimpan…' : 'Buat sesi' }}
                        </Button>
                    </div>
                </footer>
            </form>
        </SheetContent>
    </Sheet>
</template>
