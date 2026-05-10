<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import FormFillLayout from '@/layouts/FormFillLayout.vue'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { DatePicker } from '@/components/ui/date-picker'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { readFieldMetadata, readFieldRules } from '@/lib/formFieldMetadata'
import { getFormFieldOptionRows, formFieldBuilderType } from '@/lib/formFieldOptions'
import { normalizeBannerSrc } from '@/components/modules/builder/formBanner'
import FormFieldAnswerDisplay from '@/components/modules/dashboard/FormFieldAnswerDisplay.vue'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { CheckCircle2, Send, Users } from 'lucide-vue-next'

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
    return formFieldBuilderType(field)
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

const declineDialogOpen = ref(false)
const acceptConfirmOpen = ref(false)

function onCheckboxToggle(fieldName: string, option: string, checked: boolean) {
    const current = Array.isArray(confirmForm[fieldName]) ? (confirmForm[fieldName] as string[]) : []
    confirmForm[fieldName] = checked ? [...current, option] : current.filter((x) => x !== option)
}

function submitConfirm() {
    confirmForm
        .transform((data) => ({ ...data, invitation_decision: 'accept' as const }))
        .post(props.confirmUrl, { forceFormData: true })
}

const declineForm = useForm({
    invitation_decision: 'reject' as const,
    decline_reason: '',
})

function openDeclineDialog() {
    declineForm.clearErrors()
    declineForm.reset()
    declineForm.invitation_decision = 'reject'
    declineDialogOpen.value = true
}

function submitDeclineFromDialog() {
    declineForm.post(props.confirmUrl, {
        preserveScroll: true,
        onSuccess: () => {
            declineDialogOpen.value = false
        },
    })
}
</script>

