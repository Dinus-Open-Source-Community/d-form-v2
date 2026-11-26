<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Form;
use Illuminate\Foundation\Http\FormRequest;

/** Menyalakan/mematikan subfitur referral pada satu form. */
final class UpdateReferralSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $event = $this->route('event');
        $form = $this->route('form');

        if (! $user || ! $event instanceof Event || ! $form instanceof Form) {
            return false;
        }

        if ($form->event_id !== $event->id) {
            return false;
        }

        return $user->can('update', $event);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
        ];
    }
}
