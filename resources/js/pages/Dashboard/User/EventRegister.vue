<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import FormFillLayout from '@/layouts/FormFillLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import { Upload, Send, X, Star, ImagePlus } from 'lucide-vue-next';
import { normalizeBannerSrc, pickFormBannerField } from '@/components/modules/builder/formBanner';

defineOptions({ layout: FormFillLayout });

const props = defineProps<{
    event: IEvent;
    form: IForm | null;
    fields: IFormField[];
    submitUrl: string;
}>();

const event = props.event;
const formDef = computed(() => props.form);

// ─── Logic Detection (Same as Fill.vue / Preview) ────────────────
function metadata(field: IFormField): Record<string, unknown> {
    return field.metadata && typeof field.metadata === 'object' ? field.metadata : {};
}

function rules(field: IFormField): Record<string, unknown> {
    const value = metadata(field).rules;
    return value && typeof value === 'object' ? (value as Record<string, unknown>) : {};
}

function builderType(field: IFormField): string {
    const value = metadata(field).builderType;
    if (typeof value === 'string') return value;
    if (field.name === 'form_banner' || field.type === 'banner') return 'banner';
    return field.type;
}

function isDisplayOnly(field: IFormField): boolean {
    const bt = builderType(field);
    return ['heading', 'paragraph', 'divider', 'banner'].includes(bt);
}

const formBannerField = computed(() => pickFormBannerField(props.fields));
const formBannerImageSrc = computed(() => {
    const fb = formBannerField.value;
    const meta = fb ? metadata(fb) : {};
    const url = (meta.bannerUrl as string) || formDef.value?.banner_url;
    if (!url) return '';
    return normalizeBannerSrc(url);
});

const formBannerCaption = computed(() => {
    const fb = formBannerField.value;
    if (fb) {
        const raw = metadata(fb).content;
        if (typeof raw === 'string' && raw.trim()) return raw;
    }
    return formDef.value?.banner_caption ?? '';
});

// ─── Form State ──────────────────────────────────────────────────
type AnswerValue = string | string[] | File | null;
const initialValues: Record<string, AnswerValue> = {};
for (const field of props.fields) {
    if (isDisplayOnly(field)) continue;
    if (field.type === 'checkbox' || (field.type === 'select' && metadata(field).is_multiple)) {
        initialValues[field.name] = [];
    } else if (field.type === 'fileUpload') {
        initialValues[field.name] = null;
    } else {
        initialValues[field.name] = '';
    }
}

const answerForm = useForm<Record<string, AnswerValue>>(initialValues);
const showConfirmModal = ref(false);

// ─── Pagination Logic ──────────────────────────────────────────
const currentPage = ref(1);
const itemsPerPage = 5;

const totalPages = computed(() => Math.ceil(props.fields.length / itemsPerPage));

const pagedFields = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    const end = start + itemsPerPage;
    return props.fields.slice(start, end);
});

function validateCurrentPage(): boolean {
    for (const field of pagedFields.value) {
        if (isDisplayOnly(field)) continue;
        if (isRequired(field)) {
            const val = answerForm[field.name];
            const isEmpty = Array.isArray(val) ? val.length === 0 : !String(val ?? '').trim();
            if (isEmpty) {
                toast.error(`"${field.label}" is required.`);
                return false;
            }
        }
    }
    return true;
}

function nextPage() {
    if (validateCurrentPage()) {
        if (currentPage.value < totalPages.value) {
            currentPage.value++;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        } else {
            handleSubmit();
        }
    }
}

