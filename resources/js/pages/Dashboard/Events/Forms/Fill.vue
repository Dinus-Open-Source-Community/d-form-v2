<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select'
import { AlertCircle, CheckCircle2, Send, ArrowLeft } from 'lucide-vue-next'

defineOptions({ layout: DashboardFocusLayout })

const props = defineProps<{
    event: { id: string; title: string }
    form: { id: string; title: string; description: string; closed_at: string | null }
    fields: IFormField[]
    submitUrl: string
    alreadySubmitted: boolean
}>()

const isClosed = computed(() => {
    if (!props.form.closed_at) return false
    return new Date(props.form.closed_at) < new Date()
})

type AnswerMap = Record<string, string | string[] | File | null>

const initialValues: AnswerMap = {}
for (const field of props.fields) {
    if (field.type === 'checkbox') {
        initialValues[field.name] = []
    } else if (field.type === 'fileUpload') {
        initialValues[field.name] = null
    } else {
        initialValues[field.name] = ''
    }
}

const form = useForm(initialValues)

function getOptions(field: IFormField): string[] {
    const meta = field.metadata as Record<string, unknown>
    const raw = meta.options as string | undefined
    if (!raw) return []
    return raw.split(',').map((s) => s.trim()).filter(Boolean)
}

function getSelectOptions(field: IFormField): string[] {
    const meta = field.metadata as Record<string, unknown>
    const rules = (meta.rules ?? {}) as Record<string, unknown>
    const raw = rules.in as string | undefined
    if (!raw) return []
    return raw.split(',').map((s) => s.trim()).filter(Boolean)
}

function getInputSubtype(field: IFormField): string {
    const meta = field.metadata as Record<string, unknown>
    return (meta.type as string) ?? 'text'
}

function getPlaceholder(field: IFormField): string {
    const meta = field.metadata as Record<string, unknown>
    return (meta.placeholder as string) ?? ''
}

function isRequired(field: IFormField): boolean {
    const meta = field.metadata as Record<string, unknown>
    const rules = (meta.rules ?? {}) as Record<string, unknown>
    return Boolean(rules.required)
}

function isMultipleSelect(field: IFormField): boolean {
    const meta = field.metadata as Record<string, unknown>
    return Boolean(meta.is_multiple)
}

function onCheckboxToggle(fieldName: string, option: string, checked: boolean) {
    const current = (form[fieldName] as string[]) ?? []
    if (checked) {
        form[fieldName] = [...current, option]
    } else {
        form[fieldName] = current.filter((v) => v !== option)
    }
}

function onFileChange(fieldName: string, e: Event) {
    const input = e.target as HTMLInputElement
    form[fieldName] = input.files?.[0] ?? null
}

function submit() {
    form.post(props.submitUrl, {
        forceFormData: true,
    })
}

function fieldError(name: string): string | undefined {
    return (form.errors as Record<string, string>)[name]
}
</script>

