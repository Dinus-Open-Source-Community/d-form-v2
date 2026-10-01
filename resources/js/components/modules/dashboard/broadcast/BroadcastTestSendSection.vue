<script setup lang="ts">
import axios from 'axios'
import { ref } from 'vue'
import { toast } from 'vue-sonner'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Spinner } from '@/components/ui/spinner'
import { BROADCAST_BASE_PATH } from '@/lib/broadcastHub'
import {
    getFieldError,
    handleInertiaFormErrors,
    showErrorToast,
    showHttpErrorToast,
    type ValidationErrors,
} from '@/lib/error-message'
import { FlaskConical } from 'lucide-vue-next'

const props = defineProps<{ broadcastId: string }>()

const email = ref('')
const emailError = ref<string | undefined>()
const sending = ref(false)

/** Saring body error tak dikenal menjadi ValidationErrors tanpa cast. */
function toValidationErrors(body: unknown): ValidationErrors | null {
    if (!body || typeof body !== 'object' || !('errors' in body)) return null
    const raw: unknown = body.errors
    if (!raw || typeof raw !== 'object') return null
    const out: ValidationErrors = {}
    for (const [key, value] of Object.entries(raw)) {
        if (typeof value === 'string') {
            out[key] = value
        } else if (Array.isArray(value)) {
            const strings = value.filter((item): item is string => typeof item === 'string')
            if (strings.length > 0 && strings.length === value.length) out[key] = strings
        }
    }
    return Object.keys(out).length > 0 ? out : null
}

async function submitTest(): Promise<void> {
    if (sending.value) return
    const target = email.value.trim()
    if (!target) {
        handleInertiaFormErrors({ email: 'Email wajib diisi.' }, { title: 'Email uji belum valid' })
        emailError.value = 'Email wajib diisi.'
        return
    }
    emailError.value = undefined
    sending.value = true
    try {
        const { data } = await axios.post<{ sent: boolean; email: string }>(
            `${BROADCAST_BASE_PATH}/${props.broadcastId}/test`,
            { email: target },
            { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } },
        )
        toast.success(`Email uji terkirim ke ${data.email}.`)
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const errors = toValidationErrors(error.response.data)
            if (errors) {
                handleInertiaFormErrors(errors, { title: 'Email uji tidak valid' })
                emailError.value = getFieldError(errors, 'email')
            } else {
                showErrorToast('Email uji tidak valid. Periksa alamat email.')
            }
            return
        }
        if (axios.isAxiosError(error) && error.response) {
            showHttpErrorToast(error.response.status, error.response.data)
            return
        }
        showErrorToast('Gagal mengirim email uji. Coba lagi.')
    } finally {
        sending.value = false
    }
}
</script>

<template>
    <Card class="rounded-2xl border-border/70">
        <CardHeader class="pb-2">
            <CardTitle class="flex items-center gap-2 text-base">
                <FlaskConical class="size-4 text-muted-foreground" aria-hidden="true" />
                Email uji
            </CardTitle>
            <p class="text-xs text-muted-foreground">
                Kirim satu email percobaan (lampiran ikut) — tidak tercatat di tracking.
            </p>
        </CardHeader>
        <CardContent>
            <form class="flex flex-col gap-3 sm:flex-row sm:items-start" @submit.prevent="submitTest">
                <div class="min-w-0 flex-1 space-y-2">
                    <Label for="broadcast-test-email" class="sr-only">Email tujuan uji</Label>
                    <Input
                        id="broadcast-test-email"
                        v-model="email"
                        type="email"
                        inputmode="email"
                        autocomplete="email"
                        placeholder="nama@contoh.id"
                        :aria-invalid="emailError ? true : undefined"
                        :disabled="sending"
                    />
                    <p v-if="emailError" role="alert" class="text-xs text-destructive">{{ emailError }}</p>
                </div>
                <Button type="submit" size="sm" :disabled="sending" class="shrink-0">
                    <span v-if="sending" class="flex items-center gap-2">
                        <Spinner />
                        Mengirim…
                    </span>
                    <span v-else>Kirim email uji</span>
                </Button>
            </form>
        </CardContent>
    </Card>
</template>
