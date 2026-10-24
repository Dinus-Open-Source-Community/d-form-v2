<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\EmailAvailabilityRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class EmailAvailabilityController extends Controller
{
    /**
     * Cek apakah email belum dipakai, dipanggil debounce dari form register.
     * withTrashed() agar konsisten dengan rule unique:users,email yang ikut menghitung baris soft-deleted.
     */
    public function __invoke(EmailAvailabilityRequest $request): JsonResponse
    {
        $exists = User::withTrashed()
            ->where('email', $request->validated('email'))
            ->exists();

        return response()->json(['available' => ! $exists]);
    }
}
