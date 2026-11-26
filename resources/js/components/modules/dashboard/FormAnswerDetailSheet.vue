<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { AlertTriangle, Check, Clock, FileText, Mail, RefreshCw, X } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import { SearchableSelect, type SearchableSelectOption } from '@/components/ui/searchable-select';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import FormFieldAnswerDisplay from '@/components/modules/dashboard/FormFieldAnswerDisplay.vue';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';
import { formSubmissionReviewIsPending, submissionReviewBadge } from '@/lib/formSubmissionsUi';
import { formFieldApiType, formFieldBuilderType } from '@/lib/formFieldOptions';
import { duIssuanceBadge } from '@/lib/duSubmissionUi';
import { formatDateTime } from '@/lib/dateTime';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import { useDuAnswerRedeem } from '@/hooks/useDuAnswerRedeem';
import { DU_CLASS_NOT_FOUND_MESSAGE, DU_ISSUE_FAILED_MESSAGE, DU_ISSUE_UNKNOWN_MESSAGE } from '@/constants/du';
import type { IDuCourseOption } from '@/types/du';

const open = defineModel<boolean>('open', { required: true });

const props = defineProps<{
    submission: IFormSubmission | null;
    answerKeys: string[];
    fields: IFormField[];
    humanizeKey: (value: string) => string;
    isSubmissionReviewing: (submissionId: string) => boolean;
    /** Katalog course DU aktif; dipakai pemilih mapping kelas. */
    duCatalog: IDuCourseOption[];
    duMappingUrl: string;
    duIssueUrl: string;
}>();

const emit = defineEmits<{
    review: [payload: { action: 'accept' | 'reject'; submission: IFormSubmission }];
    /** Mapping tersimpan untuk semua jawaban dengan opsi yang sama; halaman memuat ulang daftar. */
    'du-changed': [];
}>();

const du = computed(() => props.submission?.du ?? null);
const reviewAccepted = computed<boolean>(() => props.submission?.review_status === 'accepted');
/** Field sistem D-Kelas: nilainya harus ada di katalog, tidak bisa dipetakan dari drawer. */
const DU_SYSTEM_FIELD_NAME = 'du_course_uid';
const systemMapping = computed<boolean>(() => du.value?.class_field?.name === DU_SYSTEM_FIELD_NAME);
const {
    mapping,
    issuance,
    canIssue,
    saving,
    issuing,
    saveError,
    issueError,
    saveMapping,
    issue,
    recovering,
    lastRecovery,
    recoveryError,
    resendEmail,
    reissueCode,
    syncingCatalog,
    catalogSyncError,
    syncCatalog,
} = useDuAnswerRedeem({
    mappingUrl: () => props.duMappingUrl,
    issueUrl: () => props.duIssueUrl,
    submission: () => du.value,
    answerId: () => props.submission?.id ?? null,
    reviewAccepted: () => reviewAccepted.value,
    catalog: () => props.duCatalog,
});

/** Satu alasan terminal untuk tombol terbit yang nonaktif; kosong bila tidak ada alasan. */
const issueHint = computed<string>(() => {
    if (du.value === null || du.value.class_value === null) return '';
    if (props.submission?.review_status === 'rejected') return 'Jawaban ditolak; kode tidak dapat diterbitkan.';
    if (!reviewAccepted.value) return 'Terima jawaban ini dulu untuk menerbitkan kode.';
    if (canIssue.value) return '';
    if (systemMapping.value) return '';

    return mapping.value === null ? 'Hubungkan kelas ini ke course DU dulu.' : '';
});

const selectedCourseUid = ref('');
const issueButtonRef = ref<InstanceType<typeof Button> | null>(null);
const courseOptions = computed<SearchableSelectOption[]>(() =>
    props.duCatalog.map((course) => ({ value: course.uid, label: course.title }))
);

watch(
    () => props.submission?.id,
    (): void => {
        selectedCourseUid.value = '';
    }
);

