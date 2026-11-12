<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmailAvailabilityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EmailAvailabilityController extends Controller
{
    /**
     * Cek apakah email belum dipakai, dipanggil debounce dari form register.
     * Query builder dipakai agar SQL menjadi `select exists(select 1 ...)`, bukan `select *`;
     * tabel diakses tanpa scope soft-delete supaya konsisten dengan rule unique:users,email.
     */
    public function __invoke(EmailAvailabilityRequest $request): JsonResponse
    {
        $exists = DB::table('users')
            ->selectRaw('1')
            ->where('email', $request->validated('email'))
            ->exists();

        return response()->json(['available' => ! $exists]);
    }
}
