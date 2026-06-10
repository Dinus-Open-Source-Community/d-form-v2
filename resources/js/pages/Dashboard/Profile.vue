<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import UserAvatarFallback from '@/components/modules/user/UserAvatarFallback.vue';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import useAuth from '@/utils/composables/useAuth';
import { Eye, EyeOff, Save, UploadCloud } from 'lucide-vue-next';
import { routes } from '@/lib/routes';

const DASHBOARD_PROFILE_UPDATE_URL = routes.dashboard.profile
const DASHBOARD_PROFILE_AVATAR_URL = routes.dashboard.profileAvatar
const DASHBOARD_PROFILE_PASSWORD_URL = routes.dashboard.profilePassword

defineOptions({ layout: DashboardLayout });

const page = usePage();
const user = useAuth(page.props);

const hasLocalPassword = computed<boolean>(() => user.value?.has_local_password !== false);

const committedName = ref(user.value?.name ?? '');
const committedEmail = ref(user.value?.email ?? '');
const avatarFileInputRef = ref<HTMLInputElement | null>(null);
const committedAvatarSrc = ref<string | null>(user.value?.avatar ?? null);
const committedObjectUrl = ref<string | null>(null);
const avatarLocalOverride = ref(false);
const avatarOverrideStartedFrom = ref<string | null>(null);
const pendingPreviewUrl = ref<string | null>(null);
const pendingFile = ref<File | null>(null);
const pendingAvatarRemoval = ref(false);
const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
const isSavingAll = ref(false);

const avatarDisplaySrc = computed<string | null>(() => {
    if (pendingAvatarRemoval.value) return null;
    return pendingPreviewUrl.value ?? committedAvatarSrc.value;
});

const avatarHasChanges = computed<boolean>(() => pendingFile.value !== null || pendingAvatarRemoval.value);

const profileHasChanges = computed<boolean>(
    () => profileForm.name !== committedName.value || profileForm.email !== committedEmail.value
);

const passwordHasChanges = computed<boolean>(
    () =>
        passwordForm.current_password !== '' ||
        passwordForm.password !== '' ||
        passwordForm.password_confirmation !== ''
);

const isProcessing = computed<boolean>(
    () =>
        profileForm.processing ||
        passwordForm.processing ||
        avatarUploadForm.processing ||
        avatarDeleteForm.processing ||
        isSavingAll.value
);

const hasPendingChanges = computed<boolean>(
    () => avatarHasChanges.value || profileHasChanges.value || passwordHasChanges.value
);

function clearPendingAvatar(): void {
    if (pendingPreviewUrl.value) {
        URL.revokeObjectURL(pendingPreviewUrl.value);
        pendingPreviewUrl.value = null;
    }
    pendingFile.value = null;
    pendingAvatarRemoval.value = false;
    if (avatarFileInputRef.value) {
        avatarFileInputRef.value.value = '';
    }
}

function setCommittedAvatar(src: string | null, isObjectUrl = false): void {
    if (committedObjectUrl.value && committedObjectUrl.value !== src) {
        URL.revokeObjectURL(committedObjectUrl.value);
    }

    committedAvatarSrc.value = src;
    committedObjectUrl.value = isObjectUrl ? src : null;
}

function commitPendingAvatarPreview(): void {
    const previewUrl = pendingPreviewUrl.value;

    if (previewUrl) {
        setCommittedAvatar(previewUrl, true);
        pendingPreviewUrl.value = null;
    }

    pendingFile.value = null;
    pendingAvatarRemoval.value = false;
    avatarLocalOverride.value = true;
    avatarOverrideStartedFrom.value = user.value?.avatar ?? null;

    if (avatarFileInputRef.value) {
        avatarFileInputRef.value.value = '';
    }
}

function commitAvatarRemoval(): void {
    setCommittedAvatar(null);
    pendingFile.value = null;
    pendingAvatarRemoval.value = false;
    avatarLocalOverride.value = true;
    avatarOverrideStartedFrom.value = user.value?.avatar ?? null;

    if (avatarFileInputRef.value) {
        avatarFileInputRef.value.value = '';
    }
}

function onAvatarFileChange(ev: Event): void {
    const input = ev.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
        clearPendingAvatar();
        return;
    }
    clearPendingAvatar();
    pendingAvatarRemoval.value = false;
    pendingFile.value = file;
    pendingPreviewUrl.value = URL.createObjectURL(file);
}

