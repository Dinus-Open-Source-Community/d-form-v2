<?php

namespace App\Http\Requests\Recruitment;

use App\Enums\Recruitment\EvaluationRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecruitmentEvaluationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'speaking_score' => ['required', 'integer', 'min:1', 'max:10'],
            'technical_score' => ['required', 'integer', 'min:1', 'max:10'],
            'attitude_score' => ['required', 'integer', 'min:1', 'max:10'],
            'recommendation' => ['required', 'string', Rule::enum(EvaluationRecommendation::class)],
            // Rekomendasi hanya berlaku untuk divisi sesi interview ini,
            // sehingga alasan wajib ditulis setiap kali menilai.
            'notes' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.required' => 'Catatan wajib diisi — jelaskan mengapa applicant direkomendasikan / tidak direkomendasikan untuk divisi yang di-interview.',
            'notes.min' => 'Catatan minimal :min karakter — jelaskan alasan rekomendasi untuk divisi yang di-interview.',
        ];
    }
}
