<script setup lang="ts">
import { watch } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
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
import { Plus, GripVertical, Trash2, Save, Send, ChevronsUp, ChevronsDown } from 'lucide-vue-next'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event: { id: string; title: string }
    form: IForm
    fields: IFormField[]
    saveFieldsUrl: string
    updateFormUrl: string
}>()

const visibilityOptions = [
    { value: 'public', label: 'Public' },
    { value: 'participant', label: 'Participant' },
    { value: 'admin', label: 'Admin' },
] as const

const fieldTypes: { value: FormFieldType; label: string }[] = [
    { value: 'input', label: 'Text input' },
    { value: 'select', label: 'Select (dropdown)' },
    { value: 'radio', label: 'Radio (single choice)' },
    { value: 'checkbox', label: 'Checkbox (multiple choice)' },
    { value: 'textarea', label: 'Textarea' },
    { value: 'datePicker', label: 'Date' },
    { value: 'fileUpload', label: 'File upload' },
]

const inputSubtypes = [
    { value: 'text', label: 'Text' },
    { value: 'number', label: 'Number' },
    { value: 'email', label: 'Email' },
    { value: 'password', label: 'Password' },
    { value: 'tel', label: 'Phone' },
]

const settingsForm = useForm({
    _method: 'put' as const,
    title: props.form.title,
    description: props.form.description,
    closed_at: props.form.closed_at ?? '',
    visible_for: [...props.form.visible_for],
})

watch(
    () => props.form,
    (f) => {
        settingsForm.title = f.title
        settingsForm.description = f.description
        settingsForm.closed_at = f.closed_at ?? ''
        settingsForm.visible_for = [...f.visible_for]
    },
    { deep: true }
)

const fieldForm = useForm({ fields: [] as IFormField[] })

function normalizeMetadata(type: FormFieldType): Record<string, unknown> {
    const base: Record<string, unknown> = { rules: {} }
    if (type === 'input') {
        return { ...base, placeholder: '', type: 'text' }
    }
    if (type === 'select') {
        return { ...base, is_multiple: false, rules: { in: '' } }
    }
    if (type === 'radio') {
        return { ...base, options: '' }
    }
    if (type === 'checkbox') {
        return { ...base, options: '' }
    }
    if (type === 'textarea') {
        return { ...base, placeholder: '' }
    }
    if (type === 'datePicker') {
        return { ...base, rules: {} }
    }
    if (type === 'fileUpload') {
        return { ...base, rules: { mimes: 'pdf,jpg,png', max_size: 2048 } }
    }
    return base
}

function defaultFieldRow(): IFormField {
    const order = fieldForm.fields.length + 1
    return {
        id: crypto.randomUUID(),
        type: 'input',
        label: '',
        name: `field_${crypto.randomUUID().replace(/-/g, '').slice(0, 12)}`,
        order,
        description: null,
        metadata: normalizeMetadata('input'),
    }
}

function syncFieldsFromProps() {
    const copy = JSON.parse(JSON.stringify(props.fields)) as IFormField[]
    copy.sort((a, b) => a.order - b.order)
    fieldForm.fields = copy
}

watch(
    () => props.fields,
    () => {
        syncFieldsFromProps()
    },
    { immediate: true, deep: true }
)

function toggleVisibility(value: string, checked: boolean) {
    const set = new Set(settingsForm.visible_for)
    if (checked) set.add(value)
    else set.delete(value)
    settingsForm.visible_for = Array.from(set)
}

function addField() {
    fieldForm.fields.push(defaultFieldRow())
    renumberOrders()
}

function removeField(id: string) {
    fieldForm.fields = fieldForm.fields.filter((f) => f.id !== id)
    renumberOrders()
}

function renumberOrders() {
    fieldForm.fields.forEach((f, i) => {
        f.order = i + 1
    })
}

function moveField(index: number, direction: -1 | 1) {
    const next = index + direction
    if (next < 0 || next >= fieldForm.fields.length) return
    const arr = [...fieldForm.fields]
    const t = arr[index]
    arr[index] = arr[next]!
    arr[next] = t!
    fieldForm.fields = arr
    renumberOrders()
}

function onTypeChange(field: IFormField, type: string) {
    const t = type as FormFieldType
    field.type = t
    field.metadata = normalizeMetadata(t)
}

function saveSettings() {
    settingsForm.put(props.updateFormUrl, {
        preserveScroll: true,
        onSuccess: () => toast.success('Form settings saved.'),
    })
}

function saveFields() {
    renumberOrders()
    fieldForm.post(props.saveFieldsUrl, {
        preserveScroll: true,
        onSuccess: () => toast.success('Fields saved.'),
    })
}

