<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue'
import PageHeader from '@/components/modules/dashboard/PageHeader.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { AlertCircle, CheckCircle2, Send, ArrowLeft, Star } from 'lucide-vue-next'
import { normalizeBannerSrc, pickFormBannerField, filterBodyFields } from '@/components/modules/builder/formBanner'

defineOptions({ layout: DashboardFocusLayout })

type FormAccessStatus = 'allowed' | 'not_visible' | 'form_closed' | 'registration_not_open' | 'quota_full' | 'already_submitted'
type AnswerValue = string | string[] | File | null
type AnswerMap = Record<string, AnswerValue>

const props = defineProps<{
    event: { id: string; title: string }
    form: { id: string; title: string; description: string | null; closed_at: string | null }
    fields: IFormField[]
    submitUrl: string
    accessStatus: FormAccessStatus
    accessMessage: string
}>()

function metadata(field: IFormField): Record<string, unknown> {
    return field.metadata && typeof field.metadata === 'object' ? field.metadata : {}
}

function rules(field: IFormField): Record<string, unknown> {
    const value = metadata(field).rules
    return value && typeof value === 'object' ? value as Record<string, unknown> : {}
}

function builderType(field: IFormField): string {
    const value = metadata(field).builderType
    return typeof value === 'string' ? value : field.type
}

function isDisplayOnly(field: IFormField): boolean {
    return ['heading', 'paragraph', 'divider', 'banner'].includes(builderType(field))
}

const formBannerField = computed(() => pickFormBannerField(props.fields))
const bodyFields = computed(() => filterBodyFields(props.fields))
const formBannerImageSrc = computed(() => {
    const fb = formBannerField.value
    if (!fb) return ''
    return normalizeBannerSrc(bannerUrl(fb))
})
const formBannerCaption = computed(() => {
    const fb = formBannerField.value
    if (!fb) return ''
    const raw = metadata(fb).content
    return typeof raw === 'string' ? raw : ''
})

const formHasDescription = computed(() => Boolean(props.form.description?.trim()))

const answerFields = computed(() => props.fields.filter((field) => !isDisplayOnly(field)))
const isBlocked = computed(() => props.accessStatus !== 'allowed')

const blockCopy = computed(() => {
    const fallback = props.accessMessage || 'This form is not available right now.'
    const map: Record<FormAccessStatus, { title: string; body: string; success?: boolean }> = {
        allowed: { title: '', body: '' },
        already_submitted: { title: 'You have already submitted this form.', body: fallback, success: true },
        form_closed: { title: 'Registration is closed.', body: fallback },
        registration_not_open: { title: 'Registration is not currently open.', body: fallback },
        quota_full: { title: 'Registration is full.', body: fallback },
        not_visible: { title: 'You do not have access to this form.', body: fallback },
    }
    return map[props.accessStatus]
})

const initialValues: AnswerMap = {}
for (const field of answerFields.value) {
    if (field.type === 'checkbox' || isMultipleSelect(field)) {
        initialValues[field.name] = []
    } else if (field.type === 'fileUpload') {
        initialValues[field.name] = null
    } else {
        initialValues[field.name] = ''
    }
}

const answerForm = useForm<AnswerMap>(initialValues)

function listFromCsv(value: unknown): string[] {
    return typeof value === 'string' ? value.split(',').map((item) => item.trim()).filter(Boolean) : []
}

function getOptions(field: IFormField): string[] {
    const direct = metadata(field).options
    if (typeof direct === 'string') return listFromCsv(direct)
    const ruleOptions = rules(field).in
    return listFromCsv(ruleOptions)
}

type OptionRow = { label: string; imageSrc?: string }

function getOptionRows(field: IFormField): OptionRow[] {
    const oc = metadata(field).optionChoices
    if (Array.isArray(oc)) {
        const rows: OptionRow[] = []
        for (const item of oc) {
            if (item && typeof item === 'object' && item !== null && 'label' in item) {
                const label = String((item as { label: unknown }).label ?? '').trim()
                if (!label) continue
                const rawUrl =
                    typeof (item as { imageUrl?: unknown }).imageUrl === 'string'
                        ? String((item as { imageUrl: string }).imageUrl).trim()
                        : ''
                rows.push({
                    label,
                    imageSrc: rawUrl ? normalizeBannerSrc(rawUrl) : undefined,
                })
            }
        }
        if (rows.length > 0) return rows
    }
    return getOptions(field).map((label) => ({ label }))
}

