<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Github, Mail, Pencil, Trash2 } from 'lucide-vue-next';
import UserAvatarFallback from '@/components/core/UserAvatarFallback.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/dateTime';
import { userRoleLabel } from '@/lib/userRoleLabel';
import type { IUserRoleOption } from '@/types/user-management';

interface IUserDetailHeaderUser {
    id: string;
    name: string;
    email: string;
    avatar_url: string | null;
    email_verified_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    roles: string[];
    has_local_password: boolean;
    oauth: { google: boolean; github: boolean };
}

interface IUserDetailHeaderProps {
    user: IUserDetailHeaderUser;
    roleOptions: IUserRoleOption[];
    canEdit: boolean;
    canDelete: boolean;
    /** Tujuan panel edit di direktori, dibangun di halaman pemanggil. */
    editHref: string;
}

const props = defineProps<IUserDetailHeaderProps>();

const emit = defineEmits<{ delete: [] }>();

const DAY_IN_MS = 24 * 60 * 60 * 1000;
const DAYS_PER_MONTH = 30;
const DAYS_PER_YEAR = 365;

/** Umur akun dalam satuan kasar; konteks bacaan, bukan perhitungan presisi. */
function accountAgeText(ageDays: number): string {
    if (ageDays < DAYS_PER_MONTH) return `${ageDays} hari lalu`;
    if (ageDays < DAYS_PER_YEAR) return `${Math.floor(ageDays / DAYS_PER_MONTH)} bulan lalu`;

    return `${Math.floor(ageDays / DAYS_PER_YEAR)} tahun lalu`;
}

/** Satu baris "Bergabung <tanggal> · <umur>" dari `created_at`; kosong bila tanggal hilang. */
function buildJoinedLabel(createdAt: string | null): string {
    const joinedDate = formatDateTime({ value: createdAt, variant: 'date-medium' });

    if (createdAt === null || joinedDate === '') {
        return 'Waktu bergabung tidak tersedia';
    }

    const joinedAt = new Date(createdAt);
    if (Number.isNaN(joinedAt.getTime())) {
        return `Bergabung ${joinedDate}`;
    }

    const ageDays = Math.floor((Date.now() - joinedAt.getTime()) / DAY_IN_MS);
    if (ageDays < 1) {
        return `Bergabung ${joinedDate}`;
    }

    return `Bergabung ${joinedDate} · ${accountAgeText(ageDays)}`;
}

const isVerified = computed<boolean>(() => props.user.email_verified_at !== null);
const joinedLabel = computed<string>(() => buildJoinedLabel(props.user.created_at));
const updatedAtLabel = computed<string>(
    () => formatDateTime({ value: props.user.updated_at, variant: 'date-time-medium' }) || '-'
);
</script>

<template>
    <section class="border-border bg-card rounded-2xl border shadow-xs">
        <div class="flex flex-col gap-5 p-5 sm:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 items-start gap-4">
                    <UserAvatarFallback
                        :src="user.avatar_url"
                        :seed="user.id"
                        avatar-class="size-14 shrink-0 sm:size-16"
                        fallback-round-class="rounded-2xl"
                    />

                    <div class="min-w-0">
                        <h1
                            class="font-display text-foreground text-2xl font-semibold tracking-tight break-words sm:text-3xl"
                        >
                            {{ user.name }}
                        </h1>

                        <p class="text-muted-foreground mt-1 flex min-w-0 items-start gap-1.5 text-sm">
                            <Mail class="mt-0.5 size-3.5 shrink-0" aria-hidden="true" />
                            <span class="break-words">{{ user.email }}</span>
                        </p>

                        <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                            <Badge v-for="role in user.roles" :key="role" variant="outline">
                                {{ userRoleLabel(role, roleOptions) }}
                            </Badge>
                            <Badge
                                variant="outline"
                                :class="
                                    isVerified
                                        ? 'border-success/30 bg-success/10 text-success'
                                        : 'border-border bg-muted/40 text-muted-foreground'
                                "
                            >
                                {{ isVerified ? 'Email terverifikasi' : 'Email belum terverifikasi' }}
                            </Badge>
                        </div>

                        <p class="text-muted-foreground mt-2 text-xs">{{ joinedLabel }}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:shrink-0 sm:flex-row">
                    <Button v-if="canEdit" as-child class="min-h-[44px] w-full sm:w-auto">
                        <Link :href="editHref" data-testid="user-detail-edit">
                            <Pencil class="size-4" aria-hidden="true" />
                            Edit akun
                        </Link>
                    </Button>
                    <Button
                        v-if="canDelete"
                        variant="outline"
                        class="text-destructive border-destructive/30 hover:bg-destructive/10 hover:text-destructive min-h-[44px] w-full sm:w-auto"
                        data-testid="user-detail-delete"
                        @click="emit('delete')"
                    >
                        <Trash2 class="size-4" aria-hidden="true" />
                        Hapus akun
                    </Button>
                </div>
            </div>

            <dl class="border-border/70 grid gap-x-6 gap-y-3 border-t pt-4 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-muted-foreground text-xs">Password lokal</dt>
                    <dd class="text-foreground mt-0.5 text-sm">{{ user.has_local_password ? 'Ada' : 'Tidak ada' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs">Google</dt>
                    <dd class="text-foreground mt-0.5 text-sm">
                        {{ user.oauth.google ? 'Terhubung' : 'Belum' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground flex items-center gap-1 text-xs">
                        <Github class="size-3.5" aria-hidden="true" />
                        GitHub
                    </dt>
                    <dd class="text-foreground mt-0.5 text-sm">
                        {{ user.oauth.github ? 'Terhubung' : 'Belum' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground text-xs">Terakhir diperbarui</dt>
                    <dd class="text-foreground mt-0.5 text-sm">{{ updatedAtLabel }}</dd>
                </div>
            </dl>
        </div>
    </section>
</template>
