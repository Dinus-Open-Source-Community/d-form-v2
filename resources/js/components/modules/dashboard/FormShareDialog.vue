<script setup lang="ts">
import { computed, ref } from 'vue';
import { AlertCircle, Check, QrCode } from 'lucide-vue-next';
import ShareLinkField from '@/components/modules/dashboard/ShareLinkField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useCopyFeedback } from '@/hooks/useCopyFeedback';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import type { ICopyFeedback } from '@/hooks/useCopyFeedback';
import type { IFormShareActions } from '@/types/formShare';

const props = defineProps<{
    open: boolean;
    formTitle: string;
    actions: IFormShareActions;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const shortLinkButtonLabel = computed<string>(() =>
    props.actions.shortUrl.value === '' ? 'Buat short link' : 'Simpan alias'
);
const aliasInput = computed<string>({
    get: (): string => props.actions.alias.value,
    set: (value: string): void => props.actions.setAlias(value),
});
const aliasErrorId = 'form-share-alias-error';
const aliasDescribedBy = computed<string>(() =>
    props.actions.aliasErrorMessage.value === '' ? 'alias-help' : `alias-help ${aliasErrorId}`
);

const { showSuccessToast } = useToastNotifications();
const participantFeedback = useCopyFeedback();
const shortLinkFeedback = useCopyFeedback();
const adminFeedback = useCopyFeedback();

/**
 * Konfirmasi salin: ikon tombol bertukar ke centang selama satu detik dan toast
 * memberi umpan balik yang tidak menumpuk (id sama menggantikan toast sebelumnya).
 * Toast juga yang mengumumkan ke pembaca layar lewat live region `AppToaster`.
 */
function confirmCopy(feedback: ICopyFeedback, message: string): void {
    feedback.trigger();
    showSuccessToast(message, { id: 'form-share-copy' });
}

async function copyParticipantLink(): Promise<void> {
    if (await props.actions.copyParticipantLink()) confirmCopy(participantFeedback, 'Link peserta disalin.');
}

async function copyShortLink(): Promise<void> {
    if (await props.actions.copyShortLink()) confirmCopy(shortLinkFeedback, 'Short link disalin.');
}

async function copyAdminLink(): Promise<void> {
    if (await props.actions.copyAdminLink()) confirmCopy(adminFeedback, 'Link admin disalin.');
}

function closeDialog(): void {
    emit('update:open', false);
}

/** Target fokus netral saat dialog dibuka (judul), bukan input readonly pertama. */
const titleTarget = ref<HTMLElement | null>(null);

/**
 * reka-ui memfokuskan elemen tabbable pertama, yaitu field link readonly. Fokus itu
 * memunculkan ring biru pada field yang bukan aksi; arahkan ke judul agar pembukaan
 * dialog tenang dan screen reader membacakan konteksnya lebih dulu.
 */
function onOpenAutoFocus(event: Event): void {
    event.preventDefault();
    titleTarget.value?.focus();
}
</script>

<template>
    <Dialog :open="open" @update:open="(value: boolean) => emit('update:open', value)">
        <DialogContent
            class="max-h-[calc(100dvh-2rem)] gap-0 overflow-y-auto rounded-2xl p-0 sm:max-w-xl"
            @open-auto-focus="onOpenAutoFocus"
        >
            <DialogHeader class="border-border/70 border-b px-5 py-5 text-left sm:px-7">
                <div ref="titleTarget" tabindex="-1" class="space-y-1 outline-none">
                    <DialogTitle class="font-display text-xl leading-tight">Bagikan formulir</DialogTitle>
                    <p data-testid="share-form-title" class="text-foreground truncate text-sm font-medium">
                        {{ formTitle }}
                    </p>
                    <DialogDescription
                        >Salin link peserta, tautan admin, atau barcode dari satu tempat.</DialogDescription
                    >
                </div>
            </DialogHeader>

            <div class="space-y-6 px-5 py-5 sm:px-7 sm:py-6">
                <section aria-labelledby="participant-share-title" class="space-y-3">
                    <div class="space-y-1">
                        <h3 id="participant-share-title" class="text-sm font-semibold">Link peserta</h3>
                        <p class="text-muted-foreground text-xs">Untuk membuka dan mengisi formulir.</p>
                    </div>

                    <ShareLinkField
                        test-id="share-field-participant"
                        field-label="Link peserta"
                        copy-label="Salin link peserta"
                        :value="actions.participantUrl.value"
                        :disabled="!actions.canShareParticipant.value"
                        :confirmed="participantFeedback.active.value"
                        primary
                        @copy="copyParticipantLink"
                    />

                    <p
                        v-if="actions.draftExplanation.value"
                        id="draft-share-explanation"
                        class="bg-muted/50 text-muted-foreground rounded-lg px-3 py-2 text-xs"
                    >
                        {{ actions.draftExplanation.value }}
                    </p>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        class="min-h-11 cursor-pointer gap-1.5 sm:min-h-9"
                        :disabled="!actions.canShareParticipant.value"
                        @click="actions.showBarcode"
                    >
                        <QrCode class="size-4" aria-hidden="true" />
                        Buat barcode
                    </Button>
                </section>

                <section
                    v-if="actions.barcodeVisible.value && actions.canShareParticipant.value"
                    aria-labelledby="barcode-preview-title"
                    class="border-border/70 bg-muted/20 space-y-3 rounded-xl border p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <h3 id="barcode-preview-title" class="text-sm font-semibold">Barcode peserta</h3>
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground focus-visible:ring-ring/40 -mr-1 cursor-pointer rounded px-2 py-1 text-xs font-medium underline underline-offset-4 outline-none focus-visible:ring-[3px]"
                            @click="actions.hideBarcode"
                        >
                            Tutup barcode
                        </button>
                    </div>

                    <div class="bg-background flex justify-center rounded-lg border p-3">
                        <img
                            v-if="actions.barcodeStatus.value !== 'error'"
                            :key="actions.barcodeAttempt.value"
                            :src="actions.barcodeUrl.value"
                            :alt="`Barcode untuk ${formTitle}`"
                            width="260"
                            height="260"
                            class="h-auto max-w-full"
                            @load="actions.markBarcodeLoaded"
                            @error="actions.markBarcodeFailed"
                        />
                        <p
                            v-if="actions.barcodeStatus.value === 'loading'"
                            role="status"
                            class="text-muted-foreground py-8 text-sm"
                        >
                            Memuat barcode…
                        </p>
                    </div>

                    <p v-if="actions.barcodeStatus.value === 'error'" role="alert" class="text-destructive text-sm">
                        Barcode tidak dapat dimuat. Periksa koneksi, lalu coba lagi.
                    </p>

                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="actions.barcodeStatus.value === 'error'"
                            type="button"
                            variant="outline"
                            size="sm"
                            class="min-h-11 cursor-pointer sm:min-h-9"
                            @click="actions.retryBarcode"
                        >
                            Coba lagi
                        </Button>
                        <a
                            v-if="actions.barcodeStatus.value === 'loaded'"
                            :href="actions.barcodeUrl.value"
                            download="formulir-barcode.png"
                            class="text-primary focus-visible:ring-ring/40 inline-flex min-h-11 items-center gap-1.5 text-sm font-medium underline underline-offset-4 outline-none focus-visible:ring-[3px] sm:min-h-9"
                        >
                            Unduh barcode PNG
                        </a>
                    </div>
                </section>

                <section aria-labelledby="short-link-title" class="border-border/70 space-y-3 border-t pt-5">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <h3 id="short-link-title" class="text-sm font-semibold">Short link</h3>
                            <span
                                class="bg-muted text-muted-foreground rounded-md px-1.5 py-0.5 text-[10px] font-medium"
                                >Opsional</span
                            >
                        </div>
                        <p class="text-muted-foreground text-xs">
                            Pilih alias sendiri atau biarkan kosong untuk alias otomatis.
                        </p>
                    </div>

                    <Input
                        id="form-share-alias"
                        v-model="aliasInput"
                        :disabled="!actions.canShareParticipant.value || actions.isSaving.value"
                        :aria-invalid="actions.aliasErrorMessage.value !== ''"
                        aria-label="Alias short link"
                        :aria-describedby="aliasDescribedBy"
                        autocomplete="off"
                        autocapitalize="none"
                        placeholder="contoh: pendaftaran-2026"
                        class="h-11 sm:h-10"
                    />
                    <p id="alias-help" class="text-muted-foreground text-xs">
                        Gunakan huruf kecil, angka, dan tanda hubung.
                    </p>
                    <p
                        v-if="actions.aliasErrorMessage.value"
                        :id="aliasErrorId"
                        role="alert"
                        class="text-destructive text-sm"
                    >
                        {{ actions.aliasErrorMessage.value }}
                    </p>

                    <ShareLinkField
                        v-if="actions.shortUrl.value"
                        test-id="share-field-short"
                        field-label="Short link"
                        copy-label="Salin short link"
                        :value="actions.shortUrl.value"
                        :disabled="!actions.canShareParticipant.value"
                        :confirmed="shortLinkFeedback.active.value"
                        @copy="copyShortLink"
                    />

                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11 w-full cursor-pointer sm:min-h-10 sm:w-auto"
                        :disabled="!actions.canShareParticipant.value || actions.isSaving.value"
                        @click="actions.saveAlias"
                    >
                        {{ actions.isSaving.value ? 'Menyimpan…' : shortLinkButtonLabel }}
                    </Button>
                </section>

                <section aria-labelledby="admin-share-title" class="border-border/70 space-y-3 border-t pt-5">
                    <div class="space-y-1">
                        <h3 id="admin-share-title" class="text-sm font-semibold">Link admin</h3>
                        <p class="text-muted-foreground text-xs">Untuk kembali ke editor formulir ini.</p>
                    </div>

                    <ShareLinkField
                        test-id="share-field-admin"
                        field-label="Link admin"
                        copy-label="Salin link admin"
                        :value="actions.adminUrl.value"
                        :confirmed="adminFeedback.active.value"
                        @copy="copyAdminLink"
                    />
                </section>

                <div
                    v-if="actions.errorMessage.value"
                    role="alert"
                    class="border-destructive/30 bg-destructive/5 text-foreground flex items-start gap-2 rounded-lg border px-3 py-2 text-sm"
                >
                    <AlertCircle class="text-destructive mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <span>{{ actions.errorMessage.value }}</span>
                </div>
                <div
                    v-if="actions.successMessage.value"
                    role="status"
                    class="border-success/30 bg-success/5 text-foreground flex items-start gap-2 rounded-lg border px-3 py-2 text-sm"
                >
                    <Check class="text-success mt-0.5 size-4 shrink-0" aria-hidden="true" />
                    <span>{{ actions.successMessage.value }}</span>
                </div>
            </div>

            <div class="border-border/70 flex justify-end border-t px-5 py-4 sm:px-7">
                <Button
                    type="button"
                    variant="outline"
                    class="min-h-11 w-full cursor-pointer sm:min-h-10 sm:w-auto"
                    @click="closeDialog"
                    >Selesai</Button
                >
            </div>
        </DialogContent>
    </Dialog>
</template>