function getRules(field: IFormField): Record<string, unknown> {
    const m = field.metadata
    if (!m.rules) m.rules = {}
    return m.rules as Record<string, unknown>
}
</script>

<template>
    <Head :title="`Form: ${form.title}`" />

    <div class="flex flex-col gap-6">
        <PageHeader :title="form.title" subtitle="Form settings and dynamic fields" />

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="flex flex-col gap-4 lg:col-span-1">
                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-sm font-medium">Settings</CardTitle>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-3 pt-0">
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs">Title</Label>
                            <Input v-model="settingsForm.title" class="text-xs" />
                            <p v-if="settingsForm.errors.title" class="text-destructive text-xs">
                                {{ settingsForm.errors.title }}
                            </p>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs">Description</Label>
                            <Textarea v-model="settingsForm.description" rows="3" class="text-xs" />
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <Label class="text-xs">Closes at</Label>
                            <Input v-model="settingsForm.closed_at" type="datetime-local" class="text-xs" />
                        </div>
                        <div class="flex flex-col gap-2">
                            <Label class="text-xs">Visible for</Label>
                            <div class="flex flex-col gap-2">
                                <label v-for="opt in visibilityOptions" :key="opt.value" class="flex items-center gap-2 text-xs">
                                    <Checkbox
                                        :checked="settingsForm.visible_for.includes(opt.value)"
                                        @update:checked="(v: boolean) => toggleVisibility(opt.value, !!v)"
                                    />
                                    {{ opt.label }}
                                </label>
                            </div>
                        </div>
                        <Button size="sm" :disabled="settingsForm.processing" @click="saveSettings">
                            <Send class="mr-1.5 size-3.5" />Update settings
                        </Button>
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-col gap-4 lg:col-span-2">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-medium">Form fields</h2>
                    <div class="flex gap-2">
                        <Button variant="outline" size="sm" @click="addField">
                            <Plus class="mr-1.5 size-3.5" />Add field
                        </Button>
                        <Button size="sm" :disabled="fieldForm.processing" @click="saveFields">
                            <Save class="mr-1.5 size-3.5" />Save fields
                        </Button>
                    </div>
                </div>

                <p v-if="Object.keys(fieldForm.errors).length" class="text-destructive text-xs">
                    Please fix validation errors and try again.
                </p>

                <div class="flex flex-col gap-3">
                    <Card
                        v-for="(field, idx) in fieldForm.fields"
                        :key="field.id"
                        class="rounded-xl border shadow-xs"
                    >
                        <CardContent class="p-4">
                            <div class="flex items-start gap-2">
                                <div class="mt-1 flex flex-col gap-0.5 text-muted-foreground/30">
                                    <Button type="button" variant="ghost" size="icon" class="size-7" @click="moveField(idx, -1)">
                                        <ChevronsUp class="size-3.5" />
                                    </Button>
                                    <div class="flex justify-center">
                                        <GripVertical class="size-4" />
                                    </div>
                                    <Button type="button" variant="ghost" size="icon" class="size-7" @click="moveField(idx, 1)">
                                        <ChevronsDown class="size-3.5" />
                                    </Button>
                                </div>
                                <div class="flex min-w-0 flex-1 flex-col gap-3">
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="flex flex-col gap-1.5">
                                            <Label class="text-xs">Label</Label>
                                            <Input v-model="field.label" class="text-xs" placeholder="Field label" />
                                        </div>
                                        <div class="flex flex-col gap-1.5">
                                            <Label class="text-xs">Name (key)</Label>
                                            <Input v-model="field.name" class="text-xs" placeholder="internal_name" />
                                        </div>
                                    </div>
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div class="flex flex-col gap-1.5">
                                            <Label class="text-xs">Type</Label>
                                            <Select
                                                :model-value="field.type"
                                                @update:model-value="(v) => v && onTypeChange(field, v)"
                                            >
                                                <SelectTrigger class="text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem v-for="ft in fieldTypes" :key="ft.value" :value="ft.value">
                                                        {{ ft.label }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                        <div v-if="field.type === 'input'" class="flex flex-col gap-1.5">
                                            <Label class="text-xs">Input subtype</Label>
                                            <Select
                                                :model-value="String((field.metadata as Record<string, unknown>).type ?? 'text')"
                                                @update:model-value="
                                                    (v) => {
                                                        if (v) (field.metadata as Record<string, unknown>).type = v
                                                    }
                                                "
                                            >
                                                <SelectTrigger class="text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="st in inputSubtypes"
                                                        :key="st.value"
                                                        :value="st.value"
                                                    >
                                                        {{ st.label }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div
                                        v-if="field.type === 'input' || field.type === 'textarea'"
                                        class="flex flex-col gap-1.5"
                                    >
                                        <Label class="text-xs">Placeholder</Label>
                                        <Input
                                            :model-value="String((field.metadata as Record<string, unknown>).placeholder ?? '')"
                                            class="text-xs"
                                            @update:model-value="
                                                (v) => ((field.metadata as Record<string, unknown>).placeholder = v)
                                            "
                                        />
                                    </div>

                                    <div v-if="field.type === 'select'" class="grid gap-3 sm:grid-cols-2">
                                        <div class="flex items-center gap-2">
                                            <Checkbox
                                                :id="`multi-${field.id}`"
                                                :checked="(field.metadata as { is_multiple: boolean }).is_multiple"
                                                @update:checked="
                                                    (v) => ((field.metadata as { is_multiple: boolean }).is_multiple = !!v)
                                                "
                                            />
                                            <Label :for="`multi-${field.id}`" class="text-xs">Allow multiple</Label>
                                        </div>
                                        <div class="flex flex-col gap-1.5">
                                            <Label class="text-xs">Options (comma separated)</Label>
                                            <Input
                                                class="text-xs"
                                                :model-value="(getRules(field)['in'] as string) ?? ''"
                                                @update:model-value="(v) => (getRules(field)['in'] = v as string)"
                                            />
                                        </div>
                                    </div>

                                    <div v-if="field.type === 'radio' || field.type === 'checkbox'" class="flex flex-col gap-1.5">
                                        <Label class="text-xs">Options (comma separated)</Label>
                                        <Input
                                            class="text-xs"
                                            placeholder="e.g. Option A,Option B,Option C"
                                            :model-value="(field.metadata as { options: string }).options ?? ''"
                                            @update:model-value="
                                                (v) => ((field.metadata as { options: string }).options = v as string)
                                            "
                                        />
                                        <p class="text-[11px] text-muted-foreground">
                                            {{ field.type === 'radio' ? 'Respondent picks one option.' : 'Respondent can pick multiple options.' }}
                                        </p>
                                    </div>

                                    <div v-if="field.type === 'datePicker'" class="grid gap-2 sm:grid-cols-2">
                                        <div class="flex flex-col gap-1.5">
                                            <Label class="text-xs">Min date</Label>
                                            <Input
                                                type="date"
                                                class="text-xs"
                                                :model-value="(getRules(field)['min_date'] as string) ?? ''"
                                                @update:model-value="(v) => (getRules(field)['min_date'] = v as string)"
                                            />
                                        </div>
                                        <div class="flex flex-col gap-1.5">
                                            <Label class="text-xs">Max date</Label>
                                            <Input
                                                type="date"
                                                class="text-xs"
                                                :model-value="(getRules(field)['max_date'] as string) ?? ''"
                                                @update:model-value="(v) => (getRules(field)['max_date'] = v as string)"
                                            />
                                        </div>
                                    </div>

                                    <div v-if="field.type === 'fileUpload'" class="grid gap-2 sm:grid-cols-2">
                                        <div class="flex flex-col gap-1.5">
                                            <Label class="text-xs">Allowed mimes (comma)</Label>
                                            <Input
                                                class="text-xs"
                                                :model-value="(getRules(field)['mimes'] as string) ?? ''"
                                                @update:model-value="(v) => (getRules(field)['mimes'] = v as string)"
                                            />
                                        </div>
                                        <div class="flex flex-col gap-1.5">
                                            <Label class="text-xs">Max size (KB)</Label>
                                            <Input
                                                type="number"
                                                class="text-xs"
                                                :model-value="(getRules(field)['max_size'] as number) ?? ''"
                                                @update:model-value="(v) => (getRules(field)['max_size'] = Number(v) || 0)"
                                            />
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <Checkbox
                                            :id="`req-${field.id}`"
                                            :checked="!!(getRules(field)['required'] as boolean)"
                                            @update:checked="(v) => (getRules(field)['required'] = !!v)"
                                        />
                                        <Label :for="`req-${field.id}`" class="text-xs font-normal">Required</Label>
                                    </div>
                                </div>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    class="size-7 text-destructive hover:text-destructive"
                                    type="button"
                                    @click="removeField(field.id)"
                                >
                                    <Trash2 class="size-3.5" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <div
                        v-if="fieldForm.fields.length === 0"
                        class="flex flex-col items-center justify-center rounded-xl border border-dashed border-border/60 bg-muted/20 py-12 text-center"
                    >
                        <p class="text-sm text-muted-foreground">No fields yet.</p>
                        <Button variant="outline" size="sm" class="mt-3" @click="addField">
                            <Plus class="mr-1.5 size-3.5" />Add first field
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
