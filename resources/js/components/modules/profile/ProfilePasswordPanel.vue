<script setup lang="ts">
import { computed } from 'vue';
import { Eye, EyeOff } from 'lucide-vue-next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import PasswordMatchHint from '@/components/core/PasswordMatchHint.vue';
import PasswordStrengthMeter from '@/components/core/PasswordStrengthMeter.vue';
import { getFieldError } from '@/lib/error-message';
import { PASSWORD_STRENGTH_HINT } from '@/lib/passwordStrength';
import type { IProfilePasswordForm, TProfilePasswordField } from '@/types/profile';

/**
 * Isi bagian Keamanan. Kata sandi saat ini hanya diminta bila akun memang punya
 * kata sandi lokal; akun yang masuk lewat Google atau GitHub langsung membuat
 * kata sandi baru. Tiap field punya tombol mata sendiri di dalam input, sehingga
 * membuka satu field tidak ikut membuka field lain. Validasi kekuatan dan
 * kecocokan memakai komponen yang sama dengan halaman daftar.
 */
const props = defineProps<{
    password: IProfilePasswordForm;
}>();

/** Id hint indikator kekuatan; juga menjadi deskripsi input kata sandi baru. */
const NEW_PASSWORD_HINT_ID = 'profile-new-password-hint';

/** Nama aksesibel tombol mata per field; `shown` dipakai saat kata sandi sedang tampil. */
const VISIBILITY_LABELS: Record<TProfilePasswordField, { shown: string; hidden: string }> = {
    current: { shown: 'Sembunyikan kata sandi saat ini', hidden: 'Tampilkan kata sandi saat ini' },
    next: { shown: 'Sembunyikan kata sandi baru', hidden: 'Tampilkan kata sandi baru' },
    confirm: { shown: 'Sembunyikan konfirmasi kata sandi', hidden: 'Tampilkan konfirmasi kata sandi' },
};

const form = props.password.form;
const visibility = props.password.visibility;
const errors = computed<Record<string, string>>(() => ({
    current_password: getFieldError(form.errors, 'current_password') ?? '',
    password: getFieldError(form.errors, 'password') ?? '',
    password_confirmation: getFieldError(form.errors, 'password_confirmation') ?? '',
}));
const hasLocalPassword = computed<boolean>(() => props.password.hasLocalPassword.value);

/** Hint kekuatan selalu dideskripsikan; pesan error server ditambahkan bila ada. */
const newPasswordDescribedBy = computed<string>(() =>
    errors.value.password === '' ? NEW_PASSWORD_HINT_ID : `${NEW_PASSWORD_HINT_ID} new-password-error`
);

function isVisible(field: TProfilePasswordField): boolean {
    return visibility[field].value;
}

function inputType(field: TProfilePasswordField): 'text' | 'password' {
    return isVisible(field) ? 'text' : 'password';
}

function visibilityLabel(field: TProfilePasswordField): string {
    const labels = VISIBILITY_LABELS[field];
    return isVisible(field) ? labels.shown : labels.hidden;
}

/**
 * Enter di dalam field ikut men-submit karena form punya tombol simpan sebagai
 * default button (footer dirender di luar form dengan atribut `form`). Submit
 * tanpa perubahan atau saat request berjalan tidak melakukan apa pun, sama
 * seperti tombol yang nonaktif.
 */
function handleSubmit(): void {
    if (form.processing || !props.password.isDirty.value) return;

    props.password.submit();
}
</script>

