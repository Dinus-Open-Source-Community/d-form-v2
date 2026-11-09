<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import FormFillLayout from '@/layouts/FormFillLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { DatePicker } from '@/components/ui/date-picker';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select';
import { readFieldMetadata, readFieldRules } from '@/lib/formFieldMetadata';
import FormParagraphContent from '@/components/modules/dashboard/FormParagraphContent.vue';
import { isCheckboxOptionSelected, toggleCheckboxSelection } from '@/lib/formCheckboxAnswers';
import { getFormFieldOptionRows, formFieldBuilderType } from '@/lib/formFieldOptions';
import { validateUploadFile } from '@/lib/uploadValidation';
import { normalizePublicStorageUrl } from '@/lib/publicStorageUrl';
import FormFieldAnswerDisplay from '@/components/modules/dashboard/FormFieldAnswerDisplay.vue';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { CheckCircle2, Users } from 'lucide-vue-next';
import { routes } from '@/lib/routes';
import {
    buildFieldLabelMap,
    getFieldError,
    handleInertiaFormErrors,
    type TErrorMessageContext,
    type TValidationErrors,
} from '@/lib/error-message';

defineOptions({ layout: FormFillLayout });

const props = defineProps<{
    event: { id: string; slug: string; title: string };
    form: { id: string; title: string };
    fields: IFormField[];
    answers: Record<string, unknown>;
    leader: { name: string; email: string };
    alreadyConfirmed: boolean;
    confirmUrl: string;
}>();

function metadata(field: IFormField): Record<string, unknown> {
    return field.metadata && typeof field.metadata === 'object' ? field.metadata : {};
}

function builderType(field: IFormField): string {
    return formFieldBuilderType(field);
}

function dropdownOptions(field: IFormField): SimpleSelectOption[] {
    return getFormFieldOptionRows(field).map((row) => ({ value: row.label, label: row.label }));
}

const appendableFields = computed(() => props.fields.filter((f) => f.is_append));

const errorContext = computed<TErrorMessageContext>(() => ({
    fields: appendableFields.value,
    fieldLabels: buildFieldLabelMap(appendableFields.value),
}));

/** Teks terpangkas dari nilai metadata mentah; string kosong bila bukan string. */
function trimText(value: unknown): string {
    return typeof value === 'string' ? value.trim() : '';
}

function invitationFieldError(errors: TValidationErrors, key: string): string | undefined {
    return getFieldError(errors, key, errorContext.value);
}

async function onInvitationFileChange(fieldName: string, event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;
    if (file) {
        const message = await validateUploadFile(file);
        if (message) {
            input.value = '';
            confirmForm.setError(fieldName, message);
            return;
        }
    }
    confirmForm[fieldName] = file;
}

/** Nilai yang bisa ditampung form undangan: teks, pilihan ganda, atau berkas. */
type TInvitationAnswer = string | string[] | File | null;

function initialFormState(): Record<string, TInvitationAnswer> {
    const o: Record<string, TInvitationAnswer> = {};
    for (const f of appendableFields.value) {
        const raw = props.answers[f.name];
        const meta = readFieldMetadata(f);
        if (f.type === 'checkbox' || (f.type === 'select' && meta.is_multiple)) {
            o[f.name] = Array.isArray(raw) ? raw.filter((item): item is string => typeof item === 'string') : [];
        } else if (f.type === 'fileUpload') {
            o[f.name] = null;
        } else {
            o[f.name] = raw != null && raw !== '' ? String(raw) : '';
        }
    }
    return o;
}

const confirmForm = useForm(initialFormState());

const declineDialogOpen = ref(false);
const acceptConfirmOpen = ref(false);

function onCheckboxToggle(fieldName: string, option: string, checked: boolean) {
    confirmForm[fieldName] = toggleCheckboxSelection(confirmForm[fieldName], option, checked);
}

function submitConfirm() {
    confirmForm
        .transform((data) => ({ ...data, invitation_decision: 'accept' as const }))
        .post(props.confirmUrl, {
            forceFormData: true,
            onError: (errors) => {
                handleInertiaFormErrors(errors, {
                    ...errorContext.value,
                    title: 'Gagal mengonfirmasi undangan',
                });
            },
        });
}

const declineForm = useForm({
    invitation_decision: 'reject' as const,
    decline_reason: '',
});

function openDeclineDialog() {
    declineForm.clearErrors();
    declineForm.reset();
    declineForm.invitation_decision = 'reject';
    declineDialogOpen.value = true;
}

function submitDeclineFromDialog() {
    declineForm.post(props.confirmUrl, {
        preserveScroll: true,
        onSuccess: () => {
            declineDialogOpen.value = false;
        },
        onError: (errors) => {
            handleInertiaFormErrors(errors, {
                title: 'Gagal menolak undangan',
            });
        },
    });
}
</script>

