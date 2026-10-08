<?php

namespace App\Http\Controllers\Dashboard\Recruitment;

use App\Enums\Recruitment\MembershipType;
use App\Http\Controllers\Controller;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentPeriod;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RecruitmentInterviewExportController extends Controller
{
    /**
     * Kolom export sesuai urutan kontrak (designer lane; JANGAN ubah urutan/nama).
     *
     * @var list<string>
     */
    private const COLUMN_KEYS = [
        'registration_number',
        'full_name',
        'nim',
        'semester',
        'phone',
        'personal_email',
        'primary_division',
        'secondary_division',
        'interview_kind',
        'session_date',
        'session_time',
        'location',
        'room',
        'session_division',
        'interview_status',
        'checked_in_at',
        'attendance_method',
        'interviewer_name',
        'speaking_score',
        'technical_score',
        'attitude_score',
        'recommendation',
        'save_count',
        'evaluated_at',
        'application_stage',
        'application_result',
        'final_division',
        'membership_type',
    ];

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

    public function __invoke(Request $request, RecruitmentPeriod $period): StreamedResponse
    {
        abort_unless($request->user()?->can('recruitment.interviews.schedule'), 403);

        $validated = $request->validate([
            'scope' => ['sometimes', 'string', 'in:all,evaluated,pending'],
            'include_secondary' => ['sometimes', 'boolean'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', 'in:'.implode(',', self::COLUMN_KEYS)],
        ]);

        $scope = (string) ($validated['scope'] ?? 'all');
        $includeSecondary = (bool) ($validated['include_secondary'] ?? true);
        $requested = (array) ($validated['columns'] ?? self::COLUMN_KEYS);
        $columns = array_values(array_unique(array_intersect($requested, self::COLUMN_KEYS)));

        if ($columns === []) {
            $columns = self::COLUMN_KEYS;
        }

        $fileName = 'interview-'.$period->slug.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($period, $scope, $includeSecondary, $columns): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(self::sanitizeCsvCell(...), array_map(
                fn (string $key): string => self::COLUMN_LABELS[$key],
                $columns
            )));

            $this->baseQuery($period, $scope, $includeSecondary)
                ->chunk(200, function ($interviews) use ($out, $columns): void {
                    foreach ($interviews as $interview) {
                        fputcsv($out, self::row($interview, $columns));
                    }
                });

            fclose($out);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<RecruitmentInterview>
     */
    private function baseQuery(RecruitmentPeriod $period, string $scope, bool $includeSecondary)
    {
        $query = RecruitmentInterview::query()
            ->select('recruitment_interviews.*')
            ->join('recruitment_applications', 'recruitment_applications.id', '=', 'recruitment_interviews.recruitment_application_id')
            ->where('recruitment_applications.recruitment_period_id', $period->id)
            ->with([
                'application.primaryDivision:id,name',
                'application.secondaryDivision:id,name',
                'application.attendance',
                'application.finalDecision.finalDivision:id,name',
                'session.division:id,name',
                'interviewer:id,name',
                'evaluation',
            ])
            ->orderBy('recruitment_applications.registration_number')
            ->orderBy('recruitment_interviews.interview_kind')
            ->orderBy('recruitment_interviews.id');

        if (! $includeSecondary) {
            $query->where('recruitment_interviews.interview_kind', RecruitmentInterview::KIND_PRIMARY);
        }

        if ($scope === 'evaluated') {
            $query->whereHas('evaluation');
        } elseif ($scope === 'pending') {
            $query->whereDoesntHave('evaluation');
        }

        return $query;
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
            fn (string $key): string => self::sanitizeCsvCell($values[$key] ?? ''),
            $columns
        );
    }

    private static function sanitizeCsvCell(mixed $value): string
    {
        $cell = (string) $value;
        $trimmed = ltrim($cell, " \t");

        return match (true) {
            str_starts_with($trimmed, '='),
            str_starts_with($trimmed, '+'),
            str_starts_with($trimmed, '-'),
            str_starts_with($trimmed, '@'),
            str_starts_with($trimmed, "\t"),
            str_starts_with($trimmed, "\r") => "'".$cell,
            default => $cell,
        };
    }
}
