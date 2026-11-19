<script setup lang="ts">
import { computed, ref } from 'vue';
import { Check } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';
import { PROFILE_AVATAR_MAX_SIZE_KB } from '@/constants/profileConnections';
import { formatDateTime } from '@/lib/dateTime';
import { userAvatarSeed } from '@/lib/userAvatarFallback';
import type { IProfileAvatar } from '@/types/profile';

/**
 * Kepala halaman profil: identitas akun (foto, nama, email, status verifikasi,
 * peran, tanggal bergabung) dan satu-satunya tempat foto diubah. Berkas yang
 * dipilih hanya dipratinjau; baris tertunda muncul di bawah identitas dengan
 * tombol simpan sendiri, dan progres unggah tampil di baris yang sama.
 */
const props = defineProps<{
    user: IUser | null;
    avatar: IProfileAvatar;
}>();

const AVATAR_ACCEPT = 'image/jpeg,image/png,image/gif,image/webp';
/** Batas ukuran ditampilkan dalam MB; server tetap memvalidasi nilai yang sama. */
const KB_PER_MB = 1024;

const fileInputRef = ref<HTMLInputElement | null>(null);

/** `super-admin` → `Super Admin`: slug role dari server dibuat enak dibaca. */
function formatRoleLabel(role: string): string {
    return role
        .split('-')
        .filter((part) => part !== '')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

const displayName = computed<string>(() => props.user?.name ?? '');
const email = computed<string>(() => props.user?.email ?? '');
const isEmailVerified = computed<boolean>(() => (props.user?.email_verified_at ?? null) !== null);
const roleLabels = computed<string[]>(() => (props.user?.roles ?? []).map((role) => formatRoleLabel(role)));

const previewUrl = computed<string | null>(() => props.avatar.previewUrl.value);
const isDirty = computed<boolean>(() => props.avatar.isDirty.value);
const isRemovalPending = computed<boolean>(() => props.avatar.isRemovalPending.value);
const isProcessing = computed<boolean>(() => props.avatar.isProcessing.value);
const progress = computed<number | null>(() => props.avatar.progress.value);
const errorMessage = computed<string>(() => props.avatar.errorMessage.value);

const hasSavedAvatar = computed<boolean>(() => (props.user?.avatar ?? null) !== null);
const maxSizeLabel = computed<string>(() => `${PROFILE_AVATAR_MAX_SIZE_KB / KB_PER_MB} MB`);

/** Teks baris tertunda: berkas baru siap disimpan atau foto menunggu dihapus. */
const pendingText = computed<string>(() => {
    if (isRemovalPending.value) return 'Foto profil akan dihapus setelah Anda menyimpan.';

    return isDirty.value ? 'Foto baru siap disimpan.' : '';
});

/** Baris meta kanan: tanggal bergabung, kosong bila data tidak tersedia. */
const joinedLine = computed<string>(() => {
    const joinedDate = formatDateTime({ value: props.user?.created_at, variant: 'date-medium' });

    return joinedDate === '' ? '' : `Bergabung ${joinedDate}`;
});

function openPicker(): void {
    if (isProcessing.value) return;

    fileInputRef.value?.click();
}

async function onFilePicked(event: Event): Promise<void> {
    const target = event.target as HTMLInputElement | null;
    const file = target?.files?.[0];

    if (file === undefined) return;

    await props.avatar.pick(file);

    // Reset agar berkas yang sama bisa dipilih ulang setelah gagal.
    if (target !== null) target.value = '';
}
</script>

<template>
    <header data-testid="profile-header" class="min-w-0 pb-6 @min-[640px]:pb-8">
        <div
            class="flex flex-col gap-5 @min-[640px]:flex-row @min-[640px]:items-start @min-[640px]:justify-between @min-[640px]:gap-8"
        >
            <div class="flex min-w-0 items-start gap-4">
                <UserAvatarFallback
                    :src="previewUrl"
                    :seed="userAvatarSeed(user)"
                    avatar-class="size-20 shrink-0 rounded-full border border-border"
                    fallback-round-class="rounded-full"
                />

                <div class="min-w-0 flex-1">
                    <h1
                        class="text-foreground text-xl font-semibold tracking-[-0.01em] wrap-anywhere @min-[640px]:text-2xl"
                    >
                        {{ displayName }}
                    </h1>

                    <p class="text-muted-foreground mt-1 text-sm wrap-anywhere">{{ email }}</p>

                    <p class="mt-2 flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                        <span v-if="roleLabels.length > 0">{{ roleLabels.join(' · ') }}</span>
                        <span v-if="roleLabels.length > 0" aria-hidden="true">·</span>
                        <span
                            data-testid="profile-email-status"
                            class="inline-flex items-center gap-1"
                            :class="isEmailVerified ? 'text-muted-foreground' : 'text-warning'"
                        >
                            <Check v-if="isEmailVerified" class="text-success size-3.5 shrink-0" aria-hidden="true" />
                            {{ isEmailVerified ? 'Terverifikasi' : 'Email belum diverifikasi' }}
                        </span>
                        <span v-if="joinedLine !== ''" aria-hidden="true">·</span>
                        <span v-if="joinedLine !== ''" class="text-muted-foreground wrap-anywhere">{{
                            joinedLine
                        }}</span>
                    </p>
                </div>
            </div>

            <div class="flex flex-col gap-2 @min-[640px]:shrink-0 @min-[640px]:items-end">
                <input
                    ref="fileInputRef"
                    data-testid="profile-avatar-input"
                    type="file"
                    :accept="AVATAR_ACCEPT"
                    class="sr-only"
                    tabindex="-1"
                    aria-hidden="true"
                    @change="onFilePicked"
                />

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-[44px] flex-1 @min-[640px]:flex-none"
                        :disabled="isProcessing"
                        @click="openPicker"
                    >
                        Ubah foto
                    </Button>
                    <Button
                        v-if="hasSavedAvatar && !isRemovalPending"
                        type="button"
                        variant="outline"
                        data-testid="profile-avatar-remove"
                        class="min-h-[44px] flex-1 @min-[640px]:flex-none"
                        :disabled="isProcessing"
                        @click="avatar.remove()"
                    >
                        Hapus
                    </Button>
                </div>

                <p class="text-muted-foreground text-xs @min-[640px]:text-right">
                    PNG, JPEG, GIF, atau WebP. Maksimal {{ maxSizeLabel }}.
                </p>
            </div>
        </div>

        <div v-if="isDirty" class="border-border bg-muted/40 mt-5 rounded-lg border px-4 py-3">
            <div
                class="flex flex-col gap-3 @min-[640px]:flex-row @min-[640px]:items-center @min-[640px]:justify-between"
            >
                <p class="text-foreground min-w-0 text-sm">{{ pendingText }}</p>

                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        type="button"
                        data-testid="profile-avatar-save"
                        class="min-h-[44px] flex-1 @min-[640px]:flex-none"
                        :disabled="isProcessing"
                        @click="avatar.save()"
                    >
                        Simpan foto
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        class="min-h-[44px] flex-1 @min-[640px]:flex-none"
                        :disabled="isProcessing"
                        @click="avatar.cancel()"
                    >
                        Batalkan
                    </Button>
                </div>
            </div>

            <div
                v-if="progress !== null"
                role="progressbar"
                :aria-valuenow="progress"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-label="Progres unggah foto profil"
                class="mt-3"
            >
                <p class="text-muted-foreground text-xs" aria-live="polite">Mengunggah foto… {{ progress }}%</p>
                <div class="bg-muted mt-1 h-1.5 w-full overflow-hidden rounded-full">
                    <div class="bg-primary h-full rounded-full" :style="{ width: `${progress}%` }" />
                </div>
            </div>
        </div>

        <p v-if="errorMessage" role="alert" class="text-destructive mt-3 text-sm">{{ errorMessage }}</p>
    </header>
</template>
