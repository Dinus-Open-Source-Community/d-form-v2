<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import FormFillLayout from '@/layouts/FormFillLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { SimpleSelect, type SimpleSelectOption } from '@/components/ui/simple-select';
import { Separator } from '@/components/ui/separator';
import { ArrowLeft } from 'lucide-vue-next';
import { routes } from '@/lib/routes';

defineOptions({ layout: FormFillLayout });

interface DivisionOption {
    id: string;
    name: string;
}

interface ApplicationFormData {
    full_name: string;
    nim: string;
    semester: number;
    phone: string;
    personal_email: string;
    student_email: string;
    instagram_username: string;
    primary_division_id: string;
    secondary_division_id: string | null;
    portfolio_type: string;
    portfolio_url: string | null;
    cv_original_name: string | null;
    portfolio_original_name: string | null;
    instagram_follow_original_name: string | null;
    twibbon_url: string | null;
}

const props = defineProps<{
    application: ApplicationFormData;
    divisions: DivisionOption[];
    updateUrl: string;
    dashboardUrl: string;
}>();

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
    portfolio_type: (props.application.portfolio_type as 'url' | 'file' | 'none') || 'none',
    portfolio_url: props.application.portfolio_url ?? '',
    portfolio_file: null as File | null,
    cv: null as File | null,
    instagram_follow_proof: null as File | null,
    twibbon_url: props.application.twibbon_url ?? '',
});

const semesterOptions: SimpleSelectOption[] = [
    { value: '1', label: 'Semester 1' },
    { value: '3', label: 'Semester 3' },
];

const divisionOptions = computed<SimpleSelectOption[]>(() =>
    props.divisions.map((division: DivisionOption): SimpleSelectOption => ({
        value: division.id,
        label: division.name,
    }))
);

const secondaryDivisionOptions = computed<SimpleSelectOption[]>(() => [
    { value: '', label: 'Tidak ada' },
    ...divisionOptions.value,
]);

const cvHint = computed(() =>
    props.application.cv_original_name
        ? `File saat ini: ${props.application.cv_original_name} (kosongkan jika tidak diganti)`
        : 'Unggah CV PDF'
);

const instagramFollowHint = computed(() =>
    props.application.instagram_follow_original_name
        ? `File saat ini: ${props.application.instagram_follow_original_name} (kosongkan jika tidak diganti)`
        : 'Unggah screenshot follow Instagram (jpg/jpeg/png/webp)'
);

function onCvChange(event: Event) {
    const target = event.target as HTMLInputElement;
    form.cv = target.files?.[0] ?? null;
}

function onPortfolioFileChange(event: Event) {
    const target = event.target as HTMLInputElement;
    form.portfolio_file = target.files?.[0] ?? null;
}

function onInstagramFollowChange(event: Event) {
    const target = event.target as HTMLInputElement;
    form.instagram_follow_proof = target.files?.[0] ?? null;
}

function submit() {
    form.clearErrors('semester', 'primary_division_id', 'secondary_division_id');
    let firstEmpty: string | null = null;
    if (form.semester === '') {
        form.setError('semester', 'Semester wajib dipilih.');
        firstEmpty = 'semester';
    }
    if (form.primary_division_id === '') {
        form.setError('primary_division_id', 'Divisi utama wajib dipilih.');
        if (firstEmpty === null) firstEmpty = 'primary_division_id';
    }
    if (firstEmpty !== null) {
        document.getElementById(firstEmpty)?.focus();
        return;
    }
    form.put(props.updateUrl, {
        forceFormData: true,
        preserveScroll: true,
    });
}

const portfolioUrlRadio = ref<HTMLButtonElement | null>(null);
const portfolioFileRadio = ref<HTMLButtonElement | null>(null);
const portfolioNoneRadio = ref<HTMLButtonElement | null>(null);

type PortfolioType = 'none' | 'url' | 'file';

function focusPortfolioRadio(value: PortfolioType): void {
    const target =
        value === 'url'
            ? portfolioUrlRadio.value
            : value === 'file'
              ? portfolioFileRadio.value
              : portfolioNoneRadio.value;
    target?.focus();
}

function onPortfolioTypeKeydown(event: KeyboardEvent): void {
    const order: PortfolioType[] = ['none', 'url', 'file'];
    const current = order.indexOf(form.portfolio_type as PortfolioType);
    let next: number | null = null;
    switch (event.key) {
        case 'ArrowRight':
        case 'ArrowDown':
            next = (current + 1) % order.length;
            break;
        case 'ArrowLeft':
        case 'ArrowUp':
            next = (current - 1 + order.length) % order.length;
            break;
        case 'Home':
            next = 0;
            break;
        case 'End':
            next = order.length - 1;
            break;
        default:
            return;
    }
    event.preventDefault();
    const value = order[next] ?? 'none';
    if (value !== form.portfolio_type) form.portfolio_type = value;
    focusPortfolioRadio(value);
}
</script>

