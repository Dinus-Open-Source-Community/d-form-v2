<?php

namespace App\Http\Controllers\Dashboard\Events\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\FieldModifyRequest;
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
        $data = $request->validated();
        $formId = $request->route('form');

        try {
            DB::transaction(function () {
                //
            });

            Inertia::flash('toast', []);

            return to_route('');

        } catch (\Exception $e) {
            Log::error('[FieldOperationController, __invoke]: ' . $e->getMessage());
        }
    }
}