<template>
    <form id="profile-password-form" class="grid min-w-0 gap-5" @submit.prevent="handleSubmit">
        <div v-if="hasLocalPassword" class="grid min-w-0 gap-2">
            <Label for="current_password">Kata sandi saat ini</Label>
            <div class="relative">
                <Input
                    id="current_password"
                    v-model="form.current_password"
                    data-testid="profile-field-current-password"
                    :type="inputType('current')"
                    autocomplete="current-password"
                    placeholder="Kata sandi saat ini"
                    class="h-[44px] pr-12"
                    :aria-invalid="errors.current_password !== ''"
                    :aria-describedby="errors.current_password === '' ? undefined : 'current-password-error'"
                />
                <button
                    type="button"
                    class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute inset-y-0 right-0 flex w-[44px] items-center justify-center rounded-r-lg transition-colors focus-visible:ring-2 focus-visible:outline-none"
                    :aria-label="visibilityLabel('current')"
                    :aria-pressed="isVisible('current')"
                    @click="props.password.toggleVisibility('current')"
                >
                    <EyeOff v-if="isVisible('current')" class="size-4" aria-hidden="true" />
                    <Eye v-else class="size-4" aria-hidden="true" />
                </button>
            </div>
            <p v-if="errors.current_password" id="current-password-error" role="alert" class="text-destructive text-sm">
                {{ errors.current_password }}
            </p>
        </div>

        <p v-else class="text-muted-foreground text-sm">
            Akun ini belum punya kata sandi. Buat kata sandi untuk bisa masuk tanpa Google atau GitHub.
        </p>

        <div class="grid min-w-0 gap-5 @min-[640px]:grid-cols-2">
            <div class="grid min-w-0 gap-2">
                <Label for="new_password">{{ hasLocalPassword ? 'Kata sandi baru' : 'Kata sandi' }}</Label>
                <div class="relative">
                    <Input
                        id="new_password"
                        v-model="form.password"
                        data-testid="profile-field-new-password"
                        :type="inputType('next')"
                        autocomplete="new-password"
                        placeholder="Minimal 8 karakter"
                        class="h-[44px] pr-12"
                        :aria-invalid="errors.password !== ''"
                        :aria-describedby="newPasswordDescribedBy"
                    />
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute inset-y-0 right-0 flex w-[44px] items-center justify-center rounded-r-lg transition-colors focus-visible:ring-2 focus-visible:outline-none"
                        :aria-label="visibilityLabel('next')"
                        :aria-pressed="isVisible('next')"
                        @click="props.password.toggleVisibility('next')"
                    >
                        <EyeOff v-if="isVisible('next')" class="size-4" aria-hidden="true" />
                        <Eye v-else class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <p v-if="errors.password" id="new-password-error" role="alert" class="text-destructive text-sm">
                    {{ errors.password }}
                </p>
                <PasswordStrengthMeter
                    :password="form.password"
                    :hint-id="NEW_PASSWORD_HINT_ID"
                    :hint="PASSWORD_STRENGTH_HINT"
                />
            </div>

            <div class="grid min-w-0 gap-2">
                <Label for="confirm_password">Konfirmasi kata sandi</Label>
                <div class="relative">
                    <Input
                        id="confirm_password"
                        v-model="form.password_confirmation"
                        data-testid="profile-field-confirm-password"
                        :type="inputType('confirm')"
                        autocomplete="new-password"
                        placeholder="Ulangi kata sandi"
                        class="h-[44px] pr-12"
                        :aria-invalid="errors.password_confirmation !== ''"
                        :aria-describedby="errors.password_confirmation === '' ? undefined : 'confirm-password-error'"
                    />
                    <button
                        type="button"
                        class="text-muted-foreground hover:text-foreground focus-visible:ring-ring absolute inset-y-0 right-0 flex w-[44px] items-center justify-center rounded-r-lg transition-colors focus-visible:ring-2 focus-visible:outline-none"
                        :aria-label="visibilityLabel('confirm')"
                        :aria-pressed="isVisible('confirm')"
                        @click="props.password.toggleVisibility('confirm')"
                    >
                        <EyeOff v-if="isVisible('confirm')" class="size-4" aria-hidden="true" />
                        <Eye v-else class="size-4" aria-hidden="true" />
                    </button>
                </div>
                <p
                    v-if="errors.password_confirmation"
                    id="confirm-password-error"
                    role="alert"
                    class="text-destructive text-sm"
                >
                    {{ errors.password_confirmation }}
                </p>
                <PasswordMatchHint :password="form.password" :confirmation="form.password_confirmation" />
            </div>
        </div>
    </form>
</template>