function markAvatarForRemoval(): void {
    clearPendingAvatar();
    pendingAvatarRemoval.value = true;
}

const avatarUploadForm = useForm<{ avatar: File | null }>({
    avatar: null,
});

const avatarDeleteForm = useForm({});

const profileForm = useForm<{ name: string; email: string }>({
    name: user.value?.name ?? '',
    email: user.value?.email ?? '',
});

watch(
    user,
    (next) => {
        if (!next) return;
        profileForm.defaults({
            name: next.name,
            email: next.email,
        });
        profileForm.reset();
        committedName.value = next.name;
        committedEmail.value = next.email;
        clearPendingAvatar();

        if (avatarLocalOverride.value) {
            if (next.avatar !== avatarOverrideStartedFrom.value) {
                avatarLocalOverride.value = false;
                avatarOverrideStartedFrom.value = null;
                setCommittedAvatar(next.avatar ?? null);
            }
            return;
        }

        setCommittedAvatar(next.avatar ?? null);
    },
    { deep: true }
);

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
}).dontRemember('current_password', 'password', 'password_confirmation');

function openAvatarPicker(): void {
    avatarFileInputRef.value?.click();
}

function saveAllChanges(): void {
    if (!hasPendingChanges.value) {
        toast.info('Tidak ada perubahan untuk disimpan.');
        return;
    }

    type SaveTask = (next: () => void, fail: () => void) => void;

    const tasks: SaveTask[] = [];
    const fileToUpload = pendingFile.value;
    const shouldRemoveAvatar = pendingAvatarRemoval.value && !fileToUpload;

    if (profileHasChanges.value) {
        tasks.push((next, fail) => {
            profileForm.patch(DASHBOARD_PROFILE_UPDATE_URL, {
                preserveScroll: true,
                onSuccess: () => {
                    committedName.value = profileForm.name;
                    committedEmail.value = profileForm.email;
                    profileForm.defaults({
                        name: profileForm.name,
                        email: profileForm.email,
                    });
                    next();
                },
                onError: fail,
            });
        });
    }

    if (passwordHasChanges.value) {
        tasks.push((next, fail) => {
            passwordForm.put(DASHBOARD_PROFILE_PASSWORD_URL, {
                preserveScroll: true,
                onSuccess: () => {
                    passwordForm.reset();
                    next();
                },
                onError: fail,
            });
        });
    }

    if (fileToUpload) {
        tasks.push((next, fail) => {
            avatarUploadForm.avatar = fileToUpload;
            avatarUploadForm.post(DASHBOARD_PROFILE_AVATAR_URL, {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    commitPendingAvatarPreview();
                    next();
                },
                onError: fail,
            });
        });
    } else if (shouldRemoveAvatar) {
        tasks.push((next, fail) => {
            avatarDeleteForm.delete(DASHBOARD_PROFILE_AVATAR_URL, {
                preserveScroll: true,
                onSuccess: () => {
                    commitAvatarRemoval();
                    next();
                },
                onError: fail,
            });
        });
    }

    let index = 0;
    isSavingAll.value = true;

    const fail = () => {
        isSavingAll.value = false;
        toast.error('Gagal menyimpan. Periksa field yang ditandai lalu coba lagi.');
    };

    const next = () => {
        const task = tasks[index];
        index += 1;

        if (!task) {
            isSavingAll.value = false;
            toast.success('Perubahan berhasil disimpan.');
            return;
        }

        task(next, fail);
    };

    next();
}
</script>

