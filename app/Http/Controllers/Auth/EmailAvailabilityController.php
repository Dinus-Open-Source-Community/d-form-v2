<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmailAvailabilityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EmailAvailabilityController extends Controller
{
    /** Cek apakah email belum dipakai, dipanggil debounce dari form register. */
    public function __invoke(EmailAvailabilityRequest $request): JsonResponse
    {
        $exists = DB::table('users')
            ->selectRaw('1')
            ->where('email', $request->validated('email'))
            ->exists();

        return response()->json(['available' => ! $exists]);
    }
}
