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

interface PeriodSummary {
    id: string
    name: string
    status_label: string
}

interface DivisionOption {
    id: string
    name: string
}

const props = defineProps<{
    period: PeriodSummary | null
    registration: {
        is_open: boolean
        message: string
    }
    divisions: DivisionOption[]
    submitUrl: string
}>()

const form = useForm({
    full_name: '',
    nim: '',
    semester: '',
    phone: '',
    personal_email: '',
    student_email: '',
    instagram_username: '',
    primary_division_id: '',
    secondary_division_id: '',
    portfolio_type: 'url' as 'url' | 'file',
    portfolio_url: '',
    portfolio_file: null as File | null,
    cv: null as File | null,
})

const isBlocked = computed(() => !props.registration.is_open)

function onCvChange(event: Event) {
    const target = event.target as HTMLInputElement
    form.cv = target.files?.[0] ?? null
}

function onPortfolioFileChange(event: Event) {
    const target = event.target as HTMLInputElement
    form.portfolio_file = target.files?.[0] ?? null
}

function submit() {
    form.post(props.submitUrl, {
        forceFormData: true,
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="`Daftar — ${period?.name ?? 'OpenRecruitment'}`" />

    <div class="mx-auto max-w-2xl px-2">
        <div class="mb-6 space-y-2 text-center">
            <p class="text-primary text-xs font-semibold tracking-wide uppercase">OpenRecruitment DOSCOM</p>
            <h1 class="text-2xl font-bold tracking-tight">Formulir Pendaftaran</h1>
            <p v-if="period" class="text-muted-foreground text-sm">
                Periode: {{ period.name }} · {{ period.status_label }}
            </p>
            <p class="text-muted-foreground mx-auto max-w-md text-xs leading-relaxed">
                Isi sekali — setelah submit, pantau progress lewat
                <Link :href="routes.openRecruitment.track.login" class="text-primary underline-offset-2 hover:underline">
                    portal tracking
                </Link>
                dengan nomor pendaftaran & token email.
            </p>
        </div>

        <Card v-if="isBlocked" class="rounded-2xl border-dashed border-border/70">
            <CardContent class="space-y-4 p-6 text-center">
                <p class="font-medium">Pendaftaran belum tersedia</p>
                <p class="text-muted-foreground text-sm">{{ registration.message }}</p>
                <Button as-child variant="outline">
                    <Link :href="routes.openRecruitment.landing">Kembali ke landing</Link>
                </Button>
            </CardContent>
        </Card>

        <Card v-else class="rounded-2xl border-border/70">
            <CardHeader>
                <CardTitle class="text-lg">Data pribadi & preferensi divisi</CardTitle>
            </CardHeader>
            <CardContent>
                <form class="space-y-5" @submit.prevent="submit">
                    <p v-if="form.errors.period" class="text-destructive text-sm">{{ form.errors.period }}</p>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="full_name">Nama lengkap</Label>
                            <Input id="full_name" v-model="form.full_name" required />
                            <p v-if="form.errors.full_name" class="text-destructive text-xs">{{ form.errors.full_name }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="nim">NIM</Label>
                            <Input id="nim" v-model="form.nim" required />
                            <p v-if="form.errors.nim" class="text-destructive text-xs">{{ form.errors.nim }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="semester">Semester</Label>
                            <select
                                id="semester"
                                v-model="form.semester"
                                required
                                class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            >
                                <option disabled value="">Pilih semester</option>
                                <option value="1">1</option>
                                <option value="2">2</option>
                                <option value="3">3</option>
                            </select>
                            <p v-if="form.errors.semester" class="text-destructive text-xs">{{ form.errors.semester }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="phone">Nomor telepon</Label>
                            <Input id="phone" v-model="form.phone" placeholder="081234567890" required />
                            <p v-if="form.errors.phone" class="text-destructive text-xs">{{ form.errors.phone }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="instagram_username">Instagram</Label>
                            <Input id="instagram_username" v-model="form.instagram_username" placeholder="username" required />
                            <p v-if="form.errors.instagram_username" class="text-destructive text-xs">
                                {{ form.errors.instagram_username }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="personal_email">Email pribadi</Label>
                            <Input id="personal_email" v-model="form.personal_email" type="email" required />
                            <p v-if="form.errors.personal_email" class="text-destructive text-xs">
                                {{ form.errors.personal_email }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="student_email">Email kampus</Label>
                            <Input id="student_email" v-model="form.student_email" type="email" required />
                            <p v-if="form.errors.student_email" class="text-destructive text-xs">
                                {{ form.errors.student_email }}
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="primary_division_id">Divisi utama</Label>
                            <select
                                id="primary_division_id"
                                v-model="form.primary_division_id"
                                required
                                class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            >
                                <option disabled value="">Pilih divisi</option>
                                <option v-for="division in divisions" :key="division.id" :value="division.id">
                                    {{ division.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.primary_division_id" class="text-destructive text-xs">
                                {{ form.errors.primary_division_id }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="secondary_division_id">Divisi cadangan (opsional)</Label>
                            <select
                                id="secondary_division_id"
                                v-model="form.secondary_division_id"
                                class="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                            >
                                <option value="">— Tidak ada —</option>
                                <option v-for="division in divisions" :key="division.id" :value="division.id">
                                    {{ division.name }}
                                </option>
                            </select>
                            <p v-if="form.errors.secondary_division_id" class="text-destructive text-xs">
                                {{ form.errors.secondary_division_id }}
                            </p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <Label for="cv">CV (PDF, maks. 5 MB)</Label>
                        <Input id="cv" type="file" accept="application/pdf,.pdf" required @change="onCvChange" />
                        <p v-if="form.errors.cv" class="text-destructive text-xs">{{ form.errors.cv }}</p>
                    </div>

                    <div class="space-y-3">
                        <Label>Portfolio</Label>
                        <div class="flex flex-wrap gap-4 text-sm">
                            <label class="flex items-center gap-2">
                                <input v-model="form.portfolio_type" type="radio" value="url" />
                                URL
                            </label>
                            <label class="flex items-center gap-2">
                                <input v-model="form.portfolio_type" type="radio" value="file" />
                                File PDF
                            </label>
                        </div>

                        <div v-if="form.portfolio_type === 'url'" class="space-y-2">
                            <Input v-model="form.portfolio_url" type="url" placeholder="https://..." />
                            <p v-if="form.errors.portfolio_url" class="text-destructive text-xs">
                                {{ form.errors.portfolio_url }}
                            </p>
                        </div>

                        <div v-else class="space-y-2">
                            <Input type="file" accept="application/pdf,.pdf" @change="onPortfolioFileChange" />
                            <p v-if="form.errors.portfolio_file" class="text-destructive text-xs">
                                {{ form.errors.portfolio_file }}
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3 pt-2">
                        <Button type="submit" :disabled="form.processing">
                            {{ form.processing ? 'Mengirim...' : 'Kirim pendaftaran' }}
                        </Button>
                        <Button as-child variant="outline" type="button">
                            <Link :href="routes.openRecruitment.landing">Batal</Link>
                        </Button>
                    </div>
                </form>
            </CardContent>
        </Card>
    </div>
</template>
