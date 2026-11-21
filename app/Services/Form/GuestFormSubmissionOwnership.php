<?php

namespace App\Services\Form;

use App\Models\Event;
use App\Models\Form;
use App\Models\FormAnswer;
use Illuminate\Http\Request;

/** Proves that a public success request belongs to the current guest browser. */
final class GuestFormSubmissionOwnership
{
    public static function assertOwned(Request $request, Event $event, Form $form): string
    {
        GuestFormAccess::assertEligible($event, $form);
        abort_unless($form->event_id === $event->id, 404);
        $token = GuestFormSession::assertEstablished($request, $form);
        $hash = GuestFormToken::hash($token);
        abort_unless(FormAnswer::query()->where('form_id', $form->id)->whereNull('user_id')->where('guest_token_hash', $hash)->where('is_active', 1)->exists(), 404);
        return $hash;
    }
}
