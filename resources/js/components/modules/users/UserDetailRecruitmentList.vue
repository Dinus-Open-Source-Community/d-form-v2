<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime } from '@/lib/dateTime';
import { routes } from '@/lib/routes';

interface IUserDetailRecruitmentApplication {
    id: string;
    registration_number: string;
    full_name: string;
    stage: string | null;
    result: string | null;
    submitted_at: string | null;
    period: { id: string; name: string } | null;
    primary_division: string | null;
}

interface IUserDetailRecruitmentListProps {
    applications: IUserDetailRecruitmentApplication[];
}

interface IStatusBadge {
    label: string;
    className: string;
}

const props = defineProps<IUserDetailRecruitmentListProps>();

const NEUTRAL_BADGE_CLASS = 'border-border bg-muted/40 text-muted-foreground';

/** Tahap rekrutmen: label Indonesia untuk nilai enum yang dikenal. */
const STAGE_LABELS: Record<string, string> = {
    submitted: 'Diajukan',
    screening: 'Screening',
    interview: 'Interview',
    final_review: 'Seleksi akhir',
    completed: 'Selesai',
};

/** Hasil seleksi: hanya hasil final yang diberi warna status. */
const RESULT_BADGES: Record<string, IStatusBadge> = {
    pending: { label: 'Menunggu', className: NEUTRAL_BADGE_CLASS },
    accepted: { label: 'Diterima', className: 'border-success/30 bg-success/10 text-success' },
    rejected: { label: 'Ditolak', className: 'border-destructive/25 bg-destructive/10 text-destructive' },
    cancelled: { label: 'Dibatalkan', className: NEUTRAL_BADGE_CLASS },
};

function stageLabel(stage: string | null): string {
    if (stage === null || stage === '') {
        return '-';
    }

    return STAGE_LABELS[stage] ?? stage;
}

function resultBadge(result: string | null): IStatusBadge {
    if (result === null || result === '') {
        return { label: '-', className: NEUTRAL_BADGE_CLASS };
    }

    return RESULT_BADGES[result] ?? { label: result, className: NEUTRAL_BADGE_CLASS };
}

function submittedAt(application: IUserDetailRecruitmentApplication): string {
    return formatDateTime({ value: application.submitted_at, variant: 'date-time-medium' }) || '-';
}

function divisionName(application: IUserDetailRecruitmentApplication): string {
    return application.primary_division === null || application.primary_division === ''
        ? '-'
        : application.primary_division;
}
</script>

