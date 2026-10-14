<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Form;
use App\Support\PublicStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FormImageUploadController extends Controller
{
    public function __invoke(Request $request, Event $event, Form $form): JsonResponse
    {
        abort_unless($form->event_id === $event->id, 404);
        $this->authorize('update', $event);

        $request->validate([
            'file' => 'required|image|mimes:png,jpg,jpeg,gif,webp|max:2048',
        ]);

        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        $extension = strtolower($file->getClientOriginalExtension() !== ''
            ? $file->getClientOriginalExtension()
            : ($file->guessExtension() ?? 'png'));

        $path = $file->storeAs(
            "form-images/{$form->id}",
            (string) Str::uuid().".{$extension}",
            'public'
        );

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => PublicStorage::url($path, $request),
        ]);
    }
}
