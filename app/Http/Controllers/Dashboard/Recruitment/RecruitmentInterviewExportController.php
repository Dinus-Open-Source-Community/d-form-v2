<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Enums\Recruitment\MembershipType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Recruitment\ExportInterviewReportRequest;
use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentPeriod;
use App\Models\User;
use App\Services\Recruitment\RecruitmentActivityLogger;
use App\Support\Export\CsvStream;
use App\Support\Export\KeysetPage;
use App\Support\Export\KeysetPageIterator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecruitmentInterviewExportController extends Controller
{
    /**
     * Batas atas ukuran halaman: satu halaman 500 baris terukur ±25 MB, jadi
     * nilai env yang terlalu besar akan berakhir sebagai 500 memory limit.
     */
    private const MAX_PAGE_SIZE = 2000;

    /**
     * @var array<string, string>
     */
    private const COLUMN_LABELS = [
        'registration_number' => 'Nomor Pendaftaran',
        'full_name' => 'Nama Lengkap',
        'nim' => 'NIM',
        'semester' => 'Semester',
        'phone' => 'No. Telepon',
        'personal_email' => 'Email Pribadi',
        'primary_division' => 'Divisi Primer',
        'secondary_division' => 'Divisi Sekunder',
        'interview_kind' => 'Jenis Interview',
        'session_date' => 'Tanggal Sesi',
        'session_time' => 'Jam Sesi',
        'location' => 'Lokasi',
        'room' => 'Ruang',
        'session_division' => 'Divisi Sesi',
        'interview_status' => 'Status Interview',
        'checked_in_at' => 'Waktu Check-in',
        'attendance_method' => 'Metode Check-in',
        'interviewer_name' => 'Nama Interviewer',
        'speaking_score' => 'Nilai Speaking',
        'technical_score' => 'Nilai Teknis',
        'attitude_score' => 'Nilai Sikap',
        'recommendation' => 'Rekomendasi',
        'save_count' => 'Jumlah Simpan',
        'evaluated_at' => 'Waktu Dinilai',
        'application_stage' => 'Tahap Pendaftaran',
        'application_result' => 'Hasil Pendaftaran',
        'final_division' => 'Divisi Final',
        'membership_type' => 'Tipe Keanggotaan',
    ];

    public function __invoke(
        ExportInterviewReportRequest $request,
        RecruitmentPeriod $period,
        RecruitmentActivityLogger $activityLogger,
    ): StreamedResponse {
        $actor = $request->user();

        if (! ($actor instanceof User)) {
            abort(403);
        }

        $validated = $request->validated();

        $scope = (string) ($validated['scope'] ?? 'all');
        $includeSecondary = (bool) ($validated['include_secondary'] ?? true);
        $requested = (array) ($validated['columns'] ?? ExportInterviewReportRequest::COLUMN_KEYS);
        $columns = array_values(array_unique(array_intersect($requested, ExportInterviewReportRequest::COLUMN_KEYS)));

        if ($columns === []) {
            $columns = ExportInterviewReportRequest::COLUMN_KEYS;
        }

        $fileName = 'interview-'.$period->slug.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use (
            $period,
            $scope,
            $includeSecondary,
            $columns,
            $activityLogger,
            $actor,
        ): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            $auditScope = sprintf(
                'interviews:%s:secondary=%s:columns=%d',
                $scope,
                $includeSecondary ? 'included' : 'excluded',
                count($columns),
            );

            $rowsGenerated = 0;

            try {
                CsvStream::write(
                    $out,
                    array_map(fn (string $key): string => self::COLUMN_LABELS[$key], $columns),
                    $this->rows([
                        'period' => $period,
                        'scope' => $scope,
                        'includeSecondary' => $includeSecondary,
                        'columns' => $columns,
                    ]),
                    $rowsGenerated,
                );
            } finally {
                fclose($out);

                // Audit tetap jalan saat generator/DB gagal atau klien memutus
                // stream: `$rowsGenerated` berisi baris yang benar-benar sudah ditulis.
                $activityLogger->recordExport($actor, $period->id, $auditScope, $rowsGenerated);
            }
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Baris CSV seluruh periode, dipaginasi keyset `registration_number`.
     *
     * Offset chunking tidak dipakai karena query ini menyaring satu periode lalu
     * mengurutkan `registration_number`, sehingga OFFSET besar mematerialisasi ulang
     * hasil terurut pada setiap chunk.
     * `registration_number` unik global, jadi kolom yang sama dipakai sebagai sort
     * key sekaligus tie-breaker dan urutan global tetap
     * `(registration_number, interview_kind, id)`.
     *
     * Interview diambil satu query per halaman aplikasi; kursor maju per aplikasi
     * terakhir halaman, bukan per baris interview terakhir yang tertulis, sehingga
     * halaman yang seluruh barisnya terfilter tetap maju.
     *
     * @param  array{period: RecruitmentPeriod, scope: string, includeSecondary: bool, columns: list<string>}  $options
     * @return \Generator<int, list<string>>
     */
    private function rows(array $options): \Generator
    {
        $pageSize = min(self::MAX_PAGE_SIZE, max(1, (int) config('recruitment.interview_export_page_size')));

        $applications = new KeysetPageIterator(
            RecruitmentApplication::query()
                ->select(['id', 'registration_number'])
                ->where('recruitment_period_id', $options['period']->id)
        );

        $page = new KeysetPage('registration_number', 'registration_number', pageSize: $pageSize);

        foreach ($applications->pages($page) as $applicationPage) {
            if ($applicationPage->isEmpty()) {
                return;
            }

            $interviewsByApplication = $this->interviewsForPage(
                $applicationPage->modelKeys(),
                $options['scope'],
                $options['includeSecondary']
            );

            foreach ($applicationPage as $application) {
                foreach ($interviewsByApplication[$application->id] ?? [] as $interview) {
                    yield self::row($interview, $options['columns']);
                }
            }
        }
    }

    /**
     * Interview milik satu halaman aplikasi, dikelompokkan per id aplikasi agar
     * baris ditulis sesuai urutan halaman lalu `(interview_kind, id)`.
     *
     * @param  list<string>  $applicationIds
     * @return array<string, list<RecruitmentInterview>>
     */
    private function interviewsForPage(array $applicationIds, string $scope, bool $includeSecondary): array
    {
        $query = RecruitmentInterview::query()
            ->whereIn('recruitment_application_id', $applicationIds)
            ->with([
                'application.primaryDivision:id,name',
                'application.secondaryDivision:id,name',
                'application.attendance',
                'application.finalDecision.finalDivision:id,name',
                'session.division:id,name',
                'interviewer:id,name',
                'evaluation',
            ])
            ->orderBy('interview_kind')
            ->orderBy('id');

        if (! $includeSecondary) {
            $query->where('interview_kind', RecruitmentInterview::KIND_PRIMARY);
        }

        if ($scope === 'evaluated') {
            $query->whereHas('evaluation');
        } elseif ($scope === 'pending') {
            $query->whereDoesntHave('evaluation');
        }

        /** @var array<string, list<RecruitmentInterview>> $grouped */
        $grouped = [];

        foreach ($query->get() as $interview) {
            $grouped[(string) $interview->recruitment_application_id][] = $interview;
        }

        return $grouped;
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private static function row(RecruitmentInterview $interview, array $columns): array
    {
        $application = $interview->application;
        $session = $interview->session;
        $evaluation = $interview->evaluation;
        $decision = $application?->finalDecision;
        $membershipType = filled($decision?->membership_type)
            ? MembershipType::tryFrom((string) $decision->membership_type)
            : null;

        $startsAt = $session !== null && $session->starts_at !== null ? substr((string) $session->starts_at, 0, 5) : '';
        $endsAt = $session !== null && $session->ends_at !== null ? substr((string) $session->ends_at, 0, 5) : '';

        /** @var array<string, mixed> $values */
        $values = [
            'registration_number' => $application?->registration_number ?? '',
            'full_name' => $application?->full_name ?? '',
            'nim' => $application?->nim ?? '',
            'semester' => $application?->semester ?? '',
            'phone' => $application?->phone ?? '',
            'personal_email' => $application?->personal_email ?? '',
            'primary_division' => $application?->primaryDivision?->name ?? '',
            'secondary_division' => $application?->secondaryDivision?->name ?? '',
            'interview_kind' => match ((string) $interview->interview_kind) {
                RecruitmentInterview::KIND_SECONDARY => 'Sekunder',
                RecruitmentInterview::KIND_PRIMARY => 'Primer',
                default => (string) $interview->interview_kind,
            },
            'session_date' => $session?->session_date?->toDateString() ?? '',
            'session_time' => $startsAt !== '' && $endsAt !== '' ? $startsAt.'-'.$endsAt : ($startsAt !== '' ? $startsAt : $endsAt),
            'location' => (string) ($interview->location ?? ''),
            'room' => (string) ($interview->room ?? ''),
            'session_division' => $session?->division?->name ?? '',
            'interview_status' => $interview->status->label(),
            'checked_in_at' => $application?->attendance?->checked_in_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
            'attendance_method' => $application?->attendance?->method?->label() ?? '',
            'interviewer_name' => $interview->interviewer?->name ?? '',
            'speaking_score' => $evaluation?->speaking_score ?? '',
            'technical_score' => $evaluation?->technical_score ?? '',
            'attitude_score' => $evaluation?->attitude_score ?? '',
            'recommendation' => $evaluation?->recommendation?->label() ?? '',
            'save_count' => $evaluation?->save_count ?? '',
            'evaluated_at' => $evaluation?->evaluated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '',
            'application_stage' => $application?->stage->label() ?? '',
            'application_result' => $application?->result->label() ?? '',
            'final_division' => $decision?->finalDivision?->name ?? '',
            'membership_type' => $membershipType?->label() ?? '',
        ];

        return array_map(
            fn (string $key): mixed => $values[$key] ?? '',
            $columns
        );
    }
}
