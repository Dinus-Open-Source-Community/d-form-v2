<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldModifyRequest;
use App\Models\Form;
use App\Models\FormField;
use App\Models\Event;
use App\Support\FormFieldTypeMapping;
use App\Support\LegacyFormImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;

class FieldOperationController extends Controller
{
    public function __invoke(FieldModifyRequest $request, Event $event, Form $form)
    {
        abort_unless($form->event_id === $event->id, 404);

        $formId = $form->id;
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

        try {
            DB::transaction(function () use ($rows, $formId) {
                $oldById = FormField::query()->where('form_id', $formId)->get()->keyBy('id');
                $incomingIds = collect($rows)->pluck('id')->filter()->all();

                $toDelete = $oldById->keys()->diff($incomingIds);
                if ($toDelete->isNotEmpty()) {
                    FormField::query()
                        ->where('form_id', $formId)
                        ->whereIn('id', $toDelete)
                        ->delete();
                }

                foreach ($rows as $row) {
                    $id = $row['id'] ?? null;
                    $attrs = $this->rowToModelAttributes($row);
                    if (is_string($id) && $id !== '' && $oldById->has($id)) {
                        $oldById->get($id)->update($attrs);

                        continue;
                    }

                    FormField::query()->create(array_merge(
                        $attrs,
                        [
                            'id' => (is_string($id) && $id !== '') ? $id : (string) Str::uuid(),
                            'form_id' => $formId,
                        ]
                    ));
                }
            });

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Fields have been saved',
            ]);

            // Autosave wizard (background, tanpa navigasi) → JSON.
            if ($request->expectsJson()) {
                return response()->json(['ok' => true]);
            }

            return to_route('dashboard.events.forms.show', ['event' => $event, 'form' => $form]);
        } catch (\Exception $e) {
            Log::error('[FieldOperationController, __invoke]: ' . $e->getMessage());

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Fields cannot be saved'], 422);
            }

            return Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Fields cannot be saved',
            ])->back();
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function rowToModelAttributes(array $row): array
    {
        return [
            'input_type' => FormFieldTypeMapping::toInputType($row['type']),
            'label' => $row['label'],
            'description' => $row['description'] ?? null,
            'name' => $row['name'],
            'order' => (int) $row['order'],
            'metadata' => $row['metadata'],
            'is_append' => (bool) ($row['is_append'] ?? false),
        ];
    }
}