async function onSaveMapping(courseUid: string): Promise<void> {
    if (!(await saveMapping(courseUid))) return;

    selectedCourseUid.value = '';
    emit('du-changed');
    // Tombol pemicu hilang setelah mapping tersimpan; pindahkan fokus ke aksi berikutnya.
    await nextTick();
    const issueButton = issueButtonRef.value?.$el;

    if (issueButton instanceof HTMLElement) issueButton.focus();
}

async function onIssue(): Promise<void> {
    await issue();
}

const reissueConfirmOpen = ref(false);

/** Kirim ulang email kode yang sama; kode tidak berubah. */
async function onResendEmail(): Promise<void> {
    await resendEmail();
}

/** Kode baru mengganti kode lama di tabel, jadi halaman diminta memuat ulang. */
async function onReissueCode(): Promise<void> {
    const reissued = await reissueCode();

    reissueConfirmOpen.value = false;
    if (reissued) emit('du-changed');
}

function fieldForKey(key: string): IFormField | null {
    return props.fields.find((field) => field.name === key) ?? null;
}

/** True bila field adalah unggahan berkas/foto (banner dikecualikan — bukan jawaban). */
function isFileAnswerField(field: IFormField | null): boolean {
    if (!field) return false;
    const builderType = formFieldBuilderType(field);
    if (builderType === 'banner' || field.name === 'form_banner') return false;
    if (formFieldApiType(field) === 'fileUpload') return true;
    return builderType === 'file_upload' || builderType === 'image_upload' || builderType === 'fileUpload';
}

const fileAnswerKeys = computed(() => props.answerKeys.filter((key) => isFileAnswerField(fieldForKey(key))));
const textAnswerKeys = computed(() => props.answerKeys.filter((key) => !isFileAnswerField(fieldForKey(key))));

/** Seksi jawaban di drawer: teks dulu, lalu "Berkas / Foto" agar lampiran mudah ditemukan. */
const answerSections = computed(() => {
    const sections: { title: string; keys: string[] }[] = [];
    if (textAnswerKeys.value.length > 0) sections.push({ title: 'Jawaban formulir', keys: textAnswerKeys.value });
    if (fileAnswerKeys.value.length > 0) sections.push({ title: 'Berkas / Foto', keys: fileAnswerKeys.value });
    return sections;
});
</script>

