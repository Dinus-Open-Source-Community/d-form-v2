<?php

namespace App\Services\Recruitment;

use App\Models\Recruitment\RecruitmentInterview;

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
            'tracking_url' => url(route('open-recruitment.track.login', absolute: false)),
            'interviewer_name' => $interview->interviewer?->name ?? '',
            'application_admin_url' => $application
                ? url(route('dashboard.recruitment.applications.show', $application, false))
                : '',
        ];
    }
}
