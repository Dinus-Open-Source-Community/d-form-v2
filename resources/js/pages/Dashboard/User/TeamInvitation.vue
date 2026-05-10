<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import FormFillLayout from '@/layouts/FormFillLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { readFieldMetadata, readFieldRules } from '@/lib/formFieldMetadata'
import { normalizeBannerSrc } from '@/components/modules/builder/formBanner'
import type { FormFillOptionRow } from '@/types/form'
import { CheckCircle2, Send } from 'lucide-vue-next'

defineOptions({ layout: FormFillLayout })

const props = defineProps<{
    event: { id: string; slug: string; title: string }
    form: { id: string; title: string }
    fields: IFormField[]
    answers: Record<string, unknown>
    leader: { name: string; email: string }
    alreadyConfirmed: boolean
    confirmUrl: string
}>()

function metadata(field: IFormField): Record<string, unknown> {
    return field.metadata && typeof field.metadata === 'object' ? field.metadata : {}
}

function builderType(field: IFormField): string {
    const value = metadata(field).builderType
    if (typeof value === 'string') return value
    return field.type
}

const appendableFields = computed(() => props.fields.filter((f) => f.is_append))

function initialFormState(): Record<string, unknown> {
    const o: Record<string, unknown> = {}
    for (const f of appendableFields.value) {
        const raw = props.answers[f.name]
        const meta = readFieldMetadata(f)
        if (f.type === 'checkbox' || (f.type === 'select' && meta.is_multiple)) {
            o[f.name] = Array.isArray(raw) ? [...raw] : []
        } else if (f.type === 'fileUpload') {
            o[f.name] = null
        } else {
            o[f.name] = raw != null && raw !== '' ? String(raw) : ''
        }
    }
    return o
}

const confirmForm = useForm(initialFormState())

function displayValue(field: IFormField): string {
    const v = props.answers[field.name]
    if (v == null || v === '') return '—'
    if (Array.isArray(v)) return v.join(', ')
    return String(v)
}

function getOptionRows(field: IFormField): FormFillOptionRow[] {
    const oc = metadata(field).optionChoices
    if (Array.isArray(oc)) {
        const rows: FormFillOptionRow[] = []
        for (const item of oc) {
            if (item && typeof item === 'object' && item !== null) {
                const typedItem = item as { type?: string; label?: string; imageUrl?: string }
                const type = (typedItem.type === 'image' ? 'image' : 'text') as 'text' | 'image'
                const label = String(typedItem.label ?? '').trim()
                const rawUrl = typeof typedItem.imageUrl === 'string' ? String(typedItem.imageUrl).trim() : ''
                rows.push({
                    type,
                    label: type === 'text' ? label : (label || 'Image Choice'),
                    imageSrc: rawUrl ? normalizeBannerSrc(rawUrl) : undefined,
                })
            }
        }
        if (rows.length > 0) return rows
    }
    const csv = typeof metadata(field).options === 'string' ? metadata(field).options as string : ''
    const labels = csv.split(',').map((s) => s.trim()).filter(Boolean)
    return labels.map((label) => ({ type: 'text' as const, label }))
}

function onCheckboxToggle(fieldName: string, option: string, checked: boolean) {
    const current = Array.isArray(confirmForm[fieldName]) ? (confirmForm[fieldName] as string[]) : []
    confirmForm[fieldName] = checked ? [...current, option] : current.filter((x) => x !== option)
}

function submitConfirm() {
    confirmForm
        .transform((data) => ({ ...data, invitation_decision: 'accept' as const }))
        .post(props.confirmUrl, { forceFormData: true })
}

function submitReject() {
    router.post(props.confirmUrl, { invitation_decision: 'reject' })
}
</script>

