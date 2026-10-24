<script setup lang="ts">
import { computed, toRef } from 'vue'
import { useForm } from '@inertiajs/vue3'
import { Check, Loader2, X } from 'lucide-vue-next'
import { AuthField } from '@/components/core/field'
import { AuthSubmitButton } from '@/components/core/button'
import { store as register } from '@/actions/App/Http/Controllers/Auth/RegisterController'
import { getFieldError, handleInertiaFormErrors, showErrorToast } from '@/lib/error-message'
import { useEmailAvailability } from '@/hooks/useEmailAvailability'
import type { PasswordRule } from '@/types/auth'

/** Jumlah segmen indikator kekuatan; level 0–4 dipetakan ke segmen terisi. */
const STRENGTH_SEGMENT_TOTAL = 4

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
}).dontRemember('password', 'password_confirmation')

/** Status ketersediaan email: dicek otomatis saat mengetik, tanpa submit. */
const { status: emailStatus, message: emailMessage } = useEmailAvailability({
    email: toRef(form, 'email'),
})

const rules = computed<PasswordRule[]>(() => [
    { label: 'Minimal 8 karakter', met: form.password.length >= 8 },
    { label: 'Huruf besar', met: /[A-Z]/.test(form.password) },
    { label: 'Angka', met: /\d/.test(form.password) },
])

/** Jumlah kriteria yang sudah terpenuhi. */
const metRuleCount = computed<number>(() => rules.value.filter((rule) => rule.met).length)

/** Segmen terisi: kosong = 0, sudah mengetik = 1, lalu +1 untuk tiap kriteria (maksimal 4). */
const filledSegmentCount = computed<number>(() => {
    if (form.password.length === 0) return 0
    return Math.min(STRENGTH_SEGMENT_TOTAL, metRuleCount.value + 1)
})

/** Indikator kecocokan hanya muncul setelah kolom konfirmasi diisi. */
const isConfirmationFilled = computed<boolean>(() => form.password_confirmation.length > 0)

const doPasswordsMatch = computed<boolean>(() => form.password === form.password_confirmation)

function submit(): void {
    if (form.password.length < 8) {
        showErrorToast('Password must be at least 8 characters.')
        return
    }
    if (form.password !== form.password_confirmation) {
        showErrorToast('Password does not match')
        return
    }

    form.submit(register(), {
        onError: (errors) => {
            handleInertiaFormErrors(errors, { title: 'Gagal mendaftar' })
        },
    })
}
</script>

<template>
    <div>
        <div class="mb-6">
            <h1 class="font-display text-[2rem] leading-tight font-bold tracking-[-0.035em] text-foreground sm:text-4xl">
                Buat akun
            </h1>
            <p class="mt-2.5 text-sm text-muted-foreground">Siapkan akun untuk mulai membuat formulir.</p>
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
                            class="size-4 animate-spin text-muted-foreground"
                            aria-hidden="true"
                        />
                        <Check
                            v-else-if="emailStatus === 'available'"
                            class="size-4 text-success"
                            :stroke-width="3"
                            aria-hidden="true"
                        />
                        <X v-else class="size-4 text-destructive" :stroke-width="3" aria-hidden="true" />
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
            <div class="space-y-2">
                <div
                    class="flex gap-1.5"
                    role="progressbar"
                    aria-label="Kekuatan kata sandi"
                    aria-describedby="register-password-hint"
                    aria-valuemin="0"
                    :aria-valuemax="STRENGTH_SEGMENT_TOTAL"
                    :aria-valuenow="filledSegmentCount"
                >
                    <span
                        v-for="segment in STRENGTH_SEGMENT_TOTAL"
                        :key="segment"
                        class="h-1 flex-1 rounded-full transition-colors duration-300 ease-out"
                        :class="segment <= filledSegmentCount ? 'bg-success' : 'bg-muted'"
                    />
                </div>
                <p id="register-password-hint" class="text-xs text-muted-foreground">
                    Minimal 8 karakter dengan huruf besar dan angka.
                </p>
            </div>

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

                <!-- Live region selalu ada agar perubahan status kecocokan diumumkan screen reader. -->
                <div class="min-h-5" aria-live="polite" aria-atomic="true">
                    <p
                        v-if="isConfirmationFilled"
                        class="flex items-center gap-1.5 text-[11px] font-medium"
                        :class="doPasswordsMatch ? 'text-success' : 'text-destructive'"
                    >
                        <Check v-if="doPasswordsMatch" class="size-3.5 shrink-0" :stroke-width="3" aria-hidden="true" />
                        <X v-else class="size-3.5 shrink-0" :stroke-width="3" aria-hidden="true" />
                        {{ doPasswordsMatch ? 'Kata sandi cocok' : 'Kata sandi belum cocok' }}
                    </p>
                </div>
            </div>

            <AuthSubmitButton :form="form">
                <template #processing>Membuat akun</template>
                Buat akun
            </AuthSubmitButton>
        </form>
    </div>
</template>
