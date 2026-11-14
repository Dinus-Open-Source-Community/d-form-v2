<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\FormAnswer;
use App\Services\Registration\FormAnswerFileStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Serves submission uploads only to a valid signed URL or an authorized user. */
class FormAnswerFileController extends Controller
{
    public function __invoke(
        Request $request,
        FormAnswer $formAnswer,
        string $field,
        string $filename,
        FormAnswerFileStorage $files,
    ): StreamedResponse {
        $authorized = $request->hasValidSignature()
            || ($request->user() !== null && $request->user()->can('viewFile', $formAnswer));

        abort_unless($authorized, 403);

        $located = $files->locate($formAnswer, $field, $filename);
        abort_if($located === null, 404);

        $downloadName = str_replace(["\r", "\n", '"'], '', basename(rawurldecode($filename)));
        if ($downloadName === '' || $downloadName === '.') {
            $downloadName = basename($located['path']);
        }

        $disk = Storage::disk($located['disk']);
        $headers = ['X-Content-Type-Options' => 'nosniff'];

        if ($request->boolean('download')) {
            return $disk->download($located['path'], $downloadName, $headers);
        }

        return $disk->response($located['path'], $downloadName, $headers, 'inline');
    }
}
