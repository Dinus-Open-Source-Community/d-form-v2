<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';
import DashboardFocusLayout from '@/layouts/DashboardFocusLayout.vue';
import PageHeader from '@/components/modules/dashboard/PageHeader.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import TipTapEditor from '@/components/modules/dashboard/events/TipTapEditor.vue';
import EventMultiValuePicker from '@/components/modules/dashboard/events/EventMultiValuePicker.vue';
import { Upload, X, Save, Send } from 'lucide-vue-next';
import { store as storeEvent } from '@/actions/App/Http/Controllers/Dashboard/Events/EventController';
import { showEventValidationToast } from '@/lib/eventValidationToast';
import {
    formatIntegerId,
    formatPriceId,
    parsePriceInput,
    parseQuotaInput,
    sanitizeQuotaTyping,
} from '@/lib/indonesianNumericInput';

defineOptions({ layout: DashboardFocusLayout });

const props = defineProps<{
    options?: {
        categories: { value: string; label: string }[];
        sessions: { value: string; label: string }[];
    };
}>();

const defaultSessions = [
    { value: 'general', label: 'General' },
    { value: 'programming', label: 'Programming' },
    { value: 'network', label: 'Networking' },
    { value: 'media_creative', label: 'Media Creative' },
    { value: 'data', label: 'Data' },
];

const defaultCategories = [
    { value: 'rkt', label: 'RKT' },
    { value: 'non-rkt', label: 'NON RKT' },
    { value: 'recruitment', label: 'Recruitment' },
    { value: 'etc', label: 'Etc' },
];

const sessions = props.options?.sessions ?? defaultSessions;
const categories = props.options?.categories ?? defaultCategories;

function toTokenList(v: unknown): string[] {
    if (Array.isArray(v)) return v.map((s) => String(s).trim()).filter(Boolean);
    if (typeof v === 'string') return v.split(',').map((s) => s.trim()).filter(Boolean);
    return [];
}

const form = useForm({
    title: '',
    description: '',
    location: '',
    start_date: '',
    end_date: '',
    registration_start: '',
    registration_end: '',
    quota: 100,
    price: 0,
    session: '',
    category: '',
    banner: null as File | null,
    publish: false,
});

const bannerPreview = ref<string | null>(null);
const isDragging = ref(false);

const quotaDisplay = ref(form.quota > 0 ? formatIntegerId(form.quota) : '');
const priceDisplay = ref(Number(form.price) > 0 ? formatPriceId(Number(form.price)) : '');

function onQuotaInput(v: string | number) {
    const s = sanitizeQuotaTyping(String(v));
    quotaDisplay.value = s;
    form.quota = parseQuotaInput(s);
}

function onQuotaBlur() {
    const q = parseQuotaInput(quotaDisplay.value);
    form.quota = q;
    quotaDisplay.value = q > 0 ? formatIntegerId(q) : '';
}

function onPriceInput(v: string | number) {
    const s = String(v).replace(/[^\d.,]/g, '');
    priceDisplay.value = s;
    form.price = parsePriceInput(s);
}

function onPriceBlur() {
    const p = parsePriceInput(priceDisplay.value);
    form.price = p;
    priceDisplay.value = p > 0 ? formatPriceId(p) : '';
}

function commitQuotaPriceFromFields() {
    form.quota = parseQuotaInput(quotaDisplay.value);
    form.price = parsePriceInput(priceDisplay.value);
}

function handleBannerChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files?.[0]) {
        form.banner = input.files[0];
        bannerPreview.value = URL.createObjectURL(input.files[0]);
    }
}
function handleDrop(e: DragEvent) {
    isDragging.value = false;
    const file = e.dataTransfer?.files[0];
    if (file && file.type.startsWith('image/')) {
        form.banner = file;
        bannerPreview.value = URL.createObjectURL(file);
    }
}
function removeBanner() {
    form.banner = null;
    bannerPreview.value = null;
}

function submitForm(publish: boolean) {
    form.publish = publish;
    commitQuotaPriceFromFields();
    if (typeof form.start_date === 'string') form.start_date = form.start_date.trim();
    if (typeof form.end_date === 'string') form.end_date = form.end_date.trim();
    if (typeof form.registration_start === 'string') form.registration_start = form.registration_start.trim();
    if (typeof form.registration_end === 'string') form.registration_end = form.registration_end.trim();
    form.transform((data) => ({
        ...data,
        category: toTokenList(data.category),
        session: toTokenList(data.session),
    }));

    form.post(storeEvent().url, {
        forceFormData: true,
        onSuccess: () =>
            toast.success(publish ? 'Acara berhasil dipublikasikan.' : 'Disimpan sebagai draf.'),
        onError: (errors) => showEventValidationToast(errors),
    });
}
</script>