function getInputSubtype(field: IFormField): string {
    const type = metadata(field).type
    return typeof type === 'string' ? type : 'text'
}

function getPlaceholder(field: IFormField): string {
    const placeholder = metadata(field).placeholder
    return typeof placeholder === 'string' ? placeholder : ''
}

function isRequired(field: IFormField): boolean {
    return Boolean(rules(field).required)
}

function isMultipleSelect(field: IFormField): boolean {
    return Boolean(metadata(field).is_multiple)
}

function isRadioLike(field: IFormField): boolean {
    return field.type === 'radio' || builderType(field) === 'radio'
}

function isCheckboxLike(field: IFormField): boolean {
    return field.type === 'checkbox' || builderType(field) === 'checkbox' || isMultipleSelect(field)
}

function fileHint(field: IFormField): string {
    const parts: string[] = []
    const fieldRules = rules(field)
    if (fieldRules.mimes) parts.push(`Allowed: ${String(fieldRules.mimes)}`)
    if (fieldRules.max_size) parts.push(`Max size: ${String(fieldRules.max_size)} KB`)
    return parts.join(' · ')
}

function acceptValue(field: IFormField): string | undefined {
    const mimes = rules(field).mimes
    if (typeof mimes !== 'string') return undefined
    return mimes.split(',').map((ext) => `.${ext.trim().replace(/^\./, '')}`).join(',')
}

function contentText(field: IFormField): string {
    const content = metadata(field).content
    return typeof content === 'string' ? content : ''
}

function bannerUrl(field: IFormField): string {
    const url = metadata(field).bannerUrl
    return typeof url === 'string' ? url : ''
}

function onCheckboxToggle(fieldName: string, option: string, checked: boolean) {
    const current = Array.isArray(answerForm[fieldName]) ? answerForm[fieldName] as string[] : []
    answerForm[fieldName] = checked ? [...current, option] : current.filter((value) => value !== option)
}

function onFileChange(fieldName: string, event: Event) {
    const input = event.target as HTMLInputElement
    answerForm[fieldName] = input.files?.[0] ?? null
}

function submit() {
    if (isBlocked.value) return
    answerForm.post(props.submitUrl, { forceFormData: true })
}

function fieldError(name: string): string | undefined {
    return (answerForm.errors as Record<string, string>)[name]
}
</script>

