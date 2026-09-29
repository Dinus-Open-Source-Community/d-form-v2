<?php

namespace App\Http\Requests\Broadcasting;

use Illuminate\Foundation\Http\FormRequest;

class StoreBroadcastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('email-broadcast.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'schedule_date' => ['required', 'date_format:Y-m-d'],
            'schedule_time' => ['required', 'date_format:H:i'],
            'delay_min' => ['required', 'integer', 'min:0', 'max:3600'],
            'delay_max' => ['required', 'integer', 'min:0', 'max:3600', 'gte:delay_min'],
            'event_id' => ['nullable', 'uuid', 'exists:events,id'],
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
