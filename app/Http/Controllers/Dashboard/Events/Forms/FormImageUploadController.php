<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormImageRequest;
use App\Models\Event;
use App\Models\Form;
use App\Support\PublicStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class FormImageUploadController extends Controller
{
    public function __invoke(StoreFormImageRequest $request, Event $event, Form $form): JsonResponse
    {
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

        abort_if($path === false, 500, 'Gambar form gagal disimpan.');

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => PublicStorage::url($path, $request),
        ]);
    }
}
