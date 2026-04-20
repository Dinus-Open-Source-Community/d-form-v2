<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldModifyRequest;
use App\Models\FormField;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class FieldOperationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(FieldModifyRequest $request)
    {
        $newFields = collect($request->validated()['fields'])->keyBy('id');
        $formId = $request->route('form');

        try {
            DB::transaction(function () use ($newFields, $formId) {
                $oldFields = FormField::query()->where('form_id', $formId)->get()->keyBy('id');

                // check for delete
                $keysToDelete = $oldFields->diffKeys($newFields)->keys();
                FormField::query()->where('form_id', $formId)->whereIn('id', $keysToDelete)->delete();

                // check for update and create
                foreach ($newFields as $id => $field) {
                    if ($oldFields->has($id)) {
                        $oldFields->get($id)->fill($field);

                        if ($oldFields->get($id)->isDirty()) {
                            $oldFields->get($id)->save();
                        }

                        continue;
                    }

                    FormField::create(array_merge(
                        $field,
                        ['form_id' => $formId]
                    ));
                }
            });

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => 'Fields have been saved'
            ]);

            return to_route('dashboard.events.forms.show', ['event' => $request->route('event'), 'form' => $formId]);

        } catch (\Exception $e) {
            Log::error('[FieldOperationController, __invoke]: ' . $e->getMessage());

            return Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Fields cannot be saved'
            ])->back();
        }
    }
}