<template>
    <Head :title="`Register: ${form.title}`" />

    <div class="mx-auto max-w-2xl">
        <div class="mb-6 flex items-center gap-3">
            <Button variant="ghost" size="sm" class="gap-1.5" as-child>
                <Link :href="`/dashboard/events/${event.id}`">
                    <ArrowLeft class="size-3.5" />
                    Back to event
                </Link>
            </Button>
        </div>

        <PageHeader :title="form.title" :subtitle="form.description ?? undefined" />

        <!-- Already submitted -->
        <Card v-if="alreadySubmitted" class="mt-6 rounded-xl border shadow-xs">
            <CardContent class="flex flex-col items-center gap-3 py-12 text-center">
                <CheckCircle2 class="size-10 text-success" />
                <p class="text-base font-semibold">You have already submitted this form.</p>
                <p class="text-sm text-muted-foreground">
                    Your registration for <span class="font-medium">{{ event.title }}</span> has been recorded.
                </p>
                <Button variant="outline" size="sm" class="mt-2" as-child>
                    <Link :href="`/dashboard/events/${event.id}`">View event</Link>
                </Button>
            </CardContent>
        </Card>

        <!-- Form closed -->
        <Card v-else-if="isClosed" class="mt-6 rounded-xl border shadow-xs">
            <CardContent class="flex flex-col items-center gap-3 py-12 text-center">
                <AlertCircle class="size-10 text-muted-foreground" />
                <p class="text-base font-semibold">Registration is closed.</p>
                <p class="text-sm text-muted-foreground">
                    This form is no longer accepting submissions.
                </p>
            </CardContent>
        </Card>

        <!-- The form -->
        <form v-else class="mt-6 flex flex-col gap-4" @submit.prevent="submit">
            <Card
                v-for="field in fields"
                :key="field.id"
                class="rounded-xl border shadow-xs"
            >
                <CardHeader class="pb-2 pt-4">
                    <CardTitle class="flex items-start gap-1 text-sm font-medium">
                        {{ field.label }}
                        <span v-if="isRequired(field)" class="text-destructive">*</span>
                    </CardTitle>
                    <p v-if="field.description" class="text-xs text-muted-foreground">
                        {{ field.description }}
                    </p>
                </CardHeader>
                <CardContent class="pb-4 pt-0">
                    <!-- Text / Number / Email / Tel / Password input -->
                    <template v-if="field.type === 'input'">
                        <Input
                            :id="field.name"
                            :type="getInputSubtype(field)"
                            :placeholder="getPlaceholder(field)"
                            :model-value="form[field.name] as string"
                            class="text-sm"
                            @update:model-value="(v) => (form[field.name] = v)"
                        />
                    </template>

                    <!-- Textarea -->
                    <template v-else-if="field.type === 'textarea'">
                        <Textarea
                            :id="field.name"
                            :placeholder="getPlaceholder(field)"
                            :model-value="form[field.name] as string"
                            rows="4"
                            class="text-sm"
                            @update:model-value="(v) => (form[field.name] = v)"
                        />
                    </template>

                    <!-- Date -->
                    <template v-else-if="field.type === 'datePicker'">
                        <Input
                            :id="field.name"
                            type="date"
                            :model-value="form[field.name] as string"
                            class="text-sm"
                            @update:model-value="(v) => (form[field.name] = v)"
                        />
                    </template>

                    <!-- Select (dropdown) -->
                    <template v-else-if="field.type === 'select'">
                        <Select
                            :model-value="form[field.name] as string"
                            @update:model-value="(v) => (form[field.name] = v ?? '')"
                        >
                            <SelectTrigger class="text-sm">
                                <SelectValue placeholder="Select an option" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="opt in getSelectOptions(field)"
                                    :key="opt"
                                    :value="opt"
                                >
                                    {{ opt }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="isMultipleSelect(field)" class="mt-1 text-xs text-muted-foreground">
                            Multiple selections allowed.
                        </p>
                    </template>

                    <!-- Radio (single choice) -->
                    <template v-else-if="field.type === 'radio'">
                        <div class="flex flex-col gap-2">
                            <label
                                v-for="opt in getOptions(field)"
                                :key="opt"
                                class="flex cursor-pointer items-center gap-2.5 text-sm"
                            >
                                <input
                                    type="radio"
                                    :name="field.name"
                                    :value="opt"
                                    :checked="(form[field.name] as string) === opt"
                                    class="accent-primary"
                                    @change="() => (form[field.name] = opt)"
                                />
                                {{ opt }}
                            </label>
                        </div>
                    </template>

                    <!-- Checkbox (multiple choice) -->
                    <template v-else-if="field.type === 'checkbox'">
                        <div class="flex flex-col gap-2">
                            <label
                                v-for="opt in getOptions(field)"
                                :key="opt"
                                class="flex cursor-pointer items-center gap-2.5 text-sm"
                            >
                                <Checkbox
                                    :id="`${field.name}-${opt}`"
                                    :checked="(form[field.name] as string[]).includes(opt)"
                                    @update:checked="(v) => onCheckboxToggle(field.name, opt, !!v)"
                                />
                                {{ opt }}
                            </label>
                        </div>
                    </template>

                    <!-- File upload -->
                    <template v-else-if="field.type === 'fileUpload'">
                        <Input
                            :id="field.name"
                            type="file"
                            class="cursor-pointer text-sm"
                            @change="onFileChange(field.name, $event)"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{
                                (() => {
                                    const meta = field.metadata as Record<string, unknown>
                                    const rules = (meta.rules ?? {}) as Record<string, unknown>
                                    const parts: string[] = []
                                    if (rules.mimes) parts.push(`Allowed: ${rules.mimes}`)
                                    if (rules.max_size) parts.push(`Max size: ${rules.max_size} KB`)
                                    return parts.join(' · ')
                                })()
                            }}
                        </p>
                    </template>

                    <!-- Field error -->
                    <p v-if="fieldError(field.name)" class="mt-1.5 text-xs text-destructive">
                        {{ fieldError(field.name) }}
                    </p>
                </CardContent>
            </Card>

            <div v-if="fields.length === 0" class="rounded-xl border border-dashed border-border/60 bg-muted/20 py-10 text-center text-sm text-muted-foreground">
                This form has no fields configured.
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <Button variant="outline" as-child>
                    <Link :href="`/dashboard/events/${event.id}`">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Send class="mr-1.5 size-4" />
                    Submit registration
                </Button>
            </div>
        </form>
    </div>
</template>
