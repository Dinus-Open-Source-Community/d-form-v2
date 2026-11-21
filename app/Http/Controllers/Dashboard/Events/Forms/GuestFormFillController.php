<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Services\Form\GuestFormAccess;
use App\Services\Form\GuestFormSession;
use App\Services\Form\GuestFormToken;
use App\Services\Form\PublicFormFillPayload;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Renders an eligible public form and establishes its guest browser marker. */
final class GuestFormFillController extends Controller
{
    public function __invoke(Request $request, Event $event, Form $form): Response
    {
        GuestFormAccess::assertEligible($event, $form);
        $existingToken = $request->cookie(GuestFormToken::COOKIE_NAME);
        $token = GuestFormSession::establish($request, $form);
        $response = Inertia::render('Dashboard/Events/Forms/Fill', PublicFormFillPayload::build($event, $form))->toResponse($request);
        return $existingToken === $token ? $response : $response->withCookie(GuestFormToken::cookie($token));
    }
}