function prevPage() {
    if (currentPage.value > 1) {
        currentPage.value--;
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
}

// ─── Helpers ─────────────────────────────────────────────────────
function isRequired(field: IFormField): boolean {
    return Boolean(rules(field).required);
}

function isMultipleSelect(field: IFormField): boolean {
    return Boolean(metadata(field).is_multiple);
}

function getPlaceholder(field: IFormField): string {
    return String(metadata(field).placeholder ?? '');
}

function listFromCsv(value: unknown): string[] {
    return typeof value === 'string'
        ? value
              .split(',')
              .map((item) => item.trim())
              .filter(Boolean)
        : [];
}

function getOptions(field: IFormField): string[] {
    const direct = metadata(field).options;
    if (typeof direct === 'string') return listFromCsv(direct);
    const ruleOptions = rules(field).in as string | undefined;
    return listFromCsv(ruleOptions);
}

type OptionRow = { type: 'text' | 'image'; label: string; imageSrc?: string };

function getOptionRows(field: IFormField): OptionRow[] {
    const oc = metadata(field).optionChoices;
    if (Array.isArray(oc)) {
        const rows: OptionRow[] = [];
        for (const item of oc) {
            if (item && typeof item === 'object' && item !== null) {
                const typedItem = item as { type?: string; label?: string; imageUrl?: string };
                const type = (typedItem.type === 'image' ? 'image' : 'text') as 'text' | 'image';
                const label = String(typedItem.label ?? '').trim();
                const rawUrl = typeof typedItem.imageUrl === 'string' ? String(typedItem.imageUrl).trim() : '';

                rows.push({
                    type,
                    label: type === 'text' ? label : label || 'Image Choice',
                    imageSrc: rawUrl ? normalizeBannerSrc(rawUrl) : undefined,
                });
            }
        }
        if (rows.length > 0) return rows;
    }
    const fallbackOptions = getOptions(field);
    return fallbackOptions.map((label) => ({ type: 'text', label }));
}

function getSelectedOptionRow(field: IFormField) {
    const val = answerForm[field.name] as string;
    return getOptionRows(field).find((r) => r.label === val);
}

function getInputSubtype(field: IFormField): string {
    const type = metadata(field).type;
    if (typeof type === 'string') {
        if (type === 'short_text') return 'text';
        if (type === 'phone') return 'tel';
        return type;
    }
    return 'text';
}

function getFieldControlValue(fieldName: string): string | number | undefined {
    const value = answerForm[fieldName];
    if (typeof value === 'string' || typeof value === 'number') return value;
    return undefined;
}

function setFieldControlValue(fieldName: string, value: string | number | undefined) {
    answerForm[fieldName] = typeof value === 'number' ? String(value) : (value ?? '');
}

function fileHint(field: IFormField): string {
    const parts: string[] = [];
    const fieldRules = rules(field);
    if (fieldRules.mimes) parts.push(`Allowed: ${String(fieldRules.mimes)}`);
    if (fieldRules.max_size) parts.push(`Max size: ${String(fieldRules.max_size)} KB`);
    return parts.join(' · ');
}

function acceptValue(field: IFormField): string | undefined {
    const mimes = rules(field).mimes;
    if (typeof mimes !== 'string') return undefined;
    return mimes
        .split(',')
        .map((ext) => `.${ext.trim().replace(/^\./, '')}`)
        .join(',');
}

function onCheckboxToggle(fieldName: string, option: string, checked: boolean) {
    const current = Array.isArray(answerForm[fieldName]) ? (answerForm[fieldName] as string[]) : [];
    answerForm[fieldName] = checked ? [...current, option] : current.filter((value) => value !== option);
}

function onFileChange(fieldName: string, event: Event) {
    const input = event.target as HTMLInputElement;
    answerForm[fieldName] = input.files?.[0] ?? null;
}

function handleSubmit() {
    for (const field of props.fields) {
        if (isDisplayOnly(field)) continue;
        if (isRequired(field)) {
            const val = answerForm[field.name];
            const isEmpty = Array.isArray(val) ? val.length === 0 : !String(val ?? '').trim();
            if (isEmpty) {
                toast.error(`"${field.label}" is required.`);
                return;
            }
        }
    }
    showConfirmModal.value = true;
}

function confirmSubmit() {
    if (!formDef.value) return;
    showConfirmModal.value = false;

    answerForm.post(props.submitUrl, {
        forceFormData: true,
        onSuccess: () => toast.success('Registration submitted successfully!'),
        onError: () => toast.error('Failed to submit registration. Please check your input.'),
    });
}
</script>

<template>
    <Head :title="`Register — ${event.title}`" />

    <div class="mx-auto max-w-7xl px-4 py-8 lg:px-8 lg:py-16">
        <div v-if="formDef" class="grid grid-cols-1 items-start gap-12 lg:grid-cols-12 lg:gap-20">
            <!-- ─── Left Column (Sticky Sidebar) ────────────────────────── -->
            <div class="lg:sticky lg:top-24 lg:col-span-5">
                <div class="flex flex-col gap-8">
                    <!-- Banner -->
                    <div
                        v-if="formBannerImageSrc"
                        class="border-foreground group relative aspect-2/1 overflow-hidden rounded-3xl border-4 shadow-[8px_8px_0_var(--brutal-ink)] transition-transform hover:scale-[1.01] lg:aspect-3/2"
                    >
                        <img :src="formBannerImageSrc" :alt="event.title" class="h-full w-full object-cover" />
                        <div class="from-foreground/20 absolute inset-0 bg-gradient-to-t to-transparent" />
                    </div>

                    <!-- Title & Description -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <span
                                class="bg-foreground text-background inline-flex rounded-full px-3 py-1 text-[10px] font-black uppercase tracking-widest"
                            >
                                Registration Form
                            </span>
                            <div class="bg-foreground/10 h-px flex-1" />
                        </div>
                        <h1 class="font-display text-foreground text-4xl font-black leading-none tracking-tighter lg:text-6xl">
                            {{ formDef.title || 'Register' }}
                        </h1>
                        <p class="text-muted-foreground max-w-md text-base leading-relaxed font-bold">
                            {{ formDef.description }}
                        </p>
                    </div>

                    <!-- Progress Indicator -->
                    <div v-if="totalPages > 1" class="space-y-3 pt-4">
                        <div class="flex items-center justify-between text-xs font-black uppercase tracking-widest">
                            <span class="text-foreground">Step {{ currentPage }} of {{ totalPages }}</span>
                            <span class="text-muted-foreground">{{ Math.round((currentPage / totalPages) * 100) }}% Complete</span>
                        </div>
                        <div class="border-foreground h-4 overflow-hidden rounded-full border-2 bg-white p-0.5">
                            <div
                                class="bg-(--brutal-yellow) h-full rounded-full transition-all duration-500 ease-out"
                                :style="{ width: `${(currentPage / totalPages) * 100}%` }"
                            />
                        </div>
                    </div>

                    <!-- Banner Caption -->
                    <div
                        v-if="formBannerCaption"
                        class="border-foreground rounded-2xl border-2 bg-(--brutal-mint)/10 p-5 shadow-[4px_4px_0_var(--brutal-ink)]"
                    >
                        <p class="text-foreground text-sm leading-relaxed font-bold">
                            <span class="text-foreground/40 mr-2 font-black">“</span>
                            {{ formBannerCaption }}
                            <span class="text-foreground/40 ml-2 font-black">”</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- ─── Right Column (Form Content) ─────────────────────────── -->
            <div class="lg:col-span-7">
                <form class="flex flex-col gap-10" @submit.prevent="nextPage">
                    <div class="flex flex-col gap-10">
                        <template v-for="field in pagedFields" :key="field.id">
                            <!-- Heading (Static) -->
                            <div v-if="builderType(field) === 'heading'" class="pt-4 pb-2">
                                <h2 class="font-display text-foreground text-3xl font-black tracking-tight underline decoration-(--brutal-yellow) decoration-6 underline-offset-4">
                                    {{ metadata(field).content || field.label }}
                                </h2>
                                <p v-if="field.description" class="text-muted-foreground mt-3 text-sm font-bold">
                                    {{ field.description }}
                                </p>
                            </div>

                            <!-- Paragraph (Static) -->
                            <div
                                v-else-if="builderType(field) === 'paragraph'"
                                class="border-foreground rounded-2xl border-2 bg-white px-6 py-5 text-sm leading-relaxed font-bold shadow-[4px_4px_0_var(--brutal-ink)]"
                            >
                                {{ metadata(field).content || field.description || field.label }}
                            </div>

                            <!-- Divider (Static) -->
                            <div v-else-if="builderType(field) === 'divider'" class="py-2">
                                <div class="border-foreground w-full border-t-4 border-dashed opacity-20" />
                            </div>

                            <!-- Banner (Static) -->
                            <div v-else-if="builderType(field) === 'banner'" class="hidden" />

                            <!-- Actual Input Fields -->
                            <div v-else class="group space-y-3">
                                <div class="flex items-center justify-between">
                                    <label
                                        :for="field.name"
                                        class="text-foreground flex items-center gap-2 text-sm font-black uppercase tracking-wider"
                                    >
                                        {{ field.label }}
                                        <span v-if="isRequired(field)" class="text-destructive font-black">*</span>
                                    </label>
                                    <span
                                        v-if="answerForm[field.name]"
                                        class="text-(--brutal-mint) flex items-center gap-1 text-[10px] font-black uppercase"
                                    >
                                        Completed
                                    </span>
                                </div>
                                <p v-if="field.description" class="text-muted-foreground text-xs leading-relaxed font-bold">
                                    {{ field.description }}
                                </p>

                                <div class="relative">
                                    <!-- Short text, email, number, phone, time -->
                                    <Input
                                        v-if="
                                            ['short_text', 'email', 'phone', 'number', 'time', 'input'].includes(
                                                builderType(field)
                                            ) && !isMultipleSelect(field)
                                        "
                                        :id="field.name"
                                        :type="getInputSubtype(field)"
                                        :placeholder="getPlaceholder(field)"
                                        :model-value="getFieldControlValue(field.name)"
                                        @update:model-value="
                                            (value: string | number | undefined) => setFieldControlValue(field.name, value)
                                        "
                                        class="border-foreground h-14 rounded-xl border-4 bg-white text-base font-bold transition-all focus:bg-(--brutal-yellow)/5 focus:ring-0 focus:shadow-[6px_6px_0_var(--brutal-ink)] focus:-translate-y-1"
                                    />

                                    <div v-else-if="builderType(field) === 'rating'" class="flex flex-col gap-3">
                                        <div class="flex gap-2">
                                            <button
                                                v-for="rating in Number(metadata(field).maxStars ?? 5)"
                                                :key="rating"
                                                type="button"
                                                class="hover:scale-110 active:scale-95 transition-transform"
                                                @click="answerForm[field.name] = String(rating)"
                                            >
                                                <Star
                                                    class="size-10"
                                                    :class="
                                                        Number(answerForm[field.name] || 0) >= rating
                                                            ? 'fill-amber-400 text-amber-400 drop-shadow-[2px_2px_0_rgba(0,0,0,0.1)]'
                                                            : 'text-muted-foreground/30'
                                                    "
                                                />
                                            </button>
                                        </div>
                                    </div>

                                    <Textarea
                                        v-else-if="builderType(field) === 'long_text' || field.type === 'textarea'"
                                        :id="field.name"
                                        :placeholder="getPlaceholder(field)"
                                        :model-value="getFieldControlValue(field.name)"
                                        @update:model-value="
                                            (value: string | number | undefined) => setFieldControlValue(field.name, value)
                                        "
                                        rows="5"
                                        class="border-foreground rounded-xl border-4 bg-white text-base font-bold transition-all focus:bg-(--brutal-yellow)/5 focus:ring-0 focus:shadow-[6px_6px_0_var(--brutal-ink)] focus:-translate-y-1"
                                    />

                                    <Input
                                        v-else-if="builderType(field) === 'date' || field.type === 'datePicker'"
                                        :id="field.name"
                                        type="date"
                                        :model-value="getFieldControlValue(field.name)"
                                        @update:model-value="
                                            (value: string | number | undefined) => setFieldControlValue(field.name, value)
                                        "
                                        class="border-foreground h-14 rounded-xl border-4 bg-white text-base font-bold transition-all focus:bg-(--brutal-yellow)/5 focus:ring-0 focus:shadow-[6px_6px_0_var(--brutal-ink)] focus:-translate-y-1"
                                    />

                                    <!-- Radio / Checkbox logic -->
                                    <div
                                        v-else-if="
                                            field.type === 'checkbox' || (field.type === 'select' && isMultipleSelect(field))
                                        "
                                        class="grid grid-cols-1 gap-3 md:grid-cols-2"
                                    >
                                        <label
                                            v-for="row in getOptionRows(field)"
                                            :key="row.label"
                                            class="border-foreground flex cursor-pointer items-center gap-4 rounded-2xl border-4 bg-white px-5 py-4 transition-all hover:bg-(--brutal-mint)/10 active:scale-[0.98]"
                                            :class="{
                                                'bg-(--brutal-mint)/10 shadow-[4px_4px_0_var(--brutal-ink)] -translate-y-1':
                                                    ((answerForm[field.name] as string[]) ?? []).includes(row.label),
                                            }"
                                        >
                                            <Checkbox
                                                :id="`${field.name}-${row.label}`"
                                                :checked="((answerForm[field.name] as string[]) ?? []).includes(row.label)"
                                                @update:checked="
                                                    (value: boolean | 'indeterminate') =>
                                                        onCheckboxToggle(field.name, row.label, value === true)
                                                "
                                                class="size-5 border-2"
                                            />
                                            <div
                                                v-if="row.type === 'image' && row.imageSrc"
                                                class="border-foreground size-12 shrink-0 overflow-hidden rounded-lg border-2"
                                            >
                                                <img :src="row.imageSrc" alt="" class="size-full object-cover" />
                                            </div>
                                            <span class="text-sm font-black">{{ row.label }}</span>
                                        </label>
                                    </div>

                                    <div
                                        v-else-if="
                                            field.type === 'radio' ||
                                            (field.type === 'select' && !isMultipleSelect(field) && metadata(field).optionChoices)
                                        "
                                        class="grid grid-cols-1 gap-3 md:grid-cols-2"
                                    >
                                        <label
                                            v-for="row in getOptionRows(field)"
                                            :key="row.label"
                                            class="border-foreground flex cursor-pointer items-center gap-4 rounded-2xl border-4 bg-white px-5 py-4 transition-all hover:bg-(--brutal-mint)/10 active:scale-[0.98]"
                                            :class="{
                                                'bg-(--brutal-mint)/10 shadow-[4px_4px_0_var(--brutal-ink)] -translate-y-1':
                                                    (answerForm[field.name] as string) === row.label,
                                            }"
                                        >
                                            <input
                                                type="radio"
                                                :name="field.name"
                                                :value="row.label"
                                                :checked="(answerForm[field.name] as string) === row.label"
                                                class="accent-foreground size-5"
                                                @change="() => (answerForm[field.name] = row.label)"
                                            />
                                            <div
                                                v-if="row.type === 'image' && row.imageSrc"
                                                class="border-foreground size-12 shrink-0 overflow-hidden rounded-lg border-2"
                                            >
                                                <img :src="row.imageSrc" alt="" class="size-full object-cover" />
                                            </div>
                                            <span class="text-sm font-black">{{ row.label }}</span>
                                        </label>
                                    </div>

                                    <Select
                                        v-else-if="field.type === 'select' || builderType(field) === 'dropdown'"
                                        :model-value="getFieldControlValue(field.name)"
                                        @update:model-value="
                                            (value: string | number | undefined) => setFieldControlValue(field.name, value)
                                        "
                                    >
                                        <SelectTrigger
                                            class="border-foreground h-14 rounded-xl border-4 bg-white text-base font-bold transition-all focus:ring-0 focus:shadow-[6px_6px_0_var(--brutal-ink)] focus:-translate-y-1"
                                        >
                                            <SelectValue placeholder="Select an option">
                                                <template v-if="answerForm[field.name]">
                                                    <span class="flex items-center gap-3">
                                                        <img
                                                            v-if="getSelectedOptionRow(field)?.imageSrc"
                                                            :src="getSelectedOptionRow(field)?.imageSrc"
                                                            alt=""
                                                            class="border-foreground size-7 rounded-md border-2 object-cover"
                                                        />
                                                        {{ answerForm[field.name] }}
                                                    </span>
                                                </template>
                                            </SelectValue>
                                        </SelectTrigger>
                                        <SelectContent
                                            class="border-foreground border-4 bg-white p-2 shadow-[8px_8px_0_var(--brutal-ink)]"
                                        >
                                            <SelectItem
                                                v-for="row in getOptionRows(field)"
                                                :key="row.label"
                                                :value="row.label"
                                                class="rounded-lg font-black transition-colors focus:bg-(--brutal-yellow)/20"
                                            >
                                                <span class="flex items-center gap-3 py-1">
                                                    <img
                                                        v-if="row.imageSrc"
                                                        :src="row.imageSrc"
                                                        alt=""
                                                        class="border-foreground size-8 rounded border-2 object-cover"
                                                    />
                                                    {{ row.label }}
                                                </span>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>

                                    <div
                                        v-else-if="['file_upload', 'image_upload', 'fileUpload'].includes(builderType(field))"
                                        class="group/upload border-foreground bg-foreground/5 hover:bg-(--brutal-mint)/5 relative flex min-h-[160px] flex-col items-center justify-center rounded-2xl border-4 border-dashed transition-all"
                                        :class="{ 'bg-(--brutal-mint)/10 border-solid': answerForm[field.name] }"
                                    >
                                        <div class="flex flex-col items-center gap-3 p-8 text-center">
                                            <div
                                                class="border-foreground flex size-14 items-center justify-center rounded-2xl border-4 bg-white shadow-[4px_4px_0_var(--brutal-ink)] transition-transform group-hover/upload:-translate-y-1"
                                            >
                                                <component
                                                    :is="builderType(field) === 'image_upload' ? ImagePlus : Upload"
                                                    class="text-foreground size-7"
                                                />
                                            </div>
                                            <div class="space-y-1">
                                                <p class="text-base font-black uppercase tracking-tight">
                                                    {{ answerForm[field.name] ? 'File Attached' : 'Drop file here' }}
                                                </p>
                                                <p class="text-muted-foreground text-[10px] font-bold uppercase tracking-widest">
                                                    {{ fileHint(field) }}
                                                </p>
                                            </div>
                                            <input
                                                type="file"
                                                :accept="acceptValue(field)"
                                                class="absolute inset-0 cursor-pointer opacity-0"
                                                @change="onFileChange(field.name, $event)"
                                            />
                                        </div>

                                        <div
                                            v-if="answerForm[field.name]"
                                            class="border-foreground bg-background relative z-10 mb-6 flex items-center gap-3 rounded-xl border-4 px-4 py-2 shadow-[4px_4px_0_var(--brutal-ink)]"
                                        >
                                            <span class="max-w-[180px] truncate text-xs font-black">{{
                                                (answerForm[field.name] as File).name
                                            }}</span>
                                            <button
                                                type="button"
                                                @click="answerForm[field.name] = null"
                                                class="text-destructive hover:scale-125 transition-transform"
                                            >
                                                <X class="size-4" />
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <p v-if="answerForm.errors[field.name]" class="text-destructive text-[10px] font-black uppercase">
                                    {{ answerForm.errors[field.name] }}
                                </p>
                            </div>
                        </template>
                    </div>

                    <!-- Form Navigation -->
                    <div class="border-foreground flex items-center justify-between border-t-4 pt-10 pb-20">
                        <Button
                            type="button"
                            variant="ghost"
                            class="h-14 rounded-2xl px-8 text-base font-black uppercase tracking-widest transition-all hover:bg-black/5"
                            :class="{ invisible: currentPage === 1 }"
                            @click="prevPage"
                        >
                            Back
                        </Button>

                        <div class="flex items-center gap-4">
                            <Button
                                v-if="currentPage === 1"
                                variant="ghost"
                                class="h-14 rounded-2xl px-8 text-base font-black uppercase tracking-widest transition-all hover:bg-black/5"
                                as-child
                            >
                                <Link :href="`/dashboard/user/events/${event.id}`">Cancel</Link>
                            </Button>

                            <Button
                                type="submit"
                                :disabled="answerForm.processing"
                                class="border-foreground h-16 min-w-[200px] gap-3 rounded-2xl border-4 bg-black px-10 text-lg font-black uppercase tracking-widest text-white shadow-[6px_6px_0_var(--brutal-ink)] transition-all hover:-translate-y-1 hover:shadow-[10px_10px_0_var(--brutal-ink)] active:translate-y-0 active:shadow-none"
                            >
                                <template v-if="currentPage < totalPages">
                                    Next Step
                                </template>
                                <template v-else>
                                    <Send class="size-6" />
                                    Submit
                                </template>
                            </Button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- No Form State -->
        <div v-else class="border-foreground flex flex-col items-center justify-center rounded-3xl border-4 border-dashed py-32 text-center">
            <div class="bg-foreground/5 mb-6 flex size-20 items-center justify-center rounded-full">
                <X class="text-foreground/20 size-10" />
            </div>
            <p class="text-muted-foreground text-xl font-black uppercase tracking-widest">No form available for this event.</p>
            <Button variant="link" as-child class="mt-4 font-bold">
                <Link :href="`/dashboard/user/events/${event.id}`">Return to Event Details</Link>
            </Button>
        </div>
    </div>

    <ConfirmationModal
        :open="showConfirmModal"
        title="Confirm Submission"
        description="Ready to lock in your registration? Please double-check your information before submitting."
        confirm-text="Submit Registration"
        @confirm="confirmSubmit"
        @cancel="showConfirmModal = false"
        @update:open="showConfirmModal = $event"
    />
</template>

<style scoped>
.brutal-divider {
    border: none;
    border-top: 2px solid var(--brutal-ink);
    opacity: 0.15;
}
</style>
