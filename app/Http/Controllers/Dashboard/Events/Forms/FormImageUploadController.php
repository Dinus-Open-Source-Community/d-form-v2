<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormImageRequest;
use App\Models\Event;
use App\Models\Form;
use App\Services\Upload\SafeUploadStorage;
use App\Services\Upload\UploadStorageTarget;
use App\Support\PublicStorage;
use Illuminate\Http\JsonResponse;

class FormImageUploadController extends Controller
{
    public function __invoke(StoreFormImageRequest $request, Event $event, Form $form): JsonResponse
    {
        /** @var \Illuminate\Http\UploadedFile $file */
        $file = $request->file('file');

        $path = app(SafeUploadStorage::class)->store($file, UploadStorageTarget::public("form-images/{$form->id}"));

        return response()->json([
            'ok' => true,
            'path' => $path,
            'url' => PublicStorage::url($path, $request),
        ]);
    }
}
