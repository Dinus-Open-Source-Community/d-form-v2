<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { IApplicant, RecruitDivisionId } from '@/types/recruitment';
import { divisionLabels } from '@/lib/dummyRecruitment';
import { computed, reactive } from 'vue';

const props = defineProps<{
    divisions: RecruitDivisionId[];
    initial?: IApplicant | null;
}>();

const emit = defineEmits<{
    submit: [applicant: IApplicant];
    cancel: [];
}>();

const form = reactive({
    fullName: props.initial?.fullName ?? '',
    nim: props.initial?.nim ?? '',
    semester: props.initial ? String(props.initial.semester) : '',
    phone: props.initial?.phone ?? '',
    personalEmail: props.initial?.personalEmail ?? '',
    studentEmail: props.initial?.studentEmail ?? '',
    instagram: props.initial?.instagram ?? '',
    primaryDivision: (props.initial?.primaryDivision ?? '') as RecruitDivisionId | '',
    secondaryDivision: (props.initial?.secondaryDivision ?? '') as RecruitDivisionId | '',
    motivation: props.initial?.motivation ?? '',
    organizationExperience: props.initial?.organizationExperience ?? '',
    skills: props.initial?.skills ?? '',
});

const isEditing = computed(() => props.initial !== null && props.initial !== undefined);

const errors = reactive<Record<string, string>>({});

const divisionOptions = computed<Array<{ id: RecruitDivisionId; label: string }>>(() =>
    props.divisions.map((id) => ({ id, label: divisionLabels[id] ?? id })),
);

function setError(field: string, message: string): void {
    errors[field] = message;
}

function validate(): boolean {
    for (const key of Object.keys(errors)) {
        delete errors[key];
    }

    if (!form.fullName.trim()) {
        setError('fullName', 'Nama lengkap wajib diisi.');
    }
    if (!form.nim.trim()) {
        setError('nim', 'NIM wajib diisi.');
    }
    const semester = Number(form.semester);
    if (!form.semester || !Number.isInteger(semester) || semester < 1 || semester > 3) {
        setError('semester', 'Semester harus 1–3.');
    }
    if (!form.phone.trim()) {
        setError('phone', 'Nomor WhatsApp wajib diisi.');
    }
    if (!form.personalEmail.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.personalEmail)) {
        setError('personalEmail', 'Email pribadi tidak valid.');
    }
    if (!form.studentEmail.trim() || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.studentEmail)) {
        setError('studentEmail', 'Email mahasiswa tidak valid.');
    }
    if (!form.instagram.trim()) {
        setError('instagram', 'Username Instagram wajib diisi.');
    }
    if (!form.primaryDivision) {
        setError('primaryDivision', 'Pilih divisi utama.');
    }
    if (form.secondaryDivision && form.secondaryDivision === form.primaryDivision) {
        setError('secondaryDivision', 'Divisi kedua tidak boleh sama dengan divisi utama.');
    }
    if (!form.motivation.trim()) {
        setError('motivation', 'Motivasi wajib diisi.');
    }

    return Object.keys(errors).length === 0;
}

function handleSubmit(): void {
    if (!validate()) {
        return;
    }

    const applicant: IApplicant = {
        id: props.initial?.id ?? `apl-${Date.now()}`,
        nim: form.nim.trim(),
        fullName: form.fullName.trim(),
        semester: Number(form.semester) as 1 | 2 | 3,
        phone: form.phone.trim(),
        personalEmail: form.personalEmail.trim(),
        studentEmail: form.studentEmail.trim(),
        instagram: form.instagram.trim(),
        primaryDivision: form.primaryDivision as RecruitDivisionId,
        secondaryDivision: form.secondaryDivision || null,
        cvFile: props.initial?.cvFile ?? 'cv-dummy.pdf',
        portfolioType: props.initial?.portfolioType ?? 'url',
        portfolioUrl: props.initial?.portfolioUrl ?? '',
        motivation: form.motivation.trim(),
        organizationExperience: form.organizationExperience.trim() || undefined,
        skills: form.skills.trim() || undefined,
    };

    emit('submit', applicant);
}

function handleCancel(): void {
    emit('cancel');
}
</script>

