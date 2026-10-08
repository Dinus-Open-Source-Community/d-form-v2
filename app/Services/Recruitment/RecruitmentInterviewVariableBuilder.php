<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentApplication;
use App\Models\Recruitment\RecruitmentInterview;
use App\Models\Recruitment\RecruitmentInterviewSession;

final class RecruitmentInterviewVariableBuilder
{
    /**
     * @return array<string, string>
     */
    public function build(RecruitmentInterview $interview): array
    {
        $interview->loadMissing(['application.period', 'application.primaryDivision', 'session.division', 'interviewer']);

        $application = $interview->application;
        $scheduledAt = $interview->scheduled_at;

        return [
            'applicant_name' => $application?->full_name ?? '',
            'registration_number' => $application?->registration_number ?? '',
            'period_name' => $application?->period?->name ?? 'OpenRecruitment DOSCOM',
            'organization_name' => 'DOSCOM',
            'nim' => $application?->nim ?? '',
            'semester' => (string) ($application?->semester ?? ''),
            'primary_division' => $application?->primaryDivision?->name ?? '',
            'interview_date' => $scheduledAt?->timezone(config('app.timezone'))->translatedFormat('d F Y') ?? '',
            'interview_time' => $scheduledAt?->timezone(config('app.timezone'))->format('H:i') ?? '',
            'interview_location' => $interview->location,
            'interview_room' => $interview->room,
            'division_name' => $interview->session?->division?->name ?? $application?->primaryDivision?->name ?? '',
            'tracking_url' => url(route('recruitment.track.login', absolute: false)),
            'interviewer_name' => $interview->interviewer?->name ?? '',
            'application_admin_url' => $application
                ? url(route('dashboard.recruitment.periods.show', $application->recruitment_period_id, false))
                : '',
        ];
    }

    /**
     * Daftar sesi interview aktif mendatang milik divisi primer applicant
     * (pengisi kartu jadwal email bulk QR yang tidak membawa interviewId).
     *
     * @return array{sessions: list<array{date: string, time_range: string, location: string, room: string}>, session_division_name: string}
     */
    public function buildSessionList(RecruitmentApplication $application): array
    {
        $application->loadMissing('primaryDivision');

        $sessions = RecruitmentInterviewSession::query()
            ->where('recruitment_period_id', $application->recruitment_period_id)
            ->where('recruitment_division_id', $application->primary_division_id)
            ->where('is_active', true)
            ->whereDate('session_date', '>=', today())
            ->orderBy('session_date')
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        $timezone = (string) config('app.timezone');

        $list = $sessions
            ->map(fn (RecruitmentInterviewSession $session): array => [
                'date' => $session->session_date?->timezone($timezone)->translatedFormat('d F Y') ?? '',
                'time_range' => substr((string) $session->starts_at, 0, 5).'-'.substr((string) $session->ends_at, 0, 5),
                'location' => (string) ($session->location ?? ''),
                'room' => (string) ($session->room ?? ''),
            ])
            ->values()
            ->all();

        return [
            'sessions' => $list,
            'session_division_name' => $application->primaryDivision?->name ?? '',
        ];
    }
}
