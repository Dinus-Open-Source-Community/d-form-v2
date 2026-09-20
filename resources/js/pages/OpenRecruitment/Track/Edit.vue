<script setup lang="ts">
import { computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import FormFillLayout from '@/layouts/FormFillLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { routes } from '@/lib/routes'

defineOptions({ layout: FormFillLayout })

interface DivisionOption {
    id: string
    name: string
}

interface ApplicationFormData {
    full_name: string
    nim: string
    semester: number
    phone: string
    personal_email: string
    student_email: string
    instagram_username: string
    primary_division_id: string
    secondary_division_id: string | null
    portfolio_type: string
    portfolio_url: string | null
    cv_original_name: string | null
    portfolio_original_name: string | null
    instagram_follow_original_name: string | null
    twibbon_url: string | null
}

const props = defineProps<{
    application: ApplicationFormData
    divisions: DivisionOption[]
    updateUrl: string
    dashboardUrl: string
}>()

const form = useForm({
    full_name: props.application.full_name,
    nim: props.application.nim,
    semester: String(props.application.semester),
    phone: props.application.phone,
    personal_email: props.application.personal_email,
    student_email: props.application.student_email,
    instagram_username: props.application.instagram_username,
    primary_division_id: props.application.primary_division_id,
    secondary_division_id: props.application.secondary_division_id ?? '',
    portfolio_type: (props.application.portfolio_type as 'url' | 'file') ?? 'url',
    portfolio_url: props.application.portfolio_url ?? '',
    portfolio_file: null as File | null,
    cv: null as File | null,
    instagram_follow_proof: null as File | null,
    twibbon_url: props.application.twibbon_url ?? '',
})

const cvHint = computed(() =>
    props.application.cv_original_name
        ? `File saat ini: ${props.application.cv_original_name} (kosongkan jika tidak diganti)`
        : 'Unggah CV PDF',
)

const instagramFollowHint = computed(() =>
    props.application.instagram_follow_original_name
        ? `File saat ini: ${props.application.instagram_follow_original_name} (kosongkan jika tidak diganti)`
        : 'Unggah screenshot follow Instagram (jpg/jpeg/png/webp)',
)

function onCvChange(event: Event) {
    const target = event.target as HTMLInputElement
    form.cv = target.files?.[0] ?? null
}

function onPortfolioFileChange(event: Event) {
    const target = event.target as HTMLInputElement
    form.portfolio_file = target.files?.[0] ?? null
}

function onInstagramFollowChange(event: Event) {
    const target = event.target as HTMLInputElement
    form.instagram_follow_proof = target.files?.[0] ?? null
}

function submit() {
    form.put(props.updateUrl, {
        forceFormData: true,
        preserveScroll: true,
    })
}
</script>

<template>
    <Head title="Edit Pendaftaran OpRec" />

    <div class="mx-auto max-w-2xl px-2 pb-8">
        <div class="mb-6 space-y-3">
            <Button as-child variant="link" class="h-auto p-0 text-sm">
                <Link :href="dashboardUrl">← Kembali ke portal</Link>
            </Button>
            <div>
                <p class="text-primary text-xs font-semibold tracking-wide uppercase">Portal OpRec</p>
                <h1 class="text-xl font-bold tracking-tight">Perbarui pendaftaran</h1>
                <p class="text-muted-foreground text-sm">Perbaiki data sesuai instruksi tim.</p>
            </div>
        </div>

        <Card class="rounded-2xl border-border/70">
            <CardHeader>
                <CardTitle class="text-lg">Data pendaftaran</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-5" @submit.prevent="submit">
                    <p v-if="form.errors.application" class="text-destructive text-sm">
                        {{ form.errors.application }}
                    </p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="full_name">Nama lengkap</Label>
                            <Input id="full_name" v-model="form.full_name" required />
                        </div>

                        <div class="space-y-2">
                            <Label for="nim">NIM</Label>
                            <Input id="nim" v-model="form.nim" required />
                        </div>

                        <div class="space-y-2">
                            <Label for="semester">Semester</Label>
                            <select
                                id="semester"
                                v-model="form.semester"
                                required
                                class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            >
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label for="phone">Telepon</Label>
                            <Input id="phone" v-model="form.phone" required />
                        </div>

                        <div class="space-y-2">
                            <Label for="instagram_username">Instagram</Label>
                            <Input id="instagram_username" v-model="form.instagram_username" required />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="personal_email">Email pribadi</Label>
                            <Input id="personal_email" v-model="form.personal_email" type="email" required />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <Label for="student_email">Email kampus</Label>
                            <Input id="student_email" v-model="form.student_email" type="email" required />
                        </div>

                        <div class="space-y-2">
                            <Label for="primary_division_id">Divisi utama</Label>
                            <select
                                id="primary_division_id"
                                v-model="form.primary_division_id"
                                required
                                class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            >
                                <option v-for="division in divisions" :key="division.id" :value="division.id">
                                    {{ division.name }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <Label for="secondary_division_id">Divisi cadangan</Label>
                            <select
                                id="secondary_division_id"
                                v-model="form.secondary_division_id"
                                class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            >
                                <option value="">—</option>
                                <option v-for="division in divisions" :key="division.id" :value="division.id">
                                    {{ division.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="cv">CV (PDF)</Label>
                        <Input id="cv" type="file" accept="application/pdf" @change="onCvChange" />
                        <p class="text-muted-foreground text-xs">{{ cvHint }}</p>
                    </div>

                    <div class="space-y-3">
                        <Label>Portfolio</Label>
                        <div class="flex gap-4 text-sm">
                            <label class="flex items-center gap-2">
                                <input v-model="form.portfolio_type" type="radio" value="url" />
                                URL
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="form.portfolio_type" type="radio" value="file" />
                                File PDF
                            </label>
                        </div>
                        <Input
                            v-if="form.portfolio_type === 'url'"
                            v-model="form.portfolio_url"
                            placeholder="https://..."
                        />
                        <div v-else class="space-y-1">
                            <Input type="file" accept="application/pdf" @change="onPortfolioFileChange" />
                            <p
                                v-if="application.portfolio_original_name"
                                class="text-muted-foreground text-xs"
                            >
                                File saat ini: {{ application.portfolio_original_name }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="instagram_follow_proof">Bukti Follow Instagram</Label>
                        <Input
                            id="instagram_follow_proof"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            @change="onInstagramFollowChange"
                        />
                        <p class="text-muted-foreground text-xs">{{ instagramFollowHint }}</p>
                        <p v-if="form.errors.instagram_follow_proof" class="text-destructive text-sm">
                            {{ form.errors.instagram_follow_proof }}
                        </p>
                    </div>

                    <div class="space-y-2">
                        <Label for="twibbon_url">Link Bukti Twibbon</Label>
                        <Input
                            id="twibbon_url"
                            v-model="form.twibbon_url"
                            type="url"
                            placeholder="https://..."
                            required
                        />
                        <p v-if="form.errors.twibbon_url" class="text-destructive text-sm">
                            {{ form.errors.twibbon_url }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2">
                        <Button type="submit" :disabled="form.processing">Simpan perubahan</Button>
                        <Button as-child variant="outline">
                            <Link :href="dashboardUrl">Batal</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
