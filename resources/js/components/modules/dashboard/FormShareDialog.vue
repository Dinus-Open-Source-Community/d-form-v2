<script setup lang="ts">
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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

const copyAnnouncement = computed<string>(() => {
    if (props.actions.copyStatus.value === 'participant') return 'Link peserta berhasil disalin.';
    if (props.actions.copyStatus.value === 'admin') return 'Link admin berhasil disalin.';
    if (props.actions.copyStatus.value === 'short') return 'Short link berhasil disalin.';
    return '';
});

function closeDialog(): void {
    emit('update:open', false);
}
</script>

<template>
    <Dialog :open="open" @update:open="(value: boolean) => emit('update:open', value)">
        <DialogContent class="max-h-[calc(100dvh-2rem)] gap-0 overflow-y-auto rounded-2xl p-0 sm:max-w-xl">
            <DialogHeader class="border-border/70 border-b px-5 py-5 text-left sm:px-7">
                <p class="text-muted-foreground text-sm">Bagikan formulir</p>
                <DialogTitle class="font-display text-xl leading-tight">{{ formTitle }}</DialogTitle>
                <DialogDescription
                    >Salin link untuk peserta atau buka formulir admin secara terpisah.</DialogDescription
                >
            </DialogHeader>

            <div class="space-y-6 px-5 py-5 sm:px-7 sm:py-6">
                <section aria-labelledby="participant-share-title" class="space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 id="participant-share-title" class="text-sm font-semibold">Link peserta</h3>
                            <p class="text-muted-foreground mt-1 text-xs">Untuk membuka dan mengisi formulir.</p>
                        </div>
                        <span class="bg-primary/10 text-primary rounded-full px-2.5 py-1 text-xs font-medium"
                            >Peserta</span
                        >
                    </div>
                    <Input
                        :model-value="actions.participantUrl.value"
                        readonly
                        aria-label="Link peserta"
                        class="h-10 font-mono text-xs"
                    />
                    <p
                        v-if="actions.draftExplanation.value"
                        id="draft-share-explanation"
                        class="text-muted-foreground text-sm"
                    >
                        {{ actions.draftExplanation.value }}
                    </p>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            class="min-h-10 flex-1"
                            :disabled="!actions.canShareParticipant.value"
                            @click="actions.copyParticipantLink"
                        >
                            Salin link peserta
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-10 flex-1"
                            :disabled="!actions.canShareParticipant.value"
                            @click="actions.showBarcode"
                        >
                            Generate barcode
                        </Button>
                    </div>
                </section>

                <section aria-labelledby="short-link-title" class="border-border/70 space-y-3 border-t pt-5">
                    <div>
                        <h3 id="short-link-title" class="text-sm font-semibold">Short link</h3>
                        <p class="text-muted-foreground mt-1 text-xs">
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
                        class="h-10"
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
                    <div v-if="actions.shortUrl.value" class="space-y-2">
                        <Input
                            :model-value="actions.shortUrl.value"
                            readonly
                            aria-label="Short link"
                            class="h-10 font-mono text-xs"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            class="min-h-10 w-full"
                            :disabled="!actions.canShareParticipant.value"
                            @click="actions.copyShortLink"
                        >
                            Salin short link
                        </Button>
                    </div>
                    <Button
                        type="button"
                        class="min-h-10 w-full"
                        :disabled="!actions.canShareParticipant.value || actions.isSaving.value"
                        @click="actions.saveAlias"
                    >
                        {{ actions.isSaving.value ? 'Menyimpan…' : shortLinkButtonLabel }}
                    </Button>
                </section>

                <section aria-labelledby="admin-share-title" class="border-border/70 space-y-3 border-t pt-5">
                    <div>
                        <h3 id="admin-share-title" class="text-sm font-semibold">Link admin</h3>
                        <p class="text-muted-foreground mt-1 text-xs">Untuk kembali ke editor formulir ini.</p>
                    </div>
                    <Input
                        :model-value="actions.adminUrl.value"
                        readonly
                        aria-label="Link admin"
                        class="h-10 font-mono text-xs"
                    />
                    <Button type="button" variant="outline" class="min-h-10 w-full" @click="actions.copyAdminLink">
                        Salin link admin
                    </Button>
                </section>

                <section
                    v-if="actions.barcodeVisible.value && actions.canShareParticipant.value"
                    aria-labelledby="barcode-preview-title"
                    class="border-border/70 border-t pt-5"
                >
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <h3 id="barcode-preview-title" class="text-sm font-semibold">Barcode peserta</h3>
                        <button
                            type="button"
                            class="text-muted-foreground hover:text-foreground rounded px-2 py-1 text-sm underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2"
                            @click="actions.hideBarcode"
                        >
                            Tutup barcode
                        </button>
                    </div>
                    <div class="bg-muted/30 flex justify-center rounded-xl border p-4">
                        <img
                            v-if="actions.barcodeStatus.value !== 'error'"
                            :key="actions.barcodeAttempt.value"
                            :src="actions.barcodeUrl.value"
                            :alt="`Barcode untuk ${formTitle}`"
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
                    <div v-if="actions.barcodeStatus.value === 'error'" class="space-y-3">
                        <p role="alert" class="text-destructive text-sm">
                            Barcode tidak dapat dimuat. Periksa koneksi, lalu coba lagi.
                        </p>
                        <Button type="button" variant="outline" class="min-h-10 w-full" @click="actions.retryBarcode">
                            Coba lagi
                        </Button>
                    </div>
                    <a
                        v-if="actions.canShareParticipant.value && actions.barcodeStatus.value === 'loaded'"
                        :href="actions.barcodeUrl.value"
                        download="formulir-barcode.png"
                        class="text-primary mt-3 inline-flex min-h-10 items-center underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2"
                    >
                        Unduh barcode PNG
                    </a>
                </section>

                <p v-if="actions.errorMessage.value" role="alert" class="text-destructive text-sm">
                    {{ actions.errorMessage.value }}
                </p>
                <p
                    v-if="actions.successMessage.value"
                    role="status"
                    class="text-sm text-emerald-700 dark:text-emerald-400"
                >
                    {{ actions.successMessage.value }}
                </p>
                <p role="status" aria-live="polite" class="sr-only">{{ copyAnnouncement }}</p>
            </div>

            <div class="border-border/70 border-t px-5 py-4 sm:px-7">
                <Button type="button" variant="ghost" class="min-h-10 w-full sm:w-auto" @click="closeDialog"
                    >Selesai</Button
                >
            </div>
        </DialogContent>
    </Dialog>
</template>
