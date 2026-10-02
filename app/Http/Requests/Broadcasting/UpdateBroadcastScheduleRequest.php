<?php

namespace App\Http\Requests\Broadcasting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBroadcastScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('email-broadcast.schedule') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'schedule_time' => ['required', 'date_format:H:i'],
        ];
    }

    public function scheduledAt(): \Carbon\Carbon
    {
        return \Carbon\Carbon::parse(
            $this->string('schedule_date')->toString().' '.$this->string('schedule_time')->toString(),
            config('app.timezone')
        );
    }
}
