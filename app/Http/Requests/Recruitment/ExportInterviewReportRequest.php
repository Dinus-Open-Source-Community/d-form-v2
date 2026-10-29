<?php

namespace App\Http\Requests\Recruitment;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Filter export CSV interview satu periode; daftar kolom sah disimpan di sini
 * sebagai satu sumber kebenaran (dipakai juga controller untuk default kolom).
 */
class ExportInterviewReportRequest extends FormRequest
{
    /** Kolom export sesuai urutan kontrak (designer lane; JANGAN ubah urutan/nama). */
    public const COLUMN_KEYS = [
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

    public function authorize(): bool
    {
        return $this->user()?->can('recruitment.interviews.schedule') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'scope' => ['sometimes', 'string', 'in:all,evaluated,pending'],
            'include_secondary' => ['sometimes', 'boolean'],
            'columns' => ['sometimes', 'array'],
            'columns.*' => ['string', 'in:'.implode(',', self::COLUMN_KEYS)],
        ];
    }
}
