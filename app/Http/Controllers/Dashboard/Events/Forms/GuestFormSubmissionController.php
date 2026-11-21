<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Models\FormField;
use App\Services\Form\GuestFormAccess;
use App\Services\Form\GuestFormSession;
use App\Services\Form\GuestFormToken;
use App\Services\Form\GuestFormSubmissionContext;
use App\Services\Form\GuestFormSubmissionService;
use App\Support\SecretAnswerInput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Handles eligible public non-registration form submissions without auth-bound services. */
final class GuestFormSubmissionController extends Controller
{
    public function __construct(private GuestFormSubmissionService $submissionService)
    {
    }

    public function __invoke(Request $request, Event $event, Form $form): RedirectResponse
    {
        GuestFormAccess::assertEligible($event, $form);
        $token = GuestFormSession::assertEstablished($request, $form);
        $fields = FormField::query()->where('form_id', $form->id)->orderBy('order')->get(['id', 'name', 'input_type', 'metadata']);

        try {
            $this->submissionService->submit(new GuestFormSubmissionContext(
                input: $request->all(),
                form: $form,
                fields: $fields,
                guestTokenHash: GuestFormToken::hash($token),
            ));
        } catch (ValidationException $exception) {
            return redirect()->route('dashboard.public.events.forms.fill', compact('event', 'form'))
                ->withErrors($exception->errors())
                ->withInput(SecretAnswerInput::flashable($request, $fields, 0));
        }

        return redirect()->route('dashboard.public.events.forms.submitted', compact('event', 'form'));
    }
}