<template>
    <section class="border-border bg-card rounded-2xl border shadow-xs" aria-labelledby="user-detail-recruitment-title">
        <div class="border-border/70 flex flex-wrap items-baseline justify-between gap-2 border-b px-5 py-3.5">
            <h2 id="user-detail-recruitment-title" class="text-foreground text-sm font-semibold">Lamaran OpRec</h2>
        </div>

        <template v-if="props.applications.length > 0">
            <div class="hidden lg:block">
                <Table>
                    <TableHeader>
                        <TableRow class="border-border/70 hover:bg-transparent">
                            <TableHead
                                class="text-muted-foreground px-4 py-2.5 text-xs leading-snug font-medium whitespace-normal"
                            >
                                Nomor registrasi
                            </TableHead>
                            <TableHead
                                class="text-muted-foreground px-4 py-2.5 text-xs leading-snug font-medium whitespace-normal"
                            >
                                Nama
                            </TableHead>
                            <TableHead
                                class="text-muted-foreground px-4 py-2.5 text-xs leading-snug font-medium whitespace-normal"
                            >
                                Periode
                            </TableHead>
                            <TableHead
                                class="text-muted-foreground px-4 py-2.5 text-xs leading-snug font-medium whitespace-normal"
                            >
                                Divisi
                            </TableHead>
                            <TableHead
                                class="text-muted-foreground px-4 py-2.5 text-xs leading-snug font-medium whitespace-normal"
                            >
                                Tahap
                            </TableHead>
                            <TableHead
                                class="text-muted-foreground px-4 py-2.5 text-xs leading-snug font-medium whitespace-normal"
                            >
                                Hasil
                            </TableHead>
                            <TableHead
                                class="text-muted-foreground px-4 py-2.5 text-xs leading-snug font-medium whitespace-normal"
                            >
                                Dikirim
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow
                            v-for="application in props.applications"
                            :key="application.id"
                            class="border-border/70 hover:bg-muted/40 align-top transition-colors"
                            data-testid="user-detail-recruitment"
                        >
                            <TableCell
                                class="text-foreground px-4 py-3.5 text-sm leading-snug whitespace-normal tabular-nums xl:whitespace-nowrap"
                            >
                                {{ application.registration_number }}
                            </TableCell>
                            <TableCell
                                class="text-foreground px-4 py-3.5 text-sm leading-snug break-words whitespace-normal"
                            >
                                {{ application.full_name }}
                            </TableCell>
                            <TableCell class="px-4 py-3.5 leading-snug whitespace-normal">
                                <Link
                                    v-if="application.period"
                                    :href="routes.admin.recruitment.periods.show(application.period.id)"
                                    class="text-foreground text-sm break-words hover:underline"
                                >
                                    {{ application.period.name }}
                                </Link>
                                <span v-else class="text-muted-foreground text-sm">-</span>
                            </TableCell>
                            <TableCell class="px-4 py-3.5 text-sm leading-snug break-words whitespace-normal">
                                {{ divisionName(application) }}
                            </TableCell>
                            <TableCell class="px-4 py-3.5 leading-snug whitespace-normal">
                                <Badge variant="outline" class="whitespace-nowrap" :class="NEUTRAL_BADGE_CLASS">
                                    {{ stageLabel(application.stage) }}
                                </Badge>
                            </TableCell>
                            <TableCell class="px-4 py-3.5 leading-snug whitespace-normal">
                                <Badge
                                    variant="outline"
                                    class="whitespace-nowrap"
                                    :class="resultBadge(application.result).className"
                                >
                                    {{ resultBadge(application.result).label }}
                                </Badge>
                            </TableCell>
                            <TableCell
                                class="text-muted-foreground px-4 py-3.5 text-sm leading-snug whitespace-normal tabular-nums xl:whitespace-nowrap"
                            >
                                {{ submittedAt(application) }}
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <ul class="divide-border/70 divide-y lg:hidden">
                <li
                    v-for="application in props.applications"
                    :key="application.id"
                    class="px-5 py-4"
                    data-testid="user-detail-recruitment"
                >
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="text-foreground text-sm font-medium break-words tabular-nums">
                                {{ application.registration_number }}
                            </p>
                            <p class="text-muted-foreground mt-0.5 text-xs break-words">{{ application.full_name }}</p>
                        </div>
                        <Badge variant="outline" class="whitespace-nowrap" :class="NEUTRAL_BADGE_CLASS">{{
                            stageLabel(application.stage)
                        }}</Badge>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2">
                        <div>
                            <dt class="text-muted-foreground text-xs">Periode</dt>
                            <dd class="mt-0.5 text-sm">
                                <Link
                                    v-if="application.period"
                                    :href="routes.admin.recruitment.periods.show(application.period.id)"
                                    class="text-foreground break-words hover:underline"
                                >
                                    {{ application.period.name }}
                                </Link>
                                <span v-else class="text-muted-foreground">-</span>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Divisi</dt>
                            <dd class="text-foreground mt-0.5 text-sm break-words">{{ divisionName(application) }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Hasil</dt>
                            <dd class="mt-0.5">
                                <Badge
                                    variant="outline"
                                    class="whitespace-nowrap"
                                    :class="resultBadge(application.result).className"
                                >
                                    {{ resultBadge(application.result).label }}
                                </Badge>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-muted-foreground text-xs">Dikirim</dt>
                            <dd class="text-foreground mt-0.5 text-sm tabular-nums">{{ submittedAt(application) }}</dd>
                        </div>
                    </dl>
                </li>
            </ul>

            <p class="border-border/70 text-muted-foreground border-t px-5 py-3 text-xs">
                Dicocokkan lewat email akun (personal/student email pada formulir OpRec).
            </p>
        </template>

        <div v-else class="flex flex-col items-start gap-1.5 px-5 py-6">
            <p class="text-muted-foreground text-sm">Belum ada lamaran OpRec.</p>
            <Link :href="routes.admin.users.index" class="text-primary text-sm font-medium hover:underline">
                Kembali ke daftar pengguna
            </Link>
        </div>
    </section>
</template>
