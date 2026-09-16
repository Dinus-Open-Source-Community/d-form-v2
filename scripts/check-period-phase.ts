/**
 * Pemeriksaan logika fase periode (dev-only).
 * Jalankan: node --experimental-strip-types scripts/check-period-phase.ts
 */
import {
    daysRemaining,
    parsePeriodDate,
    phaseCountdownLabel,
    phaseDeadline,
    phaseLabel,
    resolvePeriodPhase,
    statusLabel,
    type PeriodPhaseInput,
} from '../resources/js/lib/recruitmentPeriodPhase.ts'

let failures = 0

function check(label: string, actual: unknown, expected: unknown): void {
    const ok = JSON.stringify(actual) === JSON.stringify(expected)
    if (!ok) {
        failures += 1
        console.error(`FAIL ${label}: dapat ${JSON.stringify(actual)}, harusnya ${JSON.stringify(expected)}`)
    }
}

const NOW = new Date('2026-06-15T12:00:00+07:00')

function input(overrides: Partial<PeriodPhaseInput>): PeriodPhaseInput {
    return {
        status: 'open',
        registrationOpensAt: null,
        registrationClosesAt: null,
        interviewStartsAt: null,
        interviewEndsAt: null,
        finalizationDeadlineAt: null,
        ...overrides,
    }
}

// Status menentukan fase lebih dulu
check('draft', resolvePeriodPhase(input({ status: 'draft' }), NOW), 'draft')
check('closed', resolvePeriodPhase(input({ status: 'closed' }), NOW), 'closed')
check('archived', resolvePeriodPhase(input({ status: 'archived' }), NOW), 'archived')

// Open tanpa tanggal sama sekali = pendaftaran masih dibuka
check('open tanpa tanggal', resolvePeriodPhase(input({}), NOW), 'registration')

// Belum dibuka
check(
    'belum dibuka',
    resolvePeriodPhase(input({ registrationOpensAt: '2026-07-01T08:00:00+07:00' }), NOW),
    'not_open',
)

// Sedang dibuka (buka sudah lewat, tutup masih depan)
check(
    'pendaftaran dibuka',
    resolvePeriodPhase(
        input({
            registrationOpensAt: '2026-06-01T08:00:00+07:00',
            registrationClosesAt: '2026-06-30T23:59:00+07:00',
        }),
        NOW,
    ),
    'registration',
)

// Sudah tutup, interview belum mulai
check(
    'menunggu interview',
    resolvePeriodPhase(
        input({
            registrationClosesAt: '2026-06-10T23:59:00+07:00',
            interviewStartsAt: '2026-06-20T09:00:00+07:00',
            interviewEndsAt: '2026-06-25T17:00:00+07:00',
        }),
        NOW,
    ),
    'awaiting_interview',
)

// Jendela interview berlangsung
check(
    'interview berlangsung',
    resolvePeriodPhase(
        input({
            registrationClosesAt: '2026-06-10T23:59:00+07:00',
            interviewStartsAt: '2026-06-14T09:00:00+07:00',
            interviewEndsAt: '2026-06-25T17:00:00+07:00',
        }),
        NOW,
    ),
    'interview',
)

// Interview selesai, menuju finalisasi
check(
    'finalisasi',
    resolvePeriodPhase(
        input({
            registrationClosesAt: '2026-06-10T23:59:00+07:00',
            interviewStartsAt: '2026-06-11T09:00:00+07:00',
            interviewEndsAt: '2026-06-12T17:00:00+07:00',
            finalizationDeadlineAt: '2026-06-30T23:59:00+07:00',
        }),
        NOW,
    ),
    'finalization',
)

// Tutup pendaftaran, belum ada jadwal interview, tenggat finalisasi masih depan
check(
    'tutup tanpa jadwal interview, tenggat depan',
    resolvePeriodPhase(
        input({
            registrationClosesAt: '2026-06-10T23:59:00+07:00',
            finalizationDeadlineAt: '2026-06-30T23:59:00+07:00',
        }),
        NOW,
    ),
    'awaiting_interview',
)

// Semua tenggat terlewat
check(
    'melewati tenggat',
    resolvePeriodPhase(
        input({
            registrationClosesAt: '2026-05-01T23:59:00+07:00',
            interviewStartsAt: '2026-05-10T09:00:00+07:00',
            interviewEndsAt: '2026-05-12T17:00:00+07:00',
            finalizationDeadlineAt: '2026-05-31T23:59:00+07:00',
        }),
        NOW,
    ),
    'overdue',
)

