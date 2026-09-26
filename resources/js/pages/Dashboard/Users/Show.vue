<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import ConfirmationModal from '@/components/core/ConfirmationModal.vue'
import EmptyState from '@/components/modules/dashboard/EmptyState.vue'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { showErrorToast } from '@/lib/error-message'
import { routes } from '@/lib/routes'
import DashboardLayout from '@/layouts/DashboardLayout.vue'
import { setTopbar } from '@/utils/composables/useDashboardTopbar'
import {
    ArrowLeft,
    CalendarDays,
    CheckCircle2,
    ClipboardList,
    Github,
    Mail,
    Pencil,
    ScanLine,
    Trash2,
    UserRound,
} from 'lucide-vue-next'

defineOptions({ layout: DashboardLayout })

interface DetailUser {
    id: string
    name: string
    email: string
    avatar_url: string | null
    email_verified_at: string | null
    created_at: string | null
    updated_at: string | null
    deleted_at: string | null
    roles: string[]
    has_local_password: boolean
    oauth: { google: boolean; github: boolean }
}

interface Stats {
    events_joined: number
    registrations_pending: number
    registrations_accepted: number
    attendances_as_participant: number
    events_created: number
    recruitment_applications: number
    scans_recorded: number
    interviews_assigned: number
}

interface RegistrationRow {
    form_answer_id: string
    registration_code: string | null
    review_status: string | null
    registration_role: string | null
    member_confirmation_status: string | null
    created_at: string | null
    attended_at: string | null
    event: {
        id: string
        title: string
        slug: string
        start_date: string | null
        status: string | null
    } | null
    form: { id: string; title: string | null } | null
}

interface EventCreatedRow {
    id: string
    title: string
    slug: string
    status: string | null
    start_date: string | null
    created_at: string | null
}

interface RecruitmentAppRow {
    id: string
    registration_number: string
    full_name: string
    stage: string | null
    result: string | null
    submitted_at: string | null
    match: 'personal_email' | 'student_email'
    period: { id: string; name: string } | null
    primary_division: string | null
}

interface StaffInfo {
    interviewer_divisions: Array<{ id: string; name: string | null; code: string | null }>
    interviews_assigned_count: number
    scans_recorded_count: number
}

const props = defineProps<{
    user: DetailUser
    stats: Stats
    registrations: RegistrationRow[]
    events_created: EventCreatedRow[]
    recruitment_applications: RecruitmentAppRow[]
    staff: StaffInfo
    permissions: { can_edit: boolean; can_delete: boolean }
}>()

const roleLabels: Record<string, string> = {
    'super-admin': 'Super Admin',
    admin: 'Admin',
    member: 'Member',
    'recruitment-staff': 'Recruitment Staff',
    'recruitment-interviewer': 'Recruitment Interviewer',
}

const isDeleting = ref(false)
const showDeleteModal = ref(false)

const hasStaffActivity = computed(
    () =>
        props.staff.interviewer_divisions.length > 0 ||
        props.staff.interviews_assigned_count > 0 ||
        props.staff.scans_recorded_count > 0 ||
        props.events_created.length > 0,
)

onMounted(() => {
    setTopbar({ title: props.user.name, subtitle: 'Detail pengguna' })
})

function formatDate(value: string | null | undefined): string {
    if (!value) return '—'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return value
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    })
}

function formatDateTime(value: string | null | undefined): string {
    if (!value) return '—'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return value
    return date.toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    })
}

function reviewLabel(status: string | null): string {
    if (!status) return '—'
    const map: Record<string, string> = {
        pending: 'Menunggu',
        accepted: 'Diterima',
        rejected: 'Ditolak',
    }
    return map[status] ?? status
}

function confirmDelete(): void {
    if (isDeleting.value || !props.permissions.can_delete) return
    isDeleting.value = true
    router.delete(routes.admin.users.destroy(props.user.id), {
        onError: () => showErrorToast('Gagal menghapus akun.'),
        onFinish: () => {
            isDeleting.value = false
            showDeleteModal.value = false
        },
    })
}
</script>