<template>
    <Head title="Buat acara" />

    <div class="flex flex-col gap-8">
        <PageHeader
            title="Buat acara"
            subtitle="Lengkapi informasi berikut. Simpan dulu sebagai draf atau langsung terbitkan jika sudah siap."
        />

        <div class="grid gap-6 lg:grid-cols-12 lg:items-start">
            <div class="flex flex-col gap-6 lg:col-span-7">
                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-4">
                        <CardTitle class="text-base">Informasi utama</CardTitle>
                        <CardDescription>Judul, deskripsi, banner, dan lokasi yang terlihat peserta.</CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-6">
                        <div class="flex flex-col gap-2">
                            <Label for="title" class="text-sm font-medium">Judul acara</Label>
                            <Input id="title" v-model="form.title" placeholder="Contoh: Bootcamp Web 2026" />
                            <p v-if="form.errors.title" class="text-destructive text-xs">{{ form.errors.title }}</p>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label class="text-sm font-medium">Deskripsi</Label>
                            <TipTapEditor v-model="form.description" />
                            <p v-if="form.errors.description" class="text-destructive text-xs">
                                {{ form.errors.description }}
                            </p>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label class="text-sm font-medium">Banner</Label>
                            <p class="text-muted-foreground text-xs leading-relaxed">
                                Tampil memanjang di hero acara. Disarankan gambar lebar (mis. 1600×680) agar tidak blur.
                            </p>
                            <div class="overflow-hidden rounded-xl border-2 border-border bg-muted/25 shadow-sm">
                                <div
                                    class="relative aspect-[2.35/1] w-full min-h-[10.5rem] max-h-[17rem] sm:min-h-[12rem] sm:max-h-[19rem]"
                                >
                                    <template v-if="bannerPreview">
                                        <img
                                            :src="bannerPreview"
                                            alt="Pratinjau banner"
                                            class="absolute inset-0 size-full object-cover"
                                        />
                                        <div
                                            class="pointer-events-none absolute inset-x-0 top-0 h-[45%] bg-gradient-to-b from-black/40 to-transparent"
                                        />
                                        <span
                                            class="absolute top-3 left-3 rounded-md bg-background/92 px-2 py-1 text-[10px] font-semibold tracking-wide text-foreground uppercase shadow-sm backdrop-blur-md"
                                        >
                                            Pratinjau banner
                                        </span>
                                        <div class="absolute top-3 right-3 flex gap-2">
                                            <Button
                                                variant="secondary"
                                                size="sm"
                                                class="h-9 text-xs shadow-md"
                                                type="button"
                                                @click="($refs.bannerInput as HTMLInputElement)?.click()"
                                            >
                                                Ganti
                                            </Button>
                                            <Button
                                                variant="destructive"
                                                size="icon"
                                                class="size-9 shadow-md"
                                                type="button"
                                                @click="removeBanner"
                                            >
                                                <X class="size-4" />
                                            </Button>
                                        </div>
                                    </template>
                                    <div
                                        v-else
                                        :class="[
                                            'absolute inset-0 flex cursor-pointer flex-col items-center justify-center px-5 py-8 text-center transition-colors',
                                            isDragging
                                                ? 'border-primary bg-primary/8'
                                                : 'hover:bg-muted/40',
                                        ]"
                                        @dragover.prevent="isDragging = true"
                                        @dragleave="isDragging = false"
                                        @drop.prevent="handleDrop"
                                        @click="($refs.bannerInput as HTMLInputElement)?.click()"
                                    >
                                        <div class="bg-muted flex size-12 items-center justify-center rounded-xl shadow-inner">
                                            <Upload class="text-muted-foreground size-5" />
                                        </div>
                                        <p class="mt-3 text-sm font-medium">Unggah atau seret gambar ke sini</p>
                                        <p class="text-muted-foreground mt-1 max-w-xs text-xs">
                                            PNG, JPG, atau WebP — maks. 10MB
                                        </p>
                                    </div>
                                </div>
                                <input
                                    ref="bannerInput"
                                    type="file"
                                    accept="image/*"
                                    class="hidden"
                                    @change="handleBannerChange"
                                />
                                <p class="text-muted-foreground border-t border-border bg-muted/20 px-3 py-2 text-xs leading-snug">
                                    Kotak ini memperkirakan tampilan banner mendatar; pada halaman sungguhan bisa sedikit
                                    dipotong tepinya.
                                </p>
                            </div>
                            <p v-if="form.errors.banner" class="text-destructive text-xs">{{ form.errors.banner }}</p>
                        </div>

                        <div class="flex flex-col gap-2">
                            <Label for="location" class="text-sm font-medium">Lokasi</Label>
                            <Input
                                id="location"
                                v-model="form.location"
                                placeholder="Mis. Online — Zoom, atau Semarang — Auditorium A"
                            />
                            <p v-if="form.errors.location" class="text-destructive text-xs">{{ form.errors.location }}</p>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div class="flex flex-col gap-6 lg:col-span-5">
                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-4">
                        <CardTitle class="text-base">Jadwal & kapasitas</CardTitle>
                        <CardDescription>
                            Tanggal pelaksanaan, jendela pendaftaran, kuota, dan harga tiket.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-5">
                        <div class="space-y-3 rounded-xl border border-border/80 bg-muted/20 p-4">
                            <p class="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">
                                Pelaksanaan
                            </p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="flex flex-col gap-1.5">
                                    <Label for="start_date" class="text-xs font-medium">Mulai</Label>
                                    <Input id="start_date" v-model="form.start_date" type="date" class="h-9 text-xs" />
                                    <p v-if="form.errors.start_date" class="text-destructive text-xs">
                                        {{ form.errors.start_date }}
                                    </p>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <Label for="end_date" class="text-xs font-medium">Selesai</Label>
                                    <Input id="end_date" v-model="form.end_date" type="date" class="h-9 text-xs" />
                                    <p v-if="form.errors.end_date" class="text-destructive text-xs">
                                        {{ form.errors.end_date }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3 rounded-xl border border-border/80 bg-muted/20 p-4">
                            <p class="text-muted-foreground text-[11px] font-semibold tracking-wide uppercase">
                                Pendaftaran
                            </p>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="flex flex-col gap-1.5">
                                    <Label for="registration_start" class="text-xs font-medium">Buka</Label>
                                    <Input
                                        id="registration_start"
                                        v-model="form.registration_start"
                                        type="datetime-local"
                                        class="h-9 text-xs"
                                    />
                                    <p v-if="form.errors.registration_start" class="text-destructive text-xs">
                                        {{ form.errors.registration_start }}
                                    </p>
                                </div>
                                <div class="flex flex-col gap-1.5">
                                    <Label for="registration_end" class="text-xs font-medium">Tutup</Label>
                                    <Input
                                        id="registration_end"
                                        v-model="form.registration_end"
                                        type="datetime-local"
                                        class="h-9 text-xs"
                                    />
                                    <p v-if="form.errors.registration_end" class="text-destructive text-xs">
                                        {{ form.errors.registration_end }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="flex flex-col gap-1.5">
                                <Label for="quota" class="text-xs font-medium">Kuota</Label>
                                <Input
                                    id="quota"
                                    type="text"
                                    inputmode="numeric"
                                    autocomplete="off"
                                    class="h-9 text-xs tabular-nums"
                                    :model-value="quotaDisplay"
                                    placeholder="contoh: 500"
                                    @update:model-value="onQuotaInput"
                                    @blur="onQuotaBlur"
                                />
                                <p v-if="form.errors.quota" class="text-destructive text-xs">{{ form.errors.quota }}</p>
                            </div>
                            <div class="flex flex-col gap-1.5">
                                <Label for="price" class="text-xs font-medium">Harga (Rp)</Label>
                                <Input
                                    id="price"
                                    type="text"
                                    inputmode="decimal"
                                    autocomplete="off"
                                    class="h-9 text-xs tabular-nums"
                                    :model-value="priceDisplay"
                                    placeholder="contoh: 1.500.000"
                                    @update:model-value="onPriceInput"
                                    @blur="onPriceBlur"
                                />
                                <p v-if="form.errors.price" class="text-destructive text-xs">{{ form.errors.price }}</p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card class="rounded-xl border shadow-xs">
                    <CardHeader class="pb-4">
                        <CardTitle class="text-base">Klasifikasi</CardTitle>
                        <CardDescription>
                            Sesi (divisi) dan kategori dipakai untuk filter di dashboard. Bisa lebih dari satu.
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="flex flex-col gap-6">
                        <div class="grid gap-6 md:grid-cols-2 md:gap-8">
                            <EventMultiValuePicker
                                id="field-session"
                                v-model="form.session"
                                :options="sessions"
                                label="Sesi / divisi"
                                description="Pilih dari daftar atau tambah nilai kustom yang valid."
                                :error="form.errors.session"
                            />
                            <EventMultiValuePicker
                                id="field-category"
                                v-model="form.category"
                                :options="categories"
                                label="Kategori"
                                description="Contoh: RKT, rekrutmen, atau label kustom Anda."
                                :error="form.errors.category"
                            />
                        </div>
                    </CardContent>
                </Card>

                <div class="flex flex-col gap-2 sm:flex-row sm:gap-3">
                    <Button
                        type="button"
                        class="sm:flex-1"
                        :disabled="form.processing"
                        @click="submitForm(true)"
                    >
                        <Send class="mr-2 size-4" />
                        Terbitkan
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        class="sm:flex-1"
                        :disabled="form.processing"
                        @click="submitForm(false)"
                    >
                        <Save class="mr-2 size-4" />
                        Simpan draf
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