// Tenggat per fase — satu assertion per fase yang punya target
check(
    'tenggat fase not_open = registrationOpensAt',
    phaseDeadline('not_open', input({ registrationOpensAt: '2026-07-01T08:00:00+07:00' }))?.toISOString(),
    new Date('2026-07-01T08:00:00+07:00').toISOString(),
)
check(
    'tenggat fase pendaftaran',
    phaseDeadline('registration', input({ registrationClosesAt: '2026-06-30T23:59:00+07:00' }))?.toISOString(),
    new Date('2026-06-30T23:59:00+07:00').toISOString(),
)
check(
    'tenggat fase awaiting_interview = interviewStartsAt',
    phaseDeadline('awaiting_interview', input({ interviewStartsAt: '2026-06-20T09:00:00+07:00' }))?.toISOString(),
    new Date('2026-06-20T09:00:00+07:00').toISOString(),
)
check(
    'tenggat fase interview = interviewEndsAt',
    phaseDeadline(
        'interview',
        input({
            interviewStartsAt: '2026-06-14T09:00:00+07:00',
            interviewEndsAt: '2026-06-25T17:00:00+07:00',
        }),
    )?.toISOString(),
    new Date('2026-06-25T17:00:00+07:00').toISOString(),
)
check(
    'tenggat fase interview BUKAN interviewStartsAt',
    phaseDeadline(
        'interview',
        input({
            interviewStartsAt: '2026-06-14T09:00:00+07:00',
            interviewEndsAt: '2026-06-25T17:00:00+07:00',
        }),
    )?.toISOString() === new Date('2026-06-14T09:00:00+07:00').toISOString(),
    false,
)
check(
    'tenggat fase finalization = finalizationDeadlineAt',
    phaseDeadline('finalization', input({ finalizationDeadlineAt: '2026-06-30T23:59:00+07:00' }))?.toISOString(),
    new Date('2026-06-30T23:59:00+07:00').toISOString(),
)
check('tenggat fase draft null', phaseDeadline('draft', input({})), null)
check('tenggat fase overdue null', phaseDeadline('overdue', input({})), null)
check('tenggat fase closed null', phaseDeadline('closed', input({})), null)
check('tenggat fase archived null', phaseDeadline('archived', input({})), null)

// daysRemaining
check('sisa hari null', daysRemaining(null, NOW), null)
check('sisa hari 0 bila <24 jam', daysRemaining(new Date('2026-06-16T06:00:00+07:00'), NOW), 0)
check('sisa hari 0 bila sudah lewat', daysRemaining(new Date('2026-06-01T00:00:00+07:00'), NOW), 0)
check('sisa hari hari penuh', daysRemaining(new Date('2026-06-18T12:00:00+07:00'), NOW), 3)
check('sisa hari tepat 24 jam = 1', daysRemaining(new Date(NOW.getTime() + 86_400_000), NOW), 1)
check('sisa hari kurang 1 ms dari 24 jam = 0', daysRemaining(new Date(NOW.getTime() + 86_400_000 - 1), NOW), 0)

// Fix 1: nilai date-only (`YYYY-MM-DD`) = akhir hari waktu lokal
check(
    'parsePeriodDate date-only = akhir hari lokal',
    parsePeriodDate('2026-06-15')?.getTime(),
    new Date(2026, 5, 15, 23, 59, 59, 999).getTime(),
)
check(
    'parsePeriodDate datetime tidak berubah',
    parsePeriodDate('2026-06-15T10:00:00+07:00')?.toISOString(),
    new Date('2026-06-15T10:00:00+07:00').toISOString(),
)
check('parsePeriodDate invalid = null', parsePeriodDate('bukan-tanggal'), null)
check('parsePeriodDate null = null', parsePeriodDate(null), null)

const DEADLINE_DATE_ONLY = '2026-06-15'
const localMorningOnDeadline = new Date(2026, 5, 15, 8, 0, 0, 0)
const localNoonOnDeadline = new Date(2026, 5, 15, 12, 0, 0, 0)
const localEndOfDeadline = new Date(2026, 5, 15, 23, 59, 59, 999)
const localAfterDeadline = new Date(2026, 5, 16, 0, 0, 0, 0)
const localLateBeforeDeadline = new Date(2026, 5, 14, 23, 0, 0, 0)

const deadlineOnlyInput = input({
    registrationClosesAt: '2026-06-10T23:59:00+07:00',
    finalizationDeadlineAt: DEADLINE_DATE_ONLY,
})

