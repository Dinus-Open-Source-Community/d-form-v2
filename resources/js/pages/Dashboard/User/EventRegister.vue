<script setup lang="ts">
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Badge } from '@/components/ui/badge'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import { CalendarDays, MapPin, Upload, Send, X } from 'lucide-vue-next'
import {
    dummyEvents, dummyForms, dummyFormFields, formatDate,
    categoryLabelMap, categoryColorMap,
} from '@/lib/dummyData'
import { toCategoryList } from '@/lib/eventCategories'

defineOptions({ layout: DashboardLayout })

const props = defineProps<{
    event?: IEvent
    form?: IForm
    fields?: IFormField[]
}>()

const event = props.event ?? dummyEvents[0]
const formDef = props.form ?? dummyForms.find((f) => f.event_id === event.id) ?? dummyForms[0]
const fields = props.fields ?? dummyFormFields.filter((f) => f.form_id === formDef.id)

const answers = ref<Record<string, string>>(
    Object.fromEntries(fields.map((f) => [f.id, ''])),
)
const fileAnswers = ref<Record<string, File | null>>(
    Object.fromEntries(
        fields.filter((f) => f.input_type === 'fileUpload').map((f) => [f.id, null]),
    ),
)
const showConfirmModal = ref(false)

function getLabel(field: IFormField): string {
    return (field.metadata as Record<string, unknown>).label as string ?? 'Untitled Field'
}
function getPlaceholder(field: IFormField): string {
    return (field.metadata as Record<string, unknown>).placeholder as string ?? ''
}
function getOptions(field: IFormField): string[] {
    return (field.metadata as Record<string, unknown>).options as string[] ?? []
}
function isRequired(field: IFormField): boolean {
    return (field.metadata as Record<string, unknown>).required as boolean ?? false
}
function getAccept(field: IFormField): string {
    return (field.metadata as Record<string, unknown>).accept as string ?? '*'
}

function handleFileChange(fieldId: string, e: Event) {
    const input = e.target as HTMLInputElement
    if (input.files?.[0]) {
        fileAnswers.value[fieldId] = input.files[0]
        answers.value[fieldId] = input.files[0].name
    }
}
function removeFile(fieldId: string) {
    fileAnswers.value[fieldId] = null
    answers.value[fieldId] = ''
}

function handleSubmit() {
    for (const field of fields) {
        if (isRequired(field) && !answers.value[field.id]?.trim()) {
            toast.error(`"${getLabel(field)}" is required.`)
            return
        }
    }
    showConfirmModal.value = true
}

function confirmSubmit() {
    showConfirmModal.value = false
    toast.success('Registration submitted successfully! You will be notified by email.')
}
</script>

<template>
    <Head :title="`Register — ${event.title}`" />

    <div class="flex flex-col gap-6">
        <PageHeader :title="`Register for ${event.title}`" subtitle="Fill out the form below to complete your registration." :backHref="`/dashboard/user/events/${event.id}`" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-6 lg:col-span-2">
                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base font-medium">{{ formDef.title }}</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-5 pt-0">
                        <div v-for="field in fields" :key="field.id" class="flex flex-col gap-1.5">
                            <Label class="text-sm">
                                {{ getLabel(field) }}
                                <span v-if="isRequired(field)" class="text-destructive">*</span>
                            </Label>

                            <Input
                                v-if="field.input_type === 'textInput'"
                                v-model="answers[field.id]"
                                :placeholder="getPlaceholder(field)"
                            />

                            <Select v-else-if="field.input_type === 'selectInput'" v-model="answers[field.id]">
                                <SelectTrigger><SelectValue :placeholder="`Select ${getLabel(field).toLowerCase()}`" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="opt in getOptions(field)" :key="opt" :value="opt">{{ opt }}</SelectItem>
                                </SelectContent>
                            </Select>

                            <Textarea
                                v-else-if="field.input_type === 'textarea'"
                                v-model="answers[field.id]"
                                :placeholder="getPlaceholder(field)"
                                rows="3"
                            />

                            <Input
                                v-else-if="field.input_type === 'datePicker'"
                                type="date"
                                v-model="answers[field.id]"
                            />

                            <div v-else-if="field.input_type === 'fileUpload'" class="flex flex-col gap-2">
                                <div v-if="!fileAnswers[field.id]"
                                    class="flex cursor-pointer items-center gap-3 rounded-lg border-2 border-dashed border-border px-4 py-3 transition-colors hover:border-primary/50 hover:bg-muted/30"
                                    @click="($refs[`file-${field.id}`] as HTMLInputElement[])?.[0]?.click()"
                                >
                                    <Upload class="size-5 text-muted-foreground" />
                                    <div>
                                        <p class="text-sm">Click to upload</p>
                                        <p class="text-xs text-muted-foreground">{{ getAccept(field) }}</p>
                                    </div>
                                    <input :ref="`file-${field.id}`" type="file" :accept="getAccept(field)" class="hidden" @change="handleFileChange(field.id, $event)" />
                                </div>
                                <div v-else class="flex items-center justify-between rounded-lg border bg-muted/20 px-3 py-2">
                                    <span class="truncate text-sm">{{ fileAnswers[field.id]?.name }}</span>
                                    <Button variant="ghost" size="icon" class="size-6" @click="removeFile(field.id)">
                                        <X class="size-3.5" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <Button class="mt-2 w-full sm:w-auto sm:self-end" @click="handleSubmit">
                            <Send class="mr-1.5 size-4" />Submit Registration
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-col gap-4">
                <Card class="overflow-hidden rounded-xl border shadow-xs">
                    <div class="h-32 overflow-hidden bg-muted">
                        <img :src="event.banner_url ?? ''" :alt="event.title" class="h-full w-full object-cover" />
                    </div>
                    <CardContent class="p-4">
                        <div class="mb-2 flex flex-wrap gap-1">
                            <Badge
                                v-for="cat in toCategoryList(event.category)"
                                :key="cat"
                                class="text-[10px] text-white"
                                :style="{ backgroundColor: categoryColorMap[cat] ?? '#6B7280' }"
                            >
                                {{ categoryLabelMap[cat] ?? cat }}
                            </Badge>
                        </div>
                        <h3 class="text-sm font-semibold">{{ event.title }}</h3>
                        <div class="mt-2 flex flex-col gap-1.5 text-xs text-muted-foreground">
                            <div class="flex items-center gap-1.5"><CalendarDays class="size-3" />{{ formatDate(event.start_date) }}</div>
                            <div class="flex items-center gap-1.5"><MapPin class="size-3" />{{ event.location }}</div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>

    <ConfirmationModal
        :open="showConfirmModal"
        title="Confirm Registration"
        description="Are you sure you want to submit your registration? Please make sure all information is correct."
        confirm-text="Submit"
        @confirm="confirmSubmit"
        @cancel="showConfirmModal = false"
        @update:open="showConfirmModal = $event"
    />
</template>
