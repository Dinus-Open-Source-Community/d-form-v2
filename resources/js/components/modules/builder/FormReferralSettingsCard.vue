<script setup lang="ts">
import { computed, ref } from 'vue';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import FormReferralCodeSheet from '@/components/modules/builder/FormReferralCodeSheet.vue';
import ReferralCodeCopyButton from '@/components/modules/builder/ReferralCodeCopyButton.vue';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { formatReferralDiscount, formatReferralPeriod, formatReferralUsage } from '@/lib/formReferral';
import type { IReferralCode, IReferralCodeInput, IReferralPayload } from '@/types/referral';

const props = defineProps<{
    referral: IReferralPayload;
    processing: boolean;
    fieldErrors: Record<string, string>;
    errorMessage: string;
}>();

const emit = defineEmits<{
    toggleEnabled: [value: boolean];
    create: [input: IReferralCodeInput, onSuccess: () => void];
    update: [id: string, input: Partial<IReferralCodeInput>, onSuccess: () => void];
    remove: [id: string, onSuccess: () => void];
    clearErrors: [];
}>();

const codeSheetRef = ref<InstanceType<typeof FormReferralCodeSheet> | null>(null);
const pendingDeleteCode = ref<IReferralCode | null>(null);
const isDeleteDialogOpen = ref(false);

const deleteDialogDescription = computed<string>(() =>
    pendingDeleteCode.value === null
        ? ''
        : `Kode ${pendingDeleteCode.value.code} akan dihapus dan tidak dapat digunakan lagi.`
);

function openCreate(): void {
    emit('clearErrors');
    codeSheetRef.value?.openCreate();
}

function openEdit(code: IReferralCode): void {
    emit('clearErrors');
    codeSheetRef.value?.openEdit(code);
}

function requestRemove(code: IReferralCode): void {
    if (!code.can_delete || props.processing) return;
    pendingDeleteCode.value = code;
    isDeleteDialogOpen.value = true;
}

function confirmRemove(): void {
    const code = pendingDeleteCode.value;
    if (code === null) return;

    emit('remove', code.id, closeDeleteDialog);
}

function closeDeleteDialog(): void {
    isDeleteDialogOpen.value = false;
    pendingDeleteCode.value = null;
}
</script>

<template>
    <section
        id="form-settings-referral"
        class="border-border bg-card mt-6 scroll-mt-6 space-y-5 rounded-2xl border p-6 shadow-xs"
        aria-labelledby="referral-title"
    >
        <div class="flex items-center justify-between gap-3">
            <h2 id="referral-title" class="text-foreground text-sm font-semibold">Kode referral</h2>
            <div class="flex shrink-0 items-center gap-2">
                <Label for="referral-enabled" class="text-xs font-medium">Aktifkan</Label>
                <Switch
                    id="referral-enabled"
                    :model-value="props.referral.enabled"
                    :disabled="props.processing"
                    @update:model-value="emit('toggleEnabled', $event)"
                />
            </div>
        </div>

        <p v-if="props.errorMessage" role="alert" class="text-destructive text-xs">{{ props.errorMessage }}</p>

        <template v-if="props.referral.enabled">
            <div class="flex justify-end">
                <Button type="button" size="sm" :disabled="props.processing" @click="openCreate">
                    <Plus class="size-4" aria-hidden="true" />
                    Tambah kode
                </Button>
            </div>

            <p
                v-if="props.referral.codes.length === 0"
                class="text-muted-foreground rounded-xl border border-dashed px-4 py-5 text-center text-sm"
            >
                Belum ada kode. Buat satu untuk mulai mencatat pemberi kode.
            </p>

            <ul v-else class="space-y-2">
                <li
                    v-for="code in props.referral.codes"
                    :key="code.id"
                    class="border-border/70 bg-background/60 flex items-start justify-between gap-3 rounded-xl border px-4 py-3"
                >
                    <div class="min-w-0 space-y-1.5">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-foreground font-mono text-sm font-semibold">{{ code.code }}</span>
                            <Badge
                                variant="outline"
                                :class="[
                                    'h-5 px-1.5 text-[10px] font-medium',
                                    code.is_active
                                        ? 'border-success/30 bg-success/10 text-success'
                                        : 'border-border bg-muted text-muted-foreground',
                                ]"
                            >
                                {{ code.is_active ? 'Aktif' : 'Nonaktif' }}
                            </Badge>
                        </div>
                        <p class="text-muted-foreground truncate text-xs">
                            {{ code.assigned_user?.name ?? code.attribution_name ?? 'Tanpa pemberi kode' }}
                        </p>
                        <div
                            class="text-muted-foreground flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] tabular-nums"
                        >
                            <span>{{ formatReferralUsage(code) }}</span>
                            <span aria-hidden="true">·</span>
                            <span>{{ formatReferralPeriod(code) }}</span>
                            <template v-if="formatReferralDiscount(code)">
                                <span aria-hidden="true">·</span>
                                <span>{{ formatReferralDiscount(code) }}</span>
                            </template>
                        </div>
                        <p v-if="!code.can_delete" class="text-muted-foreground text-[11px] leading-relaxed">
                            Sudah pernah dipakai, jadi tidak bisa dihapus. Nonaktifkan untuk menghentikannya.
                        </p>
                    </div>

                    <div class="flex shrink-0 items-center gap-1">
                        <ReferralCodeCopyButton :code="code.code" :disabled="props.processing" />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="hover:bg-primary/10 hover:text-primary rounded-md"
                            :aria-label="`Ubah kode ${code.code}`"
                            :disabled="props.processing"
                            @click="openEdit(code)"
                        >
                            <Pencil class="size-4" aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-sm"
                            class="text-destructive hover:bg-destructive/10 hover:text-destructive rounded-md"
                            :aria-label="`Hapus kode ${code.code}`"
                            :disabled="!code.can_delete || props.processing"
                            @click="requestRemove(code)"
                        >
                            <Trash2 class="size-4" aria-hidden="true" />
                        </Button>
                    </div>
                </li>
            </ul>
        </template>

        <p v-else class="text-muted-foreground rounded-xl border border-dashed px-4 py-5 text-center text-sm">
            Aktifkan kode referral untuk mulai mencatat pemberi kode peserta.
        </p>

        <FormReferralCodeSheet
            ref="codeSheetRef"
            :processing="props.processing"
            :field-errors="props.fieldErrors"
            :error-message="props.errorMessage"
            @create="(input, onSuccess) => emit('create', input, onSuccess)"
            @update="(id, input, onSuccess) => emit('update', id, input, onSuccess)"
        />

        <ConfirmationModal
            v-model:open="isDeleteDialogOpen"
            title="Hapus kode referral?"
            :description="deleteDialogDescription"
            confirm-text="Hapus kode"
            cancel-text="Batal"
            variant="destructive"
            :loading="props.processing"
            @confirm="confirmRemove"
            @cancel="closeDeleteDialog"
        />
    </section>
</template>
