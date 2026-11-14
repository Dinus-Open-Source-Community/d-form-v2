<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\FormStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\FormShareLinkRequest;
use App\Models\Event;
use App\Models\Form;
use App\Services\Form\FormShareLinkService;
use Illuminate\Http\JsonResponse;

final class FormShareLinkController extends Controller
{
    public function __invoke(
        FormShareLinkRequest $request,
        Event $event,
        Form $form,
        FormShareLinkService $shareLinks,
    ): JsonResponse {
        $this->guardFormOnEvent($event, $form);

        $shareLinks->upsert($form, $request->validated('alias'));

        return response()->json(self::payload($event, $form->refresh()));
    }

    /**
     *
     * Build the relative URLs shared by the editor and share-link mutation.
     *
     * @return array{
     * participant_path: string,
     * admin_path: string,
     * short_path: string|null,
     * barcode_path: string,
     * update_url: string,
     * alias: string|null,
     * can_share_participant: bool
     * }
     *
     */
    public static function payload(Event $event, Form $form): array
    {
        return [
            'participant_path' => route('dashboard.events.forms.fill', [
                'event' => $event,
                'form' => $form,
            ], false),
            'admin_path' => route('dashboard.events.forms.show', [
                'event' => $event,
                'form' => $form,
                'tab' => 'editor',
            ], false),
            'short_path' => $form->share_slug === null
                ? null
                : route('form-share.redirect', ['slug' => $form->share_slug], false),
            'barcode_path' => route('dashboard.events.forms.share-link.barcode', [
                'event' => $event,
                'form' => $form,
            ], false),
            'update_url' => route('dashboard.events.forms.share-link', [
                'event' => $event,
                'form' => $form,
            ], false),
            'alias' => $form->share_slug,
            'can_share_participant' => $form->status === FormStatus::Published,
        ];
    }

    private function guardFormOnEvent(Event $event, Form $form): void
    {
        abort_unless($form->event_id === $event->id, 404);
        abort_unless($form->isDraftVisibleTo($event, auth()->user()), 404);
        $this->authorize('update', $event);
    }
}
