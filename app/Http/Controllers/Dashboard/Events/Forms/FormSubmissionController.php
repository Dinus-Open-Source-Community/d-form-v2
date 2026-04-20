<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Models\FormAnswer;
use App\Models\FormField;
use App\Services\Form\RulesBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class FormSubmissionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $formId = $request->route('form');

        $fields = FormField::query()->where('form_id', $formId)->get(['name', 'metadata', 'id']);

        $rawRules = RulesBuilder::extractRulesFromFields($fields);

        $validator = Validator::make($request->all(), RulesBuilder::build($rawRules));

        $validatedAnswer = $validator->validate();

        $userId = auth()->guard()->id;

        $data = array_merge(['answers' => $validatedAnswer], ['form_id' => $formId, 'user_id' => $userId]);

        if (FormAnswer::create($data)) {
            Inertia::flash('toast', [
                'message' => 'Answer saved successfully',
                'type' => 'success'
            ]);

            return to_route('dashboard.events.forms.show', [
                'event' => $request()->route('event'),
                'form' => $formId
            ]);
        }

        return Inertia::flash('toast', [
            'message' => "Failed to save answer",
            'type' => 'failed'
        ])->back();
    }
}