<template>
    <form class="space-y-8" novalidate @submit.prevent="handleSubmit">
        <!-- Data Pribadi -->
        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold">Data Pribadi</h2>
                <p class="text-muted-foreground text-sm">Informasi dasar identitas Anda.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label for="fullName">Nama Lengkap <span class="text-destructive">*</span></Label>
                    <Input id="fullName" v-model="form.fullName" placeholder="Nama lengkap" :aria-invalid="!!errors.fullName" />
                    <p v-if="errors.fullName" class="text-destructive text-xs">{{ errors.fullName }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="nim">NIM <span class="text-destructive">*</span></Label>
                    <Input id="nim" v-model="form.nim" placeholder="A11.2026.00001" :aria-invalid="!!errors.nim" />
                    <p v-if="errors.nim" class="text-destructive text-xs">{{ errors.nim }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="semester">Semester <span class="text-destructive">*</span></Label>
                    <Input id="semester" v-model="form.semester" type="number" min="1" max="3" placeholder="1–3" :aria-invalid="!!errors.semester" />
                    <p v-if="errors.semester" class="text-destructive text-xs">{{ errors.semester }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="phone">No. WhatsApp <span class="text-destructive">*</span></Label>
                    <Input id="phone" v-model="form.phone" type="tel" placeholder="08xxxxxxxxxx" :aria-invalid="!!errors.phone" />
                    <p v-if="errors.phone" class="text-destructive text-xs">{{ errors.phone }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="personalEmail">Email Pribadi <span class="text-destructive">*</span></Label>
                    <Input id="personalEmail" v-model="form.personalEmail" type="email" placeholder="nama@gmail.com" :aria-invalid="!!errors.personalEmail" />
                    <p v-if="errors.personalEmail" class="text-destructive text-xs">{{ errors.personalEmail }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="studentEmail">Email Mahasiswa <span class="text-destructive">*</span></Label>
                    <Input id="studentEmail" v-model="form.studentEmail" type="email" placeholder="nama@students.dinus.ac.id" :aria-invalid="!!errors.studentEmail" />
                    <p v-if="errors.studentEmail" class="text-destructive text-xs">{{ errors.studentEmail }}</p>
                </div>
                <div class="space-y-2 sm:col-span-2">
                    <Label for="instagram">Username Instagram <span class="text-destructive">*</span></Label>
                    <Input id="instagram" v-model="form.instagram" placeholder="username" :aria-invalid="!!errors.instagram" />
                    <p v-if="errors.instagram" class="text-destructive text-xs">{{ errors.instagram }}</p>
                </div>
            </div>
        </section>

        <!-- Data Pendaftaran -->
        <section class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold">Data Pendaftaran</h2>
                <p class="text-muted-foreground text-sm">Pilihan divisi dan motivasi Anda.</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-2">
                    <Label>Divisi Utama <span class="text-destructive">*</span></Label>
                    <Select v-model="form.primaryDivision">
                        <SelectTrigger :class="errors.primaryDivision ? 'border-destructive' : ''">
                            <SelectValue placeholder="Pilih divisi" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="d in divisionOptions" :key="d.id" :value="d.id">
                                {{ d.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors.primaryDivision" class="text-destructive text-xs">{{ errors.primaryDivision }}</p>
                </div>
                <div class="space-y-2">
                    <Label>Divisi Kedua <span class="text-muted-foreground">(opsional)</span></Label>
                    <Select v-model="form.secondaryDivision">
                        <SelectTrigger :class="errors.secondaryDivision ? 'border-destructive' : ''">
                            <SelectValue placeholder="Pilih divisi" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="d in divisionOptions" :key="d.id" :value="d.id">
                                {{ d.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="errors.secondaryDivision" class="text-destructive text-xs">{{ errors.secondaryDivision }}</p>
                </div>
                <div class="space-y-2 sm:col-span-2">
                    <Label for="motivation">Motivasi <span class="text-destructive">*</span></Label>
                    <Textarea id="motivation" v-model="form.motivation" rows="3" placeholder="Ceritakan alasan Anda bergabung..." :aria-invalid="!!errors.motivation" />
                    <p v-if="errors.motivation" class="text-destructive text-xs">{{ errors.motivation }}</p>
                </div>
                <div class="space-y-2">
                    <Label for="org">Pengalaman Organisasi</Label>
                    <Textarea id="org" v-model="form.organizationExperience" rows="3" placeholder="Opsional" />
                </div>
                <div class="space-y-2">
                    <Label for="skills">Keahlian</Label>
                    <Textarea id="skills" v-model="form.skills" rows="3" placeholder="Opsional" />
                </div>
            </div>
        </section>

        <!-- Dokumen (dummy) -->
        <section class="space-y-3 rounded-xl border border-dashed p-5">
            <div>
                <h2 class="text-lg font-semibold">Dokumen</h2>
                <p class="text-muted-foreground text-sm">
                    Upload CV (PDF) dan portfolio (URL atau PDF). Simulasi — file dummy digunakan pada mockup ini.
                </p>
            </div>
        </section>

        <div class="flex flex-wrap items-center gap-3">
            <Button type="submit" size="lg">
                {{ isEditing ? 'Kirim Perbaikan' : 'Kirim Pendaftaran' }}
            </Button>
            <Button v-if="isEditing" type="button" variant="outline" size="lg" @click="handleCancel">
                Batal
            </Button>
        </div>
    </form>
</template>
