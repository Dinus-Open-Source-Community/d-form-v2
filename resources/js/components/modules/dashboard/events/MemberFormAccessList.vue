<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Check, ChevronRight, ClipboardList, Lock, MessageSquareText } from 'lucide-vue-next';
import EmptyState from '@/components/core/EmptyState.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader } from '@/components/ui/card';
import { FORM_ACCESS_STATUS_LABEL, formPurposeLabel } from '@/lib/eventDetailSummary';
import type { IEventPortalFormSummary } from '@/types/form';

const props = defineProps<{
    forms: IEventPortalFormSummary[];
    /** Hanya terisi saat peserta sudah terdaftar. */
    registrationDetailUrl?: string | null;
    registeredFormTitle?: string | null;
}>();

/** Baris formulir yang mewakili pendaftaran aktif; tombol detail menempel di baris itu. */
const registrationActionFormId = computed<string | null>(() => {
    if (!props.registrationDetailUrl) return null;

    const registrationRows = props.forms.filter((form) => form.purpose === 'registration');
    if (registrationRows.length === 0) return null;

    const matched =
        props.registeredFormTitle === null || props.registeredFormTitle === undefined
            ? undefined
            : registrationRows.find((form) => form.title === props.registeredFormTitle);

    return (matched ?? registrationRows[0]).id;
});

/** Ikon mengikuti tujuan form, bukan satu ikon generik untuk semua baris. */
function formIcon(form: IEventPortalFormSummary): typeof ClipboardList {
    return form.purpose === 'other' ? MessageSquareText : ClipboardList;
}
</script>

<template>
    <Card class="border-border/70 gap-0 rounded-2xl py-0">
        <CardHeader class="border-border/60 border-b px-5 pt-4 pb-3">
            <h2 class="font-display text-base font-bold tracking-tight">Formulir acara</h2>
            <p class="text-muted-foreground text-xs leading-relaxed">
                Form yang tersedia untuk acara ini. Setiap form memiliki status dan aksi yang berbeda.
            </p>
        </CardHeader>

        <CardContent class="px-5 py-4">
            <ul v-if="forms.length > 0" class="divide-border/60 divide-y">
                <li
                    v-for="form in forms"
                    :key="form.id"
                    class="flex flex-col gap-3 py-3.5 first:pt-0 last:pb-0 sm:flex-row sm:items-start sm:justify-between sm:gap-4"
                >
                    <div class="flex min-w-0 items-start gap-2.5">
                        <component
                            :is="formIcon(form)"
                            class="text-muted-foreground mt-0.5 size-4 shrink-0"
                            aria-hidden="true"
                        />
                        <div class="min-w-0">
                            <p class="text-foreground text-sm leading-snug font-semibold break-words">
                                {{ form.title }}
                                <span class="text-muted-foreground ml-1.5 text-[11px] font-medium">
                                    {{ formPurposeLabel(form.purpose) }}
                                </span>
                            </p>
                            <p
                                v-if="form.description"
                                class="text-muted-foreground mt-0.5 line-clamp-2 text-xs leading-relaxed"
                            >
                                {{ form.description }}
                            </p>
                            <p
                                v-if="form.requires_form_title && form.access_status === 'prerequisite_not_met'"
                                class="text-muted-foreground mt-1 text-[11px]"
                            >
                                Perlu diterima di: {{ form.requires_form_title }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <Button
                            v-if="registrationDetailUrl && form.id === registrationActionFormId"
                            variant="outline"
                            size="sm"
                            class="h-9"
                            as-child
                        >
                            <Link :href="registrationDetailUrl">Detail pendaftaran</Link>
                        </Button>
                        <Badge
                            v-if="form.access_status === 'already_submitted'"
                            variant="outline"
                            class="border-success/30 bg-success/10 text-success gap-1 px-2 py-0.5 text-[11px] font-semibold"
                        >
                            <Check class="size-3" aria-hidden="true" />
                            {{ FORM_ACCESS_STATUS_LABEL[form.access_status] }}
                        </Badge>
                        <Button v-else-if="form.can_start" size="sm" class="h-9 w-full sm:w-auto" as-child>
                            <Link :href="form.fill_url" :prefetch="false" class="justify-center">
                                Isi form
                                <ChevronRight class="size-4" aria-hidden="true" />
                            </Link>
                        </Button>
                        <span v-else class="text-muted-foreground inline-flex items-center gap-1 text-[11px] font-medium">
                            <Lock class="size-3 shrink-0" aria-hidden="true" />
                            {{ FORM_ACCESS_STATUS_LABEL[form.access_status] }}
                        </span>
                    </div>
                </li>
            </ul>

            <EmptyState
                v-else
                kind="empty"
                :surface="false"
                title="Belum ada formulir"
                description="Panitia belum membuka formulir untuk acara ini. Coba periksa kembali nanti."
            />

            <p
                v-if="forms.length > 0"
                class="border-border/60 text-muted-foreground mt-4 border-t pt-3 text-[11px] leading-relaxed"
            >
                Menyelesaikan satu form tidak otomatis menyelesaikan atau menyembunyikan form lain.
            </p>

        </CardContent>
    </Card>
</template>
