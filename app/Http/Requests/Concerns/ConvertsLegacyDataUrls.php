<?php

namespace App\Http\Requests\Concerns;

use App\Models\Event;
use App\Models\Form;
use App\Support\LegacyFormImage;
use Illuminate\Support\Facades\Log;

/**
 * Rescue `data:` URL legacy (era base64) SEBELUM validasi berjalan.
 *
 * Latar: after-validation menolak `data:` URL (422). Tanpa konversi di sini,
 * payload autosave form yang bannernya masih base64 di DB selalu 422 —
 * dan auto-convert di controller tidak pernah tercapai karena validasi
 * gagal duluan. Konversi di `prepareForValidation` membuat data legacy
 * terselamatkan sebagai file storage, bukan hilang.
 *
 * Guard hak akses dicek di dalam trait karena `prepareForValidation`
 * berjalan sebelum `authorize()` — request anonim / tanpa hak update
 * tidak boleh bisa menulis file ke storage.
 */
trait ConvertsLegacyDataUrls
{
    protected function convertLegacyDataUrlsInRequest(): void
    {
        $user = $this->user();
        if (! $user) {
            return;
        }

        $eventParam = $this->route('event');
        $formParam = $this->route('form');

        $event = $eventParam instanceof Event
            ? $eventParam
            : ($eventParam !== null ? Event::query()->find($eventParam) : null);
        $form = $formParam instanceof Form
            ? $formParam
            : ($formParam !== null ? Form::query()->find($formParam) : null);

        if (! $event || ! $form || $form->event_id !== $event->id) {
            return;
        }

        if (! $user->can('update', $event)) {
            return;
        }

        $formId = (string) $form->getKey();
        $convertedTotal = 0;

        if ($this->exists('banner_url')) {
            $result = LegacyFormImage::convertFormBanner($this->input('banner_url'), $formId);
            if ($result['converted']) {
                $this->merge(['banner_url' => $result['value']]);
                $convertedTotal++;
            }
        }

        $fields = $this->input('fields');
        if (is_array($fields)) {
            $result = LegacyFormImage::convertFieldRows($fields, $formId);
            if ($result['converted'] > 0) {
                $this->merge(['fields' => $result['rows']]);
                $convertedTotal += $result['converted'];
            }
        }

        if ($convertedTotal > 0) {
            Log::warning('[LegacyFormImage, prepareForValidation]: converted '.$convertedTotal.' data: URL(s) to storage. Jalankan `php artisan forms:migrate-legacy-images --write` untuk membersihkan sisa data lama.');
        }
    }
}
