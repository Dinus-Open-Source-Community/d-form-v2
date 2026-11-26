<script setup lang="ts">
import { computed, toRef } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Check, Loader2, X } from 'lucide-vue-next';
import { AuthField } from '@/components/core/field';
import { AuthSubmitButton } from '@/components/core/button';
import PasswordMatchHint from '@/components/core/PasswordMatchHint.vue';
import PasswordStrengthMeter from '@/components/core/PasswordStrengthMeter.vue';
import { store as register } from '@/actions/App/Http/Controllers/Auth/RegisterController';
import { getFieldError } from '@/lib/error-message';
import { PASSWORD_STRENGTH_HINT, passwordSubmitBlockerMessage } from '@/lib/passwordStrength';
import { useToastNotifications } from '@/hooks/useToastNotifications';
import { useEmailAvailability } from '@/hooks/useEmailAvailability';

const props = defineProps<{ isActive: boolean }>();

const { handleInertiaFormErrors, showErrorToast } = useToastNotifications();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
}).dontRemember('password', 'password_confirmation');

/** Hanya jalankan effect ketersediaan email saat tab Daftar benar-benar aktif. */
const isEffectEnabled = computed<boolean>(() => props.isActive);

/** Status ketersediaan email: dicek otomatis saat mengetik, tanpa submit. */
const { status: emailStatus, message: emailMessage } = useEmailAvailability({
    email: toRef(form, 'email'),
    enabled: isEffectEnabled,
});

function submit(): void {
    const blockerMessage = passwordSubmitBlockerMessage(form.password, form.password_confirmation);
    if (blockerMessage !== null) {
        showErrorToast(blockerMessage);
        return;
    }

    form.submit(register(), {
        onError: (errors) => {
            handleInertiaFormErrors(errors, { title: 'Gagal mendaftar' });
        },
    });
}
</script>

<template>
    <div>
        <div class="mb-6">
            <h1
                class="font-display text-foreground text-[2rem] leading-tight font-bold tracking-[-0.035em] sm:text-4xl"
            >
                Buat akun
            </h1>
            <p class="text-muted-foreground mt-2.5 text-sm">Siapkan akun untuk mulai membuat formulir.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <AuthField
                label="Name"
                type="name"
                :error="getFieldError(form.errors, 'name')"
                id="register-name"
                v-model="form.name"
                required
                autocomplete="name"
                placeholder="Your name"
                :focus="true"
            />
            <AuthField
                label="Email"
                type="email"
                :error="getFieldError(form.errors, 'email')"
                id="register-email"
                v-model="form.email"
                required
                autocomplete="email"
                placeholder="you@example.com"
                :focus="false"
            >
                <template #trailing>
                    <span
                        v-if="emailStatus === 'checking' || emailStatus === 'available' || emailStatus === 'taken'"
                        :data-email-availability="emailStatus"
                        class="flex size-4 items-center justify-center"
                    >
                        <Loader2
                            v-if="emailStatus === 'checking'"
                            class="text-muted-foreground size-4 animate-spin"
                            aria-hidden="true"
                        />
                        <Check
                            v-else-if="emailStatus === 'available'"
                            class="text-success size-4"
                            :stroke-width="3"
                            aria-hidden="true"
                        />
                        <X v-else class="text-destructive size-4" :stroke-width="3" aria-hidden="true" />
                    </span>
                </template>
            </AuthField>

            <!-- Live region sr-only: pengumuman status tanpa menambah tinggi layout. -->
            <div class="sr-only" aria-live="polite" aria-atomic="true">
                <span>{{ emailMessage }}</span>
            </div>
            <AuthField
                label="Password"
                type="password"
                :error="getFieldError(form.errors, 'password')"
                id="register-password"
                v-model="form.password"
                required
                autocomplete="new-password"
                placeholder="••••••••"
                :focus="false"
            />

            <!-- Indikator kekuatan selalu tampil: 4 segmen terisi kiri→kanan + satu baris aturan. -->
            <PasswordStrengthMeter
                :password="form.password"
                hint-id="register-password-hint"
                :hint="PASSWORD_STRENGTH_HINT"
            />

            <div class="space-y-1.5">
                <AuthField
                    label="Confirm password"
                    type="password"
                    :error="getFieldError(form.errors, 'password_confirmation')"
                    id="register-password-confirmation"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="••••••••"
                    :focus="false"
                />

                <PasswordMatchHint :password="form.password" :confirmation="form.password_confirmation" />
            </div>

            <AuthSubmitButton :form="form">
                <template #processing>Membuat akun</template>
                Buat akun
            </AuthSubmitButton>
        </form>
    </div>
</template>
