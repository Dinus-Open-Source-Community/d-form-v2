<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Form\FormAccessGuard;
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

        return Inertia::render('Dashboard/Events/Forms/Fill', [
            'event'        => ['id' => $event->id, 'title' => $event->title],
            'form'         => [
                'id'          => $form->id,
                'title'       => $form->title,
                'description' => $form->description,
                'closed_at'   => $closed ? $closed->toISOString() : null,
            ],
            'fields'       => $fields,
            'submitUrl'    => route('dashboard.forms.submission', ['event' => $event, 'form' => $form]),
            'accessStatus' => $status->value,
            'accessMessage' => $status->message(),
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
        ];
    }
}
