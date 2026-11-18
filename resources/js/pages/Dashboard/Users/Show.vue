<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ConfirmationModal from '@/components/core/ConfirmationModal.vue';
import UserDetailHeader from '@/components/modules/users/UserDetailHeader.vue';
import UserDetailRecruitmentList from '@/components/modules/users/UserDetailRecruitmentList.vue';
import UserDetailRegistrationList from '@/components/modules/users/UserDetailRegistrationList.vue';
import UserDetailStatCards from '@/components/modules/users/UserDetailStatCards.vue';
import { Badge } from '@/components/ui/badge';
import { useUserDeletion } from '@/hooks/useUserDeletion';
import { setTopbar } from '@/hooks/useDashboardTopbar';
import DashboardLayout from '@/layouts/DashboardLayout.vue';
import { buildUserIndexUrl } from '@/lib/buildUserIndexUrl';
import { formatDateTime } from '@/lib/dateTime';
import { routes } from '@/lib/routes';
import type { IManagedUser, IUserRoleOption } from '@/types/user-management';

defineOptions({ layout: DashboardLayout });

interface IDetailUser {
    id: string;
    name: string;
    email: string;
    avatar_url: string | null;
    email_verified_at: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
    roles: string[];
    has_local_password: boolean;
    oauth: { google: boolean; github: boolean };
}

interface IDetailStats {
    events_joined: number;
    registrations_pending: number;
    registrations_accepted: number;
    attendances_as_participant: number;
    events_created: number;
    recruitment_applications: number;
    scans_recorded: number;
    interviews_assigned: number;
}

interface IRegistrationRow {
    form_answer_id: string;
    registration_code: string | null;
    review_status: string | null;
    registration_role: string | null;
    member_confirmation_status: string | null;
    created_at: string | null;
    attended_at: string | null;
    event: { id: string; title: string; slug: string; start_date: string | null; status: string | null } | null;
    form: { id: string; title: string | null } | null;
}

interface IEventCreatedRow {
    id: string;
    title: string;
    slug: string;
    status: string | null;
    start_date: string | null;
    created_at: string | null;
}

interface IRecruitmentApplicationRow {
    id: string;
    registration_number: string;
    full_name: string;
    stage: string | null;
    result: string | null;
    submitted_at: string | null;
    match: 'personal_email' | 'student_email';
    period: { id: string; name: string } | null;
    primary_division: string | null;
}

interface IStaffInfo {
    interviewer_divisions: Array<{ id: string; name: string | null; code: string | null }>;
    interviews_assigned_count: number;
    scans_recorded_count: number;
}

const props = defineProps<{
    user: IDetailUser;
    stats: IDetailStats;
    registrations: IRegistrationRow[];
    events_created: IEventCreatedRow[];
    recruitment_applications: IRecruitmentApplicationRow[];
    staff: IStaffInfo;
    permissions: { can_edit: boolean; can_delete: boolean };
    roleOptions: IUserRoleOption[];
}>();

const { showDeleteModal, isDeleting, requestDelete, cancelDelete, dismissDelete, confirmDelete } =
    useUserDeletion();

/** Panel edit hidup di direktori; halaman detail hanya menyusun URL deep link-nya. */
const editHref = computed<string>(() => buildUserIndexUrl({ edit: props.user.id }));

/** Target hapus memakai kontrak direktori, dibangun dari props detail yang sudah ada. */
const deletionTarget = computed<IManagedUser>(() => ({
    id: props.user.id,
    name: props.user.name,
    email: props.user.email,
    roles: props.user.roles,
    avatar_url: props.user.avatar_url,
    can_edit: props.permissions.can_edit,
    can_delete: props.permissions.can_delete,
    created_at: props.user.created_at,
}));

const hasStaffActivity = computed<boolean>(
    () =>
        props.staff.interviewer_divisions.length > 0 ||
        props.staff.interviews_assigned_count > 0 ||
        props.staff.scans_recorded_count > 0 ||
        props.events_created.length > 0
);

/** Kalimat konfirmasi hapus; satu tempat agar tanda kutip nama akun tidak ter-escape. */
const deleteDescription = computed<string>(
    () => `Akun "${props.user.name}" akan dihapus dan tidak bisa dipakai masuk lagi.`
);

/** Status acara yang sudah dikenal; nilai lain tampil apa adanya dengan badge netral. */
const EVENT_STATUS_LABELS: Record<string, string> = {
    draft: 'Draf',
    published: 'Terbit',
};

