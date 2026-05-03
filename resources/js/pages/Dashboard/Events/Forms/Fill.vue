<script setup lang="ts">
import { computed } from 'vue'
import { Head, useForm, Link } from '@inertiajs/vue3'
import FormFillLayout from '@/layouts/FormFillLayout.vue'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { AlertCircle, CheckCircle2, Send, ArrowLeft, Star, ImagePlus, Upload, X } from 'lucide-vue-next'
import { normalizeBannerSrc, pickFormBannerField } from '@/components/modules/builder/formBanner'

defineOptions({ layout: FormFillLayout })

type FormAccessStatus = 'allowed' | 'not_visible' | 'form_closed' | 'registration_not_open' | 'quota_full' | 'already_submitted'
type AnswerValue = string | string[] | File | null
type AnswerMap = Record<string, AnswerValue>

const props = defineProps<{
    event: { id: string; title: string }
    form: { id: string; title: string; description: string | null; closed_at: string | null; banner_url: string | null; banner_caption: string | null }
    fields: IFormField[]
    submitUrl: string
    accessStatus: FormAccessStatus
    accessMessage: string
}>()

function metadata(field: IFormField): Record<string, any> {
    return field.metadata && typeof field.metadata === 'object' ? field.metadata : {}
}

function rules(field: IFormField): Record<string, any> {
    const value = metadata(field).rules
    return value && typeof value === 'object' ? value : {}
}

function builderType(field: IFormField): string {
    const value = metadata(field).builderType
    if (typeof value === 'string') return value
    if (field.name === 'form_banner' || field.type === 'banner') return 'banner'
    return field.type
}

function isDisplayOnly(field: IFormField): boolean {
    const bt = builderType(field)
    return ['heading', 'paragraph', 'divider', 'banner'].includes(bt)
}

const formBannerField = computed(() => pickFormBannerField(props.fields))

const formBannerImageSrc = computed(() => {
    const fb = formBannerField.value
    const meta = fb ? metadata(fb) : {}
    const url = (meta.bannerUrl as string) || props.form.banner_url
    if (!url) return ''
    return normalizeBannerSrc(url)
})

const formBannerCaption = computed(() => {
    const fb = formBannerField.value
    if (fb) {
        const raw = metadata(fb).content
        if (typeof raw === 'string' && raw.trim()) return raw
    }
    return props.form.banner_caption ?? ''
})

