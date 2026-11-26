<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime } from '@/lib/dateTime';
import { routes } from '@/lib/routes';

interface IUserDetailRegistration {
    form_answer_id: string;
    registration_code: string | null;
    review_status: string | null;
    created_at: string | null;
    attended_at: string | null;
    event: { id: string; title: string; start_date: string | null } | null;
    form: { id: string; title: string | null } | null;
}

interface IUserDetailRegistrationListProps {
    registrations: IUserDetailRegistration[];
}

interface IReviewStatusBadge {
    label: string;
    className: string;
}

const props = defineProps<IUserDetailRegistrationListProps>();

const NEUTRAL_BADGE_CLASS = 'border-border bg-muted/40 text-muted-foreground';

/** Label review dipertahankan dari versi sebelumnya; nilai di luar peta tampil apa adanya. */
const REVIEW_STATUS_BADGES: Record<string, IReviewStatusBadge> = {
    pending: { label: 'Menunggu', className: NEUTRAL_BADGE_CLASS },
    accepted: { label: 'Diterima', className: 'border-success/30 bg-success/10 text-success' },
    rejected: { label: 'Ditolak', className: 'border-destructive/25 bg-destructive/10 text-destructive' },
};

function reviewStatusBadge(status: string | null): IReviewStatusBadge {
    if (status === null || status === '') {
        return { label: '-', className: NEUTRAL_BADGE_CLASS };
    }

    return REVIEW_STATUS_BADGES[status] ?? { label: status, className: NEUTRAL_BADGE_CLASS };
}

/** "Form · tanggal mulai" di bawah judul acara; bagian yang tidak ada dibuang. */
function eventMeta(row: IUserDetailRegistration): string {
    const formTitle = row.form?.title ?? 'Form';
    const startDate = formatDateTime({ value: row.event?.start_date ?? null, variant: 'date-medium' });

    return startDate === '' ? formTitle : `${formTitle} · ${startDate}`;
}

function registrationCode(row: IUserDetailRegistration): string {
    return row.registration_code === null || row.registration_code === '' ? '-' : row.registration_code;
}

function registrationTime(row: IUserDetailRegistration): string {
    return formatDateTime({ value: row.created_at, variant: 'date-time-medium' }) || '-';
}

function attendanceTime(row: IUserDetailRegistration): string {
    return formatDateTime({ value: row.attended_at, variant: 'date-time-medium' }) || '-';
}
</script>

<template>
    <section
        class="border-border bg-card rounded-2xl border shadow-xs"
        aria-labelledby="user-detail-registrations-title"
    >
        <div class="border-border/70 flex flex-wrap items-baseline justify-between gap-2 border-b px-5 py-3.5">
            <h2 id="user-detail-registrations-title" class="text-foreground text-sm font-semibold">
                Pendaftaran event
            </h2>
        </div>

        <template v-if="props.registrations.length > 0">
            <div class="hidden lg:block">
                <Table>
                    <TableHeader>
                        <TableRow class="border-border/70 hover:bg-transparent">
                            <TableHead class="text-muted-foreground px-4 py-2.5 text-xs font-medium xl:px-5"
                                >Acara</TableHead
                            >
                            <TableHead class="text-muted-foreground px-4 py-2.5 text-xs font-medium xl:px-5">
                                Status review
                            </TableHead>
                            <TableHead class="text-muted-foreground px-4 py-2.5 text-xs font-medium xl:px-5">
                                Kode registrasi
                            </TableHead>
                            <TableHead class="text-muted-foreground px-4 py-2.5 text-xs font-medium xl:px-5">
                                Waktu daftar
                            </TableHead>
                            <TableHead class="text-muted-foreground px-4 py-2.5 text-xs font-medium xl:px-5"
                                >Kehadiran</TableHead
                            >
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="row in props.registrations"
                            :key="row.form_answer_id"
                            class="border-border/70 hover:bg-muted/40 align-top transition-colors"
                            data-testid="user-detail-registration"
                        >
                            <TableCell class="px-4 py-3.5 whitespace-normal xl:px-5">
                                <Link
                                    v-if="row.event"
                                    :href="routes.admin.events.show(row.event.id)"
                                    class="text-foreground text-sm font-medium break-words hover:underline"
                                >
                                    {{ row.event.title }}
                                </Link>
                                <span v-else class="text-muted-foreground text-sm">Event tidak tersedia</span>
                                <p class="text-muted-foreground mt-0.5 text-xs break-words">{{ eventMeta(row) }}</p>
                            </TableCell>
                            <TableCell class="px-4 py-3.5 whitespace-normal xl:px-5">
                                <Badge variant="outline" :class="reviewStatusBadge(row.review_status).className">
                                    {{ reviewStatusBadge(row.review_status).label }}
                                </Badge>
                            </TableCell>
                            <TableCell
                                class="text-foreground px-4 py-3.5 text-sm break-all whitespace-normal tabular-nums xl:px-5"
                            >
                                {{ registrationCode(row) }}
                            </TableCell>
                            <TableCell
                                class="text-muted-foreground px-4 py-3.5 text-sm whitespace-normal tabular-nums xl:px-5"
                            >
                                {{ registrationTime(row) }}
                            </TableCell>
                            <TableCell
                                class="text-muted-foreground px-4 py-3.5 text-sm whitespace-normal tabular-nums xl:px-5"
                            >
                                {{ attendanceTime(row) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <ul class="divide-border/70 divide-y lg:hidden">
                <li
                    v-for="row in props.registrations"
                    :key="row.form_answer_id"
                    class="px-5 py-4"
                    data-testid="user-detail-registration"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <Link
                                v-if="row.event"
                                :href="routes.admin.events.show(row.event.id)"
                                class="text-foreground text-sm font-medium break-words hover:underline"
                            >
                                {{ row.event.title }}
                            </Link>
                            <span v-else class="text-muted-foreground text-sm">Event tidak tersedia</span>
                            <p class="text-muted-foreground mt-0.5 text-xs break-words">{{ eventMeta(row) }}</p>
                        </div>
                        <Badge variant="outline" :class="reviewStatusBadge(row.review_status).className">
                            {{ reviewStatusBadge(row.review_status).label }}
                        </Badge>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2">
                        <div>
                            <dt class="text-muted-foreground text-xs">Kode registrasi</dt>
                            <dd class="text-foreground mt-0.5 text-sm break-all tabular-nums">
                                {{ registrationCode(row) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Waktu daftar</dt>
                            <dd class="text-foreground mt-0.5 text-sm tabular-nums">{{ registrationTime(row) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Kehadiran</dt>
                            <dd class="text-foreground mt-0.5 text-sm tabular-nums">{{ attendanceTime(row) }}</dd>
                        </div>
                    </dl>
                </li>
            </ul>
        </template>

        <div v-else class="flex flex-col items-start gap-1.5 px-5 py-6">
            <p class="text-muted-foreground text-sm">Belum ada pendaftaran event.</p>
            <Link :href="routes.admin.users.index" class="text-primary text-sm font-medium hover:underline">
                Kembali ke daftar pengguna
            </Link>
        </div>
    </section>
</template>