<template>
    <Head title="Registration invitation" />

    <div class="mx-auto max-w-2xl space-y-6 px-2 pb-16">
        <!-- Summary: same surface as other dashboard cards (white / card) -->
        <section
            class="border-border bg-card overflow-hidden rounded-2xl border p-5 shadow-xs sm:p-6"
            aria-labelledby="invitation-heading"
        >
            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between sm:gap-8">
                <div class="min-w-0 flex-1 space-y-1">
                    <p class="text-muted-foreground text-[0.6875rem] font-semibold tracking-[0.12em] uppercase">
                        Registration invitation
                    </p>
                    <h1
                        id="invitation-heading"
                        class="font-display text-foreground text-xl leading-tight font-bold tracking-tight sm:text-2xl"
                    >
                        {{ form.title }}
                    </h1>
                    <p class="text-muted-foreground text-sm">{{ event.title }}</p>
                </div>
                <div
                    class="border-border/80 bg-muted/30 flex w-full shrink-0 flex-col gap-2 rounded-xl border p-3 sm:w-auto sm:min-w-[14rem]"
                >
                    <p class="text-muted-foreground text-[0.6875rem] font-semibold tracking-wide uppercase">
                        Invited by
                    </p>
                    <div class="flex items-start gap-3">
                        <div
                            class="border-border bg-card text-muted-foreground grid size-10 shrink-0 place-items-center rounded-full border shadow-xs"
                            aria-hidden="true"
                        >
                            <Users class="size-[1.125rem]" />
                        </div>
                        <div class="min-w-0">
                            <p class="text-foreground truncate text-sm font-semibold">{{ leader.name }}</p>
                            <p class="text-muted-foreground truncate text-xs">{{ leader.email }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <Card v-if="alreadyConfirmed" class="border-success/30 bg-success/5">
            <CardContent class="flex flex-col items-center gap-3 py-10 text-center">
                <CheckCircle2 class="text-success size-10" />
                <p class="text-foreground font-medium">You have already confirmed this team registration.</p>
                <Button variant="outline" as-child class="mt-2">
                    <a :href="routes.member.event.show(event.slug)">Back to event</a>
                </Button>
            </CardContent>
        </Card>

        <template v-else>
            <div class="flex flex-col gap-6">
                <template v-for="field in fields" :key="field.id">
                    <div
                        v-if="builderType(field) === 'heading'"
                        class="border-border bg-primary/8 rounded-2xl border px-5 py-4 shadow-xs"
                    >
                        <h2 class="font-display text-foreground text-xl font-bold">
                            {{ (metadata(field).content as string) || field.label }}
                        </h2>
                    </div>

                    <div
                        v-else-if="builderType(field) === 'paragraph'"
                        class="border-border bg-card rounded-2xl border px-5 py-4 shadow-xs"
                    >
                        <FormParagraphContent
                            :content="String(metadata(field).content ?? '')"
                            :fallback="field.description || field.label"
                        />
                    </div>

                    <hr v-else-if="builderType(field) === 'divider'" class="app-divider my-1" />

                    <div
                        v-else-if="builderType(field) === 'banner'"
                        class="border-border bg-card overflow-hidden rounded-2xl border shadow-xs"
                    >
                        <img
                            v-if="trimText(metadata(field).bannerUrl)"
                            :src="normalizePublicStorageUrl(trimText(metadata(field).bannerUrl))"
                            alt=""
                            class="aspect-video w-full object-cover sm:aspect-[3/1]"
                            loading="lazy"
                        />
                        <p
                            v-if="trimText(metadata(field).content)"
                            class="border-border text-muted-foreground border-t px-4 py-3 text-sm"
                        >
                            {{ trimText(metadata(field).content) }}
                        </p>
                    </div>

                    <!-- Appendable: editable -->
                    <Card v-else-if="field.is_append" class="overflow-hidden">
                        <div
                            v-if="invitationFieldError(confirmForm.errors, field.name)"
                            class="border-destructive/20 bg-destructive/5 border-b px-5 py-3 sm:px-6"
                        >
                            <p class="text-destructive text-xs font-medium">
                                {{ invitationFieldError(confirmForm.errors, field.name) }}
                            </p>
                        </div>
                        <CardHeader class="pt-4 pb-2">
                            <CardTitle class="text-sm font-semibold">
                                {{ field.label }}
                                <span v-if="readFieldRules(field).required" class="text-destructive">*</span>
                            </CardTitle>
                            <p v-if="field.description" class="text-muted-foreground text-xs">
                                {{ field.description }}
                            </p>
                        </CardHeader>
                        <CardContent class="space-y-2 pb-4">
                            <Input
                                v-if="
                                    ['short_text', 'email', 'phone', 'number', 'time', 'input'].includes(
                                        builderType(field)
                                    ) && !readFieldMetadata(field).is_multiple
                                "
                                v-model="confirmForm[field.name] as string"
                                class="min-h-11"
                            />
                            <Textarea
                                v-else-if="
                                    builderType(field) === 'long_text' ||
                                    builderType(field) === 'address' ||
                                    field.type === 'textarea'
                                "
                                v-model="confirmForm[field.name] as string"
                                rows="4"
                            />
                            <DatePicker
                                v-else-if="builderType(field) === 'date' || field.type === 'datePicker'"
                                v-model="confirmForm[field.name] as string"
                            />
                            <div
                                v-else-if="
                                    field.type === 'checkbox' ||
                                    (field.type === 'select' && readFieldMetadata(field).is_multiple)
                                "
                                class="flex flex-col gap-2"
                            >
                                <label
                                    v-for="row in getFormFieldOptionRows(field)"
                                    :key="row.label"
                                    class="border-border flex cursor-pointer items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                                >
                                    <Checkbox
                                        :model-value="isCheckboxOptionSelected(confirmForm[field.name], row.label)"
                                        @update:model-value="
                                            (v: boolean | 'indeterminate') =>
                                                onCheckboxToggle(field.name, row.label, v === true)
                                        "
                                    />
                                    <img
                                        v-if="row.imageSrc"
                                        :src="row.imageSrc"
                                        alt=""
                                        class="border-border size-7 rounded border object-cover"
                                        loading="lazy"
                                    />
                                    {{ row.label }}
                                </label>
                            </div>
                            <div
                                v-else-if="
                                    field.type === 'radio' ||
                                    builderType(field) === 'radio' ||
                                    builderType(field) === 'yes_no'
                                "
                                class="flex flex-col gap-2"
                            >
                                <label
                                    v-for="row in getFormFieldOptionRows(field)"
                                    :key="row.label"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input
                                        type="radio"
                                        :name="field.name"
                                        :value="row.label"
                                        :checked="(confirmForm[field.name] as string) === row.label"
                                        class="accent-primary size-4"
                                        @change="confirmForm[field.name] = row.label"
                                    />
                                    <img
                                        v-if="row.imageSrc"
                                        :src="row.imageSrc"
                                        alt=""
                                        class="border-border size-7 rounded border object-cover"
                                        loading="lazy"
                                    />
                                    {{ row.label }}
                                </label>
                            </div>
                            <SimpleSelect
                                v-else-if="field.type === 'select'"
                                v-model="confirmForm[field.name] as string"
                                :options="dropdownOptions(field)"
                                :id="`invite-select-${field.name}`"
                                placeholder="Select"
                                class="border-border/80 bg-background/80 h-10 w-full text-xs sm:text-sm"
                                aria-label="Select"
                            />
                            <div
                                v-else-if="field.type === 'fileUpload'"
                                class="border-border space-y-3 rounded-xl border border-dashed p-4"
                            >
                                <p
                                    v-if="typeof answers[field.name] === 'string' && answers[field.name]"
                                    class="text-muted-foreground text-xs font-medium"
                                >
                                    Berkas saat ini (dari pendaftar utama)
                                </p>
                                <FormFieldAnswerDisplay
                                    v-if="typeof answers[field.name] === 'string' && answers[field.name]"
                                    :field="field"
                                    :value="answers[field.name]"
                                />
                                <div>
                                    <p class="text-muted-foreground mb-1.5 text-xs">
                                        Unggah berkas baru (opsional mengganti)
                                    </p>
                                    <input
                                        type="file"
                                        class="w-full text-xs"
                                        @change="(e) => onInvitationFileChange(field.name, e)"
                                    />
                                </div>
                            </div>
                            <p
                                v-if="invitationFieldError(confirmForm.errors, field.name)"
                                class="text-destructive text-xs font-medium"
                            >
                                {{ invitationFieldError(confirmForm.errors, field.name) }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Read-only snapshot -->
                    <Card v-else class="bg-muted/20 overflow-hidden">
                        <CardHeader class="pt-4 pb-2">
                            <CardTitle class="text-muted-foreground text-sm font-semibold">{{ field.label }}</CardTitle>
                        </CardHeader>
                        <CardContent class="pb-4">
                            <FormFieldAnswerDisplay :field="field" :value="answers[field.name]" />
                        </CardContent>
                    </Card>
                </template>

                <div class="flex flex-wrap justify-end gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        size="lg"
                        :disabled="confirmForm.processing"
                        @click="openDeclineDialog"
                    >
                        Decline
                    </Button>
                    <Button
                        type="button"
                        size="lg"
                        class="gap-2"
                        :disabled="confirmForm.processing"
                        @click="acceptConfirmOpen = true"
                    >
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
            <DialogContent
                class="max-w-lg gap-4 sm:max-w-lg"
                @pointer-down-outside="(e) => declineForm.processing && e.preventDefault()"
            >
                <DialogHeader>
                    <DialogTitle>Decline this invitation?</DialogTitle>
                    <DialogDescription class="text-left">
                        This cannot be undone. Your team leader will be notified. Please share a brief reason below — it
                        is sent to the leader by email only and is not stored in the application.
                    </DialogDescription>
                </DialogHeader>
                <div class="space-y-2">
                    <label for="decline_reason" class="text-foreground text-sm font-medium">
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
                    <p
                        v-if="invitationFieldError(declineForm.errors, 'decline_reason')"
                        class="text-destructive text-xs font-medium"
                    >
                        {{ invitationFieldError(declineForm.errors, 'decline_reason') }}
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
                    <Button
                        type="button"
                        variant="destructive"
                        :disabled="declineForm.processing"
                        @click="submitDeclineFromDialog"
                    >
                        Decline invitation
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
