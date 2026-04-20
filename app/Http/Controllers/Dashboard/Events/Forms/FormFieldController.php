<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldStoreRequest;
use App\Http\Requests\FieldUpdateRequest;
use App\Models\FormField;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class FormFieldController extends Controller
{
    public function create()
    {
        // return inertia("");
    }

    public function store(FieldStoreRequest $request)
    {
        $newFields = $request->validated();

        try {
            DB::transaction(function () use ($newFields) {
                foreach ($newFields as $field) {
                    FormField::create([
                        'form_id' => request()->route('form'),
                        ...$field
                    ]);
                }
            });

            Inertia::flash('toast', [
                'message' => 'Fields created successfully',
                'type' => 'success'
            ]);

            return to_route('dashboard.events.forms.show');
        } catch (\Exception $e) {
            Log::error('[FormFieldController, store]: ' . $e->getMessage());

            return Inertia::flash('toast', [
                'message' => 'Failed to create fields',
                'type' => 'error'
            ])->back();
        }
    }

    public function edit()
    {
        //
    }

    public function update(FieldUpdateRequest $request)
    {
        $fields = $request->validated();

        try {
            DB::transaction(function () use ($fields) {
                foreach ($fields as $field) {

                }
            });

            Inertia::flash('toast', [
                'message' => 'Fields created successfully',
                'type' => 'success'
            ]);

            return to_route('dashboard.events.forms.show');
        } catch (\Exception $e) {
            Log::error('[FormFieldController, update]: ' . $e->getMessage());

            return Inertia::flash('toast', [
                'message' => 'Failed to create fields',
                'type' => 'error'
            ])->back();
        }
    }

    public function destroy()
    {
        //
    }
}