function eventStatusLabel(status: string | null): string {
    if (status === null || status === '') {
        return '-';
    }

    return EVENT_STATUS_LABELS[status] ?? status;
}

function eventStatusClass(status: string | null): string {
    return status === 'published'
        ? 'border-success/30 bg-success/10 text-success'
        : 'border-border bg-muted/40 text-muted-foreground';
}

function eventStartDate(event: IEventCreatedRow): string {
    return formatDateTime({ value: event.start_date, variant: 'date-medium' }) || '-';
}

/** "Nama (kode)" untuk badge divisi interviewer; nama kosong ditampilkan sebagai tanda pisah. */
function divisionLabel(division: { name: string | null; code: string | null }): string {
    const base = division.name ?? '-';

    return division.code === null || division.code === '' ? base : `${base} (${division.code})`;
}

function requestAccountDeletion(): void {
    if (!props.permissions.can_delete) return;

    requestDelete(deletionTarget.value);
}

function handleDeleteOpenChange(open: boolean): void {
    if (!open) {
        dismissDelete();
    }
}

onMounted(() => {
    setTopbar({ title: props.user.name, subtitle: 'Detail pengguna' });
});
</script>

<template>
    <Head :title="`Detail pengguna · ${user.name}`" />

    <div class="flex w-full max-w-full min-w-0 flex-col gap-5 pb-10" data-testid="user-detail">
        <UserDetailHeader
            :user="user"
            :role-options="roleOptions"
            :can-edit="permissions.can_edit"
            :can-delete="permissions.can_delete"
            :edit-href="editHref"
            @delete="requestAccountDeletion"
        />

        <UserDetailStatCards :stats="stats" />

        <UserDetailRegistrationList :registrations="registrations" />

        <UserDetailRecruitmentList :applications="recruitment_applications" />

        <section class="border-border bg-card rounded-2xl border shadow-xs" aria-labelledby="user-detail-staff-title">
            <div class="border-border/70 border-b px-5 py-3.5">
                <h2 id="user-detail-staff-title" class="text-foreground text-sm font-semibold">Aktivitas staff</h2>
            </div>

            <div v-if="hasStaffActivity" class="divide-border/70 divide-y">
                <dl class="grid grid-cols-2 gap-4 px-5 py-4">
                    <div>
                        <dt class="text-muted-foreground text-xs">Interview ditugaskan</dt>
                        <dd class="text-foreground mt-1 text-lg font-semibold tabular-nums">
                            {{ staff.interviews_assigned_count }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground text-xs">Scan sebagai operator</dt>
                        <dd class="text-foreground mt-1 text-lg font-semibold tabular-nums">
                            {{ staff.scans_recorded_count }}
                        </dd>
                    </div>
                </dl>

                <div v-if="staff.interviewer_divisions.length > 0" class="px-5 py-4">
                    <p class="text-muted-foreground text-xs">Divisi interviewer</p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <Badge
                            v-for="division in staff.interviewer_divisions"
                            :key="division.id"
                            variant="secondary"
                            class="max-w-full"
                        >
                            <span class="min-w-0 break-words">{{ divisionLabel(division) }}</span>
                        </Badge>
                    </div>
                </div>

                <div v-if="events_created.length > 0" class="px-5 py-4">
                    <p class="text-muted-foreground text-xs">Acara yang dibuat</p>
                    <ul class="divide-border/70 mt-1 divide-y">
                        <li
                            v-for="event in events_created"
                            :key="event.id"
                            class="flex flex-wrap items-center justify-between gap-2 py-2.5"
                        >
                            <Link
                                :href="routes.admin.events.show(event.id)"
                                class="text-foreground min-w-0 text-sm font-medium break-words hover:underline"
                            >
                                {{ event.title }}
                            </Link>
                            <span class="flex shrink-0 items-center gap-2">
                                <Badge variant="outline" :class="eventStatusClass(event.status)">
                                    {{ eventStatusLabel(event.status) }}
                                </Badge>
                                <span class="text-muted-foreground text-xs tabular-nums">
                                    {{ eventStartDate(event) }}
                                </span>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <p v-else class="text-muted-foreground px-5 py-6 text-sm">Belum ada aktivitas staff.</p>
        </section>

        <ConfirmationModal
            :open="showDeleteModal"
            title="Hapus akun?"
            :description="deleteDescription"
            confirm-text="Hapus akun"
            cancel-text="Batal"
            variant="destructive"
            :loading="isDeleting"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
            @update:open="handleDeleteOpenChange"
        />
    </div>
</template>
