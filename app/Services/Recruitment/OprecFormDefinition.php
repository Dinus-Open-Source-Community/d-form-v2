<?php

namespace App\Services\Recruitment;

use App\Models\Event;
use App\Models\Form;

class OprecFormDefinition
{
    public const EVENT_SLUG = 'doscom-open-recruitment-2026';
    public const FORM_TITLE = 'Formulir Open Recruitment';

    public function requiredForm(): Form
    {
        $eventId = Event::query()->where('slug', self::EVENT_SLUG)->value('id');
        abort_if($eventId === null, 503, 'Formulir pendaftaran sedang dalam pemeliharaan. Coba lagi nanti.');
        $form = Form::query()
            ->where('event_id', $eventId)
            ->where('title', self::FORM_TITLE)
            ->first();
        abort_if($form === null, 503, 'Formulir pendaftaran sedang dalam pemeliharaan. Coba lagi nanti.');

        return $form;
    }
}
