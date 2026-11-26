<script setup lang="ts">
import { computed } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import ProfileEmailNotice from '@/components/modules/profile/ProfileEmailNotice.vue';
import type { IProfileIdentityForm } from '@/types/profile';

/**
 * Isi bagian Profil: nama dan email. Judul bagian, tombol simpan, dan penanda
 * "Belum disimpan" disediakan `ProfileSectionCard` dan halaman, sehingga bagian
 * ini hanya berisi field. Foto profil berada di kepala halaman, bukan di sini.
 */
const props = defineProps<{
    user: IUser | null;
    identity: IProfileIdentityForm;
}>();

const form = props.identity.form;
const errors = computed<Record<string, string>>(() => props.identity.errors.value);
const savedEmail = computed<string>(() => props.user?.email ?? '');

/**
 * Enter di dalam field ikut men-submit karena form punya tombol simpan sebagai
 * default button (footer dirender di luar form dengan atribut `form`). Submit
 * tanpa perubahan atau saat request berjalan tidak melakukan apa pun, sama
 * seperti tombol yang nonaktif.
 */
function handleSubmit(): void {
    if (form.processing || !props.identity.isDirty.value) return;

    props.identity.submit();
}
</script>

<template>
    <form id="profile-identity-form" class="grid min-w-0 gap-5" @submit.prevent="handleSubmit">
        <div class="grid min-w-0 gap-2">
            <Label for="profile-name">Nama lengkap</Label>
            <Input
                id="profile-name"
                v-model="form.name"
                data-testid="profile-field-name"
                maxlength="150"
                autocomplete="name"
                placeholder="Nama Anda"
                class="h-[44px]"
                :aria-invalid="errors.name !== undefined"
                :aria-describedby="errors.name === undefined ? undefined : 'profile-name-error'"
            />
            <p v-if="errors.name" id="profile-name-error" role="alert" class="text-destructive text-sm">
                {{ errors.name }}
            </p>
        </div>

        <div class="grid min-w-0 gap-2">
            <Label for="profile-email">Email</Label>
            <Input
                id="profile-email"
                v-model="form.email"
                data-testid="profile-field-email"
                type="email"
                inputmode="email"
                autocomplete="email"
                placeholder="nama@email.com"
                class="h-[44px]"
                :aria-invalid="errors.email !== undefined"
                :aria-describedby="errors.email === undefined ? undefined : 'profile-email-error'"
            />
            <p v-if="errors.email" id="profile-email-error" role="alert" class="text-destructive text-sm">
                {{ errors.email }}
            </p>
            <ProfileEmailNotice :original-email="savedEmail" :next-email="form.email" />
        </div>
    </form>
</template>