<template>
    <Sheet v-model:open="open">
        <SheetContent
            side="right"
            class="bg-background pointer-events-auto z-[60] flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-md lg:max-w-lg"
        >
            <SheetHeader class="border-border/70 shrink-0 gap-0 border-b px-5 pt-5 pb-4 text-left sm:px-6">
                <div class="flex items-start gap-3.5 pr-8">
                    <UserAvatarFallback
                        :src="submission?.user?.avatar ?? null"
                        :seed="userAvatarSeed(submission?.user ?? null)"
                        avatar-class="size-11 shrink-0 rounded-xl border border-border"
                        fallback-round-class="rounded-xl"
                    />
                    <div class="min-w-0 flex-1">
                        <SheetTitle class="text-foreground truncate text-base font-semibold tracking-[-0.01em]">
                            {{ submission?.user?.name ?? 'Tanpa nama' }}
                        </SheetTitle>
                        <SheetDescription class="mt-0.5 truncate text-xs">
                            {{ submission?.user?.email ?? 'Email tidak tercatat' }}
                        </SheetDescription>
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <Badge
                                v-if="submission"
                                variant="outline"
                                :class="['font-medium', submissionReviewBadge(submission.review_status).class]"
                            >
                                {{ submissionReviewBadge(submission.review_status).label }}
                            </Badge>
                            <span
                                v-if="submission"
                                class="text-muted-foreground inline-flex items-center gap-1 text-[11px] tabular-nums"
                            >
                                <Clock class="size-3 shrink-0" aria-hidden="true" />
                                {{ formatDateTime({ value: submission.submitted_at, variant: 'date-time-medium' }) }}
                            </span>
                        </div>
                    </div>
                </div>
            </SheetHeader>

            <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-5 sm:px-6">
                <section v-if="submission" class="space-y-5">
                    <div v-for="section in answerSections" :key="section.title" class="space-y-3">
                        <h3 class="text-muted-foreground text-xs font-semibold tracking-[0.08em] uppercase">
                            {{ section.title }}
                        </h3>
                        <div class="space-y-3">
                            <div
                                v-for="key in section.keys"
                                :key="key"
                                class="border-border/70 bg-card/50 rounded-xl border p-4"
                            >
                                <p class="border-border/60 text-foreground mb-3 border-b pb-2 text-sm font-semibold">
                                    {{ humanizeKey(key) }}
                                </p>
                                <FormFieldAnswerDisplay
                                    :field="fieldForKey(key)"
                                    :value="submission.answers?.[key]"
                                    file-action-alignment="end"
                                />
                            </div>
                        </div>
                    </div>
                    <p v-if="answerSections.length === 0" class="text-muted-foreground py-6 text-center text-sm">
                        Belum ada jawaban tercatat.
                    </p>
                    <p
                        v-if="fileAnswerKeys.length > 0"
                        class="text-muted-foreground flex items-start gap-1.5 text-[11px] leading-relaxed"
                    >
                        <FileText class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                        Lampiran hanya berupa pratinjau di sini. Buka atau unduh berkas dari tombol yang tersedia pada
                        setiap jawaban bila perlu memeriksa isinya.
                    </p>

                    <div v-if="du && (du.enabled || du.issuance)" class="space-y-3">
                        <h3 class="text-muted-foreground text-xs font-semibold tracking-[0.08em] uppercase">
                            Redeem code DU
                        </h3>
                        <div class="border-border/70 bg-card/50 space-y-4 rounded-xl border p-4">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p
                                        class="text-muted-foreground text-[11px] font-medium tracking-[0.06em] uppercase"
                                    >
                                        Kelas
                                    </p>
                                    <p class="text-foreground mt-0.5 text-sm font-medium break-words">
                                        {{ du.class_value ?? 'Tidak tercatat' }}
                                    </p>
                                </div>
                                <Badge
                                    v-if="mapping"
                                    variant="outline"
                                    :class="[
                                        'whitespace-nowrap',
                                        mapping.is_active
                                            ? 'border-success/35 text-success'
                                            : 'border-amber-500/40 text-amber-700 dark:text-amber-300',
                                    ]"
                                >
                                    {{ mapping.is_active ? 'Terhubung' : 'Course tidak aktif' }}
                                </Badge>
                                <Badge
                                    v-else
                                    variant="outline"
                                    :class="[
                                        'whitespace-nowrap',
                                        systemMapping ? 'text-muted-foreground' : duIssuanceBadge(du)?.className,
                                    ]"
                                >
                                    {{ systemMapping ? 'Tidak ditemukan' : 'Belum terhubung' }}
                                </Badge>
                            </div>

                            <div v-if="mapping" class="space-y-1.5">
                                <p class="flex flex-wrap items-baseline gap-x-1.5 text-sm">
                                    <span class="text-muted-foreground">Terhubung:</span>
                                    <span class="font-medium break-words">{{ mapping.course_title }}</span>
                                </p>
                                <p
                                    v-if="!mapping.is_active"
                                    class="flex items-start gap-1.5 text-[11px] text-amber-700 dark:text-amber-300"
                                >
                                    <AlertTriangle class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                                    Course ini tidak aktif di katalog. Kode tetap dapat diterbitkan selama DU
                                    mengakuinya.
                                </p>
                            </div>

                            <p v-else-if="du.class_value === null" class="text-muted-foreground text-sm">
                                Jawaban kelas tidak tercatat, jadi mapping belum bisa dibuat.
                            </p>

                            <p v-else-if="systemMapping" class="text-muted-foreground text-sm">
                                {{ DU_CLASS_NOT_FOUND_MESSAGE }}
                            </p>

                            <div v-else class="space-y-2.5">
                                <p v-if="saveError" role="alert" class="text-destructive text-xs">{{ saveError }}</p>
                                <div
                                    v-if="du.suggestion"
                                    class="border-primary/20 bg-primary/5 flex flex-wrap items-center justify-between gap-2 rounded-lg border px-3 py-2"
                                >
                                    <p class="flex flex-wrap items-baseline gap-x-1.5 text-xs">
                                        <span>Terdeteksi:</span>
                                        <span class="font-semibold break-words">{{ du.suggestion.course_title }}</span>
                                    </p>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="h-11 px-3 text-xs font-bold"
                                        :disabled="saving"
                                        @click="onSaveMapping(du.suggestion.course_uid)"
                                    >
                                        Gunakan
                                    </Button>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <label
                                        :for="`du-course-${submission.id}`"
                                        class="text-muted-foreground block text-xs font-medium"
                                    >
                                        Course DU
                                    </label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="h-9 px-3 text-[11px] font-bold"
                                        :disabled="syncingCatalog"
                                        :aria-busy="syncingCatalog"
                                        @click="syncCatalog()"
                                    >
                                        <RefreshCw class="mr-1 size-3" aria-hidden="true" />
                                        {{ syncingCatalog ? 'Menyinkronkan…' : 'Sync katalog DU' }}
                                    </Button>
                                </div>
                                <p v-if="catalogSyncError" role="alert" class="text-destructive text-xs">
                                    {{ catalogSyncError }}
                                </p>
                                <SearchableSelect
                                    :id="`du-course-${submission.id}`"
                                    v-model="selectedCourseUid"
                                    :options="courseOptions"
                                    :disabled="saving || courseOptions.length === 0"
                                    placeholder="Pilih course DU"
                                    search-placeholder="Cari course DU…"
                                    empty-text="Course DU tidak ditemukan"
                                    :show-initials="false"
                                    class="h-11"
                                    aria-label="Course DU untuk kelas ini"
                                />
                                <Button
                                    type="button"
                                    class="h-11 w-full"
                                    :disabled="saving || selectedCourseUid === ''"
                                    :aria-busy="saving"
                                    @click="onSaveMapping(selectedCourseUid)"
                                >
                                    {{ saving ? 'Menyimpan…' : 'Simpan mapping' }}
                                </Button>
                                <p v-if="courseOptions.length === 0" class="text-muted-foreground text-[11px]">
                                    Belum ada course DU aktif. Jalankan Sync katalog DU lalu coba lagi.
                                </p>
                                <p class="text-muted-foreground text-[11px] leading-relaxed">
                                    Pilih course DU yang sesuai dengan kelas. Jika kelas belum ada di DU, minta DU
                                    membuatnya.
                                </p>
                                <p class="text-muted-foreground text-[11px] leading-relaxed">
                                    Mapping berlaku untuk semua jawaban dengan opsi ini.
                                </p>
                            </div>

                            <div class="border-border/60 space-y-2 border-t pt-3">
                                <p class="text-muted-foreground text-[11px] font-medium tracking-[0.06em] uppercase">
                                    Kode redeem
                                </p>
                                <template v-if="issuance?.status === 'issued'">
                                    <code
                                        v-if="issuance.code"
                                        class="border-border/70 bg-muted/50 text-foreground inline-block rounded-md border px-2 py-1 font-mono text-sm font-semibold break-all"
                                    >
                                        {{ issuance.code }}
                                    </code>
                                    <p class="text-muted-foreground text-[11px]">
                                        Kode adalah kredensial. Bagikan hanya kepada peserta terkait.
                                    </p>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="h-10 flex-1 gap-1.5 text-sm"
                                            :disabled="recovering !== null"
                                            :aria-busy="recovering === 'resend'"
                                            @click="onResendEmail"
                                        >
                                            <Mail class="size-4" aria-hidden="true" />
                                            {{ recovering === 'resend' ? 'Mengirim…' : 'Kirim ulang email' }}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="h-10 flex-1 text-sm"
                                            :disabled="recovering !== null"
                                            :aria-busy="recovering === 'reissue'"
                                            @click="reissueConfirmOpen = true"
                                        >
                                            {{ recovering === 'reissue' ? 'Menerbitkan…' : 'Terbitkan ulang' }}
                                        </Button>
                                    </div>
                                    <p
                                        v-if="issuance.message"
                                        class="text-[11px]"
                                        :class="
                                            lastRecovery === 'reissue'
                                                ? 'text-amber-700 dark:text-amber-300'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ issuance.message }}
                                    </p>
                                    <p v-if="recoveryError" role="alert" class="text-destructive text-xs">
                                        {{ recoveryError }}
                                    </p>
                                </template>
                                <template v-else-if="issuance?.status === 'unknown'">
                                    <p class="text-foreground text-sm font-medium">{{ DU_ISSUE_UNKNOWN_MESSAGE }}</p>
                                    <p class="text-muted-foreground text-[11px] leading-relaxed">
                                        Status pengiriman ke DU belum pasti. Kode tidak diterbitkan ulang otomatis.
                                    </p>
                                </template>
                                <template v-else>
                                    <p
                                        v-if="issuance?.status === 'failed'"
                                        role="alert"
                                        class="text-destructive text-xs"
                                    >
                                        {{ DU_ISSUE_FAILED_MESSAGE }}
                                    </p>
                                    <p v-else-if="issuance?.status === 'pending'" class="text-muted-foreground text-xs">
                                        Penerbitan sedang diproses DU.
                                    </p>
                                    <Button
                                        ref="issueButtonRef"
                                        type="button"
                                        class="h-11 w-full"
                                        :disabled="!canIssue || issuing"
                                        :aria-busy="issuing"
                                        @click="onIssue"
                                    >
                                        {{
                                            issuing
                                                ? 'Menerbitkan…'
                                                : issuance?.status === 'failed'
                                                  ? 'Terbitkan ulang'
                                                  : 'Terbitkan kode'
                                        }}
                                    </Button>
                                    <p v-if="issueHint" class="text-muted-foreground text-[11px]">{{ issueHint }}</p>
                                </template>
                                <p v-if="issueError" role="alert" class="text-destructive text-xs">{{ issueError }}</p>
                            </div>
                        </div>
                    </div>
                </section>
                <p v-else class="text-muted-foreground py-10 text-center text-sm">
                    Pilih salah satu jawaban untuk melihat detailnya.
                </p>
            </div>

            <SheetFooter
                v-if="submission"
                class="border-border/70 bg-muted/20 shrink-0 flex-col gap-2 border-t px-5 py-4 sm:flex-col sm:px-6"
            >
                <template v-if="formSubmissionReviewIsPending(submission)">
                    <p class="text-muted-foreground w-full text-center text-xs sm:text-left">
                        Terima jika pengajuan memenuhi syarat, tolak jika tidak dapat dilanjutkan.
                    </p>
                    <div class="flex w-full flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            class="border-success/35 text-success hover:bg-success/10 hover:text-success h-10 flex-1 gap-1.5 text-sm font-medium"
                            :disabled="isSubmissionReviewing(submission.id)"
                            @click="emit('review', { action: 'accept', submission })"
                        >
                            <Check class="size-4" aria-hidden="true" />
                            Terima
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="border-destructive/35 text-destructive hover:bg-destructive/10 hover:text-destructive h-10 flex-1 gap-1.5 text-sm font-medium"
                            :disabled="isSubmissionReviewing(submission.id)"
                            @click="emit('review', { action: 'reject', submission })"
                        >
                            <X class="size-4" aria-hidden="true" />
                            Tolak
                        </Button>
                    </div>
                </template>
                <p v-else class="text-muted-foreground w-full text-center text-xs leading-relaxed">
                    <template v-if="submission.review_status === 'accepted'">
                        Jawaban ini sudah diterima dan tidak dapat direview ulang.
                    </template>
                    <template v-else> Jawaban ini sudah ditolak dan tidak dapat direview ulang. </template>
                    <template v-if="submission.reviewed_at">
                        Direview pada
                        {{ formatDateTime({ value: submission.reviewed_at, variant: 'date-time-medium' }) }}.
                    </template>
                </p>
            </SheetFooter>
        </SheetContent>
        <ConfirmationModal
            :open="reissueConfirmOpen"
            title="Terbitkan ulang kode"
            description="Kode lama akan diganti kode baru jika DU mengizinkan. Jika DU menolak, kode lama tetap berlaku."
            confirm-text="Terbitkan ulang"
            :loading="recovering === 'reissue'"
            @confirm="onReissueCode"
            @cancel="reissueConfirmOpen = false"
            @update:open="reissueConfirmOpen = $event"
        />
    </Sheet>
</template>