// siang hari pada hari tenggat: belum overdue (pre-fix: 07:00 lokal)
check(
    'deadline date-only siang hari belum overdue',
    resolvePeriodPhase(deadlineOnlyInput, localNoonOnDeadline) !== 'overdue',
    true,
)
// sisa hari tetap 0 sepanjang hari tenggat, bukan hanya sampai 07:00
check(
    'sisa hari date-only pagi = 0',
    daysRemaining(parsePeriodDate(DEADLINE_DATE_ONLY), localMorningOnDeadline),
    0,
)
check(
    'sisa hari date-only siang = 0',
    daysRemaining(parsePeriodDate(DEADLINE_DATE_ONLY), localNoonOnDeadline),
    0,
)
check(
    'sisa hari date-only malam = 0',
    daysRemaining(parsePeriodDate(DEADLINE_DATE_ONLY), localEndOfDeadline),
    0,
)
// batas akhir hari: sehari sebelumnya masih 1 hari penuh (pre-fix: 0)
check(
    'sisa hari date-only sehari sebelumnya = 1',
    daysRemaining(parsePeriodDate(DEADLINE_DATE_ONLY), localLateBeforeDeadline),
    1,
)
// tepat akhir hari belum overdue, 1 ms setelahnya overdue
check(
    'deadline date-only tepat akhir hari belum overdue',
    resolvePeriodPhase(deadlineOnlyInput, localEndOfDeadline),
    'awaiting_interview',
)
check(
    'deadline date-only 1 ms setelah akhir hari overdue',
    resolvePeriodPhase(deadlineOnlyInput, localAfterDeadline),
    'overdue',
)

// Fix 2: interview_starts_at ada, interview_ends_at null
const interviewOpenEnd = {
    registrationClosesAt: '2026-06-10T23:59:00+07:00',
    interviewStartsAt: '2026-06-15T09:00:00+07:00',
    interviewEndsAt: null,
}
const afterInterviewStart = new Date(2026, 5, 17, 12, 0, 0, 0)

check(
    'interview tanpa ends, tenggat null = interview',
    resolvePeriodPhase(input({ ...interviewOpenEnd }), afterInterviewStart),
    'interview',
)
check(
    'interview tanpa ends, tenggat depan = interview',
    resolvePeriodPhase(
        input({ ...interviewOpenEnd, finalizationDeadlineAt: '2026-06-30' }),
        afterInterviewStart,
    ),
    'interview',
)
check(
    'interview tanpa ends, tenggat sudah lewat = overdue',
    resolvePeriodPhase(
        input({ ...interviewOpenEnd, finalizationDeadlineAt: '2026-06-01' }),
        afterInterviewStart,
    ),
    'overdue',
)

// phaseCountdownLabel — wording per fase (days === 0 dan days > 0)
check('countdown not_open 0', phaseCountdownLabel('not_open', 0), 'dibuka hari ini')
check('countdown not_open >0', phaseCountdownLabel('not_open', 5), 'dibuka dalam 5 hari')
check('countdown registration 0', phaseCountdownLabel('registration', 0), 'ditutup hari ini')
check('countdown registration >0', phaseCountdownLabel('registration', 3), 'ditutup dalam 3 hari')
check('countdown awaiting_interview 0', phaseCountdownLabel('awaiting_interview', 0), 'interview mulai hari ini')
check('countdown awaiting_interview >0', phaseCountdownLabel('awaiting_interview', 2), 'interview mulai dalam 2 hari')
check('countdown interview 0', phaseCountdownLabel('interview', 0), 'interview berakhir hari ini')
check('countdown interview >0', phaseCountdownLabel('interview', 4), 'interview berakhir dalam 4 hari')
check('countdown finalization 0', phaseCountdownLabel('finalization', 0), 'tenggat finalisasi hari ini')
check('countdown finalization >0', phaseCountdownLabel('finalization', 7), 'tenggat finalisasi dalam 7 hari')
check('countdown draft 0 null', phaseCountdownLabel('draft', 0), null)
check('countdown draft >0 null', phaseCountdownLabel('draft', 3), null)
check('countdown overdue null', phaseCountdownLabel('overdue', 0), null)
check('countdown closed null', phaseCountdownLabel('closed', 5), null)
check('countdown archived null', phaseCountdownLabel('archived', 2), null)
check('countdown days null', phaseCountdownLabel('registration', null), null)

// Label
check('label status open', statusLabel('open'), 'Dibuka')
check('label fase interview', phaseLabel('interview'), 'Interview berlangsung')

if (failures > 0) {
    console.error(`\n${failures} pemeriksaan GAGAL`)
    process.exit(1)
}
console.log('Semua pemeriksaan fase periode LULUS')
