<script setup lang="ts">
import { ref } from 'vue';
import { Lock } from 'lucide-vue-next';
import ChipToggle from '@/components/core/ChipToggle.vue';
import { Button } from '@/components/ui/button';
import { SplitDateTimeField } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { InputGroup, InputGroupAddon, InputGroupInput, InputGroupText } from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { useReferralCodeSheet } from '@/hooks/useReferralCodeSheet';
import { sanitizeReferralDiscountInput } from '@/lib/formReferral';
import type { IReferralCode, IReferralCodeInput, TReferralDiscountType } from '@/types/referral';

const props = defineProps<{
    processing: boolean;
    fieldErrors: Record<string, string>;
    errorMessage: string;
}>();

const emit = defineEmits<{
    create: [input: IReferralCodeInput, onSuccess: () => void];
    update: [id: string, input: Partial<IReferralCodeInput>, onSuccess: () => void];
}>();

const { isOpen, form, title, description, codePreview, identityLocked, openCreate, openEdit, close, submit } =
    useReferralCodeSheet({
        create: (input, onSuccess) => emit('create', input, onSuccess),
        update: (id, input, onSuccess) => emit('update', id, input, onSuccess),
    });

/** Fokus ke judul, bukan input pertama, agar drawer tidak melompat saat dibuka. */
const focusTarget = ref<HTMLElement | null>(null);
const discountInput = ref<InstanceType<typeof InputGroupInput> | null>(null);

function onOpenAutoFocus(event: Event): void {
    event.preventDefault();
    focusTarget.value?.focus();
}

function onOpenChange(open: boolean): void {
    if (!open) close();
}

/** Ganti jenis diskon sambil menjaga nilai tetap sah (mis. 25000 menjadi 100 saat pindah ke persen). */
function setDiscountType(type: TReferralDiscountType): void {
    form.discount_type = type;
    form.discount_value = sanitizeReferralDiscountInput(type, form.discount_value);
}

/** Input teks: hanya digit positif yang masuk; persen berhenti di 100. */
function onDiscountInput(value: string | number): void {
    const sanitized = sanitizeReferralDiscountInput(form.discount_type, value);
    form.discount_value = sanitized;

    // Karakter yang ditolak harus hilang dari DOM juga; tanpa ini Input menyimpan teks mentah saat nilai model tidak berubah.
    const element = discountInput.value?.$el;

    if (element instanceof HTMLInputElement && element.value !== sanitized) {
        element.value = sanitized;
    }
}

defineExpose({
    openCreate,
    openEdit: (code: IReferralCode): void => openEdit(code),
});
</script>