<template>
    <Head title="Registration invitation" />

    <div class="mx-auto max-w-2xl space-y-6 px-2 pb-16">
        <!-- Summary: same surface as other dashboard cards (white / card) -->
        <section
            class="overflow-hidden rounded-2xl border border-border bg-card p-5 shadow-xs sm:p-6"
            aria-labelledby="invitation-heading"
        >
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between sm:gap-8">
                <div class="min-w-0 flex-1 space-y-1">
                    <p class="text-[0.6875rem] font-semibold uppercase tracking-[0.12em] text-muted-foreground">
                        Registration invitation
                    </p>
                    <h1 id="invitation-heading" class="font-display text-xl font-bold leading-tight tracking-tight text-foreground sm:text-2xl">
                        {{ form.title }}
                    </h1>
                    <p class="text-sm text-muted-foreground">{{ event.title }}</p>
                </div>
                <div
                    class="flex w-full shrink-0 flex-col gap-2 rounded-xl border border-border/80 bg-muted/30 p-3 sm:w-auto sm:min-w-[14rem]"
                >
                    <p class="text-[0.6875rem] font-semibold uppercase tracking-wide text-muted-foreground">Invited by</p>
                    <div class="flex items-start gap-3">
                        <div
                            class="grid size-10 shrink-0 place-items-center rounded-full border border-border bg-card text-muted-foreground shadow-xs"
                            aria-hidden="true"
                        >
                            <Users class="size-[1.125rem]" />
                        </div>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-foreground">{{ leader.name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ leader.email }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <Card v-if="alreadyConfirmed" class="border-success/30 bg-success/5">
            <CardContent class="flex flex-col items-center gap-3 py-10 text-center">
                <CheckCircle2 class="size-10 text-success" />
                <p class="font-medium text-foreground">You have already confirmed this team registration.</p>
                <Button variant="outline" as-child class="mt-2">
                    <a :href="`/user/dashboard/events/${event.slug}`">Back to event</a>
                </Button>
            </CardContent>
        </Card>

        <template v-else>
            <div class="flex flex-col gap-6">
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

                    <div
                        v-else-if="builderType(field) === 'banner'"
                        class="overflow-hidden rounded-2xl border border-border bg-card shadow-xs"
                    >
                        <img
                            v-if="typeof metadata(field).bannerUrl === 'string' && metadata(field).bannerUrl.trim()"
                            :src="normalizeBannerSrc(metadata(field).bannerUrl as string)"
                            alt=""
                            class="max-h-72 w-full object-cover"
                            loading="lazy"
                        />
                        <p
                            v-if="typeof metadata(field).content === 'string' && metadata(field).content.trim()"
                            class="border-t border-border px-4 py-3 text-sm text-muted-foreground"
                        >
                            {{ metadata(field).content }}
                        </p>
                    </div>

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
                            <DatePicker
                                v-else-if="builderType(field) === 'date' || field.type === 'datePicker'"
                                v-model="confirmForm[field.name] as string"
                            />
                            <div v-else-if="field.type === 'checkbox' || (field.type === 'select' && readFieldMetadata(field).is_multiple)" class="flex flex-col gap-2">
                                <label
                                    v-for="row in getFormFieldOptionRows(field)"
                                    :key="row.label"
                                    class="flex cursor-pointer items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm"
                                >
                                    <Checkbox
                                        :checked="((confirmForm[field.name] as string[]) ?? []).includes(row.label)"
                                        @update:checked="(v: boolean | 'indeterminate') => onCheckboxToggle(field.name, row.label, v === true)"
                                    />
                                    <img
                                        v-if="row.imageSrc"
                                        :src="row.imageSrc"
                                        alt=""
                                        class="size-7 rounded border border-border object-cover"
                                        loading="lazy"
                                    />
                                    {{ row.label }}
                                </label>
                            </div>
                            <div v-else-if="field.type === 'radio' || builderType(field) === 'radio' || builderType(field) === 'yes_no'" class="flex flex-col gap-2">
                                <label v-for="row in getFormFieldOptionRows(field)" :key="row.label" class="flex items-center gap-2 text-sm">
                                    <input
                                        type="radio"
                                        :name="field.name"
                                        :value="row.label"
                                        :checked="(confirmForm[field.name] as string) === row.label"
                                        class="size-4 accent-primary"
                                        @change="confirmForm[field.name] = row.label"
                                    />
                                    <img
                                        v-if="row.imageSrc"
                                        :src="row.imageSrc"
                                        alt=""
                                        class="size-7 rounded border border-border object-cover"
                                        loading="lazy"
                                    />
                                    {{ row.label }}
                                </label>
                            </div>
                            <Select v-else-if="field.type === 'select'" v-model="confirmForm[field.name] as string">
                                <SelectTrigger><SelectValue :placeholder="'Select'" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="row in getFormFieldOptionRows(field)" :key="row.label" :value="row.label">
                                        <span class="flex items-center gap-2">
                                            <img
                                                v-if="row.imageSrc"
                                                :src="row.imageSrc"
                                                alt=""
                                                class="size-7 rounded border border-border object-cover"
                                                loading="lazy"
                                            />
                                            {{ row.label }}
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-else-if="field.type === 'fileUpload'" class="space-y-3 rounded-xl border border-dashed border-border p-4">
                                <p v-if="typeof answers[field.name] === 'string' && answers[field.name]" class="text-xs font-medium text-muted-foreground">
                                    Berkas saat ini (dari pendaftar utama)
                                </p>
                                <FormFieldAnswerDisplay v-if="typeof answers[field.name] === 'string' && answers[field.name]" :field="field" :value="answers[field.name]" />
                                <div>
                                    <p class="mb-1.5 text-xs text-muted-foreground">Unggah berkas baru (opsional mengganti)</p>
                                    <input type="file" class="w-full text-xs" @change="(e) => (confirmForm[field.name] = (e.target as HTMLInputElement).files?.[0] ?? null)" />
                                </div>
                            </div>
                            <p v-if="confirmForm.errors[field.name]" class="text-xs font-medium text-destructive">{{ confirmForm.errors[field.name] }}</p>
                        </CardContent>
                    </Card>

                    <!-- Read-only snapshot -->
                    <Card v-else class="overflow-hidden bg-muted/20">
                        <CardHeader class="pb-2 pt-4">
                            <CardTitle class="text-sm font-semibold text-muted-foreground">{{ field.label }}</CardTitle>
                        </CardHeader>
                        <CardContent class="pb-4">
                            <FormFieldAnswerDisplay :field="field" :value="answers[field.name]" />
                        </CardContent>
                    </Card>
                </template>

            <div
                class="flex flex-wrap justify-end gap-3 "
            >
                <Button type="button" variant="outline" size="lg" :disabled="confirmForm.processing" @click="openDeclineDialog">
                    Decline
                </Button>
                <Button type="button" size="lg" class="gap-2" :disabled="confirmForm.processing" @click="acceptConfirmOpen = true">
                    
                    Accept invitation
                </Button>
            </div>
            </div>
        </template>
        <ConfirmationModal
            v-model:open="acceptConfirmOpen"
            title="Accept this invitation?"
            description="Your answers will be submitted to the team leader and you will join this registration. Continue?"
            confirm-text="Accept and submit"
            :loading="confirmForm.processing"
            @confirm="submitConfirm"
        />

        <Dialog v-model:open="declineDialogOpen">
            <DialogContent class="max-w-lg gap-4 sm:max-w-lg" @pointer-down-outside="(e) => declineForm.processing && e.preventDefault()">
                <DialogHeader>
                    <DialogTitle>Decline this invitation?</DialogTitle>
                    <DialogDescription class="text-left">
                        This cannot be undone. Your team leader will be notified. Please share a brief reason below — it is sent to the
                        leader by email only and is not stored in the application.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-2">
                    <label for="decline_reason" class="text-sm font-medium text-foreground">
                        Reason <span class="text-destructive">*</span>
                    </label>
                    <Textarea
                        id="decline_reason"
                        v-model="declineForm.decline_reason"
                        rows="4"
                        :disabled="declineForm.processing"
                        placeholder="e.g. schedule conflict, no longer participating…"
                        class="min-h-24 resize-y"
                    />
                    <p v-if="declineForm.errors.decline_reason" class="text-xs font-medium text-destructive">
                        {{ declineForm.errors.decline_reason }}
                    </p>
                </div>
                <DialogFooter class="gap-2 sm:gap-0">
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="declineForm.processing"
                        @click="declineDialogOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button type="button" variant="destructive" :disabled="declineForm.processing" @click="submitDeclineFromDialog">
                        Decline invitation
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