<template>
    <Head title="Edit Pendaftaran OpRec" />

    <div class="mx-auto max-w-2xl px-2 pb-8">
        <div class="mb-6 space-y-3">
            <div class="flex flex-col items-center gap-1">
                <h1 class="text-xl font-bold tracking-tight">Perbarui pendaftaran</h1>
                <p class="text-muted-foreground text-sm">Perbaiki data sesuai instruksi tim.</p>
            </div>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <p v-if="form.errors.application" class="text-destructive text-sm">
                {{ form.errors.application }}
            </p>

            <!-- Data diri -->
            <Card class="border-border/70 rounded-2xl">
                <CardHeader class="pb-4">
                    <CardTitle class="text-base">Data diri</CardTitle>
                    <p class="text-muted-foreground text-sm">Nama, kontak, dan email yang bisa dihubungi.</p>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
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
                        <Label for="phone">Telepon</Label>
                        <Input id="phone" v-model="form.phone" required />
                        <p v-if="form.errors.phone" class="text-destructive text-xs">{{ form.errors.phone }}</p>
                    </div>

                    <div class="space-y-2 sm:col-span-2">
                        <Label for="instagram_username">Instagram</Label>
                        <Input id="instagram_username" v-model="form.instagram_username" required />
                        <p v-if="form.errors.instagram_username" class="text-destructive text-xs">
                            {{ form.errors.instagram_username }}
                        </p>
                    </div>

                    <div class="space-y-2 sm:col-span-2">
                        <Label for="personal_email">Email pribadi</Label>
                        <Input id="personal_email" v-model="form.personal_email" type="email" required />
                        <p v-if="form.errors.personal_email" class="text-destructive text-xs">
                            {{ form.errors.personal_email }}
                        </p>
                    </div>

                    <div class="space-y-2 sm:col-span-2">
                        <Label for="student_email">Email kampus</Label>
                        <Input id="student_email" v-model="form.student_email" type="email" required />
                        <p v-if="form.errors.student_email" class="text-destructive text-xs">
                            {{ form.errors.student_email }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Akademik & divisi -->
            <Card class="border-border/70 rounded-2xl">
                <CardHeader class="pb-4">
                    <CardTitle class="text-base">Akademik &amp; divisi</CardTitle>
                    <p class="text-muted-foreground text-sm">Semester dan pilihan divisimu.</p>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="semester">Semester</Label>
                        <SimpleSelect
                            id="semester"
                            v-model="form.semester"
                            :options="semesterOptions"
                            placeholder="Pilih semester"
                            aria-label="Semester"
                            :required="true"
                            :invalid="form.errors.semester !== undefined"
                        />
                        <p v-if="form.errors.semester" class="text-destructive text-xs">{{ form.errors.semester }}</p>
                    </div>

                    <div class="space-y-2">
                        <Label for="primary_division_id">Divisi utama</Label>
                        <SimpleSelect
                            id="primary_division_id"
                            v-model="form.primary_division_id"
                            :options="divisionOptions"
                            placeholder="Pilih divisi"
                            aria-label="Divisi utama"
                            :required="true"
                            :invalid="form.errors.primary_division_id !== undefined"
                        />
                        <p v-if="form.errors.primary_division_id" class="text-destructive text-xs">
                            {{ form.errors.primary_division_id }}
                        </p>
                    </div>

                    <div class="space-y-2 sm:col-span-2">
                        <Label for="secondary_division_id">Divisi cadangan</Label>
                        <SimpleSelect
                            id="secondary_division_id"
                            v-model="form.secondary_division_id"
                            :options="secondaryDivisionOptions"
                            placeholder="Pilih divisi cadangan"
                            aria-label="Divisi cadangan"
                            :invalid="form.errors.secondary_division_id !== undefined"
                        />
                        <p v-if="form.errors.secondary_division_id" class="text-destructive text-xs">
                            {{ form.errors.secondary_division_id }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <!-- Berkas -->
            <Card class="border-border/70 rounded-2xl">
                <CardHeader class="pb-4">
                    <CardTitle class="text-base">Berkas</CardTitle>
                    <p class="text-muted-foreground text-sm">
                        CV, portfolio, bukti follow Instagram, dan tautan twibbon.
                    </p>
                </CardHeader>
                <CardContent class="space-y-5">
                    <div class="space-y-2">
                        <Label for="cv">CV (PDF)</Label>
                        <Input id="cv" type="file" accept="application/pdf" @change="onCvChange" />
                        <p class="text-muted-foreground text-xs">{{ cvHint }}</p>
                        <p v-if="form.errors.cv" class="text-destructive text-xs">{{ form.errors.cv }}</p>
                    </div>

                    <Separator />

                    <div class="space-y-3">
                        <Label id="portfolio-type-label">Portfolio (opsional)</Label>
                        <div
                            class="border-border/70 bg-muted/50 grid grid-cols-3 gap-1 rounded-lg border p-1"
                            role="radiogroup"
                            aria-labelledby="portfolio-type-label"
                        >
                            <button
                                type="button"
                                role="radio"
                                ref="portfolioNoneRadio"
                                :tabindex="form.portfolio_type === 'none' ? 0 : -1"
                                :aria-checked="form.portfolio_type === 'none'"
                                :class="[
                                    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                    form.portfolio_type === 'none'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                ]"
                                @click="form.portfolio_type = 'none'"
                                @keydown="onPortfolioTypeKeydown"
                            >
                                Tidak ada
                            </button>
                            <button
                                type="button"
                                role="radio"
                                ref="portfolioUrlRadio"
                                :tabindex="form.portfolio_type === 'url' ? 0 : -1"
                                :aria-checked="form.portfolio_type === 'url'"
                                :class="[
                                    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                    form.portfolio_type === 'url'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                ]"
                                @click="form.portfolio_type = 'url'"
                                @keydown="onPortfolioTypeKeydown"
                            >
                                URL
                            </button>
                            <button
                                type="button"
                                role="radio"
                                ref="portfolioFileRadio"
                                :tabindex="form.portfolio_type === 'file' ? 0 : -1"
                                :aria-checked="form.portfolio_type === 'file'"
                                :class="[
                                    'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                    form.portfolio_type === 'file'
                                        ? 'bg-background text-foreground shadow-sm'
                                        : 'text-muted-foreground hover:text-foreground',
                                ]"
                                @click="form.portfolio_type = 'file'"
                                @keydown="onPortfolioTypeKeydown"
                            >
                                File PDF
                            </button>
                        </div>
                        <p v-if="form.errors.portfolio_type" class="text-destructive text-xs">
                            {{ form.errors.portfolio_type }}
                        </p>
                        <Input
                            v-if="form.portfolio_type === 'url'"
                            v-model="form.portfolio_url"
                            placeholder="https://..."
                            aria-label="URL portfolio"
                        />
                        <div v-else-if="form.portfolio_type === 'file'" class="space-y-1">
                            <Input
                                id="portfolio_file"
                                type="file"
                                accept="application/pdf"
                                aria-label="File portfolio (opsional)"
                                @change="onPortfolioFileChange"
                            />
                            <p class="text-muted-foreground text-xs">
                                Opsional.
                                <span v-if="application.portfolio_original_name">
                                    File saat ini: {{ application.portfolio_original_name }}
                                </span>
                            </p>
                        </div>
                        <p v-if="form.errors.portfolio_url" class="text-destructive text-xs">
                            {{ form.errors.portfolio_url }}
                        </p>
                        <p v-if="form.errors.portfolio_file" class="text-destructive text-xs">
                            {{ form.errors.portfolio_file }}
                        </p>
                    </div>

                    <Separator />

                    <div class="space-y-2">
                        <Label for="instagram_follow_proof">Bukti Follow Instagram</Label>
                        <Input
                            id="instagram_follow_proof"
                            type="file"
                            accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp"
                            @change="onInstagramFollowChange"
                        />
                        <p class="text-muted-foreground text-xs">{{ instagramFollowHint }}</p>
                        <p v-if="form.errors.instagram_follow_proof" class="text-destructive text-xs">
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
                        <p class="text-muted-foreground text-xs">
                            Twibbon dapat diakses di
                            <a
                                href="https://www.fotomomen.studio/oprec-doscom26"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="text-primary underline-offset-4 hover:underline"
                            >
                                https://www.fotomomen.studio/oprec-doscom26
                            </a>
                        </p>
                        <p v-if="form.errors.twibbon_url" class="text-destructive text-xs">
                            {{ form.errors.twibbon_url }}
                        </p>
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
                <Button type="submit" :disabled="form.processing" class="sm:min-w-44">
                    {{ form.processing ? 'Menyimpan…' : 'Simpan perubahan' }}
                </Button>
                <Button as-child variant="outline">
                    <Link :href="dashboardUrl">Batal</Link>
                </Button>
            </div>

            <p class="text-muted-foreground text-center text-xs">
                <Link :href="routes.recruitment.landing" class="underline-offset-2 hover:underline">
                    Info OpenRecruitment
                </Link>
            </p>
        </form>
    </div>
</template>
