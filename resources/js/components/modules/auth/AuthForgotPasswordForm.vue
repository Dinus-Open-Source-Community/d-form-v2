<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import { ref } from 'vue'
import { AuthField } from '@/components/core/field'
import { Button } from '@/components/ui/button'
import { Spinner } from '@/components/ui/spinner'
import { index as loginPage } from '@/actions/App/Http/Controllers/Auth/LoginController'
import { toast } from 'vue-sonner'
import { routes } from '@/lib/routes'
import {
    getFieldError,
    handleInertiaFormErrors,
    humanizeErrorMessage,
    showErrorToast,
} from '@/lib/error-message'

const PASSWORD_RESET_LINK_URL = routes.auth.passwordResetLink

const email = ref('')
const emailError = ref<string | undefined>()
const processing = ref(false)

async function submit(): Promise<void> {
    if (processing.value) {
        return
    }

    emailError.value = undefined
    processing.value = true

    try {
        const { data } = await axios.post<{ message: string }>(PASSWORD_RESET_LINK_URL, {
            email: email.value,
        })
        toast.success(humanizeErrorMessage(data.message))
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const errors = error.response.data?.errors as Record<string, string[]> | undefined
            if (errors) {
                handleInertiaFormErrors(errors, { title: 'Gagal mengirim tautan reset' })
                emailError.value = getFieldError(errors, 'email')
            }
            return
        }

        showErrorToast('Unable to send reset link. Please try again.')
    } finally {
        processing.value = false
    }
}
</script>

<template>
    <div>
        <div class="mb-8">
            <h1 class="font-display text-[2rem] leading-tight font-bold tracking-[-0.035em] text-foreground sm:text-4xl">
                Lupa kata sandi?
            </h1>
            <p class="mt-2.5 text-sm text-muted-foreground">
                Masukkan email Anda. Kami kirim tautan pengaturan ulang bila akun terdaftar.
            </p>
        </div>

        <form @submit.prevent="submit" class="space-y-5">
            <AuthField
                type="email"
                :error="emailError"
                label="Email"
                id="forgot-email"
                v-model="email"
                required
                autocomplete="email"
                placeholder="you@example.com"
                :focus="true"
            />

            <Button size="lg" class="w-full disabled:cursor-not-allowed" :disabled="processing">
                <span v-if="processing" class="flex items-center gap-2">
                    <Spinner />
                    Mengirim tautan
                </span>
                <span v-else>Kirim tautan reset</span>
            </Button>

            <p class="mt-2 text-center text-sm text-muted-foreground">
                Ingat kata sandi Anda?
                <Link
                    :href="loginPage()"
                    class="font-semibold text-primary underline-offset-4 transition-colors hover:underline"
                >
                    Masuk
                </Link>
            </p>
        </form>
    </div>
</template>