<template>
    <Head :title="`Register: ${props.form.title}`" />

    <div class="mx-auto max-w-3xl">
        <div class="mb-6 flex items-center gap-3">
            <Button variant="ghost" size="sm" class="gap-1.5" as-child>
                <Link :href="`/dashboard/events/${event.id}`">
                    <ArrowLeft class="size-3.5" />
                    Back to event
                </Link>
            </Button>
        </div>

        <div
            v-if="formBannerField && (formBannerImageSrc || formBannerCaption)"
            class="overflow-hidden rounded-2xl border-[1.5px] border-[var(--brutal-ink)]/12 bg-white shadow-[var(--shadow-sm)]"
            :class="formHasDescription ? 'mb-6' : 'mb-4'"
        >
            <img
                v-if="formBannerImageSrc"
                :src="formBannerImageSrc"
                :alt="props.form.title"
                class="aspect-[3/1] w-full object-cover"
            />
            <p
                v-if="formBannerCaption"
                class="border-t border-[var(--brutal-ink)]/10 px-5 py-4 text-sm leading-relaxed text-muted-foreground"
            >
                {{ formBannerCaption }}
            </p>
        </div>

        <PageHeader :title="props.form.title" :subtitle="props.form.description ?? undefined" />

        <Card v-if="isBlocked" class="mt-6 rounded-2xl border shadow-xs">
            <CardContent class="flex flex-col items-center gap-3 py-12 text-center">
                <CheckCircle2 v-if="blockCopy.success" class="size-10 text-success" />
                <AlertCircle v-else class="size-10 text-warning" />
                <p class="text-base font-semibold">{{ blockCopy.title }}</p>
                <p class="max-w-md text-sm leading-relaxed text-muted-foreground">{{ blockCopy.body }}</p>
                <Button variant="outline" size="sm" class="mt-2" as-child>
                    <Link :href="`/dashboard/events/${event.id}`">View event</Link>
                </Button>
            </CardContent>
        </Card>

        <form
            v-else
            class="flex flex-col"
            :class="formHasDescription ? 'mt-6 gap-4' : 'mt-3 gap-3'"
            @submit.prevent="submit"
        >
            <template v-for="field in bodyFields" :key="field.id">
                <div v-if="builderType(field) === 'heading'" class="rounded-2xl border-[1.5px] border-[var(--brutal-ink)] bg-[var(--brutal-yellow)]/15 px-5 py-4 shadow-[var(--shadow-sm)]">
                    <h2 class="font-display text-2xl font-bold tracking-tight text-[var(--brutal-ink)]">{{ contentText(field) || field.label }}</h2>
                    <p v-if="field.description" class="mt-1 text-sm text-muted-foreground">{{ field.description }}</p>
                </div>

                <div v-else-if="builderType(field) === 'paragraph'" class="rounded-2xl border border-[var(--brutal-ink)]/15 bg-white/70 px-5 py-4 text-sm leading-relaxed text-muted-foreground shadow-[var(--shadow-xs)]">
                    {{ contentText(field) || field.description || field.label }}
                </div>

                <hr v-else-if="builderType(field) === 'divider'" class="brutal-divider my-2" />

                <Card v-else class="rounded-2xl border shadow-xs">
                    <CardHeader class="pb-2 pt-4">
                        <CardTitle class="flex items-start gap-1 text-sm font-semibold">
                            {{ field.label }}
                            <span v-if="isRequired(field)" class="text-destructive">*</span>
                        </CardTitle>
                        <p v-if="field.description" class="text-xs leading-relaxed text-muted-foreground">{{ field.description }}</p>
                    </CardHeader>
                    <CardContent class="pb-4 pt-0">
                        <Input
                            v-if="field.type === 'input' && builderType(field) !== 'rating'"
                            :id="field.name"
                            :type="getInputSubtype(field)"
                            :placeholder="getPlaceholder(field)"
                            :model-value="answerForm[field.name] as string"
                            class="text-sm"
                            @update:model-value="(value) => (answerForm[field.name] = String(value ?? ''))"
                        />

                        <div v-else-if="builderType(field) === 'rating'" class="flex flex-col gap-3">
                            <div class="flex gap-1.5">
                                <button
                                    v-for="rating in Number(metadata(field).maxStars ?? 5)"
                                    :key="rating"
                                    type="button"
                                    class="rounded-lg p-1 transition active:scale-95"
                                    @click="answerForm[field.name] = String(rating)"
                                >
                                    <Star class="size-7" :class="Number(answerForm[field.name] || 0) >= rating ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/40'" />
                                </button>
                            </div>
                            <p class="text-xs text-muted-foreground">Choose one rating.</p>
                        </div>

                        <Textarea
                            v-else-if="field.type === 'textarea'"
                            :id="field.name"
                            :placeholder="getPlaceholder(field)"
                            :model-value="answerForm[field.name] as string"
                            rows="4"
                            class="text-sm"
                            @update:model-value="(value) => (answerForm[field.name] = String(value ?? ''))"
                        />

                        <Input
                            v-else-if="field.type === 'datePicker'"
                            :id="field.name"
                            type="date"
                            :model-value="answerForm[field.name] as string"
                            class="text-sm"
                            @update:model-value="(value) => (answerForm[field.name] = String(value ?? ''))"
                        />

                        <div v-else-if="field.type === 'select' && isCheckboxLike(field)" class="flex flex-col gap-2">
                            <label
                                v-for="row in getOptionRows(field)"
                                :key="row.label"
                                class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[var(--brutal-ink)]/10 px-3 py-2 text-sm hover:bg-muted/30"
                            >
                                <Checkbox
                                    :id="`${field.name}-${row.label}`"
                                    :checked="((answerForm[field.name] as string[]) ?? []).includes(row.label)"
                                    @update:checked="(value) => onCheckboxToggle(field.name, row.label, Boolean(value))"
                                />
                                <img
                                    v-if="row.imageSrc"
                                    :src="row.imageSrc"
                                    alt=""
                                    class="size-8 shrink-0 rounded-md border border-[var(--brutal-ink)]/10 object-cover"
                                />
                                {{ row.label }}
                            </label>
                        </div>

                        <div v-else-if="field.type === 'select' && isRadioLike(field)" class="flex flex-col gap-2">
                            <label
                                v-for="row in getOptionRows(field)"
                                :key="row.label"
                                class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-[var(--brutal-ink)]/10 px-3 py-2 text-sm hover:bg-muted/30"
                            >
                                <input
                                    type="radio"
                                    :name="field.name"
                                    :value="row.label"
                                    :checked="(answerForm[field.name] as string) === row.label"
                                    class="accent-primary"
                                    @change="() => (answerForm[field.name] = row.label)"
                                />
                                <img
                                    v-if="row.imageSrc"
                                    :src="row.imageSrc"
                                    alt=""
                                    class="size-8 shrink-0 rounded-full border border-[var(--brutal-ink)]/10 object-cover"
                                />
                                {{ row.label }}
                            </label>
                        </div>

                        <Select
                            v-else-if="field.type === 'select'"
                            :model-value="answerForm[field.name] as string"
                            @update:model-value="(value) => (answerForm[field.name] = String(value ?? ''))"
                        >
                            <SelectTrigger class="text-sm">
                                <SelectValue placeholder="Select an option" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="row in getOptionRows(field)" :key="row.label" :value="row.label">
                                    <span class="flex items-center gap-2">
                                        <img
                                            v-if="row.imageSrc"
                                            :src="row.imageSrc"
                                            alt=""
                                            class="size-7 rounded-md border border-[var(--brutal-ink)]/10 object-cover"
                                        />
                                        {{ row.label }}
                                    </span>
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <div v-else-if="field.type === 'radio'" class="flex flex-col gap-2">
                            <label
                                v-for="row in getOptionRows(field)"
                                :key="row.label"
                                class="flex cursor-pointer items-center gap-2.5 text-sm"
                            >
                                <input
                                    type="radio"
                                    :name="field.name"
                                    :value="row.label"
                                    :checked="(answerForm[field.name] as string) === row.label"
                                    class="accent-primary"
                                    @change="() => (answerForm[field.name] = row.label)"
                                />
                                <img
                                    v-if="row.imageSrc"
                                    :src="row.imageSrc"
                                    alt=""
                                    class="size-8 shrink-0 rounded-full border border-[var(--brutal-ink)]/10 object-cover"
                                />
                                {{ row.label }}
                            </label>
                        </div>

                        <div v-else-if="field.type === 'checkbox'" class="flex flex-col gap-2">
                            <label
                                v-for="row in getOptionRows(field)"
                                :key="row.label"
                                class="flex cursor-pointer items-center gap-2.5 text-sm"
                            >
                                <Checkbox
                                    :id="`${field.name}-${row.label}`"
                                    :checked="((answerForm[field.name] as string[]) ?? []).includes(row.label)"
                                    @update:checked="(value) => onCheckboxToggle(field.name, row.label, Boolean(value))"
                                />
                                <img
                                    v-if="row.imageSrc"
                                    :src="row.imageSrc"
                                    alt=""
                                    class="size-8 shrink-0 rounded-md border border-[var(--brutal-ink)]/10 object-cover"
                                />
                                {{ row.label }}
                            </label>
                        </div>

                        <div v-else-if="field.type === 'fileUpload'" class="rounded-xl border border-dashed border-[var(--brutal-ink)]/25 bg-muted/20 p-3">
                            <Input :id="field.name" type="file" :accept="acceptValue(field)" class="cursor-pointer text-sm" @change="onFileChange(field.name, $event)" />
                            <p v-if="fileHint(field)" class="mt-2 text-xs text-muted-foreground">{{ fileHint(field) }}</p>
                            <p v-if="answerForm.progress" class="mt-2 text-xs font-semibold text-primary">Uploading {{ answerForm.progress.percentage }}%</p>
                        </div>

                        <p v-if="fieldError(field.name)" class="mt-2 text-xs font-semibold text-destructive">{{ fieldError(field.name) }}</p>
                    </CardContent>
                </Card>
            </template>

            <div
                v-if="bodyFields.length === 0 && !formBannerField"
                class="neo-muted-panel py-10 text-center text-sm text-muted-foreground"
            >
                This form has no fields configured.
            </div>

            <div class="flex items-center justify-end gap-3 pt-2">
                <Button variant="outline" as-child>
                    <Link :href="`/dashboard/events/${event.id}`">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="answerForm.processing">
                    <Send class="mr-1.5 size-4" />
                    Submit registration
                </Button>
            </div>
        </form>
    </div>
</template>