const formHasDescription = computed(() => Boolean(props.form.description?.trim()))
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
for (const field of props.fields) {
    if (isDisplayOnly(field)) continue
    if (field.type === 'checkbox' || (field.type === 'select' && metadata(field).is_multiple)) {
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
    const ruleOptions = (rules(field).in as string | undefined)
    return listFromCsv(ruleOptions)
}

type OptionRow = { type: 'text' | 'image'; label: string; imageSrc?: string }

function getOptionRows(field: IFormField): OptionRow[] {
    const oc = metadata(field).optionChoices
    if (Array.isArray(oc)) {
        const rows: OptionRow[] = []
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
    const fallbackOptions = getOptions(field)
    return fallbackOptions.map((label) => ({ type: 'text', label }))
}

function getSelectedOptionRow(field: IFormField) {
    const val = answerForm[field.name] as string
    return getOptionRows(field).find(r => r.label === val)
}

function getInputSubtype(field: IFormField): string {
    const type = metadata(field).type
    if (typeof type === 'string') {
        if (type === 'short_text') return 'text'
        if (type === 'phone') return 'tel'
        return type
    }
    return 'text'
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
    const bt = builderType(field)
    return field.type === 'radio' || bt === 'radio'
}

function isCheckboxLike(field: IFormField): boolean {
    const bt = builderType(field)
    return field.type === 'checkbox' || bt === 'checkbox' || isMultipleSelect(field)
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
    answerForm.post(props.submitUrl, {
        forceFormData: true,
    })
}

function fieldError(name: string): string | undefined {
    return (answerForm.errors as Record<string, string>)[name]
}
</script>

<template>
    <Head :title="`Register: ${props.form.title}`" />

    <div class="mx-auto max-w-2xl px-2">
        <!-- Static Banner Section -->
        <div
            v-if="formBannerImageSrc || formBannerCaption"
            class="overflow-hidden rounded-2xl border-[2px] border-foreground bg-white shadow-[6px_6px_0_var(--brutal-ink)] mb-10"
        >
            <img
                v-if="formBannerImageSrc"
                :src="formBannerImageSrc"
                :alt="props.form.title"
                class="aspect-[3/1] w-full object-cover"
            />
            <p
                v-if="formBannerCaption"
                class="border-t-2 border-foreground px-5 py-4 text-sm font-bold leading-relaxed text-muted-foreground"
            >
                {{ formBannerCaption }}
            </p>
        </div>

        <div class="mb-10 text-center">
            <h1 class="font-display text-4xl font-black tracking-tight text-foreground">{{ props.form.title }}</h1>
            <p v-if="props.form.description" class="mt-2 text-sm font-bold text-muted-foreground">{{ props.form.description }}</p>
        </div>

        <Card v-if="isBlocked" class="mt-6 rounded-2xl border-[2px] border-foreground shadow-[6px_6px_0_var(--brutal-ink)]">
            <CardContent class="flex flex-col items-center gap-3 py-12 text-center">
                <CheckCircle2 v-if="blockCopy.success" class="size-10 text-success" />
                <AlertCircle v-else class="size-10 text-warning" />
                <p class="text-xl font-black">{{ blockCopy.title }}</p>
                <p class="max-w-md text-sm font-bold text-muted-foreground">{{ blockCopy.body }}</p>
                <Button variant="outline" size="sm" class="mt-6 border-2 rounded-xl h-11 px-8 font-black" as-child>
                    <Link :href="`/dashboard/events/${event.id}`">View event</Link>
                </Button>
            </CardContent>
        </Card>

        <form
            v-else
            class="flex flex-col gap-6"
            @submit.prevent="submit"
        >
            <template v-for="field in fields" :key="field.id">
                <!-- Heading -->
                <div v-if="builderType(field) === 'heading'" class="rounded-2xl border-[2px] border-foreground bg-(--brutal-yellow) px-5 py-4 shadow-[4px_4px_0_var(--brutal-ink)]">
                    <h2 class="font-display text-2xl font-black tracking-tight text-foreground">
                        {{ metadata(field).content || field.label }}
                    </h2>
                    <p v-if="field.description" class="mt-1 text-sm font-bold text-foreground/70">{{ field.description }}</p>
                </div>

                <!-- Paragraph -->
                <div v-else-if="builderType(field) === 'paragraph'" class="rounded-2xl border-[2px] border-foreground bg-white px-5 py-4 text-sm font-bold leading-relaxed text-muted-foreground shadow-[4px_4px_0_var(--brutal-ink)]">
                    {{ metadata(field).content || field.description || field.label }}
                </div>

                <!-- Divider -->
                <hr v-else-if="builderType(field) === 'divider'" class="brutal-divider my-2" />

                <!-- Banner -->
                <div v-else-if="builderType(field) === 'banner'" class="hidden" />

                <!-- Input Fields -->
                <Card v-else class="rounded-2xl border-[2px] border-foreground shadow-[6px_6px_0_var(--brutal-ink)] overflow-hidden">
                    <CardHeader class="pb-2 pt-4">
                        <CardTitle class="flex items-start gap-1 text-sm font-black text-foreground">
                            {{ field.label }}
                            <span v-if="isRequired(field)" class="text-destructive">*</span>
                        </CardTitle>
                        <p v-if="field.description" class="text-xs font-bold leading-relaxed text-muted-foreground">{{ field.description }}</p>
                    </CardHeader>
                    <CardContent class="pb-4 pt-0">
                        <Input
                            v-if="['short_text', 'email', 'phone', 'number', 'time', 'input'].includes(builderType(field)) && !isMultipleSelect(field)"
                            :id="field.name"
                            :type="getInputSubtype(field)"
                            :placeholder="getPlaceholder(field)"
                            v-model="answerForm[field.name]"
                            class="text-sm border-2 border-foreground"
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
                            <p class="text-xs font-bold text-muted-foreground">Choose one rating.</p>
                        </div>

                        <Textarea
                            v-else-if="builderType(field) === 'long_text' || field.type === 'textarea'"
                            :id="field.name"
                            :placeholder="getPlaceholder(field)"
                            v-model="answerForm[field.name]"
                            rows="4"
                            class="text-sm border-2 border-foreground"
                        />

                        <Input
                            v-else-if="builderType(field) === 'date' || field.type === 'datePicker'"
                            :id="field.name"
                            type="date"
                            v-model="answerForm[field.name]"
                            class="text-sm border-2 border-foreground"
                        />

                        <div v-else-if="isCheckboxLike(field)" class="flex flex-col gap-2">
                            <label
                                v-for="row in getOptionRows(field)"
                                :key="row.label"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-foreground px-3 py-2 text-sm font-bold hover:bg-(--brutal-mint)/20"
                            >
                                <Checkbox
                                    :id="`${field.name}-${row.label}`"
                                    :checked="((answerForm[field.name] as string[]) ?? []).includes(row.label)"
                                    @update:checked="(value) => onCheckboxToggle(field.name, row.label, Boolean(value))"
                                    class="border-2"
                                />
                                <div v-if="row.type === 'image' && row.imageSrc" class="size-16 shrink-0 overflow-hidden rounded-md border-2 border-foreground">
                                    <img :src="row.imageSrc" alt="" class="size-full object-cover" />
                                </div>
                                <span v-else>{{ row.label }}</span>
                            </label>
                        </div>

                        <div v-else-if="isRadioLike(field)" class="flex flex-col gap-2">
                            <label
                                v-for="row in getOptionRows(field)"
                                :key="row.label"
                                class="flex cursor-pointer items-center gap-3 rounded-xl border-2 border-foreground px-3 py-2 text-sm font-bold hover:bg-(--brutal-mint)/20"
                            >
                                <input
                                    type="radio"
                                    :name="field.name"
                                    :value="row.label"
                                    :checked="(answerForm[field.name] as string) === row.label"
                                    class="accent-primary size-5"
                                    @change="() => (answerForm[field.name] = row.label)"
                                />
                                <div v-if="row.type === 'image' && row.imageSrc" class="size-16 shrink-0 overflow-hidden rounded-md border-2 border-foreground">
                                    <img :src="row.imageSrc" alt="" class="size-full object-cover" />
                                </div>
                                <span v-else>{{ row.label }}</span>
                            </label>
                        </div>

                        <Select
                            v-else-if="builderType(field) === 'dropdown' || field.type === 'select'"
                            v-model="answerForm[field.name]"
                        >
                            <SelectTrigger class="text-sm border-2 border-foreground h-12">
                                <SelectValue placeholder="Select an option">
                                    <template v-if="answerForm[field.name]">
                                        <span class="flex items-center gap-2">
                                            <img
                                                v-if="getSelectedOptionRow(field)?.imageSrc"
                                                :src="getSelectedOptionRow(field)?.imageSrc"
                                                alt=""
                                                class="size-6 rounded border border-foreground object-cover"
                                            />
                                            {{ answerForm[field.name] }}
                                        </span>
                                    </template>
                                </SelectValue>
                            </SelectTrigger>
                            <SelectContent class="border-2 border-foreground shadow-[4px_4px_0_var(--brutal-ink)]">
                                <SelectItem v-for="row in getOptionRows(field)" :key="row.label" :value="row.label" class="font-bold">
                                    <span class="flex items-center gap-2">
                                        <img
                                            v-if="row.imageSrc"
                                            :src="row.imageSrc"
                                            alt=""
                                            class="size-7 rounded border border-foreground object-cover"
                                        />
                                        {{ row.label }}
                                    </span>
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <div v-else-if="['file_upload', 'image_upload', 'fileUpload'].includes(builderType(field))" class="rounded-xl border-2 border-dashed border-foreground bg-muted/20 p-6 text-center relative hover:bg-muted/30 transition-colors">
                            <div class="flex flex-col items-center gap-2">
                                <div class="size-12 rounded-full bg-white flex items-center justify-center border-2 border-foreground shadow-[2px_2px_0_var(--brutal-ink)]">
                                    <component :is="builderType(field) === 'image_upload' ? ImagePlus : Upload" class="size-6 text-foreground" />
                                </div>
                                <div>
                                    <p class="text-sm font-black">Click to upload</p>
                                    <p class="text-[10px] font-bold text-muted-foreground">{{ fileHint(field) }}</p>
                                </div>
                                <input
                                    type="file"
                                    :accept="acceptValue(field)"
                                    class="absolute inset-0 cursor-pointer opacity-0"
                                    @change="onFileChange(field.name, $event)"
                                />
                            </div>
                            <div v-if="answerForm[field.name]" class="mt-4 flex items-center justify-center gap-2 rounded-xl bg-white p-3 border-2 border-foreground shadow-[2px_2px_0_var(--brutal-ink)] relative z-10">
                                <span class="text-xs font-bold truncate max-w-[200px]">{{ (answerForm[field.name] as File).name }}</span>
                                <button type="button" @click="answerForm[field.name] = null" class="text-destructive hover:scale-110 transition-transform">
                                    <X class="size-4" />
                                </button>
                            </div>
                        </div>

                        <p v-if="fieldError(field.name)" class="mt-2 text-xs font-black text-destructive">{{ fieldError(field.name) }}</p>
                    </CardContent>
                </Card>
            </template>

            <div class="flex items-center justify-end gap-3 pt-6 border-t-2 border-foreground mb-20">
                <Button variant="ghost" class="rounded-xl px-8 h-12 shadow-none border-transparent hover:bg-muted/50 font-black" as-child>
                    <Link :href="`/dashboard/events/${event.id}`">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="answerForm.processing" class="rounded-xl px-10 h-12 gap-2 text-base font-black">
                    <Send class="size-5" />
                    Submit registration
                </Button>
            </div>
        </form>
    </div>
</template>

<style scoped>
.brutal-divider {
    border: none;
    border-top: 2px solid var(--brutal-ink);
    opacity: 0.15;
}
</style>
