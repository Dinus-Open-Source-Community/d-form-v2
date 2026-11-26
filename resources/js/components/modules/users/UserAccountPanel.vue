<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import type { InertiaForm } from '@inertiajs/vue3';
import { Eye, EyeOff, Loader2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import UserAccountRoleCards from './UserAccountRoleCards.vue';
import type { TUserAccountPanelMode } from '@/hooks/useUserAccountPanel';
import type { IUserAccountForm, IUserRoleOption } from '@/types/user-management';

const props = defineProps<{
    isOpen: boolean;
    mode: TUserAccountPanelMode;
    /** Form Inertia milik pemanggil: panel hanya membaca nilai, error, dan status prosesnya. */
    form: InertiaForm<IUserAccountForm>;
    roleOptions: IUserRoleOption[];
}>();

const emit = defineEmits<{
    close: [];
    submit: [];
    'update:field': [field: keyof IUserAccountForm, value: string];
}>();

const PASSWORD_HELP_ID = 'user-account-password-help';
const PASSWORD_ERROR_ID = 'user-account-password-error';

const formElement = ref<HTMLFormElement | null>(null);
const isPasswordVisible = ref<boolean>(false);

const isCreate = computed<boolean>(() => props.mode === 'create');

const sheetOpen = computed<boolean>({
    get: () => props.isOpen,
    set: (value: boolean): void => {
        if (!value) emit('close');
    },
});

const panelTitle = computed<string>(() => (isCreate.value ? 'Buat akun baru' : 'Edit akun'));

const panelDescription = computed<string>(() =>
    isCreate.value ? 'Akun bisa langsung dipakai setelah disimpan.' : 'Ubah data atau role akses akun ini.'
);

const passwordLabel = computed<string>(() => (isCreate.value ? 'Password' : 'Password baru (opsional)'));

const passwordHelp = computed<string>(() =>
    isCreate.value ? 'Minimal 8 karakter.' : 'Kosongkan jika tidak ingin mengubah password.'
);

const confirmationLabel = computed<string>(() => (isCreate.value ? 'Ulangi password' : 'Ulangi password baru'));

const submitLabel = computed<string>(() => (isCreate.value ? 'Simpan akun' : 'Simpan perubahan'));

/** Helper selalu dibacakan; pesan error ditambahkan di depannya saat field bermasalah. */
const passwordDescribedBy = computed<string>(() =>
    props.form.errors.password ? `${PASSWORD_ERROR_ID} ${PASSWORD_HELP_ID}` : PASSWORD_HELP_ID
);

function updateField(field: keyof IUserAccountForm, value: string | number): void {
    emit('update:field', field, String(value));
}

watch(
    () => props.isOpen,
    (isOpen: boolean): void => {
        if (!isOpen) isPasswordVisible.value = false;
    }
);

/** Setelah server menolak (422), fokus pindah ke field bermasalah pertama agar terlihat. */
watch(
    () => props.form.errors,
    (): void => {
        if (!props.isOpen) return;

        void nextTick((): void => {
            const invalid = formElement.value?.querySelector('[aria-invalid="true"]');
            if (invalid instanceof HTMLElement) invalid.focus();
        });
    },
    { deep: true }
);
</script>

<template>
    <Sheet v-model:open="sheetOpen">
        <SheetContent
            side="right"
            data-testid="user-panel"
            overlay-class="bg-foreground/40"
            :aria-busy="form.processing ? 'true' : undefined"
            class="w-full gap-0 p-0 sm:max-w-[560px]"
        >
            <SheetHeader class="border-border shrink-0 gap-1 border-b px-4 py-4 pr-12 text-left sm:px-6">
                <SheetTitle data-testid="user-panel-title" class="font-display text-lg">{{ panelTitle }}</SheetTitle>
                <SheetDescription class="text-muted-foreground text-xs">{{ panelDescription }}</SheetDescription>
            </SheetHeader>

            <form ref="formElement" class="flex min-h-0 flex-1 flex-col" novalidate @submit.prevent="emit('submit')">
                <div class="min-h-0 flex-1 space-y-5 overflow-y-auto px-4 py-5 sm:px-6">
                    <p class="text-muted-foreground text-xs">
                        <span class="text-destructive" aria-hidden="true">*</span> Wajib diisi
                    </p>

                    <div class="space-y-1.5">
                        <Label for="user-account-name">
                            Nama lengkap <span class="text-destructive" aria-hidden="true">*</span>
                        </Label>
                        <Input
                            id="user-account-name"
                            data-testid="user-field-name"
                            :model-value="form.name"
                            autocomplete="name"
                            required
                            aria-required="true"
                            :aria-invalid="form.errors.name ? true : undefined"
                            :aria-describedby="form.errors.name ? 'user-account-name-error' : undefined"
                            @update:model-value="updateField('name', $event)"
                        />
                        <p
                            v-if="form.errors.name"
                            id="user-account-name-error"
                            role="alert"
                            class="text-destructive text-xs"
                        >
                            {{ form.errors.name }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="user-account-email">
                            Email <span class="text-destructive" aria-hidden="true">*</span>
                        </Label>
                        <Input
                            id="user-account-email"
                            data-testid="user-field-email"
                            :model-value="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            aria-required="true"
                            :aria-invalid="form.errors.email ? true : undefined"
                            :aria-describedby="form.errors.email ? 'user-account-email-error' : undefined"
                            @update:model-value="updateField('email', $event)"
                        />
                        <p
                            v-if="form.errors.email"
                            id="user-account-email-error"
                            role="alert"
                            class="text-destructive text-xs"
                        >
                            {{ form.errors.email }}
                        </p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="user-account-password">
                            {{ passwordLabel }}
                            <span v-if="isCreate" class="text-destructive" aria-hidden="true">*</span>
                        </Label>
                        <div class="relative">
                            <Input
                                id="user-account-password"
                                data-testid="user-field-password"
                                :model-value="form.password"
                                :type="isPasswordVisible ? 'text' : 'password'"
                                autocomplete="new-password"
                                :required="isCreate"
                                :aria-required="isCreate ? 'true' : undefined"
                                class="pr-11"
                                :aria-invalid="form.errors.password ? true : undefined"
                                :aria-describedby="passwordDescribedBy"
                                @update:model-value="updateField('password', $event)"
                            />
                            <button
                                type="button"
                                class="text-muted-foreground hover:text-foreground absolute inset-y-0 right-0 flex w-11 items-center justify-center rounded-r-lg transition-colors"
                                :aria-label="isPasswordVisible ? 'Sembunyikan password' : 'Tampilkan password'"
                                :aria-pressed="isPasswordVisible"
                                @click="isPasswordVisible = !isPasswordVisible"
                            >
                                <EyeOff v-if="isPasswordVisible" class="size-4" aria-hidden="true" />
                                <Eye v-else class="size-4" aria-hidden="true" />
                            </button>
                        </div>
                        <p
                            v-if="form.errors.password"
                            :id="PASSWORD_ERROR_ID"
                            role="alert"
                            class="text-destructive text-xs"
                        >
                            {{ form.errors.password }}
                        </p>
                        <p :id="PASSWORD_HELP_ID" class="text-muted-foreground text-xs">{{ passwordHelp }}</p>
                    </div>

                    <div class="space-y-1.5">
                        <Label for="user-account-password-confirmation">
                            {{ confirmationLabel }}
                            <span v-if="isCreate" class="text-destructive" aria-hidden="true">*</span>
                        </Label>
                        <Input
                            id="user-account-password-confirmation"
                            data-testid="user-field-password-confirmation"
                            :model-value="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            :required="isCreate"
                            :aria-required="isCreate ? 'true' : undefined"
                            :aria-invalid="form.errors.password_confirmation ? true : undefined"
                            :aria-describedby="
                                form.errors.password_confirmation
                                    ? 'user-account-password-confirmation-error'
                                    : undefined
                            "
                            @update:model-value="updateField('password_confirmation', $event)"
                        />
                        <p
                            v-if="form.errors.password_confirmation"
                            id="user-account-password-confirmation-error"
                            role="alert"
                            class="text-destructive text-xs"
                        >
                            {{ form.errors.password_confirmation }}
                        </p>
                    </div>

                    <UserAccountRoleCards
                        :model-value="form.role"
                        :options="roleOptions"
                        :error="form.errors.role"
                        @update:model-value="updateField('role', $event)"
                    />
                </div>

                <div
                    class="border-border bg-card sticky bottom-0 flex shrink-0 items-center justify-end gap-2 border-t px-4 py-3 sm:px-6"
                >
                    <Button type="button" variant="outline" data-testid="user-panel-cancel" @click="emit('close')">
                        Batal
                    </Button>
                    <Button type="submit" data-testid="user-panel-submit" :disabled="form.processing">
                        <Loader2 v-if="form.processing" class="size-4 animate-spin" aria-hidden="true" />
                        {{ form.processing ? 'Menyimpan...' : submitLabel }}
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>
