<script setup lang="ts">
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { toast } from 'vue-sonner'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Plus, GripVertical, Trash2, Save } from 'lucide-vue-next'
import { dummyEvents } from '@/lib/dummyData'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event?: { id: string; title: string }
}>()

const eventContext = props.event ?? { id: dummyEvents[0].id, title: dummyEvents[0].title }

interface FormFieldDraft {
    id: string
    input_type: string
    label: string
    placeholder: string
    required: boolean
    options: string[]
}

const formTitle = ref('')
const formDescription = ref('')
const closedAt = ref('')
const visibleFor = ref('public')

const fields = ref<FormFieldDraft[]>([
    { id: crypto.randomUUID(), input_type: 'textInput', label: 'Full Name', placeholder: 'Enter your name', required: true, options: [] },
    { id: crypto.randomUUID(), input_type: 'textInput', label: 'Email', placeholder: 'Enter your email', required: true, options: [] },
])

const fieldTypes = [
    { value: 'textInput', label: 'Text Input' },
    { value: 'selectInput', label: 'Select / Dropdown' },
    { value: 'textarea', label: 'Textarea' },
    { value: 'datePicker', label: 'Date Picker' },
    { value: 'fileUpload', label: 'File Upload' },
]

function addField() {
    fields.value.push({ id: crypto.randomUUID(), input_type: 'textInput', label: '', placeholder: '', required: false, options: [] })
}
function removeField(id: string) { fields.value = fields.value.filter((f) => f.id !== id) }
function handleSave() { toast.success('Form created successfully.') }
</script>

<template>
    <Head title="Create Form" />

    <div class="flex flex-col gap-6">
        <PageHeader title="Create Form" :subtitle="`Build a dynamic form for ${eventContext.title}.`" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-4 lg:col-span-2">
                <Card class="rounded-xl border shadow-xs">
                    <CardContent class="flex flex-col gap-4 p-4">
                        <div class="flex flex-col gap-1.5">
                            <Label>Form Title</Label>
                            <Input v-model="formTitle" placeholder="e.g. Registration Form" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label>Description</Label>
                            <Textarea v-model="formDescription" placeholder="Brief description of this form" rows="2" />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-medium">Form Fields</h2>
                        <Button variant="outline" size="sm" @click="addField"><Plus class="mr-1.5 size-3.5" />Add Field</Button>
                    </div>

                    <Card v-for="(field, idx) in fields" :key="field.id" class="rounded-xl border shadow-xs">
                        <CardContent class="p-4">
                            <div class="flex items-start gap-3">
                                <div class="mt-1 cursor-grab text-muted-foreground/30"><GripVertical class="size-4" /></div>
                                <div class="flex min-w-0 flex-1 flex-col gap-3">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="flex flex-col gap-1.5"><Label class="text-xs">Label</Label><Input v-model="field.label" placeholder="Field label" class="text-xs" /></div>
                                        <div class="flex flex-col gap-1.5">
                                            <Label class="text-xs">Type</Label>
                                            <Select v-model="field.input_type">
                                                <SelectTrigger class="text-xs"><SelectValue /></SelectTrigger>
                                                <SelectContent><SelectItem v-for="ft in fieldTypes" :key="ft.value" :value="ft.value">{{ ft.label }}</SelectItem></SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                    <div v-if="field.input_type !== 'fileUpload' && field.input_type !== 'datePicker'" class="flex flex-col gap-1.5">
                                        <Label class="text-xs">Placeholder</Label>
                                        <Input v-model="field.placeholder" placeholder="Placeholder text" class="text-xs" />
                                    </div>
                                    <div v-if="field.input_type === 'selectInput'" class="flex flex-col gap-1.5">
                                        <Label class="text-xs">Options (comma separated)</Label>
                                        <Input :model-value="field.options.join(', ')" @update:model-value="(v) => field.options = (v as string).split(',').map((s) => s.trim()).filter(Boolean)" placeholder="Option 1, Option 2, Option 3" class="text-xs" />
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Checkbox :id="`required-${field.id}`" :checked="field.required" @update:checked="(v) => field.required = !!v" />
                                        <Label :for="`required-${field.id}`" class="text-xs font-normal">Required field</Label>
                                    </div>
                                </div>
                                <Button variant="ghost" size="icon" class="size-7 text-destructive hover:text-destructive" @click="removeField(field.id)"><Trash2 class="size-3.5" /></Button>
                            </div>
                        </CardContent>
                    </Card>

                    <div v-if="fields.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border/60 bg-muted/20 py-12 text-center">
                        <p class="text-sm text-muted-foreground">No fields added yet.</p>
                        <Button variant="outline" size="sm" class="mt-3" @click="addField"><Plus class="mr-1.5 size-3.5" />Add First Field</Button>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-3"><CardTitle class="text-sm font-medium">Settings</CardTitle></CardHeader>
                    <CardContent class="flex flex-col gap-4 pt-0">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs">Visibility</Label>
                            <Select v-model="visibleFor"><SelectTrigger class="text-xs"><SelectValue /></SelectTrigger><SelectContent><SelectItem value="public">Public</SelectItem><SelectItem value="participant">Participant Only</SelectItem><SelectItem value="admin">Admin Only</SelectItem></SelectContent></Select>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs">Close Date</Label>
                            <Input type="datetime-local" v-model="closedAt" class="text-xs" />
                        </div>
                    </CardContent>
                </Card>
                <Button class="w-full" @click="handleSave"><Save class="mr-1.5 size-4" />Save Form</Button>
            </div>
        </div>
    </div>
</template>