<template>
    <Sheet :open="isOpen" @update:open="onOpenChange">
        <SheetContent
            side="right"
            class="flex h-full w-full flex-col gap-0 p-0 sm:max-w-lg"
            @open-auto-focus="onOpenAutoFocus"
        >
            <SheetHeader class="border-border/70 shrink-0 space-y-1 border-b py-4 pr-12 pl-5 text-left">
                <div ref="focusTarget" tabindex="-1" class="outline-none">
                    <SheetTitle class="font-display text-base tracking-[-0.01em]">{{ title }}</SheetTitle>
                    <SheetDescription class="text-muted-foreground mt-1 text-xs leading-relaxed">
                        {{ description }}
                    </SheetDescription>
                </div>
            </SheetHeader>

            <form class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
                <div class="min-h-0 flex-1 space-y-6 overflow-y-auto px-5 py-5">
                    <p v-if="props.errorMessage" role="alert" class="text-destructive text-sm font-medium">
                        {{ props.errorMessage }}
                    </p>

                    <p
                        v-if="identityLocked"
                        class="border-border/70 bg-muted/40 text-muted-foreground flex items-start gap-2 rounded-lg border px-3 py-2 text-xs leading-relaxed"
                    >
                        <Lock class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                        <span>
                            Prefix, isi kode, dan pemberi kode terkunci karena kode sudah pernah dipakai. Nonaktifkan
                            kode untuk menghentikan pemakaian.
                        </span>
                    </p>

                    <section class="space-y-3" aria-labelledby="referral-attribution-title">
                        <div>
                            <h3 id="referral-attribution-title" class="text-foreground text-sm font-semibold">
                                Pemberi kode
                            </h3>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Isi siapa yang memberi kode ini. Namanya muncul di laporan jawaban.
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-2" role="group" aria-label="Jenis pemberi kode">
                            <ChipToggle
                                label="Nama custom"
                                :active="form.attribution_mode === 'name'"
                                :disabled="identityLocked || props.processing"
                                @select="form.attribution_mode = 'name'"
                            />
                            <ChipToggle
                                label="Assign ke user"
                                :active="form.attribution_mode === 'user'"
                                :disabled="identityLocked || props.processing"
                                @select="form.attribution_mode = 'user'"
                            />
                        </div>

                        <div v-if="form.attribution_mode === 'name'" class="space-y-2">
                            <Label for="referral-attribution-name" class="text-xs font-medium">Nama pemberi kode</Label>
                            <Input
                                id="referral-attribution-name"
                                v-model="form.attribution_name"
                                placeholder="Contoh: Komunitas Udinus"
                                maxlength="150"
                                :disabled="identityLocked || props.processing"
                                :aria-invalid="!!props.fieldErrors.attribution_name"
                                aria-describedby="referral-attribution-name-error"
                            />
                            <p
                                v-if="props.fieldErrors.attribution_name"
                                id="referral-attribution-name-error"
                                class="text-destructive text-xs"
                            >
                                {{ props.fieldErrors.attribution_name }}
                            </p>
                        </div>

                        <div v-else class="space-y-2">
                            <Label for="referral-assigned-user" class="text-xs font-medium"
                                >Pengguna pemberi kode</Label
                            >
                            <Input
                                id="referral-assigned-user"
                                v-model="form.assigned_user_id"
                                class="font-mono text-xs"
                                placeholder="Tempel UUID pemberi kode"
                                :disabled="identityLocked || props.processing"
                                :aria-invalid="!!props.fieldErrors.assigned_user_id"
                                aria-describedby="referral-assigned-user-error referral-assigned-user-help"
                            />
                            <p
                                v-if="props.fieldErrors.assigned_user_id"
                                id="referral-assigned-user-error"
                                class="text-destructive text-xs"
                            >
                                {{ props.fieldErrors.assigned_user_id }}
                            </p>
                            <p v-else id="referral-assigned-user-help" class="text-muted-foreground text-xs">
                                Salin UUID pemberi kode dari menu Pengguna. Satu kode hanya untuk satu pengguna.
                            </p>
                        </div>
                    </section>

                    <Separator class="bg-border/60" />

                    <section class="space-y-3" aria-labelledby="referral-format-title">
                        <div>
                            <h3 id="referral-format-title" class="text-foreground text-sm font-semibold">
                                Format kode
                            </h3>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Prefix tetap, isi kode bisa otomatis atau ditentukan sendiri.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="referral-prefix" class="text-xs font-medium">Prefix</Label>
                            <Input
                                id="referral-prefix"
                                v-model="form.prefix"
                                class="font-mono uppercase"
                                placeholder="Contoh: UDIN"
                                maxlength="12"
                                :disabled="identityLocked || props.processing"
                                :aria-invalid="!!props.fieldErrors.prefix"
                                aria-describedby="referral-prefix-error"
                            />
                            <p
                                v-if="props.fieldErrors.prefix"
                                id="referral-prefix-error"
                                class="text-destructive text-xs"
                            >
                                {{ props.fieldErrors.prefix }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <span class="text-xs font-medium">Isi kode</span>
                            <div class="grid grid-cols-2 gap-2" role="group" aria-label="Cara mengisi kode">
                                <ChipToggle
                                    label="Acak"
                                    :active="form.generation_mode === 'random'"
                                    :disabled="identityLocked || props.processing"
                                    @select="form.generation_mode = 'random'"
                                />
                                <ChipToggle
                                    label="Manual"
                                    :active="form.generation_mode === 'manual'"
                                    :disabled="identityLocked || props.processing"
                                    @select="form.generation_mode = 'manual'"
                                />
                            </div>
                        </div>

                        <div v-if="form.generation_mode === 'manual'" class="space-y-2">
                            <Label for="referral-body" class="text-xs font-medium">Isi manual</Label>
                            <Input
                                id="referral-body"
                                v-model="form.body"
                                class="font-mono uppercase"
                                placeholder="Contoh: AB12"
                                maxlength="16"
                                :disabled="identityLocked || props.processing"
                                :aria-invalid="!!props.fieldErrors.body"
                                aria-describedby="referral-body-error"
                            />
                            <p v-if="props.fieldErrors.body" id="referral-body-error" class="text-destructive text-xs">
                                {{ props.fieldErrors.body }}
                            </p>
                        </div>

                        <p
                            v-if="codePreview !== ''"
                            class="border-border/70 bg-muted/40 text-muted-foreground rounded-lg border px-3 py-2 text-xs"
                        >
                            Pratinjau
                            <span class="text-foreground ml-1 font-mono font-semibold">{{ codePreview }}</span>
                        </p>
                    </section>

                    <Separator class="bg-border/60" />

                    <section class="space-y-3" aria-labelledby="referral-discount-title">
                        <div>
                            <h3 id="referral-discount-title" class="text-foreground text-sm font-semibold">Diskon</h3>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Potongan untuk harga form. Kosongkan nilai jika kode ini tanpa diskon.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-2" role="group" aria-label="Jenis diskon">
                            <ChipToggle
                                label="Persen"
                                :active="form.discount_type === 'percentage'"
                                :disabled="props.processing"
                                @select="setDiscountType('percentage')"
                            />
                            <ChipToggle
                                label="Rupiah"
                                :active="form.discount_type === 'fixed'"
                                :disabled="props.processing"
                                @select="setDiscountType('fixed')"
                            />
                        </div>

                        <div class="space-y-2">
                            <Label for="referral-discount-value" class="text-xs font-medium">
                                {{ form.discount_type === 'percentage' ? 'Persen diskon' : 'Nominal diskon' }}
                            </Label>
                            <InputGroup>
                                <InputGroupAddon v-if="form.discount_type === 'fixed'" align="inline-start">
                                    <InputGroupText>Rp</InputGroupText>
                                </InputGroupAddon>

                                <InputGroupInput
                                    id="referral-discount-value"
                                    ref="discountInput"
                                    type="text"
                                    inputmode="numeric"
                                    pattern="[0-9]*"
                                    autocomplete="off"
                                    :model-value="String(form.discount_value)"
                                    :placeholder="form.discount_type === 'percentage' ? '20' : '25000'"
                                    :disabled="props.processing"
                                    :aria-invalid="!!props.fieldErrors.discount_value"
                                    :aria-describedby="
                                        props.fieldErrors.discount_value ? 'referral-discount-value-error' : undefined
                                    "
                                    @update:model-value="onDiscountInput"
                                />

                                <InputGroupAddon v-if="form.discount_type === 'percentage'" align="inline-end">
                                    <InputGroupText>%</InputGroupText>
                                </InputGroupAddon>
                            </InputGroup>

                            <p
                                v-if="props.fieldErrors.discount_value"
                                id="referral-discount-value-error"
                                class="text-destructive text-xs"
                            >
                                {{ props.fieldErrors.discount_value }}
                            </p>
                        </div>
                    </section>

                    <Separator class="bg-border/60" />

                    <section class="space-y-3" aria-labelledby="referral-usage-title">
                        <div>
                            <h3 id="referral-usage-title" class="text-foreground text-sm font-semibold">
                                Batas dan status
                            </h3>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Batasi jumlah peserta, lalu tentukan kode langsung bisa dipakai.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="referral-usage-limit" class="text-xs font-medium">Batas pemakaian</Label>
                            <Input
                                id="referral-usage-limit"
                                v-model="form.usage_limit"
                                type="number"
                                min="1"
                                placeholder="Tanpa batas"
                                :disabled="props.processing"
                                :aria-invalid="!!props.fieldErrors.usage_limit"
                                aria-describedby="referral-usage-limit-help referral-usage-limit-error"
                            />
                            <p
                                v-if="props.fieldErrors.usage_limit"
                                id="referral-usage-limit-error"
                                class="text-destructive text-xs"
                            >
                                {{ props.fieldErrors.usage_limit }}
                            </p>
                            <p v-else id="referral-usage-limit-help" class="text-muted-foreground text-xs">
                                Kosongkan jika kode boleh dipakai tanpa batas.
                            </p>
                        </div>

                        <div
                            class="border-border/70 flex items-center justify-between gap-3 rounded-xl border px-3 py-2.5"
                        >
                            <div class="min-w-0">
                                <Label for="referral-active" class="text-xs font-medium">Kode aktif</Label>
                                <p class="text-muted-foreground text-xs">
                                    {{
                                        form.is_active
                                            ? 'Bisa dipakai peserta sekarang.'
                                            : 'Disimpan tetapi belum bisa dipakai.'
                                    }}
                                </p>
                            </div>
                            <Switch id="referral-active" v-model="form.is_active" :disabled="props.processing" />
                        </div>
                    </section>

                    <Separator class="bg-border/60" />

                    <section class="space-y-3" aria-labelledby="referral-period-title">
                        <div>
                            <h3 id="referral-period-title" class="text-foreground text-sm font-semibold">
                                Periode berlaku
                            </h3>
                            <p class="text-muted-foreground mt-1 text-xs">
                                Kosongkan tanggal jika kode berlaku tanpa batas waktu.
                            </p>
                        </div>

                        <SplitDateTimeField
                            id-prefix="referral-starts"
                            v-model="form.starts_at"
                            label="Mulai berlaku"
                            layout="col"
                            picker-class="bg-card"
                            :invalid="!!props.fieldErrors.starts_at"
                            :error="props.fieldErrors.starts_at"
                        />
                        <SplitDateTimeField
                            id-prefix="referral-expires"
                            v-model="form.expires_at"
                            label="Berakhir"
                            layout="col"
                            picker-class="bg-card"
                            :invalid="!!props.fieldErrors.expires_at"
                            :error="props.fieldErrors.expires_at"
                        />
                    </section>
                </div>

                <footer
                    class="border-border/70 flex shrink-0 gap-2 border-t px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))]"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="h-11 flex-1"
                        :disabled="props.processing"
                        @click="close"
                    >
                        Batal
                    </Button>
                    <Button type="submit" class="h-11 flex-1" :disabled="props.processing">
                        {{ props.processing ? 'Menyimpan…' : 'Simpan' }}
                    </Button>
                </footer>
            </form>
        </SheetContent>
    </Sheet>
</template>
