<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\FormAccessStatus;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Form\FormAccessGuard;
use App\Services\Registration\TeamRegistrationSubmitter;
use App\Support\FormFieldTypeMapping;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FormFillController extends Controller
{
    public function __invoke(Request $request, Event $event, Form $form): Response
    {
        abort_unless($form->event_id === $event->id, 404);

        $user   = $request->user();
        $status = FormAccessGuard::check($form, $event, $user);

        $form->load(['formFields' => fn ($q) => $q->orderBy('order')]);

        $fields = $form->formFields
            ->map(fn (FormField $f) => $this->fieldToInertia($f))
            ->values()
            ->all();

        $closed = $form->closed_at;

        $teamSize    = TeamRegistrationSubmitter::isTeamForm($form) ? TeamRegistrationSubmitter::resolveTeamSize($form) : 0;
        $memberSlots = $teamSize >= 2 ? $teamSize - 1 : 0;

        $metadata        = is_array($form->metadata) ? $form->metadata : [];
        $modeRaw         = $metadata['registration_mode'] ?? 'single';
        $registrationMode = is_string($modeRaw) && $modeRaw !== '' ? strtolower($modeRaw) : 'single';
        if (! in_array($registrationMode, ['single', 'team', 'bundle'], true)) {
            $registrationMode = 'single';
        }

        $pendingInvitationUrl = null;

        if ($status === FormAccessStatus::PendingTeamConfirmation) {
            $pendingInvitationUrl = FormAccessGuard::pendingTeamInvitationUrl($form, $user);
        }

        return Inertia::render('Dashboard/Events/Forms/Fill', [
            'event'                 => ['id' => $event->id, 'slug' => $event->slug, 'title' => $event->title],
            'form'                  => [
                'id'               => $form->id,
                'title'            => $form->title,
                'description'      => $form->description,
                'closed_at'        => $closed ? $closed->toISOString() : null,
                'banner_url'       => $form->banner_url,
                'banner_caption'   => $form->banner_caption,
            ],
            'fields'                => $fields,
            'submitUrl'             => route('dashboard.forms.submission', ['event' => $event, 'form' => $form]),
            'accessStatus'          => $status->value,
            'accessMessage'       => $status->message(),
            'registrationMode'    => $registrationMode,
            'memberSlots'         => $memberSlots,
            'pendingInvitationUrl' => $pendingInvitationUrl,
        ]);
    }

    private function fieldToInertia(FormField $f): array
    {
        $meta = $f->metadata;
        if ($meta instanceof \Illuminate\Support\Collection) {
            $meta = $meta->all();
        } elseif (is_object($meta) && method_exists($meta, 'toArray')) {
            $meta = $meta->toArray();
        } else {
            $meta = (array) $meta;
        }

        return [
            'id'          => $f->id,
            'type'        => FormFieldTypeMapping::toApiType($f->input_type),
            'label'       => $f->label,
            'description' => $f->description,
            'name'        => $f->name,
            'order'       => $f->order,
            'metadata'    => $meta,
            'is_append'   => (bool) $f->is_append,
        ];
    }
}
