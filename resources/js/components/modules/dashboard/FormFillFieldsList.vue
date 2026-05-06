<script setup lang="ts">
/* eslint-disable vue/no-mutating-props -- ctx.answerForm is Inertia useForm state; assignments are intended field updates */
import { Link } from '@inertiajs/vue3'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { UnwrapNestedRefs } from 'vue'
import { Send, Star, ImagePlus, Upload, X } from 'lucide-vue-next'
import type { FormFillPageContext } from '@/utils/composables/useFormFillPage'

defineProps<{
    fields: IFormField[]
    eventId: string
    ctx: UnwrapNestedRefs<FormFillPageContext>
}>()

defineEmits<{
    submit: []
}>()

function textAnswer(ctx: UnwrapNestedRefs<FormFillPageContext>, name: string): string {
    const v = ctx.answerForm[name]
    return typeof v === 'string' ? v : ''
}

function setTextAnswer(ctx: UnwrapNestedRefs<FormFillPageContext>, name: string, value: string | number): void {
    ctx.answerForm[name] = String(value)
}
</script>

<template>
    <form class="flex flex-col gap-6" @submit.prevent="$emit('submit')">
        <template v-for="field in fields" :key="field.id">
            <div v-if="ctx.builderType(field) === 'heading'" class="rounded-2xl border border-border bg-primary/8 px-5 py-4 shadow-xs">
                <h2 class="font-display text-2xl font-bold tracking-[-0.025em] text-foreground">
                    {{ ctx.metadata(field).content || field.label }}
                </h2>
                <p v-if="field.description" class="mt-1.5 text-sm leading-relaxed text-muted-foreground">{{ field.description }}</p>
            </div>

            <div
                v-else-if="ctx.builderType(field) === 'paragraph'"
                class="rounded-2xl border border-border bg-card px-5 py-4 text-sm leading-relaxed text-muted-foreground shadow-xs"
            >
                {{ ctx.metadata(field).content || field.description || field.label }}
            </div>

            <hr v-else-if="ctx.builderType(field) === 'divider'" class="app-divider my-2" />

            <div v-else-if="ctx.builderType(field) === 'banner'" class="hidden" />

            <Card v-else class="overflow-hidden">
                <CardHeader class="pb-2 pt-4">
                    <CardTitle class="flex items-start gap-1 text-sm font-semibold tracking-[-0.005em] text-foreground">
                        {{ field.label }}
                        <span v-if="ctx.isRequired(field)" class="text-destructive">*</span>
                    </CardTitle>
                    <p v-if="field.description" class="text-xs leading-relaxed text-muted-foreground">{{ field.description }}</p>
                </CardHeader>
                <CardContent class="pb-4 pt-0">
                    <Input
                        v-if="['short_text', 'email', 'phone', 'number', 'time', 'input'].includes(ctx.builderType(field)) && !ctx.isMultipleSelect(field)"
                        :id="field.name"
                        :type="ctx.getInputSubtype(field)"
                        :placeholder="ctx.getPlaceholder(field)"
                        :model-value="textAnswer(ctx, field.name)"
                        @update:model-value="setTextAnswer(ctx, field.name, $event)"
                    />

                    <div v-else-if="ctx.builderType(field) === 'rating'" class="flex flex-col gap-3">
                        <div class="flex gap-1.5">
                            <button
                                v-for="rating in Number(ctx.metadata(field).maxStars ?? 5)"
                                :key="rating"
                                type="button"
                                class="rounded-lg p-1 transition-transform duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] active:scale-90"
                                @click="ctx.answerForm[field.name] = String(rating)"
                            >
                                <Star
                                    class="size-7 transition-colors"
                                    :class="Number(ctx.answerForm[field.name] || 0) >= rating ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/50'"
                                />
                            </button>
                        </div>
                        <p class="text-xs text-muted-foreground">Choose one rating.</p>
                    </div>

                    <Textarea
                        v-else-if="ctx.builderType(field) === 'long_text' || field.type === 'textarea'"
                        :id="field.name"
                        :placeholder="ctx.getPlaceholder(field)"
                        :model-value="textAnswer(ctx, field.name)"
                        @update:model-value="setTextAnswer(ctx, field.name, $event)"
                        rows="4"
                    />

                    <Input
                        v-else-if="ctx.builderType(field) === 'date' || field.type === 'datePicker'"
                        :id="field.name"
                        type="date"
                        :model-value="textAnswer(ctx, field.name)"
                        @update:model-value="setTextAnswer(ctx, field.name, $event)"
                    />

                    <div v-else-if="ctx.isCheckboxLike(field)" class="flex flex-col gap-2">
                        <label
                            v-for="row in ctx.getOptionRows(field)"
                            :key="row.label"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border border-border bg-card px-3 py-2 text-sm font-medium text-foreground shadow-xs transition-[border-color,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:border-primary/30 hover:bg-muted/40"
                        >
                            <Checkbox
                                :id="`${field.name}-${row.label}`"
                                :checked="((ctx.answerForm[field.name] as string[]) ?? []).includes(row.label)"
                                @update:checked="(value: boolean | 'indeterminate') => ctx.onCheckboxToggle(field.name, row.label, value === true)"
                            />
                            <div v-if="row.type === 'image' && row.imageSrc" class="size-16 shrink-0 overflow-hidden rounded-md border border-border">
                                <img :src="row.imageSrc" alt="" class="size-full object-cover" />
                            </div>
                            <span v-else>{{ row.label }}</span>
                        </label>
                    </div>

                    <div v-else-if="ctx.isRadioLike(field)" class="flex flex-col gap-2">
                        <label
                            v-for="row in ctx.getOptionRows(field)"
                            :key="row.label"
                            class="flex cursor-pointer items-center gap-3 rounded-xl border border-border bg-card px-3 py-2 text-sm font-medium text-foreground shadow-xs transition-[border-color,background-color] duration-200 ease-[cubic-bezier(0.22,1,0.36,1)] hover:border-primary/30 hover:bg-muted/40"
                        >
                            <input
                                type="radio"
                                :name="field.name"
                                :value="row.label"
                                :checked="(ctx.answerForm[field.name] as string) === row.label"
                                class="size-4 accent-primary"
                                @change="() => (ctx.answerForm[field.name] = row.label)"
                            />
                            <div v-if="row.type === 'image' && row.imageSrc" class="size-16 shrink-0 overflow-hidden rounded-md border border-border">
                                <img :src="row.imageSrc" alt="" class="size-full object-cover" />
                            </div>
                            <span v-else>{{ row.label }}</span>
                        </label>
                    </div>

                    <Select
                        v-else-if="ctx.builderType(field) === 'dropdown' || field.type === 'select'"
                        v-model="ctx.answerForm[field.name]"
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Select an option">
                                <template v-if="ctx.answerForm[field.name]">
                                    <span class="flex items-center gap-2">
                                        <img
                                            v-if="ctx.getSelectedOptionRow(field)?.imageSrc"
                                            :src="ctx.getSelectedOptionRow(field)?.imageSrc"
                                            alt=""
                                            class="size-6 rounded border border-border object-cover"
                                        />
                                        {{ ctx.answerForm[field.name] }}
                                    </span>
                                </template>
                            </SelectValue>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="row in ctx.getOptionRows(field)" :key="row.label" :value="row.label">
                                <span class="flex items-center gap-2">
                                    <img
                                        v-if="row.imageSrc"
                                        :src="row.imageSrc"
                                        alt=""
                                        class="size-7 rounded border border-border object-cover"
                                    />
                                    {{ row.label }}
                                </span>
                            </SelectItem>
                        </SelectContent>
                    </Select>

                    <div
                        v-else-if="['file_upload', 'image_upload', 'fileUpload'].includes(ctx.builderType(field))"
                        class="relative rounded-xl border border-dashed border-border bg-muted/20 p-6 text-center transition-colors hover:border-primary/30 hover:bg-muted/30"
                    >
                        <div class="flex flex-col items-center gap-2">
                            <div class="grid size-12 place-items-center rounded-full border border-border bg-card shadow-xs">
                                <component :is="ctx.builderType(field) === 'image_upload' ? ImagePlus : Upload" class="size-5 text-muted-foreground" />
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-foreground">Click to upload</p>
                                <p class="mt-0.5 text-[10px] text-muted-foreground">{{ ctx.fileHint(field) }}</p>
                            </div>
                            <input
                                type="file"
                                :accept="ctx.acceptValue(field)"
                                class="absolute inset-0 cursor-pointer opacity-0"
                                @change="ctx.onFileChange(field.name, $event)"
                            />
                        </div>
                        <div v-if="ctx.answerForm[field.name]" class="relative z-10 mt-4 flex items-center justify-center gap-2 rounded-xl border border-border bg-card p-3 shadow-xs">
                            <span class="max-w-[200px] truncate text-xs font-medium text-foreground">{{ (ctx.answerForm[field.name] as File).name }}</span>
                            <button type="button" class="text-destructive transition-transform hover:scale-110" @click="ctx.answerForm[field.name] = null">
                                <X class="size-4" />
                            </button>
                        </div>
                    </div>

                    <p v-if="ctx.fieldError(field.name)" class="mt-2 text-xs font-medium text-destructive">{{ ctx.fieldError(field.name) }}</p>
                </CardContent>
            </Card>
        </template>

        <div class="mb-20 flex items-center justify-end gap-3 border-t border-border pt-6">
            <Button variant="ghost" size="lg" as-child>
                <Link :href="`/dashboard/events/${eventId}`">Cancel</Link>
            </Button>
            <Button type="submit" size="lg" :disabled="ctx.answerForm.processing" class="gap-2">
                <Send class="size-4" />
                Submit registration
            </Button>
        </div>
    </form>
</template>