<template>
    <Head title="Registration invitation" />

    <div class="mx-auto max-w-2xl px-2 pb-16">
        <h1 class="font-display mt-6 text-2xl font-bold tracking-tight text-foreground">Registration invitation</h1>
        <p class="mt-1 text-sm text-muted-foreground">
            {{ event.title }} — {{ form.title }}
        </p>
        <p class="mt-4 text-sm text-muted-foreground">
            Invited by: <span class="font-medium text-foreground">{{ leader.name }} ({{ leader.email }})</span>
        </p>

        <Card v-if="alreadyConfirmed" class="mt-8 border-success/30 bg-success/5">
            <CardContent class="flex flex-col items-center gap-3 py-10 text-center">
                <CheckCircle2 class="size-10 text-success" />
                <p class="font-medium text-foreground">You have already confirmed this team registration.</p>
                <Button variant="outline" as-child class="mt-2">
                    <a :href="`/user/dashboard/events/${event.slug}`">Back to event</a>
                </Button>
            </CardContent>
        </Card>

        <template v-else>
            <p class="mt-6 text-sm leading-relaxed text-muted-foreground">
                Review the details below. You may update fields marked as editable, then accept or decline this invitation.
            </p>

            <div class="mt-8 flex flex-col gap-6">
                <template v-for="field in fields" :key="field.id">
                    <div
                        v-if="builderType(field) === 'heading'"
                        class="rounded-2xl border border-border bg-primary/8 px-5 py-4 shadow-xs"
                    >
                        <h2 class="font-display text-xl font-bold text-foreground">
                            {{ (metadata(field).content as string) || field.label }}
                        </h2>
                    </div>

                    <div
                        v-else-if="builderType(field) === 'paragraph'"
                        class="rounded-2xl border border-border bg-card px-5 py-4 text-sm text-muted-foreground shadow-xs"
                    >
                        {{ (metadata(field).content as string) || field.description || field.label }}
                    </div>

                    <hr v-else-if="builderType(field) === 'divider'" class="app-divider my-1" />

                    <div v-else-if="builderType(field) === 'banner'" class="hidden" />

                    <!-- Appendable: editable -->
                    <Card v-else-if="field.is_append" class="overflow-hidden">
                        <CardHeader class="pb-2 pt-4">
                            <CardTitle class="text-sm font-semibold">
                                {{ field.label }}
                                <span v-if="readFieldRules(field).required" class="text-destructive">*</span>
                            </CardTitle>
                            <p v-if="field.description" class="text-xs text-muted-foreground">{{ field.description }}</p>
                        </CardHeader>
                        <CardContent class="space-y-2 pb-4">
                            <Input
                                v-if="
                                    ['short_text', 'email', 'phone', 'number', 'time', 'input'].includes(builderType(field)) &&
                                    !readFieldMetadata(field).is_multiple
                                "
                                v-model="confirmForm[field.name] as string"
                                class="min-h-11"
                            />
                            <Textarea
                                v-else-if="builderType(field) === 'long_text' || builderType(field) === 'address' || field.type === 'textarea'"
                                v-model="confirmForm[field.name] as string"
                                rows="4"
                            />
                            <Input v-else-if="builderType(field) === 'date' || field.type === 'datePicker'" v-model="confirmForm[field.name] as string" type="date" />
                            <div v-else-if="field.type === 'checkbox' || (field.type === 'select' && readFieldMetadata(field).is_multiple)" class="flex flex-col gap-2">
                                <label
                                    v-for="row in getOptionRows(field)"
                                    :key="row.label"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm"
                                >
                                    <Checkbox
                                        :checked="((confirmForm[field.name] as string[]) ?? []).includes(row.label)"
                                        @update:checked="(v: boolean | 'indeterminate') => onCheckboxToggle(field.name, row.label, v === true)"
                                    />
                                    {{ row.label }}
                                </label>
                            </div>
                            <div v-else-if="field.type === 'radio' || builderType(field) === 'radio' || builderType(field) === 'yes_no'" class="flex flex-col gap-2">
                                <label v-for="row in getOptionRows(field)" :key="row.label" class="flex items-center gap-2 text-sm">
                                    <input
                                        type="radio"
                                        :name="field.name"
                                        :value="row.label"
                                        :checked="(confirmForm[field.name] as string) === row.label"
                                        class="size-4 accent-primary"
                                        @change="confirmForm[field.name] = row.label"
                                    />
                                    {{ row.label }}
                                </label>
                            </div>
                            <Select v-else-if="field.type === 'select'" v-model="confirmForm[field.name] as string">
                                <SelectTrigger><SelectValue :placeholder="'Select'" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="row in getOptionRows(field)" :key="row.label" :value="row.label">
                                        {{ row.label }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-else-if="field.type === 'fileUpload'" class="rounded-xl border border-dashed p-4">
                                <p v-if="typeof answers[field.name] === 'string' && answers[field.name]" class="mb-2 text-xs text-muted-foreground">
                                    Current file: {{ String(answers[field.name]).split('/').pop() }}
                                </p>
                                <input type="file" class="w-full text-xs" @change="(e) => (confirmForm[field.name] = (e.target as HTMLInputElement).files?.[0] ?? null)" />
                            </div>
                            <p v-if="confirmForm.errors[field.name]" class="text-xs font-medium text-destructive">{{ confirmForm.errors[field.name] }}</p>
                        </CardContent>
                    </Card>

                    <!-- Read-only snapshot -->
                    <Card v-else class="overflow-hidden bg-muted/20">
                        <CardHeader class="pb-2 pt-4">
                            <CardTitle class="text-sm font-semibold text-muted-foreground">{{ field.label }}</CardTitle>
                        </CardHeader>
                        <CardContent class="pb-4 text-sm text-foreground">
                            {{ displayValue(field) }}
                        </CardContent>
                    </Card>
                </template>
            </div>

            <div class="mt-10 flex flex-wrap justify-end gap-3 border-t border-border pt-6">
                <Button type="button" variant="outline" size="lg" :disabled="confirmForm.processing" @click="submitReject">
                    Decline
                </Button>
                <Button type="button" size="lg" class="gap-2" :disabled="confirmForm.processing" @click="submitConfirm">
                    <Send class="size-4" />
                    Accept invitation
                </Button>
            </div>
        </template>
    </div>
</template>