<template>
    <Head :title="`Detail — ${user.name}`" />

    <div class="mx-auto flex w-full max-w-7xl min-w-0 flex-col gap-6 pt-0 pb-8 sm:gap-8 sm:pb-10">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-start gap-4">
                <Button as-child variant="outline" size="icon-sm" class="mt-1 shrink-0">
                    <Link :href="routes.admin.users.index" aria-label="Kembali ke daftar">
                        <ArrowLeft class="size-4" />
                    </Link>
                </Button>
                <div class="flex min-w-0 items-start gap-3">
                    <div
                        class="bg-muted text-muted-foreground grid size-14 shrink-0 place-items-center overflow-hidden rounded-2xl"
                    >
                        <img
                            v-if="user.avatar_url"
                            :src="user.avatar_url"
                            :alt="user.name"
                            class="size-full object-cover"
                        />
                        <UserRound v-else class="size-6" aria-hidden="true" />
                    </div>
                    <div class="min-w-0">
                        <h1
                            class="font-display text-foreground truncate text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            {{ user.name }}
                        </h1>
                        <p class="text-muted-foreground mt-1 flex items-center gap-1.5 text-sm">
                            <Mail class="size-3.5 shrink-0" aria-hidden="true" />
                            <span class="truncate">{{ user.email }}</span>
                        </p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            <Badge v-for="role in user.roles" :key="role" variant="outline">
                                {{ roleLabels[role] ?? role }}
                            </Badge>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                <Button v-if="permissions.can_edit" as-child class="w-full sm:w-auto">
                    <Link :href="routes.admin.users.edit(user.id)">
                        <Pencil class="mr-2 size-4" aria-hidden="true" />
                        Edit
                    </Link>
                </Button>
                <Button
                    v-if="permissions.can_delete"
                    variant="destructive"
                    class="w-full sm:w-auto"
                    @click="showDeleteModal = true"
                >
                    <Trash2 class="mr-2 size-4" aria-hidden="true" />
                    Hapus
                </Button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <Card class="rounded-2xl border-border/70">
                <CardContent class="flex items-center gap-3 p-4">
                    <span class="bg-muted grid size-10 place-items-center rounded-xl">
                        <CalendarDays class="size-4" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="text-muted-foreground text-xs">Event diikuti</p>
                        <p class="text-xl font-semibold tabular-nums">{{ stats.events_joined }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card class="rounded-2xl border-border/70">
                <CardContent class="flex items-center gap-3 p-4">
                    <span class="bg-muted grid size-10 place-items-center rounded-xl">
                        <CheckCircle2 class="size-4" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="text-muted-foreground text-xs">Registrasi diterima</p>
                        <p class="text-xl font-semibold tabular-nums">{{ stats.registrations_accepted }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card class="rounded-2xl border-border/70">
                <CardContent class="flex items-center gap-3 p-4">
                    <span class="bg-muted grid size-10 place-items-center rounded-xl">
                        <ClipboardList class="size-4" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="text-muted-foreground text-xs">Pendaftaran OpRec</p>
                        <p class="text-xl font-semibold tabular-nums">{{ stats.recruitment_applications }}</p>
                    </div>
                </CardContent>
            </Card>
            <Card class="rounded-2xl border-border/70">
                <CardContent class="flex items-center gap-3 p-4">
                    <span class="bg-muted grid size-10 place-items-center rounded-xl">
                        <ScanLine class="size-4" aria-hidden="true" />
                    </span>
                    <div>
                        <p class="text-muted-foreground text-xs">Scan tercatat</p>
                        <p class="text-xl font-semibold tabular-nums">{{ stats.scans_recorded }}</p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <Card class="rounded-2xl border-border/70 lg:col-span-1">
                <CardHeader class="pb-3">
                    <CardTitle class="text-base">Informasi akun</CardTitle>
                </CardHeader>
                <CardContent class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Email terverifikasi</span>
                        <span>{{ user.email_verified_at ? formatDateTime(user.email_verified_at) : 'Belum' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Password lokal</span>
                        <span>{{ user.has_local_password ? 'Ya' : 'Tidak' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Google</span>
                        <span>{{ user.oauth.google ? 'Terhubung' : '—' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground flex items-center gap-1">
                            <Github class="size-3.5" aria-hidden="true" />
                            GitHub
                        </span>
                        <span>{{ user.oauth.github ? 'Terhubung' : '—' }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Dibuat</span>
                        <span>{{ formatDateTime(user.created_at) }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Diperbarui</span>
                        <span>{{ formatDateTime(user.updated_at) }}</span>
                    </div>
                    <div v-if="stats.registrations_pending > 0" class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Registrasi pending</span>
                        <span>{{ stats.registrations_pending }}</span>
                    </div>
                    <div class="flex justify-between gap-3">
                        <span class="text-muted-foreground">Hadir sebagai peserta</span>
                        <span>{{ stats.attendances_as_participant }}</span>
                    </div>
                </CardContent>
            </Card>

            <div class="flex flex-col gap-6 lg:col-span-2">
                <Card class="rounded-2xl border-border/70">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Event yang diikuti</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <template v-if="registrations.length > 0">
                            <div class="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Event</TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead>Kode</TableHead>
                                            <TableHead>Hadir</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="row in registrations" :key="row.form_answer_id">
                                            <TableCell>
                                                <div class="min-w-0">
                                                    <Link
                                                        v-if="row.event"
                                                        :href="routes.admin.events.show(row.event.id)"
                                                        class="text-foreground hover:underline font-medium"
                                                    >
                                                        {{ row.event.title }}
                                                    </Link>
                                                    <span v-else class="text-muted-foreground">Event tidak tersedia</span>
                                                    <p class="text-muted-foreground mt-0.5 text-xs">
                                                        {{ row.form?.title || 'Form' }}
                                                        <span v-if="row.event?.start_date">
                                                            · {{ formatDate(row.event.start_date) }}
                                                        </span>
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="secondary">{{ reviewLabel(row.review_status) }}</Badge>
                                            </TableCell>
                                            <TableCell class="font-mono text-xs">
                                                {{ row.registration_code || '—' }}
                                            </TableCell>
                                            <TableCell class="text-sm">
                                                {{ row.attended_at ? formatDateTime(row.attended_at) : '—' }}
                                            </TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                        </template>
                        <EmptyState
                            v-else
                            title="Belum mengikuti event"
                            description="Tidak ada registrasi event aktif untuk akun ini."
                            animation-name="emptyData"
                        />
                    </CardContent>
                </Card>

                <Card class="rounded-2xl border-border/70">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Pendaftaran Open Recruitment</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <template v-if="recruitment_applications.length > 0">
                            <div class="overflow-x-auto">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>No. registrasi</TableHead>
                                            <TableHead>Periode</TableHead>
                                            <TableHead>Stage</TableHead>
                                            <TableHead>Hasil</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        <TableRow v-for="app in recruitment_applications" :key="app.id">
                                            <TableCell>
                                                <div>
                                                    <p class="font-medium">{{ app.registration_number }}</p>
                                                    <p class="text-muted-foreground text-xs">
                                                        {{ app.full_name }}
                                                        <span v-if="app.primary_division">
                                                            · {{ app.primary_division }}
                                                        </span>
                                                    </p>
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Link
                                                    v-if="app.period"
                                                    :href="routes.admin.recruitment.periods.show(app.period.id)"
                                                    class="hover:underline"
                                                >
                                                    {{ app.period.name }}
                                                </Link>
                                                <span v-else>—</span>
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant="outline">{{ app.stage || '—' }}</Badge>
                                            </TableCell>
                                            <TableCell>{{ app.result || '—' }}</TableCell>
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                            <p class="text-muted-foreground mt-3 text-xs">
                                Dicocokkan lewat email akun (personal/student email pada formulir OpRec).
                            </p>
                        </template>
                        <EmptyState
                            v-else
                            title="Tidak ada pendaftaran OpRec"
                            description="Belum ada aplikasi rekrutmen yang cocok dengan email akun ini."
                            animation-name="emptyData"
                        />
                    </CardContent>
                </Card>

                <Card v-if="hasStaffActivity" class="rounded-2xl border-border/70">
                    <CardHeader class="pb-3">
                        <CardTitle class="text-base">Aktivitas staf</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <div class="rounded-xl border border-border/60 p-3">
                                <p class="text-muted-foreground text-xs">Event dibuat</p>
                                <p class="text-lg font-semibold tabular-nums">{{ stats.events_created }}</p>
                            </div>
                            <div class="rounded-xl border border-border/60 p-3">
                                <p class="text-muted-foreground text-xs">Interview ditugaskan</p>
                                <p class="text-lg font-semibold tabular-nums">
                                    {{ staff.interviews_assigned_count }}
                                </p>
                            </div>
                            <div class="rounded-xl border border-border/60 p-3">
                                <p class="text-muted-foreground text-xs">Scan sebagai operator</p>
                                <p class="text-lg font-semibold tabular-nums">{{ staff.scans_recorded_count }}</p>
                            </div>
                        </div>

                        <div v-if="staff.interviewer_divisions.length > 0">
                            <p class="mb-2 text-sm font-medium">Divisi interviewer</p>
                            <div class="flex flex-wrap gap-1.5">
                                <Badge
                                    v-for="division in staff.interviewer_divisions"
                                    :key="division.id"
                                    variant="secondary"
                                >
                                    {{ division.name }}
                                    <span v-if="division.code" class="opacity-70"> ({{ division.code }})</span>
                                </Badge>
                            </div>
                        </div>

                        <div v-if="events_created.length > 0">
                            <p class="mb-2 text-sm font-medium">Event yang dibuat</p>
                            <ul class="space-y-2">
                                <li
                                    v-for="event in events_created"
                                    :key="event.id"
                                    class="flex flex-wrap items-center justify-between gap-2 text-sm"
                                >
                                    <Link
                                        :href="routes.admin.events.show(event.id)"
                                        class="hover:underline font-medium"
                                    >
                                        {{ event.title }}
                                    </Link>
                                    <span class="text-muted-foreground text-xs">
                                        {{ event.status }} · {{ formatDate(event.start_date) }}
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <ConfirmationModal
            :open="showDeleteModal"
            title="Hapus akun?"
            :description="`Akun “${user.name}” akan dihapus (soft delete).`"
            confirm-text="Hapus"
            cancel-text="Batal"
            variant="destructive"
            :loading="isDeleting"
            @confirm="confirmDelete"
            @cancel="showDeleteModal = false"
            @update:open="(v: boolean) => { showDeleteModal = v }"
        />
    </div>
</template>