<template>
    <Head title="Profil" />

    <div class="mx-auto w-full max-w-5xl">
        <Card class="border-border/70 bg-card overflow-hidden rounded-2xl py-0 shadow-xs">
            <CardContent class="p-0">
                <input
                    ref="avatarFileInputRef"
                    type="file"
                    accept="image/jpeg,image/png,image/gif,image/webp"
                    class="sr-only"
                    aria-hidden="true"
                    tabindex="-1"
                    @change="onAvatarFileChange"
                />

                <section class="bg-muted/20 p-5 sm:p-8">
                    <div class="border-border/70 mb-6 border-b pb-4">
                        <p class="text-muted-foreground text-xs font-semibold tracking-[0.16em] uppercase">
                            Pengaturan Profil
                        </p>
                        <h1 class="text-foreground mt-1 text-xl font-semibold tracking-[-0.02em]">Informasi Dasar</h1>
                        <p class="text-muted-foreground mt-1 text-sm">
                            Kelola identitas tampilan dan email akun Anda.
                        </p>
                    </div>

                    <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                        <UserAvatarFallback
                            :src="avatarDisplaySrc"
                            :seed="userAvatarSeed(user)"
                            avatar-class="size-20 rounded-full border border-border"
                            fallback-round-class="rounded-full"
                        />

                        <div class="min-w-0 flex-1">
                            <h1 class="text-foreground text-lg font-semibold">Foto Profil</h1>
                            <div class="mt-3 flex flex-wrap items-center gap-3">
                                <Button
                                    type="button"
                                    class="h-10 rounded-xl px-4 shadow-sm"
                                    :disabled="isProcessing"
                                    @click="openAvatarPicker"
                                >
                                    <UploadCloud class="size-4" />
                                    Unggah Foto
                                </Button>
                                <Button
                                    v-if="avatarHasChanges"
                                    type="button"
                                    variant="outline"
                                    class="h-10 rounded-xl px-4"
                                    :disabled="isProcessing"
                                    @click="clearPendingAvatar"
                                >
                                    Batal
                                </Button>
                                <Button
                                    v-else
                                    type="button"
                                    variant="outline"
                                    class="h-10 rounded-xl px-4"
                                    :disabled="isProcessing || !user?.avatar || pendingAvatarRemoval"
                                    @click="markAvatarForRemoval"
                                >
                                    Hapus
                                </Button>
                            </div>
                            <p class="text-muted-foreground mt-3 text-sm">
                                {{
                                    pendingFile
                                        ? 'Foto baru sudah dipilih. Klik Simpan Perubahan di bawah untuk mengunggahnya.'
                                        : pendingAvatarRemoval
                                          ? 'Foto profil akan dihapus setelah Anda menyimpan perubahan.'
                                          : 'Mendukung PNG, JPEG, dan GIF di bawah 5MB'
                                }}
                            </p>
                            <p v-if="avatarUploadForm.errors.avatar" class="text-destructive mt-2 text-sm">
                                {{ avatarUploadForm.errors.avatar }}
                            </p>
                        </div>
                    </div>
                </section>

                <section class="bg-muted/20 px-5 pb-7 sm:px-8">
                    <form class="grid gap-6" @submit.prevent="saveAllChanges">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="profile-name" class="text-sm font-semibold">Nama Lengkap</Label>
                                <Input
                                    id="profile-name"
                                    v-model="profileForm.name"
                                    maxlength="150"
                                    autocomplete="name"
                                    placeholder="Nama Anda"
                                    class="h-12 rounded-xl"
                                    :aria-invalid="Boolean(profileForm.errors.name)"
                                />
                                <p v-if="profileForm.errors.name" role="alert" class="text-destructive text-sm">
                                    {{ profileForm.errors.name }}
                                </p>
                            </div>
                            <div class="grid gap-2">
                                <Label for="profile-email" class="text-sm font-semibold">Email</Label>
                                <Input
                                    id="profile-email"
                                    v-model="profileForm.email"
                                    type="email"
                                    autocomplete="email"
                                    placeholder="nama@email.com"
                                    class="h-12 rounded-xl"
                                    :aria-invalid="Boolean(profileForm.errors.email)"
                                />
                                <p v-if="profileForm.errors.email" role="alert" class="text-destructive text-sm">
                                    {{ profileForm.errors.email }}
                                </p>
                            </div>
                        </div>

                    </form>
                </section>

                <section class="border-border/70 bg-card border-t p-5 sm:p-8">
                    <div class="border-border/70 mb-6 border-b pb-4">
                        <p class="text-muted-foreground text-xs font-semibold tracking-[0.16em] uppercase">Keamanan</p>
                        <h2 class="text-foreground mt-1 text-xl font-semibold tracking-[-0.02em]">Kata Sandi & Akses</h2>
                        <p class="text-muted-foreground mt-1 text-sm">
                            {{
                                hasLocalPassword
                                    ? 'Masuk menggunakan kata sandi tanpa perlu kode login sementara.'
                                    : 'Tambahkan kata sandi agar Anda bisa masuk menggunakan email dan kata sandi.'
                            }}
                        </p>
                    </div>

                    <form class="grid gap-5" @submit.prevent="saveAllChanges">
                        <div class="grid gap-5">
                            <div v-if="hasLocalPassword" class="grid gap-2">
                                <Label for="current_password" class="text-sm font-semibold">Kata Sandi Saat Ini</Label>
                                <div class="relative">
                                    <Input
                                        id="current_password"
                                        v-model="passwordForm.current_password"
                                        :type="showCurrentPassword ? 'text' : 'password'"
                                        placeholder="Kata sandi saat ini"
                                        autocomplete="current-password"
                                        class="h-12 rounded-xl pr-11"
                                        :aria-invalid="Boolean(passwordForm.errors.current_password)"
                                    />
                                    <button
                                        type="button"
                                        class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring absolute top-1/2 right-1.5 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-lg transition focus-visible:ring-2 focus-visible:outline-none"
                                        :aria-label="
                                            showCurrentPassword ? 'Sembunyikan kata sandi saat ini' : 'Tampilkan kata sandi saat ini'
                                        "
                                        @click="showCurrentPassword = !showCurrentPassword"
                                    >
                                        <EyeOff v-if="showCurrentPassword" class="size-4" />
                                        <Eye v-else class="size-4" />
                                    </button>
                                </div>
                                <p
                                    v-if="passwordForm.errors.current_password"
                                    role="alert"
                                    class="text-destructive text-sm"
                                >
                                    {{ passwordForm.errors.current_password }}
                                </p>
                            </div>

                            <div class="grid gap-5 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="new_password" class="text-sm font-semibold">
                                        {{ hasLocalPassword ? 'Kata Sandi Baru' : 'Kata Sandi' }}
                                    </Label>
                                    <div class="relative">
                                        <Input
                                            id="new_password"
                                            v-model="passwordForm.password"
                                            :type="showNewPassword ? 'text' : 'password'"
                                            placeholder="Minimal 8 karakter"
                                            autocomplete="new-password"
                                            class="h-12 rounded-xl pr-11"
                                            :aria-invalid="Boolean(passwordForm.errors.password)"
                                        />
                                        <button
                                            type="button"
                                            class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring absolute top-1/2 right-1.5 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-lg transition focus-visible:ring-2 focus-visible:outline-none"
                                            :aria-label="showNewPassword ? 'Sembunyikan kata sandi baru' : 'Tampilkan kata sandi baru'"
                                            @click="showNewPassword = !showNewPassword"
                                        >
                                            <EyeOff v-if="showNewPassword" class="size-4" />
                                            <Eye v-else class="size-4" />
                                        </button>
                                    </div>
                                    <p
                                        v-if="passwordForm.errors.password"
                                        role="alert"
                                        class="text-destructive text-sm"
                                    >
                                        {{ passwordForm.errors.password }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <Label for="confirm_password" class="text-sm font-semibold">Konfirmasi Kata Sandi</Label>
                                    <div class="relative">
                                        <Input
                                            id="confirm_password"
                                            v-model="passwordForm.password_confirmation"
                                            :type="showConfirmPassword ? 'text' : 'password'"
                                            placeholder="Ulangi kata sandi"
                                            autocomplete="new-password"
                                            class="h-12 rounded-xl pr-11"
                                        />
                                        <button
                                            type="button"
                                            class="text-muted-foreground hover:bg-accent hover:text-foreground focus-visible:ring-ring absolute top-1/2 right-1.5 inline-flex size-9 -translate-y-1/2 items-center justify-center rounded-lg transition focus-visible:ring-2 focus-visible:outline-none"
                                            :aria-label="
                                                showConfirmPassword ? 'Sembunyikan konfirmasi kata sandi' : 'Tampilkan konfirmasi kata sandi'
                                            "
                                            @click="showConfirmPassword = !showConfirmPassword"
                                        >
                                            <EyeOff v-if="showConfirmPassword" class="size-4" />
                                            <Eye v-else class="size-4" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </section>

                <section class="border-border/70 bg-muted/20 border-t p-5 sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-muted-foreground text-sm">
                            {{
                                hasPendingChanges
                                    ? 'Ada perubahan yang belum disimpan. Periksa kembali, lalu simpan sekali.'
                                    : 'Tidak ada perubahan yang belum disimpan.'
                            }}
                        </p>
                        <Button
                            type="button"
                            class="h-12 rounded-xl px-6"
                            :disabled="!hasPendingChanges || isProcessing"
                            @click="saveAllChanges"
                        >
                            <Save class="size-4" />
                            Simpan Perubahan
                        </Button>
                    </div>
                </section>
            </CardContent>
        </Card>
    </div>
</template>
