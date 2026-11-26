<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Enums\FormPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\FieldModifyRequest;
use App\Models\Event;
use App\Models\Form;
use App\Services\Du\DuCourseFieldService;
use App\Services\Form\FormFieldSnapshotSync;
use App\Support\LegacyFormImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class FieldOperationController extends Controller
{
    /** Persist the complete form-field snapshot and return the request contract. */
    public function __invoke(FieldModifyRequest $request, Event $event, Form $form): SymfonyResponse
    {
        abort_unless($form->event_id === $event->id, 404);

        $formId = $form->id;
        $requestId = $this->resolveRequestId($request);
        $rows = $request->validated()['fields'];

        // Lapis kedua: data: URL legacy yang lolos validasi (mis. optionChoices[].imageUrl)
        // dikonversi otomatis ke storage agar autosave tidak gagal untuk data lama.
        // URL/path normal dikembalikan apa adanya (no-op).
        $legacy = LegacyFormImage::convertFieldRows($rows, $formId);
        $rows = $legacy['rows'];
        if ($legacy['converted'] > 0 || $legacy['failed'] > 0) {
            Log::warning('[FieldOperationController, __invoke] legacy data: image auto-convert', [
                'form_id' => $formId,
                'converted' => $legacy['converted'],
                'failed' => $legacy['failed'],
                'hint' => 'php artisan forms:migrate-legacy-images --write',
            ]);
        }

        // Field dengan flag redeem DU membekukan konten opsinya; field sistem D-Kelas
        // dibekukan terpisah oleh enforceSnapshotIdentity. Keduanya di dalam try agar
        // kegagalan memakai kontrak log/response yang sama dengan kegagalan tulis lain.
        try {
            $duFields = app(DuCourseFieldService::class);
            $rows = $duFields->enforceLockedOptionContent($form, $rows);

            if ($form->purpose() === FormPurpose::DKelas) {
                $rows = $duFields->enforceSnapshotIdentity($form, $rows);
            }

            app(FormFieldSnapshotSync::class)->sync($formId, $rows);

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Fields have been saved',
            ]);

            // Autosave wizard (background, tanpa navigasi) → JSON.
            if ($request->expectsJson()) {
                return response()->json(['ok' => true]);
            }

            return to_route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]);
        } catch (ValidationException|HttpExceptionInterface $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('form_fields_save_failed', [
                'request_id' => $requestId,
                'event_id' => $event->id,
                'form_id' => $formId,
                'user_id' => $request->user()?->getAuthIdentifier(),
                'exception_class' => $exception::class,
            ]);

            return $this->internalFailureResponse($request, $requestId);
        }
    }

    /** Accept a valid upstream request ID or create a UUID for this request. */
    private function resolveRequestId(FieldModifyRequest $request): string
    {
        $providedRequestId = $request->header('X-Request-ID');

        if (is_string($providedRequestId) && Str::isUuid($providedRequestId)) {
            return $providedRequestId;
        }

        return (string) Str::uuid();
    }

    /** Return a public-safe 500 response without exposing exception details. */
    private function internalFailureResponse(FieldModifyRequest $request, string $requestId): SymfonyResponse
    {
        if ($request->expectsJson()) {
            return response()
                ->json([
                    'ok' => false,
                    'message' => 'Fields cannot be saved',
                    'request_id' => $requestId,
                ], 500)
                ->header('X-Request-ID', $requestId);
        }

        $response = Inertia::render('Error', ['status' => 500])
            ->toResponse($request)
            ->setStatusCode(500);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
